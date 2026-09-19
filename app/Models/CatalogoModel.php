<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use App\Interfaces\Toggleable;
use App\Interfaces\Listable;

/**
 * CatalogoModel
 * ---------------------------------------------------------------
 * Clase abstracta base para todos los catálogos del sistema
 * (grado, sección, turno, tipo_documento, grado_academico, título,
 * tipo_asignación, rol, parentesco, motivo_retiro).
 *
 * Todas las tablas de catálogo comparten el mismo esquema mínimo:
 *   <pk>   INT PK (el nombre de columna varía por tabla, ver $primaryKey)
 *   nombre VARCHAR
 *   estado ENUM('ACTIVO','INACTIVO')
 *
 * Implementa Creatable, Updatable, Toggleable y Listable acá, en la
 * clase abstracta intermedia, y no en cada modelo concreto: como
 * GradoModel, SeccionModel, etc. heredan de esta clase, todos pasan
 * el instanceof correspondiente en BaseController/CrudController sin
 * que haga falta declarar el "implements" en cada uno de los 10 archivos.
 *
 * NO implementa Deletable a propósito: ningún catálogo del sistema
 * admite eliminación, solo alta, edición y activar/desactivar. Si
 * algún catálogo puntual necesitara borrado en el futuro, ese
 * modelo concreto podría agregar "implements Deletable" él solo,
 * sin afectar a los demás.
 */
abstract class CatalogoModel extends BaseModel implements Creatable, Updatable, Toggleable, Listable
{
    /**
     * Nombre de la clave primaria por defecto.
     * Los modelos hijos (ej. TurnoModel) pueden sobrescribirla si su PK difiere.
     */
    protected string $primaryKey = 'id';

    /**
     * Expone el nombre real de la PK de este catálogo (id_turno, id_grado, etc.)
     * para que el controlador normalice la respuesta a la vista.
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * La búsqueda de texto libre (parámetro search) se realiza por el campo "nombre".
     */
    protected function searchableFields(): array
    {
        return ['nombre'];
    }

    /**
     * Intercepta la creación para garantizar que solo se envíe la columna 'nombre'
     * a la tabla de la BD, descartando metadatos como 'tipo'.
     */
    public function create(array $data): bool
    {
        $payload = [
            'nombre' => trim($data['nombre'] ?? ''),
        ];

        return parent::create($payload);
    }

    /**
     * Intercepta la actualización para filtrar únicamente la columna 'nombre'.
     */
    public function update(int $id, array $data): bool
    {
        $payload = [
            'nombre' => trim($data['nombre'] ?? ''),
        ];

        return parent::update($id, $payload);
    }

    /**
     * Verifica si ya existe un elemento con ese nombre dentro del mismo catálogo
     * (comparación case-insensitive), evitando duplicados al crear o editar.
     *
     * @param string   $nombre    Nombre a verificar.
     * @param int|null $excludeId ID a excluir en edición.
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