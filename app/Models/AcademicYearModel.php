<?php

require_once __DIR__ . '/BaseModel.php';

class AcademicYearModel extends BaseModel
{
    protected string $table = 'anio_escolar';
    protected string $primaryKey = 'id_anio_escolar';

    protected function searchableFields(): array
    {
        return ['nombre_periodo'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = 'estado = :estado';
            $params['estado'] = strtoupper($filters['estado']);
        }
        if (!empty($filters['q'])) {
            $where[] = 'nombre_periodo LIKE :q';
            $params['q'] = $filters['q'] . '%';
        }

        $sql = 'SELECT id_anio_escolar AS id, nombre_periodo AS nombre,
                       fecha_inicio, fecha_cierre, estado
                FROM anio_escolar';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY fecha_inicio DESC, id_anio_escolar DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        $where = [];
        $params = [];
        if (!empty($filters['estado'])) {
            $where[] = 'estado = :estado';
            $params['estado'] = strtoupper($filters['estado']);
        }
        if (!empty($filters['q'])) {
            $where[] = 'nombre_periodo LIKE :q';
            $params['q'] = $filters['q'] . '%';
        }

        $sql = 'SELECT COUNT(*) FROM anio_escolar';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM anio_escolar')->fetchColumn();
        $activos = (int) $this->pdo->query("SELECT COUNT(*) FROM anio_escolar WHERE estado = 'ACTIVO'")->fetchColumn();
        $inactivos = $total - $activos;
        $actual = $this->pdo->query("SELECT nombre_periodo FROM anio_escolar WHERE estado = 'ACTIVO' ORDER BY fecha_inicio DESC LIMIT 1")->fetchColumn();

        return [
            'total' => $total,
            'activos' => $activos,
            'inactivos' => $inactivos,
            'actual' => $actual ?: 'Sin período activo',
        ];
    }

    public function createYear(array $data): array
    {
        if ($this->existsByName($data['nombre'])) {
            return ['ok' => false, 'mensaje' => 'Ya existe un año escolar con ese nombre.'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO anio_escolar (nombre_periodo, fecha_inicio, fecha_cierre, estado)
                 VALUES (:nombre, :inicio, :cierre, :estado)'
            );
            $stmt->execute([
                'nombre' => $data['nombre'],
                'inicio' => $data['fecha_inicio'],
                'cierre' => $data['fecha_cierre'],
                'estado' => $data['estado'],
            ]);
            return ['ok' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log('AcademicYearModel::createYear(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo guardar el año escolar.'];
        }
    }

    public function updateYear(int $id, array $data): array
    {
        if ($this->existsByName($data['nombre'], $id)) {
            return ['ok' => false, 'mensaje' => 'Ya existe otro año escolar con ese nombre.'];
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE anio_escolar
                 SET nombre_periodo = :nombre, fecha_inicio = :inicio, fecha_cierre = :cierre
                 WHERE id_anio_escolar = :id'
            );
            $ok = $stmt->execute([
                'nombre' => $data['nombre'],
                'inicio' => $data['fecha_inicio'],
                'cierre' => $data['fecha_cierre'],
                'id' => $id,
            ]);
            return ['ok' => $ok, 'mensaje' => $ok ? 'Año escolar actualizado correctamente.' : 'No se pudo actualizar el año escolar.'];
        } catch (Throwable $e) {
            error_log('AcademicYearModel::updateYear(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar el año escolar.'];
        }
    }

    public function toggleEstado(int $id): bool
    {
        return $this->pdo->prepare(
            "UPDATE anio_escolar SET estado = IF(estado = 'ACTIVO', 'INACTIVO', 'ACTIVO') WHERE id_anio_escolar = :id"
        )->execute(['id' => $id]);
    }

    private function existsByName(string $name, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM anio_escolar WHERE nombre_periodo = :nombre';
        $params = ['nombre' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id_anio_escolar != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'nombre' => $row['nombre'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cierre' => $row['fecha_cierre'],
            'estado' => strtolower($row['estado']),
        ];
    }
}
