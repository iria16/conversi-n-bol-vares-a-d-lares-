<?php
// app/Models/EstudiantesModel.php

require_once __DIR__ . '/BaseModel.php';

class EstudiantesModel extends BaseModel
{
    protected string $table = 'Estudiante';
    protected string $primaryKey = 'id';

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'p.nro_documento'];
    }

    /**
     * Resuelve la estructura académica vigente de cada estudiante,
     * tomando la Matricula más reciente entre sus Inscripciones y
     * Ratificaciones (una ratificación puede ser más nueva que la
     * inscripción original).
     */
    private function joinInscripcionActual(): string
    {
        return "LEFT JOIN (
                    SELECT
                        m.estudiante_id,
                        ea.id AS estructura_academica_id,
                        m.fecha_matricula
                    FROM Matricula m
                    INNER JOIN Inscripcion i ON i.matricula_id = m.id
                    INNER JOIN EstructuraAcademica ea ON ea.id = i.estructura_academica_id

                    UNION ALL

                    SELECT
                        m.estudiante_id,
                        ea.id AS estructura_academica_id,
                        m.fecha_matricula
                    FROM Matricula m
                    INNER JOIN Ratificacion r ON r.matricula_id = m.id
                    INNER JOIN EstructuraAcademica ea ON ea.id = r.estructura_academica_id
                ) todas ON todas.estudiante_id = est.id
                LEFT JOIN (
                    SELECT estudiante_id, MAX(fecha_matricula) AS max_fecha
                    FROM (
                        SELECT m.estudiante_id, m.fecha_matricula
                        FROM Matricula m INNER JOIN Inscripcion i ON i.matricula_id = m.id
                        UNION ALL
                        SELECT m.estudiante_id, m.fecha_matricula
                        FROM Matricula m INNER JOIN Ratificacion r ON r.matricula_id = m.id
                    ) x
                    GROUP BY estudiante_id
                ) ultima ON ultima.estudiante_id = est.id AND ultima.max_fecha = todas.fecha_matricula
                LEFT JOIN EstructuraAcademica ea ON ea.id = todas.estructura_academica_id
                LEFT JOIN Grado g ON g.id = ea.grado_id
                LEFT JOIN Seccion sec ON sec.id = ea.seccion_id
                LEFT JOIN AnioEscolar ae ON ae.id = ea.anio_escolar_id";
    }

    protected function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = "(p.primer_nombre LIKE :q OR p.primer_apellido LIKE :q OR p.nro_documento LIKE :q)";
            $params['q'] = "%{$filters['q']}%";
        }

        if (!empty($filters['anio'])) {
            $where[] = "ae.nombre_periodo = :anio";
            $params['anio'] = $filters['anio'];
        }

        if (!empty($filters['grado'])) {
            $where[] = "g.nombre = :grado";
            $params['grado'] = $filters['grado'];
        }

        if (!empty($filters['seccion'])) {
            $where[] = "sec.nombre = :seccion";
            $params['seccion'] = $filters['seccion'];
        }

        if (!empty($filters['estado'])) {
            $where[] = "p.estado = :estado";
            $params['estado'] = strtoupper($filters['estado']);
        }

        return [$where, $params];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT
                    est.id,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS nombre,
                    UPPER(LEFT(p.primer_nombre,1)) AS iniciales,
                    p.nro_documento AS cedula_escolar,
                    p.foto AS avatar_img,
                    p.estado AS estado_persona,
                    g.nombre AS grado,
                    sec.nombre AS seccion
                FROM Estudiante est
                INNER JOIN Persona p ON p.id = est.persona_id
                " . $this->joinInscripcionActual();

        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY p.primer_nombre ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($estudiantes as &$e) {
            $e['estado'] = $e['estado_persona'] === 'ACTIVO' ? 'activo' : 'inactivo';
            $e['avatar_color'] = 'primary'; // sin lógica real todavía
            $e['grado'] = $e['grado'] ?? '—';
            $e['seccion'] = $e['seccion'] ?? '—';
            unset($e['estado_persona']);
        }

        return $estudiantes;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT COUNT(*) FROM Estudiante est
                INNER JOIN Persona p ON p.id = est.persona_id
                " . $this->joinInscripcionActual();

        if ($where) $sql .= " WHERE " . implode(" AND ", $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(p.estado = 'ACTIVO') AS activos,
                    SUM(p.estado != 'ACTIVO') AS inactivos
                FROM Estudiante est
                INNER JOIN Persona p ON p.id = est.persona_id";

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'activos'   => (int) ($row['activos'] ?? 0),
            'inactivos' => (int) ($row['inactivos'] ?? 0),
        ];
    }

    public function getAniosEscolares(): array
    {
        return $this->pdo->query("SELECT nombre_periodo FROM AnioEscolar WHERE estado = 'ACTIVO' ORDER BY nombre_periodo DESC")
                          ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getGrados(): array
    {
        return $this->pdo->query("SELECT nombre FROM Grado WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                          ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM Seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                          ->fetchAll(PDO::FETCH_COLUMN);
    }
}