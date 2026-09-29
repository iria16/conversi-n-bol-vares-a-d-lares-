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
                       ad.id_asignacion, ad.id_docente, ad.fecha_inicio, ad.fecha_fin,
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

        $sql = "SELECT COUNT(*) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total    = (int) $this->pdo->query('SELECT COUNT(*) FROM estructura_academica')->fetchColumn();
        $assigned = (int) $this->pdo->query(
            'SELECT COUNT(DISTINCT id_estructura_academica) FROM asignacion_docente WHERE fecha_fin IS NULL OR fecha_fin >= CURDATE()'
        )->fetchColumn();
        $teachers = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM docente d
             INNER JOIN empleado e ON e.id_empleado = d.id_empleado
             INNER JOIN persona p ON p.id_persona = e.id_persona
             WHERE p.estado = "ACTIVO"'
        )->fetchColumn();

        return [
            'total'     => $total,
            'asignadas' => $assigned,
            'pendientes' => max(0, $total - $assigned),
            'docentes'  => $teachers,
        ];
    }

    /** Catálogos activos (id + nombre); anio_escolar usa nombre_periodo y se aliasa a 'nombre' para que las vistas usen una sola clave. */
    public function getOptions(string $table): array
    {
        $catalogos = [
            'anio_escolar' => ['etiqueta' => 'nombre_periodo', 'orden' => 'anio_inicio DESC'],
            'grado'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'seccion'      => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
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

    public function getTeachers(): array
    {
        $sql = "SELECT d.id_docente AS id, CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       c.nombre AS cargo
                FROM docente d
                INNER JOIN empleado e ON e.id_empleado = d.id_empleado
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                WHERE p.estado = 'ACTIVO' AND e.fecha_egreso IS NULL
                ORDER BY p.primer_nombre, p.primer_apellido";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getStructures(): array
    {
        $sql = "SELECT ea.id_estructura_academica AS id,
                       CONCAT(g.nombre, ' · ', s.nombre, ' · ', t.nombre, ' · ', a.nombre_periodo) AS nombre
                FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                WHERE a.estado = 'ACTIVO'
                ORDER BY g.nombre, s.nombre";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
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

    public function remove(int $id): bool
    {
        return $this->pdo->prepare('UPDATE asignacion_docente SET fecha_fin = CURDATE() WHERE id_asignacion = :id')
                        ->execute(['id' => $id]);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach (['anio' => 'ea.id_anio_escolar', 'grado' => 'ea.id_grado', 'seccion' => 'ea.id_seccion'] as $key => $column) {
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