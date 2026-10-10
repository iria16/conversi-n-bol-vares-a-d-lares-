<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\DatoFamiliarIncompletoException;
use App\Models\ContactoModel;
use App\Models\DireccionModel;
use App\Models\PersonaModel;

trait FamiliaresModelTrait
{
    /**
 * @param array<int, array<string, mixed>> $familiares Ya normalizados.
 * @param bool $sincronizar Cuando es true, la lista es la VERDAD: lo que ya
 * estaba y no viene se marca como desvinculado y lo que vuelve se reactiva
 * (hallazgo MEDIO 4.7). Lo deja apagado a propósito la inscripción, donde la
 * lista del formulario es siempre de cero y una sincronización solo podría
 * borrar algo por error.
 * @throws DatoFamiliarIncompletoException
*/
    protected function guardarFamiliares(int $estudianteId, array $familiares, bool $sincronizar = false): void
    {
        if ($sincronizar) {
            $this->sincronizarFamiliares($estudianteId, $familiares);
        }

        $personaModel = new PersonaModel($this->pdo);
        $hayRepresentante = false;
        $vistos = [];

        foreach ($familiares as $f) {
            $esRepresentante = (int) ($f['representante_principal'] ?? 0) === 1;
            if ($esRepresentante) {
                $hayRepresentante = true;
            }

            $documento = trim((string) ($f['numero_documento'] ?? ''));
            if ($documento === '') {
                throw new DatoFamiliarIncompletoException('Cada familiar debe tener un número de documento.');
            }
            if (isset($vistos[$documento])) {
                continue;
            }
            $vistos[$documento] = true;

            $fechaNacimiento = trim((string) ($f['fecha_nacimiento'] ?? ''));
            if ($fechaNacimiento === '') {
                throw new DatoFamiliarIncompletoException('Cada familiar debe tener una fecha de nacimiento.');
            }

            $idPersona = $personaModel->buscarOCrear([
                'tipo_documento'   => (string) ($f['tipo_documento'] ?? 'V'),
                'numero_documento' => mb_substr($documento, 0, 20),
                'fecha_nacimiento' => mb_substr($fechaNacimiento, 0, 10),
                'primer_nombre'    => (string) ($f['primer_nombre'] ?? ''),
                'segundo_nombre'   => (string) ($f['segundo_nombre'] ?? ''),
                'primer_apellido'  => (string) ($f['primer_apellido'] ?? ''),
                'segundo_apellido' => (string) ($f['segundo_apellido'] ?? ''),
                'sexo'             => (string) ($f['sexo'] ?? 'M'),
                /* El familiar solo declara la entidad federal. La parroquia es dato del
                   estudiante; guardarla aquí sería exigir un dato que la secretaria no
                   tiene de un pariente y que el sistema no necesita. */
                'id_estado_nacimiento' => (int) ($f['id_estado_nacimiento'] ?? 0) ?: null,
                /* La nacionalidad sí se le pregunta al familiar: es un dato de la persona,
                que vive en `persona` como el del estudiante. No va en persona_estudiante
                porque esa tabla es el vínculo, no la persona. */
                'id_nacionalidad' => (int) ($f['id_nacionalidad'] ?? 0) ?: null,
            ]);

            $idParentesco = $this->resolverIdCatalogo('parentesco', 'id_parentesco', (string) ($f['parentesco'] ?? ''));
            $idInstruccion = $this->resolverIdCatalogo('nivel_instruccion', 'id_nivel_instruccion', (string) ($f['nivel_instruccion'] ?? ''));
            $idOcupacion = $this->resolverIdCatalogo('ocupacion', 'id_ocupacion', (string) ($f['ocupacion'] ?? ''));
            $estadoRelacion = (string) ($f['estado_relacion'] ?? 'ACTIVO');
            if (!in_array($estadoRelacion, ['ACTIVO', 'INACTIVO', 'SUSPENDIDO'], true)) {
                $estadoRelacion = 'ACTIVO';
            }

            $this->pdo->prepare(
                'INSERT INTO persona_estudiante
                    (id_persona, id_estudiante, es_representante_legal, es_autorizado_retiro,
                     vive_con_estudiante, estado_relacion, id_parentesco, id_nivel_instruccion, id_ocupacion)
                 VALUES
                    (:persona, :estudiante, :representante, :autorizado, :vive, :estado, :parentesco, :instruccion, :ocupacion)
                 ON DUPLICATE KEY UPDATE
                    es_representante_legal = VALUES(es_representante_legal),
                    es_autorizado_retiro = VALUES(es_autorizado_retiro),
                    vive_con_estudiante = VALUES(vive_con_estudiante),
                    estado_relacion = VALUES(estado_relacion),
                    id_parentesco = VALUES(id_parentesco),
                    id_nivel_instruccion = VALUES(id_nivel_instruccion),
                    id_ocupacion = VALUES(id_ocupacion)'
            )->execute([
                'persona' => $idPersona,
                'estudiante' => $estudianteId,
                'representante' => $esRepresentante ? 1 : 0,
                'autorizado' => $esRepresentante || !empty($f['autorizado_retiros']) ? 1 : 0,
                'vive' => !empty($f['vive_con_estudiante']) ? 1 : 0,
                'estado' => $esRepresentante ? 'ACTIVO' : $estadoRelacion,
                'parentesco' => $idParentesco,
                'instruccion' => $idInstruccion,
                'ocupacion' => $idOcupacion,
            ]);

            $contacto = $f['contacto'] ?? [];
            $codigoTelefono = trim((string) ($contacto['codigo'] ?? ''));
            $numeroTelefono = trim((string) ($contacto['numero'] ?? ''));
            if ($codigoTelefono !== '' && $numeroTelefono !== '') {
                (new ContactoModel($this->pdo))->upsert($idPersona, [
                    'codigo_telefono_principal' => $codigoTelefono,
                    'numero_telefono_principal' => $numeroTelefono,
                    'codigo_telefono_alternativo' => trim((string) ($contacto['codigo_alt'] ?? '')),
                    'numero_telefono_alternativo' => trim((string) ($contacto['numero_alt'] ?? '')),
                    'correo_electronico' => trim((string) ($contacto['correo'] ?? '')),
                ]);
            }

            $direccion = $f['direccion'] ?? [];
            $idParroquia = (int) ($direccion['id_parroquia'] ?? 0);
            if ($idParroquia > 0) {
                (new DireccionModel($this->pdo))->upsert($idPersona, [
                    'id_parroquia' => $idParroquia,
                    'sector_urbanizacion' => trim((string) ($direccion['sector'] ?? '')),
                    'calle_avenida' => trim((string) ($direccion['calle'] ?? '')),
                    'nro_casa_apto' => trim((string) ($direccion['casa'] ?? '')),
                    'punto_referencia' => trim((string) ($direccion['referencia'] ?? '')),
                ]);
            }
        }

        if (!$hayRepresentante) {
            throw new DatoFamiliarIncompletoException('No se ha designado un representante legal.');
        }
    }

