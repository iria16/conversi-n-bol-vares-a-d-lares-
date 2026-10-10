<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;

use Throwable;

/**
 * Asignación de docentes a las secciones de la estructura académica (asignacion_docente).
 * El listado parte de estructura_academica con LEFT JOIN a la asignación vigente, para que las aulas sin docente salgan "pendiente".
*/
class TeacherAssignmentModel extends BaseModel implements Listable
{
    protected string $table = 'asignacion_docente';
    protected string $primaryKey = 'id_asignacion';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT ea.id_estructura_academica AS estructura_id,
                       ea.id_anio_escolar, ea.id_grado, ea.id_seccion,
                       ea.id_turno, a.nombre_periodo AS anio,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                       ad.id_asignacion, ad.id_docente, ad.id_tipo_asignacion, ad.fecha_inicio, ad.fecha_fin,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS docente,
                       CASE WHEN ad.id_asignacion IS NULL THEN 'pendiente' ELSE 'asignado' END AS estado
                FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                    AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente d ON d.id_docente = ad.id_docente
                LEFT JOIN empleado e ON e.id_empleado = d.id_empleado
                LEFT JOIN persona p ON p.id_persona = e.id_persona";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, g.nombre, s.nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['estructura_id'] = (int) $row['estructura_id'];
            $row['id_asignacion'] = (int) $row['id_asignacion'];
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(DISTINCT ea.id_estructura_academica) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                LEFT JOIN asignacion_docente ad ON ad.id_estructura_academica = ea.id_estructura_academica
                    AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                LEFT JOIN docente d ON d.id_docente = ad.id_docente
                LEFT JOIN empleado e ON e.id_empleado = d.id_empleado
                LEFT JOIN persona p ON p.id_persona = e.id_persona";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $classrooms = $this->pdo->query(
            "SELECT COUNT(DISTINCT ea.id_estructura_academica) AS total,
                    COUNT(DISTINCT CASE
                        WHEN ad.id_asignacion IS NOT NULL THEN ea.id_estructura_academica
                    END) AS asignadas
               FROM anio_escolar a
               INNER JOIN estructura_academica ea ON ea.id_anio_escolar = a.id_anio_escolar
               LEFT JOIN asignacion_docente ad
                      ON ad.id_estructura_academica = ea.id_estructura_academica
                     AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
              WHERE a.estado = 'ACTIVO'"
        )->fetch(\PDO::FETCH_ASSOC);
        $total = (int) $classrooms['total'];
        $assigned = (int) $classrooms['asignadas'];
        $teachers = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM docente d
             INNER JOIN empleado e ON e.id_empleado = d.id_empleado
             INNER JOIN persona p ON p.id_persona = e.id_persona
             WHERE p.estado = "ACTIVO" AND e.fecha_egreso IS NULL'
        )->fetchColumn();

        return [
            'total'     => $total,
            'asignadas' => $assigned,
            'pendientes' => max(0, $total - $assigned),
            'docentes'  => $teachers,
        ];
    }

    /** Catálogos activos (id + nombre); anio_escolar usa nombre_periodo y se aliasa a 'nombre' para que las vistas usen una sola clave.*/
    public function getOptions(string $table): array
    {
        $catalogos = [
            'anio_escolar' => ['etiqueta' => 'nombre_periodo', 'orden' => 'anio_inicio DESC'],
            'grado'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'seccion'      => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'turno'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
        ];
        if (!isset($catalogos[$table])) {
            return [];
        }
        $etiqueta = $catalogos[$table]['etiqueta'];
        $orden    = $catalogos[$table]['orden'];
        $stmt = $this->pdo->query(
            "SELECT id_{$table} AS id, {$etiqueta} AS nombre
             FROM {$table} WHERE estado = 'ACTIVO' ORDER BY {$orden}"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActiveSchoolYearId(): ?int
    {
        $stmt = $this->pdo->query(
            "SELECT id_anio_escolar
               FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        );
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function getActiveSchoolYearName(): ?string
    {
        $stmt = $this->pdo->query(
            "SELECT nombre_periodo
               FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        );
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function getStructures(): array
    {
        $sql = "SELECT ea.id_estructura_academica AS id,
                       ea.id_turno,
                       CONCAT(g.nombre, ' · ', s.nombre, ' · ', t.nombre, ' · ', a.nombre_periodo) AS nombre
                FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                WHERE a.estado = 'ACTIVO' AND t.estado = 'ACTIVO'
                ORDER BY g.nombre, s.nombre";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTeachersForStructure(int $structureId): array
    {
        $sql = "SELECT d.id_docente AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       c.nombre AS cargo
                  FROM docente d
                  INNER JOIN empleado e ON e.id_empleado = d.id_empleado
                  INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                  INNER JOIN estructura_academica ea
                     ON ea.id_turno = e.id_turno
                    AND ea.id_estructura_academica = :estructura
                  INNER JOIN anio_escolar a
                      ON a.id_anio_escolar = ea.id_anio_escolar
                     AND a.estado = 'ACTIVO'
                  INNER JOIN turno t ON t.id_turno = ea.id_turno AND t.estado = 'ACTIVO'
                 WHERE p.estado = 'ACTIVO'
                   AND e.fecha_egreso IS NULL
                 ORDER BY p.primer_nombre, p.primer_apellido";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['estructura' => $structureId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTypes(): array
    {
        $stmt = $this->pdo->query("SELECT id_tipo_asignacion AS id, nombre FROM tipo_asignacion WHERE estado = 'ACTIVO' ORDER BY nombre");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function assign(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $tipoActivo = $this->pdo->prepare(
                "SELECT 1 FROM tipo_asignacion
                  WHERE id_tipo_asignacion = :tipo AND estado = 'ACTIVO'
                  LIMIT 1"
            );
            $tipoActivo->execute(['tipo' => (int) $data['id_tipo_asignacion']]);
            if ($tipoActivo->fetchColumn() === false) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Seleccione un tipo de asignación activo.'];
            }

            $validarTurno = $this->pdo->prepare(
                "SELECT e.id_turno AS turno_empleado, ea.id_turno AS turno_estructura
                   FROM estructura_academica ea
                   INNER JOIN docente d ON d.id_docente = :docente
                   INNER JOIN empleado e ON e.id_empleado = d.id_empleado
                   INNER JOIN persona p ON p.id_persona = e.id_persona
                   INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar AND a.estado = 'ACTIVO'
                   INNER JOIN turno t ON t.id_turno = ea.id_turno AND t.estado = 'ACTIVO'
                  WHERE ea.id_estructura_academica = :estructura
                    AND p.estado = 'ACTIVO'
                    AND e.fecha_egreso IS NULL
                  FOR UPDATE"
            );
            $validarTurno->execute([
                'docente' => (int) $data['id_docente'],
                'estructura' => (int) $data['id_estructura_academica'],
            ]);
            $turnos = $validarTurno->fetch(\PDO::FETCH_ASSOC);
            if (
                !$turnos
                || (int) $turnos['turno_empleado'] <= 0
                || (int) $turnos['turno_empleado'] !== (int) $turnos['turno_estructura']
            ) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El docente debe tener asignado el mismo turno que la sección.'];
            }

            $this->pdo->prepare(
                'UPDATE asignacion_docente SET fecha_fin = CURDATE()
                 WHERE id_estructura_academica = :estructura AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())'
            )->execute(['estructura' => $data['id_estructura_academica']]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO asignacion_docente (id_docente, id_estructura_academica, id_tipo_asignacion, fecha_inicio, fecha_fin)
                 VALUES (:docente, :estructura, :tipo, :inicio, NULL)'
            );
            $stmt->execute([
                'docente'    => $data['id_docente'],
                'estructura' => $data['id_estructura_academica'],
                'tipo'       => $data['id_tipo_asignacion'],
                'inicio'     => $data['fecha_inicio'],
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Docente asignado correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('TeacherAssignmentModel::assign(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo asignar el docente.'];
        }
    }

    public function updateAssignment(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $tipoActivo = $this->pdo->prepare(
                "SELECT 1 FROM tipo_asignacion
                  WHERE id_tipo_asignacion = :tipo AND estado = 'ACTIVO'
                  LIMIT 1"
            );
            $tipoActivo->execute(['tipo' => (int) $data['id_tipo_asignacion']]);
            if ($tipoActivo->fetchColumn() === false) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Seleccione un tipo de asignación activo.'];
            }

            $validarTurno = $this->pdo->prepare(
                "SELECT ad.id_estructura_academica,
                        e.id_turno AS turno_empleado,
                        ea.id_turno AS turno_estructura
                   FROM asignacion_docente ad
                   INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
                   INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar AND a.estado = 'ACTIVO'
                   INNER JOIN docente d ON d.id_docente = :docente
                   INNER JOIN empleado e ON e.id_empleado = d.id_empleado
                   INNER JOIN persona p ON p.id_persona = e.id_persona
                   INNER JOIN turno t ON t.id_turno = ea.id_turno AND t.estado = 'ACTIVO'
                  WHERE ad.id_asignacion = :asignacion
                    AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())
                    AND p.estado = 'ACTIVO'
                    AND e.fecha_egreso IS NULL
                  FOR UPDATE"
            );
            $validarTurno->execute([
                'docente' => (int) $data['id_docente'],
                'asignacion' => (int) $data['id_asignacion'],
            ]);
            $turnos = $validarTurno->fetch(\PDO::FETCH_ASSOC);
            if (
                !$turnos
                || (int) $turnos['turno_empleado'] <= 0
                || (int) $turnos['turno_empleado'] !== (int) $turnos['turno_estructura']
            ) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La asignación no está activa o el docente no tiene el turno de la sección.'];
            }

            $stmt = $this->pdo->prepare(
                "UPDATE asignacion_docente
                    SET id_docente = :docente,
                        id_tipo_asignacion = :tipo,
                        fecha_inicio = :inicio
                  WHERE id_asignacion = :asignacion
                    AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())"
            );
            $stmt->execute([
                'docente' => (int) $data['id_docente'],
                'tipo' => (int) $data['id_tipo_asignacion'],
                'inicio' => $data['fecha_inicio'],
                'asignacion' => (int) $data['id_asignacion'],
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Asignación docente actualizada correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('TeacherAssignmentModel::updateAssignment(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar la asignación docente.'];
        }
    }

    public function remove(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE asignacion_docente ad
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
               INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                SET ad.fecha_fin = CURDATE()
              WHERE ad.id_asignacion = :id
                AND a.estado = 'ACTIVO'
                AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())"
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach ([
            'anio' => 'ea.id_anio_escolar',
            'grado' => 'ea.id_grado',
            'seccion' => 'ea.id_seccion',
            'turno' => 'ea.id_turno',
        ] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            foreach (['g.nombre', 's.nombre', 'p.primer_nombre', 'p.primer_apellido'] as $i => $col) {
                $params["q$i"] = $filters['q'] . '%';
            }
            $where[] = '(g.nombre LIKE :q0 OR s.nombre LIKE :q1 OR p.primer_nombre LIKE :q2 OR p.primer_apellido LIKE :q3)';
        }
        return [$where, $params];
    }
}