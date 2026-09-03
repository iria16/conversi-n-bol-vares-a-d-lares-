<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Base para los modelos de catálogo simple (AnioEscolar, Grado, Seccion, Turno).
 *
 * Supuesto de esquema, común a las 4 tablas: id, nombre (VARCHAR),
 * estado ('ACTIVO'|'INACTIVO') — mismo patrón que Usuario.estado.
 */
abstract class CatalogoModel extends BaseModel
{
    protected string $primaryKey = 'id';
    protected string $campoNombre = 'nombre';
    protected string $campoEstado = 'estado';

    protected function searchableFields(): array
    {
        return [$this->campoNombre];
    }

    // -------------------------------------------------------------
    // LISTADO
    // -------------------------------------------------------------
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFiltersCatalogo($filters);

        $sql = "SELECT {$this->primaryKey} AS id, {$this->campoNombre} AS nombre, {$this->campoEstado} AS estado
                FROM {$this->table}";

        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$this->campoNombre} ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFiltersCatalogo($filters);

        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildFiltersCatalogo(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = "{$this->campoEstado} = :estado";
            $params['estado'] = strtoupper($filters['estado']); // activo -> ACTIVO
        }

        if (!empty($filters['q'])) {
            $where[] = "{$this->campoNombre} LIKE :q";
            $params['q'] = $filters['q'] . '%';
        }

        return [$where, $params];
    }

    // -------------------------------------------------------------
    // ALTA (modal "Nuevo Elemento")
    // -------------------------------------------------------------

    // Cumple el contrato de BaseModel::create(array $data): bool
    public function create(array $data): bool
    {
        $resultado = $this->crearElemento($data['nombre'] ?? '');
        return $resultado['ok'];
    }

    // Versión propia de CatalogoModel: valida duplicados y devuelve
    // el id creado o el mensaje de error, que es lo que necesita el
    // modal "Nuevo Elemento" del controlador.
    public function crearElemento(string $nombre): array
    {
        if ($this->existeNombre($nombre)) {
            return ['ok' => false, 'mensaje' => 'Ya existe un elemento con ese nombre.'];
        }

        try {
            $sql = "INSERT INTO {$this->table} ({$this->campoNombre}, {$this->campoEstado}) VALUES (:nombre, 'ACTIVO')";
            $this->pdo->prepare($sql)->execute(['nombre' => $nombre]);
            return ['ok' => true, 'id' => (int) $this->pdo->lastInsertId()];
        } catch (Throwable $e) {
            error_log(static::class . '::crearElemento(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo guardar el elemento.'];
        }
    }

    private function existeNombre(string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$this->campoNombre} = :nombre";
        $params = ['nombre' => $nombre];

        if ($excluirId !== null) {
            $sql .= " AND {$this->primaryKey} != :excluirId";
            $params['excluirId'] = $excluirId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    // -------------------------------------------------------------
    // EDICIÓN (ícono lápiz)
    // -------------------------------------------------------------
    public function updateNombre(int $id, string $nombre): bool
    {
        if ($this->existeNombre($nombre, $id)) return false;

        $sql = "UPDATE {$this->table} SET {$this->campoNombre} = :nombre WHERE {$this->primaryKey} = :id";
        return $this->pdo->prepare($sql)->execute(['nombre' => $nombre, 'id' => $id]);
    }

    // -------------------------------------------------------------
    // ACTIVAR / DESACTIVAR (switch en cada fila)
    // -------------------------------------------------------------
    public function toggleEstado(int $id): array
    {
        $sql = "UPDATE {$this->table}
                SET {$this->campoEstado} = IF({$this->campoEstado} = 'ACTIVO', 'INACTIVO', 'ACTIVO')
                WHERE {$this->primaryKey} = :id";

        $ok = $this->pdo->prepare($sql)->execute(['id' => $id]);
        return ['ok' => $ok];
    }
}