    private function resolverIdCatalogo(string $tabla, string $pk, string $nombre): ?int
    {
        if (trim($nombre) === '') { return null; }
        $stmt = $this->pdo->prepare("SELECT {$pk} FROM {$tabla} WHERE nombre = :nombre LIMIT 1");
        $stmt->execute(['nombre' => trim($nombre)]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /* Desvinculación de familiares (hallazgo MEDIO 4.7).
 *
 * LÓGICA y no física: `persona_estudiante.estado_relacion` es un INACTIVE con fecha, así
 * que el esquema ya lo soportaba y la baja física era lo que sobraba. Conservar la fila
 * permite reactivar sin recapturar y deja el rastro de quién fue familiar y cuándo dejó de serlo.*/

    /**
 * Calcula la diferencia entre lo que hay guardado y lo que llega, y aplica
 * los tres movimientos: desvincular lo que falta, reactivar lo que vuelve y
 * (en el llamador) guardar lo nuevo.
 *
 * @throws DatoFamiliarIncompletoException
*/
    private function sincronizarFamiliares(int $estudianteId, array $enviados): void
    {
        $vigentes = $this->relacionesDelEstudiante($estudianteId);

        /* La identificación es por DÍGITOS del documento, que es como lo indexa
 el formulario y como lo devuelve getFamiliares(). Con el documento en
 crudo, "V-12345678" y "12345678" serían dos personas distintas.*/
        $documentosPayload = [];
        foreach ($enviados as $f) {
            $digitos = preg_replace('/\D/', '', (string) ($f['numero_documento'] ?? ''));
            if ($digitos !== '') {
                $documentosPayload[$digitos] = true;
            }
        }

    /* GUARDA 1 — una lista vacía con familiares guardados no es una intención, es una carga que
 falló. El payload se arma en un hidden que escribe familiaresUI, y si la carga por AJAX falla
 ese hidden conserva lo anterior o queda vacío, así que un guardado sin revisar vaciaría los
 familiares del alumno.*/
        if ($enviados === [] && $vigentes !== []) {
            throw new DatoFamiliarIncompletoException(
                'No llegó la lista de familiares del alumno. Por seguridad no se desvinculó '
                . 'a nadie: vuelva a abrir la pantalla y revise los familiares antes de guardar.'
            );
        }

    /* GUARDA 2 — un alumno no puede quedar sin representante legal. En ratificación el alta NO exige
 representante (el alumno ya está inscripto), pero desvincularlo sin dejar otro lo dejaría sin
 representación legal: de ahí no se puede autorizar un retiro ni firmar documentos.*/
        $tieneRepresentanteVigente = false;
        foreach ($vigentes as $relacion) {
            if ((int) $relacion['es_representante_legal'] === 1) {
                $tieneRepresentanteVigente = true;
                break;
            }
        }

        if ($tieneRepresentanteVigente) {
            $representantesEnPayload = 0;
            foreach ($enviados as $f) {
                if ((int) ($f['representante_principal'] ?? 0) !== 1) {
                    continue;
                }
                if (preg_replace('/\D/', '', (string) ($f['numero_documento'] ?? '')) === '') {
                    continue;
                }
                $representantesEnPayload++;
            }

            if ($representantesEnPayload === 0) {
                throw new DatoFamiliarIncompletoException(
                    'No se puede desvincular al representante legal: el alumno quedaría sin '
                    . 'representante. Designe otro representante antes de quitarlo.'
                );
            }
        }

        $aReactivar = [];
        foreach ($vigentes as $relacion) {
            $viene = isset($documentosPayload[$relacion['documento']]);

            if ($viene) {
                /* Volvió a la lista. Si estaba desvinculada se reactiva, y con ella
 su parentesco, nivel de instrucción y ocupación.*/
                if ($relacion['estado_relacion'] !== 'ACTIVO') {
                    $aReactivar[] = (int) $relacion['id_persona'];
                }
                continue;
            }

            /* No vino: se desvincula. El UPDATE es condicional a propósito, para
 que guardar dos veces el mismo formulario no dispare nada.

 Los dos estados van con marcadores DISTINTOS y no con el mismo
 repetido: PDO no expande un marcador con nombre que aparece dos
 veces en la misma sentencia y la consulta muere con "Invalid
 parameter number".*/
            $this->pdo->prepare(
                'UPDATE persona_estudiante
                    SET estado_relacion = :estado_nuevo
                  WHERE id_persona = :persona AND id_estudiante = :estudiante
                    AND estado_relacion <> :estado_previo'
            )->execute([
                'estado_nuevo' => 'INACTIVO',
                'estado_previo'=> 'INACTIVO',
                'persona'      => (int) $relacion['id_persona'],
                'estudiante'   => $estudianteId,
            ]);
        }

        if ($aReactivar !== []) {
            $marcas = [];
            foreach ($aReactivar as $id) {
                $marcas[] = ':r' . $id;
            }
            $this->pdo->prepare(
                'UPDATE persona_estudiante SET estado_relacion = :activo
                  WHERE id_estudiante = :estudiante AND id_persona IN (' . implode(',', $marcas) . ')'
            )->execute(array_merge(
                array_combine($marcas, $aReactivar),
                ['activo' => 'ACTIVO', 'estudiante' => $estudianteId]
            ));
        }
    }

    /**
 * Relaciones guardadas de un alumno, en cualquier estado, con el documento
 * ya normalizado a dígitos para poder compararlo con el payload.
 *
 * @return array<int, array{id_persona:int, documento:string, es_representante_legal:int, estado_relacion:string}>
*/
    private function relacionesDelEstudiante(int $estudianteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pe.id_persona,
                    p.numero_documento,
                    pe.es_representante_legal, pe.estado_relacion
               FROM persona_estudiante pe
               INNER JOIN persona p ON p.id_persona = pe.id_persona
              WHERE pe.id_estudiante = :estudiante'
        );
        $stmt->execute(['estudiante' => $estudianteId]);

        $filas = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            $digitos = preg_replace('/\D/', '', (string) ($fila['numero_documento'] ?? ''));
            if ($digitos === '') {
                continue;
            }
            $filas[] = [
                'id_persona'            => (int) $fila['id_persona'],
                'documento'             => $digitos,
                'es_representante_legal' => (int) $fila['es_representante_legal'],
                'estado_relacion'       => (string) $fila['estado_relacion'],
            ];
        }

        return $filas;
    }
}
