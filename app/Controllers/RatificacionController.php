<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RatificacionModel;
use PDO;

/**
 * Ratificación de matrícula (gestion_estudiante/ratificacion).
 * Endpoints: ratificacion/index (GET, listado) y ratificacion/ratificar/{idEstudiante} (POST, individual).
 * La ratificación es siempre individual: no hay acción masiva.
 */
class RatificacionController extends CrudController
{
    protected string $viewPath  = 'gestion_estudiante/ratificacion';
    protected string $routeName = 'ratificacion';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): RatificacionModel
    {
        /** @var RatificacionModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new RatificacionModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'grado'   => trim($_GET['grado'] ?? ''),
            'seccion' => trim($_GET['seccion'] ?? ''),
            'q'       => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [
            'stats'          => $this->modelo()->getStats(),
            'ratificaciones' => $items,
            'grados'         => $this->modelo()->getGrados(),
            'secciones'      => $this->modelo()->getSecciones(),
        ];
    }

    public function ratificar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlash('error', 'Estudiante no válido.');
            $this->redirect('index');
        }

        $resultado = $this->modelo()->ratificar($id);
        $this->setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        $this->redirect('index');
    }
}