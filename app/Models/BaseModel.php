<?php

require_once __DIR__ . '/Model.php';

abstract class BaseModel extends Model
{
    protected string $table;
    protected string $primaryKey = 'id';

    // Ya no redefinimos __construct: lo hereda de Model tal cual.

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT * FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): bool
    {
        $cols = array_keys($data);
        $ph   = array_map(fn($c) => ":$c", $cols);
        $sql  = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
        return $this->pdo->prepare($sql)->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $sets = array_map(fn($c) => "$c = :$c", array_keys($data));
        $sql  = "UPDATE {$this->table} SET " . implode(',', $sets) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        return $this->pdo->prepare($sql)->execute($data);
    }

    public function delete(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id")
                          ->execute(['id' => $id]);
    }

    /**
     * Alterna un campo de estado tipo ENUM('ACTIVO','INACTIVO') —
     * el patrón universal en el esquema sidge (cargo, grado, rol de
     * usuario, tipo_documento, turno, etc. usan exactamente este
     * ENUM). Antes esto usaba `SET $field = NOT $field`, lo cual
     * NO funciona sobre un ENUM: MySQL convierte el string a
     * número (0) y lo niega (1), y 1 en un ENUM siempre apunta al
     * PRIMER valor de la lista — es decir, el registro quedaba
     * fijado en 'ACTIVO' sin importar su estado anterior.
     *
     * Si algún modelo hijo usa un campo de estado con otros valores
     * (no ACTIVO/INACTIVO), debe sobrescribir este método con su
     * propia lógica, igual que ya hace UserAccountModel.
     */
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        $sql = "UPDATE {$this->table} 
                SET $field = CASE WHEN $field = 'ACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END
                WHERE {$this->primaryKey} = :id";
        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }

    protected function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) continue;

            if ($field === 'search') {
                $conds = [];
                foreach ($this->searchableFields() as $i => $col) {
                    $key = "search$i";
                    $conds[] = "$col LIKE :$key";
                    $params[$key] = "%$value%";
                }
                if ($conds) $where[] = "(" . implode(" OR ", $conds) . ")";
            } else {
                $where[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        return [$where, $params];
    }

    // Cada modelo hijo dice en qué columnas buscar con el texto del buscador
    abstract protected function searchableFields(): array;
}