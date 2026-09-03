<?php

require_once __DIR__ . '/BaseModel.php';

class AcademicStructureModel extends BaseModel
{
    protected string $table = 'estructura_academica';
    protected string $primaryKey = 'id_estructura_academica';

    protected function searchableFields(): array
    {
        return ['g.nombre', 's.nombre', 't.nombre'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT ea.id_estructura_academica AS id, ea.id_anio_escolar,
                       ea.id_grado, ea.id_seccion, ea.id_turno, ea.capacidad_maxima,
                       a.nombre_periodo AS anio, a.estado AS estado_anio,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                       (SELECT COUNT(*) FROM inscripcion i WHERE i.id_estructura_academica = ea.id_estructura_academica) AS estudiantes
                FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY a.fecha_inicio DESC, g.nombre, s.nombre LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT COUNT(*) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM estructura_academica')->fetchColumn();
        $grados = (int) $this->pdo->query('SELECT COUNT(DISTINCT id_grado) FROM estructura_academica')->fetchColumn();
        $secciones = (int) $this->pdo->query('SELECT COUNT(DISTINCT id_seccion) FROM estructura_academica')->fetchColumn();
        $capacidad = (int) $this->pdo->query('SELECT COALESCE(SUM(capacidad_maxima), 0) FROM estructura_academica')->fetchColumn();
        return ['total' => $total, 'grados' => $grados, 'secciones' => $secciones, 'capacidad' => $capacidad];
    }

    public function getOptions(string $table, string $id, string $name): array
    {
        $allowed = ['anio_escolar', 'grado', 'seccion', 'turno'];
        if (!in_array($table, $allowed, true)) return [];
        $stmt = $this->pdo->query("SELECT {$id} AS id, {$name} AS nombre FROM {$table} WHERE estado = 'ACTIVO' ORDER BY {$name}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createStructure(array $data): array
    {
        if ($this->existsCombination($data)) return ['ok' => false, 'mensaje' => 'Esta combinación académica ya existe.'];
        try {
            $stmt = $this->pdo->prepare('INSERT INTO estructura_academica (id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima) VALUES (:anio, :grado, :seccion, :turno, :capacidad)');
            $stmt->execute(['anio' => $data['id_anio_escolar'], 'grado' => $data['id_grado'], 'seccion' => $data['id_seccion'], 'turno' => $data['id_turno'], 'capacidad' => $data['capacidad_maxima']]);
            return ['ok' => true];
        } catch (Throwable $e) {
            error_log('AcademicStructureModel::createStructure(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo guardar la estructura académica.'];
        }
    }

    public function updateStructure(int $id, array $data): array
    {
        if ($this->existsCombination($data, $id)) return ['ok' => false, 'mensaje' => 'Esta combinación académica ya existe.'];
        try {
            $stmt = $this->pdo->prepare('UPDATE estructura_academica SET id_anio_escolar = :anio, id_grado = :grado, id_seccion = :seccion, id_turno = :turno, capacidad_maxima = :capacidad WHERE id_estructura_academica = :id');
            $ok = $stmt->execute(['anio' => $data['id_anio_escolar'], 'grado' => $data['id_grado'], 'seccion' => $data['id_seccion'], 'turno' => $data['id_turno'], 'capacidad' => $data['capacidad_maxima'], 'id' => $id]);
            return ['ok' => $ok, 'mensaje' => $ok ? 'Estructura actualizada correctamente.' : 'No se pudo actualizar la estructura.'];
        } catch (Throwable $e) {
            error_log('AcademicStructureModel::updateStructure(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar la estructura académica.'];
        }
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach (['anio' => 'ea.id_anio_escolar', 'grado' => 'ea.id_grado', 'seccion' => 'ea.id_seccion', 'turno' => 'ea.id_turno'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') { $where[] = $column . ' = :' . $key; $params[$key] = (int) $filters[$key]; }
        }
        if (!empty($filters['q'])) { $where[] = '(g.nombre LIKE :q OR s.nombre LIKE :q OR t.nombre LIKE :q)'; $params['q'] = $filters['q'] . '%'; }
        return [$where, $params];
    }

    private function existsCombination(array $data, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM estructura_academica WHERE id_anio_escolar = :anio AND id_grado = :grado AND id_seccion = :seccion AND id_turno = :turno';
        $params = ['anio' => $data['id_anio_escolar'], 'grado' => $data['id_grado'], 'seccion' => $data['id_seccion'], 'turno' => $data['id_turno']];
        if ($excludeId !== null) { $sql .= ' AND id_estructura_academica != :id'; $params['id'] = $excludeId; }
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
}
