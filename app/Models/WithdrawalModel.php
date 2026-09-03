<?php

require_once __DIR__ . '/BaseModel.php';

class WithdrawalModel extends BaseModel
{
    protected string $table = 'retiro';
    protected string $primaryKey = 'id_retiro';

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento', 'mr.nombre'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT r.id_retiro AS id, r.estado_solicitud AS estado,
                       r.observaciones AS observaciones, m.fecha_matricula AS fecha_solicitud,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS estudiante,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       g.nombre AS grado, s.nombre AS seccion, mr.nombre AS motivo
                FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN inscripcion i ON i.id_matricula = m.id_matricula
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY m.fecha_matricula DESC, r.id_retiro DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT COUNT(*) FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN inscripcion i ON i.id_matricula = m.id_matricula
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query('SELECT estado_solicitud, COUNT(*) AS total FROM retiro GROUP BY estado_solicitud');
        $stats = ['PENDIENTE' => 0, 'APROBADO' => 0, 'RECHAZADO' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) if (array_key_exists($row['estado_solicitud'], $stats)) $stats[$row['estado_solicitud']] = (int) $row['total'];
        return ['pendientes' => $stats['PENDIENTE'], 'aprobados' => $stats['APROBADO'], 'rechazados' => $stats['RECHAZADO']];
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->pdo->prepare('UPDATE retiro SET estado_solicitud = :estado WHERE id_retiro = :id')->execute(['estado' => $status, 'id' => $id]);
    }

    public function getYears(): array
    {
        return $this->pdo->query("SELECT id_anio_escolar AS id, nombre_periodo AS nombre FROM anio_escolar ORDER BY fecha_inicio DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReasons(): array
    {
        return $this->pdo->query("SELECT id_motivo_retiro AS id, nombre FROM motivo_retiro WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function filters(array $filters): array
    {
        $where = []; $params = [];
        if (!empty($filters['estado'])) { $where[] = 'r.estado_solicitud = :estado'; $params['estado'] = strtoupper($filters['estado']); }
        if (!empty($filters['anio'])) { $where[] = 'ea.id_anio_escolar = :anio'; $params['anio'] = (int) $filters['anio']; }
        if (!empty($filters['motivo'])) { $where[] = 'r.id_motivo_retiro = :motivo'; $params['motivo'] = (int) $filters['motivo']; }
        if (!empty($filters['q'])) { $where[] = '(p.primer_nombre LIKE :q OR p.primer_apellido LIKE :q OR p.numero_documento LIKE :q OR mr.nombre LIKE :q)'; $params['q'] = $filters['q'] . '%'; }
        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $nombre = trim($row['estudiante'] ?? 'Estudiante');
        return ['id' => (int) $row['id'], 'estudiante' => $nombre, 'cedula' => $row['cedula'] ?? '—', 'iniciales' => mb_strtoupper(mb_substr($nombre, 0, 1)), 'avatar_color' => 'primary', 'grado' => $row['grado'] ?? '—', 'seccion' => $row['seccion'] ?? '—', 'fecha_solicitud' => $row['fecha_solicitud'] ? date('d/m/Y', strtotime($row['fecha_solicitud'])) : '—', 'motivo' => $row['motivo'] ?? '—', 'observaciones' => $row['observaciones'] ?? '', 'estado' => strtolower($row['estado'])];
    }
}
