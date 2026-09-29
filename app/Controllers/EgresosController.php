<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EgresoModel;
use PDO;

/**
 * Egresos / Graduación (gestion_estudiante/egreso).
 * Endpoints: egresos/index (GET, candidatos), egresos/graduar/{idEstudiante} (POST, individual)
 * y egresos/graduarMasivo (POST ids[], masivo).
 */
class EgresosController extends CrudController
{
    protected string $viewPath  = 'gestion_estudiante/egreso';
    protected string $routeName = 'egresos';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): EgresoModel
    {
        /** @var EgresoModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new EgresoModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'seccion' => trim($_GET['seccion'] ?? ''),
            'q'       => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [
            'stats'      => $this->modelo()->getStats(),
            'candidatos' => $items,
            'secciones'  => $this->modelo()->getSecciones(),
        ];
    }

    public function graduar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlash('error', 'Estudiante no válido.');
            $this->redirect('index');
        }

        $resultado = $this->modelo()->graduar($id);
        $this->setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        $this->redirect('index');
    }

    public function graduarMasivo(): void
    {
        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []), fn ($id) => $id > 0);
        if ($ids === []) {
            $this->setFlash('error', 'No se seleccionaron estudiantes.');
            $this->redirect('index');
        }

        $registrados = $this->modelo()->graduarMasivo($ids);
        $this->setFlash('success', "Egreso registrado para {$registrados} estudiante(s).");
        $this->redirect('index');
    }
}