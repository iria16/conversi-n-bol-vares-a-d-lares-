<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EstudianteModel;
use PDO;
use Throwable;

/**
 * Consulta de expedientes (consulta_academica/estudiantes).
 * Endpoints: estudiantes/index (GET, listado con filtros) y estudiantes/show/{id} (GET, expediente completo).
 */
class EstudiantesController extends CrudController
{
    protected string $viewPath  = 'consulta_academica/estudiantes';
    protected string $routeName = 'estudiantes';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): EstudianteModel
    {
        /** @var EstudianteModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new EstudianteModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'       => trim($_GET['q'] ?? ''),
            'anio'    => trim($_GET['anio'] ?? ''),
            'grado'   => trim($_GET['grado'] ?? ''),
            'seccion' => trim($_GET['seccion'] ?? ''),
            'estado'  => trim($_GET['estado'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        return [
            'stats'          => $model->getStats(),
            'estudiantes'    => $items,
            'aniosEscolares' => $model->getAniosEscolares(),
            'grados'         => $model->getGrados(),
            'secciones'      => $model->getSecciones(),
        ];
    }

    public function show(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $model = $this->modelo();

            $estudiante = $id > 0 ? $model->getById($id) : null;
            if (!$estudiante) {
                $this->setFlash('error', 'El expediente solicitado no existe.');
                $this->redirect('index');
            }

            require __DIR__ . "/../Views/{$this->viewPath}/show.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }
}