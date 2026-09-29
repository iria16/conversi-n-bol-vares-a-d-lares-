<?php

declare(strict_types=1);

namespace App\Models;

/**
 * TituloModel
 *
 * Catálogo `titulo`: desde que la tabla `titulo` tiene la columna
 * `id_grado_academico` (NOT NULL, FK a grado_academico), todo título
 * pertenece a un grado. Por eso sobrescribe create()/update() de
 * CatalogoModel (que solo permite la columna 'nombre') con SQL directo,
 * igual que hace InstitucionModel con la columna `tipo`.
 */
class TituloModel extends CatalogoModel
{
    protected string $table = 'titulo';
    protected string $primaryKey = 'id_titulo';
    protected string $campoNombre = 'nombre';

    /**
     * Un mismo nombre de título puede existir en varios grados académicos
     * (p. ej. "sistema" en Licenciatura y en Ingeniería). CatalogoModel::existeNombre()
     * solo mira el nombre, así que el duplicado del alto rápido se valida aquí,
     * por grado.
     *
     * @param string      $nombre    Nombre normalizado (case-insensitive).
     * @param int         $idGrado   Grado académico donde se intenta guardar.
     * @param int|null    $excludeId Id de título a ignorar (edición).
     */
    public function existeTituloEnGrado(string $nombre, int $idGrado, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM titulo
                WHERE LOWER(nombre) = LOWER(:nombre)
                  AND id_grado_academico = :id_grado_academico';
        $params = [
            'nombre'             => trim($nombre),
            'id_grado_academico' => $idGrado,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id_titulo != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @param array $data Se espera 'nombre' y 'id_grado_academico'.
     */
    public function create(array $data): bool
    {
        return $this->pdo->prepare(
            'INSERT INTO titulo (nombre, id_grado_academico)
             VALUES (:nombre, :id_grado_academico)'
        )->execute([
            'nombre'             => trim((string) ($data['nombre'] ?? '')),
            'id_grado_academico' => (int) ($data['id_grado_academico'] ?? 0),
        ]);
    }

    /**
     * @param array $data Se espera 'nombre' y 'id_grado_academico'.
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            'UPDATE titulo
             SET nombre = :nombre, id_grado_academico = :id_grado_academico
             WHERE id_titulo = :id'
        )->execute([
            'id'                 => $id,
            'nombre'             => trim((string) ($data['nombre'] ?? '')),
            'id_grado_academico' => (int) ($data['id_grado_academico'] ?? 0),
        ]);
    }
}