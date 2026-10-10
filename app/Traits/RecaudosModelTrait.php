<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\BitacoraModel;
use PDO;
use Throwable;

/**
 * Recaudos que debe entregar el representante (partida de nacimiento, fotos, cédula, etc.).
 *
 * La lista es el catálogo tipo_documento y se administra en Configuración; lo que el
 * representante entregó en cada caso se guarda en estudiante_documento.
 *
 * Antes el paso 5 del asistente traía la lista escrita en el archivo y, aunque se marcaba
 * "Consignado" y se escribía una observación, nada de eso se guardaba: la tabla
 * estudiante_documento estaba creada y nunca se escribió. Aquí se cierra ese hueco.
*/
trait RecaudosModelTrait
{
    /**
 * Recaudos del catálogo activo, para el paso 5 del asistente.
 *
 * Se leen de la base y no del archivo para que agregar uno en Configuración lo muestre
 * de inmediato, y para que marcarlo INACTIVO lo retire de la lista sin borrar nada.
 *
 * 'detalle' va vacío porque el catálogo solo tiene nombre y estado: el texto gris pequeño
 * ("Original y copia") era texto fijo del archivo. Si algún día cada recaudo debe llevar
 * su propio requisito, es una columna nueva en tipo_documento y se rellena aquí.
 *
 * @return array<int, array<string, mixed>>
*/
    public function getRecaudos(): array
    {
        return $this->pdo->query(
            "SELECT id_tipo_documento AS id, nombre, '' AS detalle
               FROM tipo_documento
              WHERE estado = 'ACTIVO'
           ORDER BY nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Guarda lo que se marcó en el paso 5, dentro de la transacción de la inscripción.
 *
 * Se guardan TODOS los recaudos del catálogo, no solo los marcados: la fila con
 * consignado = 0 dice "este documento se pidió y no vine", que es información distinta de
 * "este documento no existe". Así la ficha puede mostrar lo que falta.
 *
 * El id del recaudo viene del formulario y no se confía en él: se contrasta con el
 * catálogo antes de escribir. Sin esa comprobación, un id inventado o uno desactivado
 * provocaría un error de FK y tumbaría la inscripción entera por un campo secundario.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
*/
    protected function guardarRecaudos(int $estudianteId, array $recaudos): void
    {
        if ($recaudos === []) {
            return;
        }

        $consulta = $this->pdo->query("SELECT id_tipo_documento FROM tipo_documento WHERE estado = 'ACTIVO'");
        $permitidos = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        if ($permitidos === []) {
            return;
        }

        $insertar = $this->pdo->prepare(
            'INSERT INTO estudiante_documento (id_estudiante, id_tipo_documento, consignado, observacion)
             VALUES (:estudiante, :tipo, :consignado, :observacion)'
        );

        foreach ($recaudos as $id => $estado) {
            $id = (int) $id;
            if (!in_array($id, $permitidos, true)) {
                continue;
            }

            $observacion = trim((string) ($estado['observacion'] ?? ''));
            $insertar->execute([
                'estudiante'   => $estudianteId,
                'tipo'         => $id,
                'consignado'   => !empty($estado['consignado']) ? 1 : 0,
                'observacion'  => $observacion !== '' ? $observacion : null,
            ]);
        }
    }

    /**
 * Recaudos de un estudiante para su ficha.
 *
 * LEFT JOIN desde el catálogo y no desde estudiante_documento a propósito: si mañana se
 * agrega un recaudo nuevo al catálogo, tiene que aparecer como pendiente en las fichas
 * de los que ya se inscribieron. Leyendo solo las filas guardadas, un alumno con los
 * documentos completos seguiría apareciendo completo y el nuevo requisito quedaría
 * invisible sin dar ninguna pista.
 *
 * @return array<int, array<string, mixed>>
*/
    public function getRecaudosDeEstudiante(int $estudianteId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT t.id_tipo_documento AS id,
                    t.nombre,
                    IFNULL(ed.consignado, 0) AS consignado,
                    ed.observacion,
                    ed.actualizado_en,
                    u.nombre_usuario AS actualizado_por_nombre
               FROM tipo_documento t
               LEFT JOIN estudiante_documento ed
                      ON ed.id_tipo_documento = t.id_tipo_documento
                     AND ed.id_estudiante = :estudiante
               LEFT JOIN usuario u ON u.id_usuario = ed.actualizado_por
              WHERE t.estado = 'ACTIVO'
           ORDER BY t.nombre"
        );
        $consulta->execute(['estudiante' => $estudianteId]);

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
 * Actualiza lo que se entregó de un estudiante y lo registra en bitácora.
 *
 * Es un upsert (ON DUPLICATE KEY UPDATE) porque la llave única es (id_estudiante,
 * id_tipo_documento): el catálogo se muestra completo en la pantalla, así que un recaudo
 * pendiente puede no tener fila todavía y hay que crearla en el momento de marcarlo.
 *
 * Solo se tocan los recaudos que vienen marcados en el formulario; el resto se deja como
 * está. Con una actualización ciega del catálogo completo, guardar la pantalla borraría
 * de paso lo que alguien hubiera anotado desde otra sesión.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    /**
 * Actualiza lo que se entregó y lo registra en bitácora, en su propia transacción.
 *
 * Es la vía de la pantalla de actualización de recaudos. Cuando los recaudos van dentro
 * de otro guardado (el modal "Actualizar expediente"), quien lo llama debe usar
 * guardarRecaudosEnTransaccion() para que todo se guarde junto o nada.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    public function actualizarRecaudos(int $estudianteId, array $recaudos, int $idUsuario): int
    {
        if ($recaudos === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $tocados = $this->guardarRecaudosEnTransaccion($estudianteId, $recaudos, $idUsuario);
            $this->pdo->commit();

            return $tocados;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Igual que actualizarRecaudos(), pero sin abrir transacción: la usa quien ya está
 * dentro de la suya y quiere que los recaudos se guarden o se deshagan con el resto.
 *
 * @param array<int, array{consignado?: mixed, observacion?: mixed}> $recaudos
 * @return int Cuántos recaudos cambiaron de estado o se crearon.
*/
    protected function guardarRecaudosEnTransaccion(int $estudianteId, array $recaudos, int $idUsuario): int
    {
        if ($recaudos === []) {
            return 0;
        }

        $consulta = $this->pdo->query("SELECT id_tipo_documento FROM tipo_documento WHERE estado = 'ACTIVO'");
        $permitidos = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        if ($permitidos === []) {
            return 0;
        }

        $upsert = $this->pdo->prepare(
            'INSERT INTO estudiante_documento
                 (id_estudiante, id_tipo_documento, consignado, observacion, actualizado_en, actualizado_por)
             VALUES (:estudiante, :tipo, :consignado, :observacion, NOW(), :usuario)
             ON DUPLICATE KEY UPDATE
                consignado       = VALUES(consignado),
                observacion      = VALUES(observacion),
                actualizado_en  = NOW(),
                actualizado_por = VALUES(actualizado_por)'
        );

        $tocados = 0;
        $cambios = [];
        foreach ($recaudos as $id => $estado) {
            $id = (int) $id;
            if (!in_array($id, $permitidos, true)) {
                continue;
            }

            $observacion = trim((string) ($estado['observacion'] ?? ''));
            $consignado  = !empty($estado['consignado']) ? 1 : 0;

            /* Se compara con lo que ya estaba para no escribir filas que no cambian nada:
 actualizar la hora de todos los recaudos al guardar haría que "actualizado
 el" dejara de significar cuándo se recibió el documento.*/
            $antes = $this->pdo->prepare(
                'SELECT consignado, observacion FROM estudiante_documento
                  WHERE id_estudiante = :estudiante AND id_tipo_documento = :tipo'
            );
            $antes->execute(['estudiante' => $estudianteId, 'tipo' => $id]);
            $fila = $antes->fetch(PDO::FETCH_ASSOC);

            if ($fila
                && (int) $fila['consignado'] === $consignado
                && (string) ($fila['observacion'] ?? '') === $observacion) {
                continue;
            }

            $upsert->execute([
                'estudiante'  => $estudianteId,
                'tipo'        => $id,
                'consignado'  => $consignado,
                'observacion' => $observacion !== '' ? $observacion : null,
                'usuario'     => $idUsuario > 0 ? $idUsuario : null,
            ]);
            $tocados++;
            $cambios[] = ($consignado ? 'entregado' : 'pendiente') . ': ' . $this->nombreRecaudo($id);
        }

        /* La bitácora registra el hecho; los dos campos de la fila dicen quién fue el
 último. Se audita antes del commit, como en el resto del proyecto: si el
 commit fallara, el rollback también deshace la bitácora.*/
        if ($tocados > 0 && $idUsuario > 0) {
            (new BitacoraModel($this->pdo))->registrar(
                $idUsuario,
                'ACTUALIZACIÓN DE RECAUDOS del estudiante ' . $this->descripcionEstudianteBruto($estudianteId)
                    . ' · ' . implode(' | ', $cambios),
                'Recaudos'
            );
        }

        return $tocados;
    }

    /** Nombre de un recaudo del catálogo, para los mensajes de la bitácora.*/
    private function nombreRecaudo(int $idTipoDocumento): string
    {
        $consulta = $this->pdo->prepare('SELECT nombre FROM tipo_documento WHERE id_tipo_documento = :id');
        $consulta->execute(['id' => $idTipoDocumento]);
        $nombre = $consulta->fetchColumn();

        return $nombre === false ? "tipo {$idTipoDocumento}" : (string) $nombre;
    }

    /**
 * Nombre y cédula de un estudiante para los textos de la bitácora.
 *
 * El método se llama descripciónEstudianteBruto() y no descripciónEstudiante() a
 * propósito: RatificacionModel ya tenía un private con ese nombre para lo mismo, y
 * un trait que declara un método que la clase ya define es un error fatal de PHP
 * ("Cannot redeclare"), no una sobrescritura. Con dos nombres distintos conviven los
 * dos y el que se usa es explícito.
*/
    private function descripcionEstudianteBruto(int $estudianteId): string
    {
        $consulta = $this->pdo->prepare(
            'SELECT p.primer_nombre, p.primer_apellido, p.numero_documento
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $consulta->execute(['id' => $estudianteId]);
        $fila = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$fila) {
            return "estudiante {$estudianteId}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }
}
