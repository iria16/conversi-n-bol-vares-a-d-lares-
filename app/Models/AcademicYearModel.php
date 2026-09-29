<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;

use Throwable;

/**
 * Años escolares: anio_escolar (nombre_periodo, anio_inicio, anio_cierre, estado ENUM 'ACTIVO','CERRADO','PLANIFICACIÓN').
 * La vista espera fecha_inicio/fecha_cierre y estado 'activo'/'inactivo'; el modelo mapea desde las columnas reales.
 * El toggle alterna ACTIVO/CERRADO y garantiza un único período activo.
 */
class AcademicYearModel extends BaseModel implements Listable, Creatable, Updatable, Toggleable
{
    protected string $table = 'anio_escolar';
    protected string $primaryKey = 'id_anio_escolar';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT id_anio_escolar AS id, nombre_periodo AS nombre,
                       anio_inicio AS fecha_inicio, anio_cierre AS fecha_cierre, estado
                FROM anio_escolar";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY fecha_inicio DESC, id_anio_escolar DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = 'SELECT COUNT(*) FROM anio_escolar';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total    = (int) $this->pdo->query('SELECT COUNT(*) FROM anio_escolar')->fetchColumn();
        $activos  = (int) $this->pdo->query("SELECT COUNT(*) FROM anio_escolar WHERE estado = 'ACTIVO'")->fetchColumn();
        $actual   = AniosEscolares::nombreEnCurso($this->pdo);

        return [
            'total'    => $total,
            'activos'  => $activos,
            'inactivos' => $total - $activos,
            'actual'   => $actual ?: 'Sin período activo',
        ];
    }

    /**
     * Alta de un año escolar (contrato Creatable); si nace activo, cierra los demás en la misma transacción.
     * El nombre duplicado se valida en el controlador (ver existePorNombre()).
     */
    public function create(array $data): bool
    {
        $estado = ($data['estado'] ?? '') === 'ACTIVO' ? 'ACTIVO' : 'CERRADO';

        $this->pdo->beginTransaction();

        try {
            if ($estado === 'ACTIVO') {
                $this->pdo->exec("UPDATE anio_escolar SET estado = 'CERRADO' WHERE estado = 'ACTIVO'");
            }

            $this->pdo->prepare(
                'INSERT INTO anio_escolar (nombre_periodo, anio_inicio, anio_cierre, estado)
                 VALUES (:nombre, :inicio, :cierre, :estado)'
            )->execute([
                'nombre' => $data['nombre'],
                'inicio' => $data['fecha_inicio'],
                'cierre' => $data['fecha_cierre'],
                'estado' => $estado,
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Edición del período (contrato Updatable). No cambia el estado: para eso está toggleStatus().
     */
    public function update(int $id, array $data): bool
    {
        return $this->pdo->prepare(
            'UPDATE anio_escolar
             SET nombre_periodo = :nombre, anio_inicio = :inicio, anio_cierre = :cierre
             WHERE id_anio_escolar = :id'
        )->execute([
            'nombre' => $data['nombre'],
            'inicio' => $data['fecha_inicio'],
            'cierre' => $data['fecha_cierre'],
            'id'     => $id,
        ]);
    }

    /**
     * Activa o cierra un período (contrato Toggleable), con la firma de BaseModel::toggleStatus().
     * Aquí estado es un enum (ACTIVO, CERRADO, PLANIFICACIÓN); al activar uno se cierra el que estuviera activo.
     */
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT estado FROM anio_escolar WHERE id_anio_escolar = :id');
            $stmt->execute(['id' => $id]);
            $estado = $stmt->fetchColumn();

            if ($estado === false) {
                return false;
            }

            $this->pdo->beginTransaction();

            if ($estado === 'ACTIVO') {
                $this->pdo->prepare("UPDATE anio_escolar SET estado = 'CERRADO' WHERE id_anio_escolar = :id")->execute(['id' => $id]);
            } else {
                $this->pdo->exec("UPDATE anio_escolar SET estado = 'CERRADO' WHERE estado = 'ACTIVO'");
                $this->pdo->prepare("UPDATE anio_escolar SET estado = 'ACTIVO' WHERE id_anio_escolar = :id")->execute(['id' => $id]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('AcademicYearModel::toggleStatus(): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Indica si ya existe un período con ese nombre.
     * Con $exceptoId se deja fuera la fila que se está editando.
     */
    public function existePorNombre(string $nombre, ?int $exceptoId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM anio_escolar WHERE nombre_periodo = :nombre';
        $params = ['nombre' => $nombre];

        if ($exceptoId !== null) {
            $sql .= ' AND id_anio_escolar != :id';
            $params['id'] = $exceptoId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getActiveId(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            if ($filters['estado'] === 'inactivo') {
                $where[] = "estado != 'ACTIVO'";
            } else {
                $where[] = "estado = 'ACTIVO'";
            }
        }
        if (!empty($filters['q'])) {
            $where[] = 'nombre_periodo LIKE :q';
            $params['q'] = $filters['q'] . '%';
        }

        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'nombre'       => $row['nombre'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cierre' => $row['fecha_cierre'],
            'estado'       => strtoupper((string) $row['estado']) === 'ACTIVO' ? 'activo' : 'inactivo',
        ];
    }
}