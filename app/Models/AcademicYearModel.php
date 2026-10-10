<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Instituciones;
use App\Support\Matriculas;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Updatable;

use Throwable;

/**
 * Ciclo de vida de años escolares: PLANIFICADO -> ACTIVO -> CERRADO.
*/
class AcademicYearModel extends BaseModel implements Listable, Creatable, Updatable
{
    protected string $table = 'anio_escolar';
    protected string $primaryKey = 'id_anio_escolar';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT ae.id_anio_escolar AS id, ae.nombre_periodo AS nombre,
                       ae.anio_inicio AS fecha_inicio, ae.anio_cierre AS fecha_cierre, ae.estado,
                       ae.iniciado_en, ae.cerrado_en,
                       ae.reapierto_por, ae.motivo_reapertura,
                       CONCAT_WS(' ', pi.primer_nombre, pi.primer_apellido) AS iniciado_por,
                       CONCAT_WS(' ', pc.primer_nombre, pc.primer_apellido) AS cerrado_por,
                       /* Alias distinto del campo: ae.reapierto_por es el id, y sin este
 CONCAT el modal mostraría un número en vez del nombre de quien
 reabrió, igual que pasó con iniciado_por y cerrado_por.*/
                       CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) AS reapertura_por
                FROM anio_escolar ae
                LEFT JOIN usuario ui ON ui.id_usuario = ae.iniciado_por
                LEFT JOIN persona pi ON pi.id_persona = ui.id_persona
                LEFT JOIN usuario uc ON uc.id_usuario = ae.cerrado_por
                LEFT JOIN persona pc ON pc.id_persona = uc.id_persona
                LEFT JOIN usuario ur ON ur.id_usuario = ae.reapierto_por
                LEFT JOIN persona pr ON pr.id_persona = ur.id_persona";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY fecha_inicio DESC, id_anio_escolar DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = 'SELECT COUNT(*) FROM anio_escolar ae';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total       = (int) $this->pdo->query('SELECT COUNT(*) FROM anio_escolar')->fetchColumn();
        $activos     = (int) $this->pdo->query("SELECT COUNT(*) FROM anio_escolar WHERE estado = 'ACTIVO'")->fetchColumn();
        $planificados = (int) $this->pdo->query("SELECT COUNT(*) FROM anio_escolar WHERE estado = 'PLANIFICADO'")->fetchColumn();
        $cerrados    = (int) $this->pdo->query("SELECT COUNT(*) FROM anio_escolar WHERE estado = 'CERRADO'")->fetchColumn();
        $actual   = AniosEscolares::nombreEnCurso($this->pdo);

        return [
            'total'        => $total,
            'activos'      => $activos,
            'planificados' => $planificados,
            'cerrados'     => $cerrados,
            'actual'       => $actual ?: 'Sin período activo',
        ];
    }

    /**
 * Los períodos nuevos siempre comienzan planificados y no alteran el año vigente.
 *
 * Se audita la creación porque el nombre del período es lo que aparece en cada
 * matrícula, una estructura y un histórico: un año creado sin rastro de quién lo pidió
 * es el punto de partida de toda una generación de datos sin origen conocido.
 *
 * @param array $data ['nombre' => string, 'id_usuario' => ?int]
*/
    public function create(array $data): bool
    {
        $ok = $this->pdo->prepare(
            "INSERT INTO anio_escolar (nombre_periodo, anio_inicio, anio_cierre, estado)
             VALUES (:nombre, :inicio, :cierre, 'PLANIFICADO')"
        )->execute([
            'nombre' => $data['nombre'],
            'inicio' => $data['fecha_inicio'],
            'cierre' => $data['fecha_cierre'],
        ]);

        $idUsuario = (int) ($data['id_usuario'] ?? 0);
        if ($ok && $idUsuario > 0) {
            (new BitacoraModel($this->pdo))->registrar(
                $idUsuario,
                'CREACIÓN del año escolar ' . $data['nombre'],
                'Años escolares'
            );
        }

        return $ok;
    }

    /**
 * Los períodos cerrados son inmutables; los activos y planificados pueden corregirse.
*/
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE anio_escolar
             SET nombre_periodo = :nombre, anio_inicio = :inicio, anio_cierre = :cierre
             WHERE id_anio_escolar = :id AND estado <> \'CERRADO\''
        );
        $stmt->execute([
            'nombre' => $data['nombre'],
            'inicio' => $data['fecha_inicio'],
            'cierre' => $data['fecha_cierre'],
            'id'     => $id,
        ]);
        if ($stmt->rowCount() > 0) {
            return true;
        }

        $exists = $this->pdo->prepare(
            "SELECT 1 FROM anio_escolar
              WHERE id_anio_escolar = :id AND estado <> 'CERRADO'
              LIMIT 1"
        );
        $exists->execute(['id' => $id]);
        return $exists->fetchColumn() !== false;
    }

    /**
 * Inicia un período planificado sin cerrar ni reemplazar implícitamente otro año activo.
*/
    public function iniciar(int $id, int $idUsuario, ?string $ip = null): array
    {
        return $this->transicionar($id, $idUsuario, 'PLANIFICADO', 'ACTIVO', 'iniciado_en', 'iniciado_por', 'INICIO', $ip);
    }

    public function cerrar(int $id, int $idUsuario, ?string $ip = null): array
    {
        return $this->transicionar($id, $idUsuario, 'ACTIVO', 'CERRADO', 'cerrado_en', 'cerrado_por', 'CIERRE', $ip);
    }

    private function transicionar(
        int $id,
        int $idUsuario,
        string $estadoOrigen,
        string $estadoDestino,
        string $columnaFecha,
        string $columnaUsuario,
        string $accion,
        ?string $ip
    ): array {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'SELECT nombre_periodo, estado FROM anio_escolar WHERE id_anio_escolar = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $id]);
            $anio = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$anio) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El año escolar seleccionado no existe.'];
            }

            if ($anio['estado'] !== $estadoOrigen) {
                $this->pdo->rollBack();
                return [
                    'ok' => false,
                    'mensaje' => $estadoOrigen === 'PLANIFICADO'
                        ? 'Solo se puede iniciar un año escolar planificado.'
                        : 'Solo se puede cerrar un año escolar activo.',
                ];
            }

            if ($estadoDestino === 'ACTIVO') {
                $activo = $this->pdo->query("SELECT nombre_periodo FROM anio_escolar WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
                if ($activo !== false) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'mensaje' => "No se puede iniciar el período: {$activo} ya está activo."];
                }
            }

            /* Cerrar congela el período. Si se cierra con asuntos sin resolver, esos asuntos
 quedan sin salida: un retiro PENDIENTE ya no se puede aprobar (el año cerrado lo
 bloquea) y la solicitud moriría ahí, y un alumno de sexto sin egresar ni retiro
 se quedaría colgando sin registro de a qué año pertenece. Por eso se comprueba antes
 de cerrar, que es el único momento en el que aún se puede actuar.*/
            if ($estadoDestino === 'CERRADO') {
                $pendientes = $this->pendientesDeCierre((int) $id);
                if ($pendientes !== []) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'mensaje' => implode(' ', $pendientes)];
                }
            }

            $update = $this->pdo->prepare(
                "UPDATE anio_escolar
                    SET estado = :estado,
                        {$columnaFecha} = NOW(),
                        {$columnaUsuario} = :usuario
                  WHERE id_anio_escolar = :id AND estado = :origen"
            );
            $update->execute([
                'estado' => $estadoDestino,
                'usuario' => $idUsuario,
                'id' => $id,
                'origen' => $estadoOrigen,
            ]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El estado del año escolar cambió; actualice la página e intente de nuevo.'];
            }

            $this->registrarBitacora($idUsuario, $accion . ' año escolar ' . $anio['nombre_periodo'], $ip);
            $this->pdo->commit();
            return [
                'ok' => true,
                'mensaje' => $estadoDestino === 'ACTIVO'
                    ? 'Año escolar iniciado correctamente.'
                    : 'Año escolar cerrado correctamente.',
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AcademicYearModel::transicionar(): ' . $e->getMessage());
            if ($estadoDestino === 'ACTIVO' && in_array((string) $e->getCode(), ['23000', '45000'], true)) {
                return ['ok' => false, 'mensaje' => 'Ya existe otro año escolar activo.'];
            }
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar el ciclo del año escolar.'];
        }
    }

    /**
 * Qué impide cerrar el año. Devuelve una lista de motivos; vacía significa que se puede.
 *
 * Son dos, y los dos se quedan sin salida al cerrar:
 * - Retiros PENDIENTE: al cerrarse el año ya no se pueden aprobar, y la solicitud
 * quedaría eternamente pendiente sin que nadie pueda resolverla.
 * - Alumnos de último grado cursando: no tienen salida en este modelo, porque el
 * egreso exige el período activo y el retiro solo alcanza hasta el cierre. Hay que
 * egresarlos o retirarlos antes.
*/
    private function pendientesDeCierre(int $idAnioEscolar): array
    {
        $motivos = [];

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM retiro r
               INNER JOIN matricula m ON m.id_matricula = r.id_matricula
              WHERE m.id_anio_escolar = :anio AND r.estado_solicitud = 'PENDIENTE'"
        );
        $stmt->execute(['anio' => $idAnioEscolar]);
        $pendientes = (int) $stmt->fetchColumn();
        if ($pendientes > 0) {
            $motivos[] = "No se puede cerrar: hay {$pendientes} solicitud(es) de retiro sin resolver "
                . '(pendientes de aprobar o rechazar).';
        }

        /* Alias distintos porque `grado.nombre` y `seccion.nombre` se llaman igual: sin
 alias, el segundo pisa al primero en el resultado y el mensaje acababa
 diciendo "alumnos de a sección".*/
        $stmt = $this->pdo->prepare(
            'SELECT g.nombre AS grado, s.nombre AS seccion
               FROM matricula m
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               INNER JOIN grado g   ON g.id_grado   = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
              WHERE m.id_anio_escolar = :anio
                AND ' . Matriculas::condicionCursando('m') . '
                AND CAST(REGEXP_SUBSTR(g.nombre, \'^[0-9]+\') AS UNSIGNED) = ' . Instituciones::GRADO_MAYOR
            . ' LIMIT 1'
        );
        $stmt->execute(['anio' => $idAnioEscolar]);
        $ultimo = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($ultimo) {
            $seccion = trim((string) ($ultimo['seccion'] ?? ''));
            $motivos[] = 'No se puede cerrar: quedan alumnos de '
                . strtolower((string) $ultimo['grado'])
                . ' sección ' . ($seccion !== '' ? $seccion : '—')
                . ' sin egresar ni retirar. Egréselos o regístreles el retiro antes de cerrar.';
        }

        return $motivos;
    }

    /**
 * Reabre un año escolar cerrado, dejando registro de quién, cuándo y por qué.
 *
 * Es el mecanismo que faltaba para corregir un cierre por error. No es un simple cambio
 * de estado por tres razones: exige un motivo escrito, queda en la bitácora, y se niega
 * si el año ya tiene movimientos posteriores a su cierre (eso significaría que se está
 * reabriendo sobre un período que ya se usó como histórico, y eso necesita revisión
 * manual, no un clic).
 *
 * `cerrado_en` y `cerrado_por` se conservan: documentan el cierre anterior. Si el
 * período se vuelve a cerrar, se sobrescriben con los datos de ese nuevo ciclo.
 *
 * @return array{ok:bool,mensaje:string}
*/
    public function reabrir(int $id, int $idUsuario, string $motivo, ?string $ip = null): array
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            return ['ok' => false, 'mensaje' => 'Indique el motivo de la reapertura.'];
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'SELECT nombre_periodo, estado, cerrado_en
                   FROM anio_escolar WHERE id_anio_escolar = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $id]);
            $anio = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$anio) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El año escolar seleccionado no existe.'];
            }
            if ($anio['estado'] !== 'CERRADO') {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Solo se puede reabrir un año escolar cerrado.'];
            }

            $activo = $this->pdo->query("SELECT nombre_periodo FROM anio_escolar WHERE estado = 'ACTIVO' LIMIT 1")->fetchColumn();
            if ($activo !== false) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => "No se puede reabrir: {$activo} está activo. "
                    . 'Cierre el período en curso antes de reabrir el anterior.'];
            }

            /* Movimientos posteriores al cierre: si los hay, el año ya se usó como histórico
 (matrículas de otro período, retiros, egresos) y reabrirlo dejaría esos
 registros colgando de un período que vuelve a estar abierto. Se rechaza y se
 avisa, para que la corrección se haga con conocimiento de causa.*/
            if (!empty($anio['cerrado_en'])) {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*)
                       FROM matricula
                      WHERE id_anio_escolar = :anio AND fecha_creacion > :cerrado'
                );
                $stmt->execute(['anio' => $id, 'cerrado' => $anio['cerrado_en']]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'mensaje' => 'No se puede reabrir automáticamente: hay '
                        . 'matrículas registradas en este año después de su cierre. Revise el '
                        . 'histórico antes de reabrirlo.'];
                }
            }

    /* Dos marcadores distintos y no uno repetido: con emulación de prepares desactivada
 (PDO::ATTR_EMULATE_PREPARES => false) un mismo nombre no se puede usar dos veces en la misma
 sentencia. "Iniciado" y "reabierto" son hechos distintos y no se pisan entre sí.*/
            $update = $this->pdo->prepare(
                "UPDATE anio_escolar
                    SET estado = 'ACTIVO',
                        iniciado_en = COALESCE(iniciado_en, NOW()),
                        iniciado_por = COALESCE(iniciado_por, :usuario_inicio),
                        reapierto_en = NOW(),
                        reapierto_por = :usuario_reapertura,
                        motivo_reapertura = :motivo
                  WHERE id_anio_escolar = :id AND estado = 'CERRADO'"
            );
            $update->execute([
                'usuario_inicio'    => $idUsuario,
                'usuario_reapertura' => $idUsuario,
                'motivo'  => mb_substr($motivo, 0, 200),
                'id'      => $id,
            ]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El estado del año escolar cambió; actualice la página e intente de nuevo.'];
            }

            $this->registrarBitacora(
                $idUsuario,
                'REAPERTURA del año escolar ' . $anio['nombre_periodo'] . ' · ' . $motivo,
                $ip
            );
            $this->pdo->commit();

            return ['ok' => true, 'mensaje' => 'Año escolar reabierto correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AcademicYearModel::reabrir(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo reabrir el ciclo del año escolar.'];
        }
    }

    /**
 * Deja una entrada en la bitácora.
 *
 * `accion` se recorta aquí, y no en quien la llama: la columna es varchar(100) y el texto lo
 * compone el llamador. En reabrir() el texto lleva el MOTIVO, que es texto libre del usuario y
 * no tiene tope: sin recorte, un motivo largo revienta la columna y la reapertura se pierde.
*/
    private function registrarBitacora(int $idUsuario, string $accion, ?string $ip): void
    {
        $limite = 100;
        if (mb_strlen($accion, 'UTF-8') > $limite) {
            $accion = mb_substr($accion, 0, $limite - 1, 'UTF-8') . '…';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO bitacora (id_usuario, fecha, hora, accion, modulo, direccion_ip)
             VALUES (:usuario, CURDATE(), CURTIME(), :accion, :modulo, :ip)'
        );
        $stmt->execute([
            'usuario' => $idUsuario,
            'accion' => $accion,
            'modulo' => 'Años escolares',
            'ip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        ]);
    }

    /**
 * Indica si ya existe un período con ese nombre.
 * Con $exceptoId se deja fuera la fila que se está editando.
*/
    public function existePorNombre(string $nombre, ?int $exceptoId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM anio_escolar WHERE nombre_periodo = :nombre';
        $params = ['nombre' => $nombre];

        if ($exceptoId !== null) {
            $sql .= ' AND id_anio_escolar != :id';
            $params['id'] = $exceptoId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getActiveId(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $estado = strtoupper($filters['estado']);
            if (in_array($estado, ['PLANIFICADO', 'ACTIVO', 'CERRADO'], true)) {
                $where[] = 'ae.estado = :estado';
                $params['estado'] = $estado;
            }
        }
        if (!empty($filters['q'])) {
            $where[] = 'ae.nombre_periodo LIKE :q';
            $params['q'] = $filters['q'] . '%';
        }

        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $estado = (string) $row['estado'];
        $estado = $estado === 'PLANIFICACIÓN' ? 'PLANIFICADO' : $estado;

        return [
            'id'           => (int) $row['id'],
            'nombre'       => $row['nombre'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cierre' => $row['fecha_cierre'],
            'estado'       => strtolower($estado),
            'iniciado_en'  => $row['iniciado_en'] ?? null,
            'iniciado_por' => $row['iniciado_por'] ?? null,
            'cerrado_en'   => $row['cerrado_en'] ?? null,
            'cerrado_por'  => $row['cerrado_por'] ?? null,
            // Datos de la reapertura (Fase 4): solo existen si el período se reabrió.
            'reapierto_por'     => $row['reapertura_por'] ?? null,
            'motivo_reapertura' => $row['motivo_reapertura'] ?? null,
        ];
    }
}