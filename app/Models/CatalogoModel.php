<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use App\Interfaces\Toggleable;
use App\Interfaces\Listable;

/**
 * Base abstracta de los catálogos (grado, sección, turno, tipo_documento, rol, parentesco, etc.), con esquema <pk>, nombre y estado ACTIVO/INACTIVO.
 * Implementa Creatable, Updatable, Toggleable y Listable aquí para que todos los hijos pasen el instanceof sin declararlo cada uno.
 * No implementa Deletable a propósito: los catálogos solo se crean, editan y activan/desactivan.
 */
abstract class CatalogoModel extends BaseModel implements Creatable, Updatable, Toggleable, Listable
{
    /** Nombre de la PK por defecto; los hijos (ej. TurnoModel) la sobrescriben si difiere. */
    protected string $primaryKey = 'id';

    /**
     * Expone el nombre real de la PK de este catálogo (id_turno, id_grado, etc.).
     * El controlador lo usa para normalizar la respuesta a la vista.
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * La búsqueda de texto libre (parámetro search) se hace por el campo "nombre".
     */
    protected function searchableFields(): array
    {
        return ['nombre'];
    }

    /**
     * Al crear envía solo la columna 'nombre' a la BD, descartando metadatos como 'tipo'.
     */
    public function create(array $data): bool
    {
        $payload = [
            'nombre' => trim($data['nombre'] ?? ''),
        ];

        return parent::create($payload);
    }

    /**
     * Al actualizar filtra únicamente la columna 'nombre'.
     */
    public function update(int $id, array $data): bool
    {
        $payload = [
            'nombre' => trim($data['nombre'] ?? ''),
        ];

        return parent::update($id, $payload);
    }

    /**
     * Indica si ya existe un elemento con ese nombre en el catálogo (sin distinguir mayúsculas).
     * Con $excludeId se omite el registro que se está editando.
     */
    public function existeNombre(string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE LOWER(nombre) = LOWER(:nombre)";
        $params = ['nombre' => trim($nombre)];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND {$this->primaryKey} != :excludeId";
            $params['excludeId'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}