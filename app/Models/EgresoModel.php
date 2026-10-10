<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;
use App\Support\Instituciones;
use App\Support\Matriculas;

use Throwable;

/**
 * Egresos / Graduación (egreso ↔ matricula): candidatos son los estudiantes de 6to grado con matrícula vigente; por la UI circula id_estudiante.
 * El egreso es el cierre natural del ciclo; cualquier salida antes, de cualquier grado, es un retiro (WithdrawalModel).
 * El motivo se guarda en inscripcion.motivo_egreso con un valor único, sin catálogo, porque nunca se elige.
*/
class EgresoModel extends BaseModel implements Listable
{
    protected string $table = 'egreso';
    protected string $primaryKey = 'id_egreso';

    /** Solo 6to grado se egresa: ver el docblock de la clase.*/
    public const GRADO_EGRESO = 6;

    /** Motivo único del egreso: culminar 6to grado satisfactoriamente.*/
    public const MOTIVO_EGRESO = 'Culminación exitosa';

    /** Id del 6to grado, resuelto del catálogo. Ver idGradoEgreso().*/
    private ?int $idGradoEgreso = null;

    /**
 * Une la matrícula actual del alumno con su estructura y año, buscándola por el año escolar más reciente.
 * No usa la fecha porque es un DATE y dos matrículas del mismo día empataban y duplicaban la fila.
 * La estructura sale de la matrícula, no de la inscripción, que es única de por vida.
*/
    private function joinActual(): string
    {
        return "INNER JOIN (
                    SELECT id_estudiante, MAX(id_anio_escolar) AS anio_actual
                    FROM matricula GROUP BY id_estudiante
                ) ult ON ult.id_estudiante = est.id_estudiante
                LEFT JOIN matricula m ON m.id_estudiante = est.id_estudiante
                                    AND m.id_anio_escolar = ult.anio_actual
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN egreso eg ON eg.id_matricula = m.id_matricula";
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       CASE WHEN eg.id_egreso IS NOT NULL THEN 'graduado' ELSE 'pendiente' END AS estado
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id']          = (int) $row['id'];
            $row['avatar_color'] = 'primary';
            $row['iniciales']   = $row['iniciales'] ?: 'ES';
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(?int $idTurno = null): array
    {
        /* Lleva el mismo filtro de 6to grado que el listado.
 Sin él, un alumno de 1er grado aparecía como pendiente de graduarse
 y el panel se contradecía con la tabla.*/
        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(eg.id_egreso IS NOT NULL), 0) AS graduados
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual() . '
                WHERE ea.id_grado = :id_grado_egreso';
        $params = ['id_grado_egreso' => $this->idGradoEgreso()];
        if ($idTurno !== null) {
            $sql .= ' AND ea.id_turno = :turno';
            $params['turno'] = $idTurno;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $total     = (int) ($row['total'] ?? 0);
        $graduados = (int) ($row['graduados'] ?? 0);
        return [
            'total'      => $total,
            'graduados'  => $graduados,
            'pendientes' => max(0, $total - $graduados),
        ];
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
 * Registra el egreso del estudiante sobre su matrícula más reciente.
 *
 * @param int $idUsuario quién/egresa; 0 si no hay sesión (no se audita, pero no falla)
*/
    public function graduar(int $estudianteId, ?int $idTurno = null, int $idUsuario = 0): array
    {
        $vigente = $this->matriculaVigenteConGrado($estudianteId);
        if ($vigente === null) {
            return ['ok' => false, 'mensaje' => 'El estudiante no tiene una matrícula vigente.'];
        }
        if ($idTurno !== null && $vigente['id_turno'] !== $idTurno) {
            return ['ok' => false, 'mensaje' => 'El estudiante no pertenece a su turno asignado.'];
        }

        /* El egreso se registra en el período al que pertenece la matrícula. Si ese año está
 cerrado, escribir la fila EGRESO ahora sería modificar un período histórico; la
 inscripción y la ratificación ya quedan fuera por defecto sin año activo, y el
 egreso se quedaba como la única operación que atravesaba un año cerrado.*/
        if ($vigente['estado_anio'] !== 'ACTIVO') {
            return ['ok' => false, 'mensaje' => 'El año escolar ' . $vigente['anio']
                . ' está cerrado. Los egresos solo se registran en el período activo; '
                . 'para uno de un período cerrado hay que reabrir el año con un motivo registrado.'];
        }

        $matriculaId = (int) $vigente['id_matricula'];
        if ($this->yaGraduado($matriculaId)) {
            return ['ok' => false, 'mensaje' => 'El estudiante ya fue registrado como egresado.'];
        }

        /* Solo 6to grado: el egreso es la culminación del ciclo, no una baja.
 Si el alumno no está en 6to corresponde un retiro o nada, y el mensaje
 lo dice para que quien lo intenta sepa dónde ir.*/
        $ordinal = Instituciones::ordinalGrado($vigente['grado'] ?? null);
        if ($ordinal !== self::GRADO_EGRESO) {
            $nombre = trim((string) ($vigente['grado'] ?? '')) ?: 'su grado actual';
            return ['ok' => false, 'mensaje' => 'El egreso es solo para estudiantes de '
                . self::GRADO_EGRESO . 'to grado, porque es la culminación del ciclo. '
                . 'Este estudiante está en ' . $nombre . '. Si va a salir antes de '
                . 'culminar el periodo, corresponde un retiro, no un egreso.'];
        }

        try {
            $this->pdo->beginTransaction();

            // El motivo va en la inscripción del alumno, donde dice por qué terminó.
            // Por id_estudiante y no por id_matricula: la inscripción siempre apunta a la
            // matrícula de SU año de ingreso, así que filtrar por la matrícula vigente
            // no encontraba fila en cuanto el alumno había ratificado una vez, y el motivo
            // se perdía sin avisar. uk_inscripcion_estudiante deja una inscripción por
            // alumno, así que el WHERE no es ambiguo.
            //
            // Solo se escribe si el campo está vacío: la misma columna guarda el motivo de
            // procedencia (por qué salió del colegio anterior) y escribir encima borraría
            // ese dato. Para quien viene por traslado, el motivo del egreso queda implícito
            // en la fila EGRESO, que es una constante del sistema.
            $this->pdo->prepare(
                'UPDATE inscripcion SET motivo_egreso = :motivo
                  WHERE id_estudiante = :estudiante
                    AND (motivo_egreso IS NULL OR motivo_egreso = \'\')'
            )->execute([
                'motivo'     => self::MOTIVO_EGRESO,
                'estudiante' => (int) $estudianteId,
            ]);

            /* La fecha y el usuario se guardan en el mismo INSERT que la fila: si se escribieran
 después, un fallo entre medias dejaría un egreso sin rastro de quién lo hizo.*/
            $this->pdo->prepare('INSERT INTO egreso (id_matricula, fecha_egreso, id_usuario)
                                 VALUES (:matricula, NOW(), :usuario)')
                      ->execute([
                          'matricula' => $matriculaId,
                          'usuario'  => $idUsuario > 0 ? $idUsuario : null,
                      ]);

            /* Se crea una fila EGRESO nueva junto a la del año, sin cambiar esta, para no perder en el
 historial que el alumno cursó sexto. El cupo se libera solo, porque el conteo
 solo mira INSCRIPCION y RATIFICACION.*/
            $pdo = $this->pdo;
            $stmt = $pdo->prepare(
                'SELECT id_estudiante, id_anio_escolar, id_estructura_academica
                   FROM matricula WHERE id_matricula = :m'
            );
            $stmt->execute(['m' => $matriculaId]);
            $anio = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$anio) {
                throw new \RuntimeException('La matrícula del estudiante no existe.');
            }
            $pdo->prepare(
                'INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo)
                 VALUES (:estudiante, CURDATE(), :anio, :estructura, :tipo)'
            )->execute([
                'estudiante' => (int) $anio['id_estudiante'],
                'anio'       => (int) $anio['id_anio_escolar'],
                'estructura' => (int) $anio['id_estructura_academica'],
                'tipo'       => Matriculas::EGRESO,
            ]);
            $matriculaEgreso = (int) $pdo->lastInsertId();

            // `egreso` pasa a apuntar a la fila EGRESO, que describe el hecho.
            $pdo->prepare('UPDATE egreso SET id_matricula = :nueva WHERE id_matricula = :vieja')
                ->execute(['nueva' => $matriculaEgreso, 'vieja' => $matriculaId]);

            /* Dentro de la transacción y no después: si algo fallara y se revirtiera, el
 egreso tampoco se habría hecho y la bitácora no debe decir que sí.*/
            if ($idUsuario > 0) {
                (new BitacoraModel($this->pdo))->registrar(
                    $idUsuario,
                    'EGRESO ' . $this->descripcionEstudiante((int) $estudianteId),
                    'Egresos'
                );
            }

            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Egreso registrado correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('EgresoModel::graduar(): ' . $e->getMessage());
            if ($this->pdo->errorInfo()[1] === 1062) {
                return ['ok' => false, 'mensaje' => 'El estudiante ya fue registrado como egresado.'];
            }
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el egreso.'];
        }
    }

    /** Nombre y cédula del alumno, para que la bitácora se lea sin consultar la ficha.*/
    private function descripcionEstudiante(int $estudianteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.primer_nombre, p.primer_apellido, p.numero_documento
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $estudianteId]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return "estudiante {$estudianteId}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }

    /** Registro masivo de egresos; devuelve cuántos se lograron.*/
    public function graduarMasivo(array $ids, ?int $idTurno = null, int $idUsuario = 0): array
    {
        $registrados = 0;
        $fallidos    = [];

        foreach ($ids as $id) {
            $resultado = $this->graduar((int) $id, $idTurno, $idUsuario);
            if ($resultado['ok']) {
                $registrados++;
                continue;
            }
            // Se junta el motivo de cada fallo para poder reportarlo: antes el mensaje solo
            // decía cuántos salieron y no cuáles ni por qué, y quien carga la lista no
            // tenía forma de saber a quién había que volver a buscar.
            $fallidos[] = [
                'id'       => (int) $id,
                'estudiante' => $this->descripcionEstudiante((int) $id),
                'mensaje'  => $resultado['mensaje'],
            ];
        }

        return ['registrados' => $registrados, 'fallidos' => $fallidos];
    }

    /**
 * Matrícula vigente del estudiante con el grado en que está, en una sola consulta.
 * Así el id y el grado salen siempre de la misma matrícula.
 *
 * @return array{id_matricula:int,grado:?string,id_turno:int}|null
*/
    private function matriculaVigenteConGrado(int $estudianteId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula, g.nombre AS grado, ea.id_turno, a.estado AS estado_anio,
                    a.nombre_periodo AS anio
               FROM matricula m
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               LEFT JOIN grado g              ON g.id_grado = ea.id_grado
               LEFT JOIN anio_escolar a       ON a.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_estudiante = :estudiante
              ORDER BY m.id_anio_escolar DESC LIMIT 1"
        );
        $stmt->execute(['estudiante' => $estudianteId]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        return [
            'id_matricula' => (int) $fila['id_matricula'],
            'grado'        => $fila['grado'] !== null ? (string) $fila['grado'] : null,
            'id_turno'     => (int) $fila['id_turno'],
            'estado_anio'  => (string) ($fila['estado_anio'] ?? ''),
            'anio'         => (string) ($fila['anio'] ?? ''),
        ];
    }

    private function yaGraduado(int $matriculaId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM egreso WHERE id_matricula = :matricula');
        $stmt->execute(['matricula' => $matriculaId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
 * Id del catálogo del grado que se egresa (6to), resuelto por su número y no por id fijo ni por nombre.
 * En `grado` los ids no siguen el orden (el 2do es 6 y el 6to es 5). Se cachea durante la corrida.
 *
 * @return int 0 si el catálogo no tiene ese grado.
*/
    private function idGradoEgreso(): int
    {
        if ($this->idGradoEgreso !== null) {
            return $this->idGradoEgreso;
        }

        $this->idGradoEgreso = 0;
        $filas = $this->pdo->query("SELECT id_grado, nombre FROM grado WHERE estado = 'ACTIVO'")
                        ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($filas as $fila) {
            if (Instituciones::ordinalGrado($fila['nombre']) === self::GRADO_EGRESO) {
                $this->idGradoEgreso = (int) $fila['id_grado'];
                break;
            }
        }

        return $this->idGradoEgreso;
    }

    private function filters(array $filters): array
    {
        /* La lista se limita a 6to grado: a un alumno de 1er no se le gradúa, se le reinscribe.
 Filtra por el id del 6to resuelto del catálogo, porque los ids no siguen el orden de los grados.*/
        $params = ['id_grado_egreso' => $this->idGradoEgreso()];
        $where  = ['ea.id_grado = :id_grado_egreso'];

        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        if (array_key_exists('id_turno', $filters)) {
            $where[] = 'ea.id_turno = :id_turno';
            $params['id_turno'] = (int) $filters['id_turno'];
        }
        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
        }
        return [$where, $params];
    }
}