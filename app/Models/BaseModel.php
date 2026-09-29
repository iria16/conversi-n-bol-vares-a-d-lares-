<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo base con el CRUD genérico sobre $table y $primaryKey.
 * Los modelos hijos sobrescriben lo que necesiten (joins, estados propios, filtros).
 */
abstract class BaseModel extends Model
{
    /** Nombre de la tabla; cada hijo debe asignarlo. */
    protected string $table;

    /** Columna de clave primaria (por defecto 'id'). */
    protected string $primaryKey = 'id';

    /**
     * Lista registros con filtros y paginación, del más reciente al más antiguo por clave primaria.
     * Los filtros se arman con buildFilters().
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los registros que cumplen los filtros; sirve para calcular el total de páginas.
     */
    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca un registro por su clave primaria; devuelve null si no existe.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Inserta un registro; las claves de $data deben ser nombres de columna reales.
     * Los nombres de columna van directo al SQL, así que nunca deben venir del usuario.
     */
    public function create(array $data): bool
    {
        $cols = array_keys($data);
        $ph   = array_map(fn($c) => ":$c", $cols);
        $sql  = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
        return $this->pdo->prepare($sql)->execute($data);
    }

    /**
     * Actualiza las columnas indicadas en $data del registro con ese id.
     * Igual que create(), las claves de $data van directo al SQL.
     */
    public function update(int $id, array $data): bool
    {
        $sets = array_map(fn($c) => "$c = :$c", array_keys($data));
        $sql  = "UPDATE {$this->table} SET " . implode(',', $sets) . " WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;
        return $this->pdo->prepare($sql)->execute($data);
    }

    /**
     * Elimina físicamente el registro con ese id.
     */
    public function delete(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id")
                         ->execute(['id' => $id]);
    }

    /**
     * Alterna $field entre 'ACTIVO' e 'INACTIVO'.
     * Los modelos con otro conjunto de estados (como AcademicYearModel) lo sobrescriben.
     */
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        $sql = "UPDATE {$this->table} 
                SET $field = CASE WHEN $field = 'ACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END
                WHERE {$this->primaryKey} = :id";
        
        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }

    /**
     * Convierte los filtros en condiciones WHERE y parámetros; ignora los vacíos.
     * La clave 'search' busca con LIKE en searchableFields(); cualquier otra clave se compara por igualdad.
     */
    protected function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        foreach ($filters as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if ($field === 'search') {
                $conds = [];
                foreach ($this->searchableFields() as $i => $col) {
                    $key = "search$i";
                    $conds[] = "$col LIKE :$key";
                    $params[$key] = "%$value%";
                }
                if ($conds) {
                    $where[] = "(" . implode(" OR ", $conds) . ")";
                }
            } else {
                $where[] = "$field = :$field";
                $params[$field] = $value;
            }
        }

        return [$where, $params];
    }

    /**
     * Columnas donde busca el filtro 'search'; los hijos las sobrescriben (vacío por defecto).
     */
    protected function searchableFields(): array
    {
        return [];
    }
}