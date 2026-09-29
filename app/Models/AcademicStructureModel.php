<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Matriculas;

use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Updatable;

/**
 * Estructura académica: combinación de año escolar + grado + sección + turno con capacidad máxima (estructura_academica).
 * El conteo de alumnos sale de `matricula` (no de `inscripcion`, que guarda el año de origen y mostraría 0 en una sección llena).
 * Cuenta solo INSCRIPCION y RATIFICACION, para coincidir con CupoEstructuraTrait::conteoDeOcupados.
 */
class AcademicStructureModel extends BaseModel implements Listable, Creatable, Updatable
{
    protected string $table = 'estructura_academica';
    protected string $primaryKey = 'id_estructura_academica';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT ea.id_estructura_academica AS id, ea.id_anio_escolar,
                        ea.id_grado, ea.id_seccion, ea.id_turno, ea.capacidad_maxima,
                        a.nombre_periodo AS anio,
                        g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                        (SELECT COUNT(DISTINCT m.id_estudiante) FROM matricula m
                          WHERE m.id_estructura_academica = ea.id_estructura_academica
                            AND " . Matriculas::condicionCursando('m') . ") AS estudiantes
                 FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, g.nombre, s.nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['capacidad_maxima'] = (int) $row['capacidad_maxima'];
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total      = (int) $this->pdo->query('SELECT COUNT(*) FROM estructura_academica')->fetchColumn();
        $grados     = (int) $this->pdo->query('SELECT COUNT(DISTINCT id_grado) FROM estructura_academica')->fetchColumn();
        $secciones  = (int) $this->pdo->query('SELECT COUNT(DISTINCT id_seccion) FROM estructura_academica')->fetchColumn();
        $capacidad  = (int) $this->pdo->query('SELECT COALESCE(SUM(capacidad_maxima), 0) FROM estructura_academica')->fetchColumn();
        return ['total' => $total, 'grados' => $grados, 'secciones' => $secciones, 'capacidad' => $capacidad];
    }

    /**
     * Catálogos activos (id + nombre) para filtros y modales: anio_escolar, grado, seccion y turno.
     * Cada tabla declara su columna de etiqueta (anio_escolar usa nombre_periodo) y siempre se devuelve como 'nombre'.
     */
    public function getOptions(string $table): array
    {
        $catalogos = [
            'anio_escolar' => ['etiqueta' => 'nombre_periodo', 'orden' => 'anio_inicio DESC'],
            'grado'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'seccion'      => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'turno'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
        ];
        if (!isset($catalogos[$table])) {
            return [];
        }
        $etiqueta = $catalogos[$table]['etiqueta'];
        $orden    = $catalogos[$table]['orden'];
        $stmt = $this->pdo->query(
            "SELECT id_{$table} AS id, {$etiqueta} AS nombre
             FROM {$table} WHERE estado = 'ACTIVO' ORDER BY {$orden}"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Alta de una combinación académica (contrato Creatable).
     * El duplicado (UNIQUE año, grado, sección, turno) se valida en el controlador antes de llegar aquí.
     */
    public function create(array $data): bool
    {
        return $this->pdo->prepare(
            'INSERT INTO estructura_academica (id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima)
             VALUES (:anio, :grado, :seccion, :turno, :capacidad)'
        )->execute([
            'anio'      => (int) $data['id_anio_escolar'],
            'grado'     => (int) $data['id_grado'],
            'seccion'   => (int) $data['id_seccion'],
            'turno'     => (int) $data['id_turno'],
            'capacidad' => (int) $data['capacidad_maxima'],
        ]);
    }

    /**
     * Indica si ya existe esa combinación (año + grado + sección + turno).
     * Con $exceptoId se deja fuera la fila que se está editando.
     */
    public function existeCombination(array $data, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM estructura_academica
                WHERE id_anio_escolar = :anio AND id_grado = :grado
                  AND id_seccion = :seccion AND id_turno = :turno';
        $params = [
            'anio'    => (int) $data['id_anio_escolar'],
            'grado'   => (int) $data['id_grado'],
            'seccion' => (int) $data['id_seccion'],
            'turno'   => (int) $data['id_turno'],
        ];
        if ($exceptoId !== null) {
            $sql .= ' AND id_estructura_academica != :id';
            $params['id'] = $exceptoId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Edición de la combinación académica (contrato Updatable).
     * Recibe el id_estructura_academica y los mismos campos que create().
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            'UPDATE estructura_academica
             SET id_anio_escolar = :anio, id_grado = :grado, id_seccion = :seccion,
                 id_turno = :turno, capacidad_maxima = :capacidad
             WHERE id_estructura_academica = :id'
        )->execute([
            'anio'      => (int) $data['id_anio_escolar'],
            'grado'     => (int) $data['id_grado'],
            'seccion'   => (int) $data['id_seccion'],
            'turno'     => (int) $data['id_turno'],
            'capacidad' => (int) $data['capacidad_maxima'],
            'id'        => $id,
        ]);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach (['anio' => 'ea.id_anio_escolar', 'grado' => 'ea.id_grado', 'seccion' => 'ea.id_seccion', 'turno' => 'ea.id_turno'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            foreach (['g.nombre', 's.nombre', 't.nombre'] as $i => $col) {
                $params["q$i"] = $filters['q'] . '%';
            }
            $where[] = '(g.nombre LIKE :q0 OR s.nombre LIKE :q1 OR t.nombre LIKE :q2)';
        }
        return [$where, $params];
    }

}