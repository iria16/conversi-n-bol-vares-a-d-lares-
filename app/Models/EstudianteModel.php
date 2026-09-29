<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;

/**
 * Consulta de expedientes (estudiante ↔ persona).
 * Resuelve el grado y la sección vigentes de cada estudiante a partir de su matrícula más reciente (inscripción o ratificación).
 */
class EstudianteModel extends BaseModel implements Listable
{
    protected string $table = 'estudiante';
    protected string $primaryKey = 'id_estudiante';

    /**
     * Une la matrícula actual del alumno con su estructura y año, buscándola por el año escolar más reciente y no por la fecha.
     * Así una fecha atrasada no lo deja atrás y un empate de fechas no duplica la fila.
     * La estructura sale de la matrícula (dónde cursa), no de la inscripción, que solo dice dónde entró.
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
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion";
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT est.id_estudiante AS id, est.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       p.numero_documento AS cedula_escolar,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       p.estado AS estado_persona,
                       g.nombre AS grado, s.nombre AS seccion,
                       a.nombre_periodo AS anio_escolar
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
        foreach ($rows as &$estudiante) {
            $estudiante['id']        = (int) $estudiante['id'];
            $estudiante['estado']    = $estudiante['estado_persona'] === 'ACTIVO' ? 'activo' : 'inactivo';
            $estudiante['avatar_color'] = 'primary';
            $estudiante['iniciales'] = $estudiante['iniciales'] ?: 'ES';
            unset($estudiante['estado_persona']);
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

    public function getStats(): array
    {
        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(p.estado = 'ACTIVO'), 0) AS activos,
                       COALESCE(SUM(p.estado != 'ACTIVO'), 0) AS inactivos
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona";
        $row = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'activos'   => (int) ($row['activos'] ?? 0),
            'inactivos' => (int) ($row['inactivos'] ?? 0),
        ];
    }

    /** Detalle completo para la ficha del estudiante. */
    public function getById(int $id): ?array
    {
        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento, p.numero_documento, p.fecha_nacimiento, p.sexo,
                       p.foto AS avatar_img, p.estado AS estado_persona,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       g.nombre AS grado, s.nombre AS seccion, a.nombre_periodo AS anio_escolar,
                       c.correo_electronico,
                       CONCAT(c.codigo_telefono_principal, '-', c.numero_telefono_principal) AS telefono,
                       CONCAT(c.codigo_telefono_alternativo, '-', c.numero_telefono_alternativo) AS telefono_alternativo,
                       d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto, d.punto_referencia
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
                LEFT JOIN direccion d ON d.id_persona = p.id_persona
                " . $this->joinActual() . "
                WHERE est.id_estudiante = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id']          = (int) $row['id'];
        $row['estado']      = $row['estado_persona'] === 'ACTIVO' ? 'activo' : 'inactivo';
        $row['avatar_color'] = 'primary';
        $row['iniciales']   = $row['iniciales'] ?: 'ES';
        unset($row['estado_persona']);
        return $row;
    }

    public function getAniosEscolares(): array
    {
        return $this->pdo->query("SELECT nombre_periodo FROM anio_escolar WHERE estado = 'ACTIVO' ORDER BY anio_inicio DESC LIMIT 1")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getGrados(): array
    {
        return $this->pdo->query("SELECT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = "(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)";
        }
        if (!empty($filters['anio'])) {
            $where[] = 'a.nombre_periodo = :anio';
            $params['anio'] = $filters['anio'];
        }
        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        if (!empty($filters['estado'])) {
            $where[] = $filters['estado'] === 'inactivo' ? "p.estado != 'ACTIVO'" : "p.estado = 'ACTIVO'";
        }
        return [$where, $params];
    }
}