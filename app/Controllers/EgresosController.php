<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EgresoModel;
use PDO;
use Throwable;

/**
 * Egresos / Graduación (gestion_estudiante/egreso).
 * Endpoints: egresos/index (GET, candidatos), egresos/graduar (POST, individual)
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
        /** @var EgresoModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new EgresoModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        $filters = [
            // Casting a (string) para cumplir estrictamente con strict_types
            'seccion' => trim((string) ($_GET['seccion'] ?? '')),
            'q'       => trim((string) ($_GET['q'] ?? '')),
        ];
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null) {
            $filters['id_turno'] = $turno;
        }
        return $filters;
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [
            'stats'      => $this->modelo()->getStats($filters['id_turno'] ?? null),
            'candidatos' => $items,
            'secciones'  => $this->modelo()->getSecciones(),
        ];
    }

    /**
 * Registra el egreso de un solo estudiante.
 * SOLO acepta peticiones POST por seguridad (previene CSRF).
*/
    public function graduar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlash('error', 'Acción no permitida por esta vía.');
            $this->redirect('index');
        }

        $id = (int) ($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            $this->setFlash('error', 'Estudiante no válido.');
            $this->redirect('index');
        }

        try {
            $resultado = $this->modelo()->graduar($id, $this->turnoSecretariaActual(), (int) ($this->idUsuarioActual() ?? 0));
            $this->setFlash($resultado['ok'] ? 'success' : 'error', $resultado['mensaje']);
        } catch (Throwable $e) {
            error_log('Error en EgresosController::graduar: ' . $e->getMessage());
            $this->setFlash('error', 'Ocurrió un error interno al intentar registrar el egreso.');
        }

        $this->redirect('index');
    }

    /**
 * Registra el egreso de múltiples estudiantes a la vez.
 * SOLO acepta peticiones POST por seguridad.
*/
    public function graduarMasivo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlash('error', 'Acción no permitida por esta vía.');
            $this->redirect('index');
        }

        $ids = array_filter(array_map('intval', $_POST['ids'] ?? []), fn ($id) => $id > 0);
        
        if ($ids === []) {
            $this->setFlash('error', 'No se seleccionaron estudiantes.');
            $this->redirect('index');
        }

        try {
            $resultado = $this->modelo()->graduarMasivo(
                $ids,
                $this->turnoSecretariaActual(),
                (int) ($this->idUsuarioActual() ?? 0)
            );

            $this->setFlash('success', "Egreso registrado para {$resultado['registrados']} estudiante(s).");

            /* Los que no salieron se nombran uno por uno con su motivo. Antes solo se
 informaba la cantidad, y con la lista filtrada por 6to grado quien lo ejecutó
 no tenía forma de saber a qué alumno había que volver.*/
            if ($resultado['fallidos'] !== []) {
                $detalle = [];
                foreach ($resultado['fallidos'] as $fallo) {
                    $detalle[] = $fallo['estudiante'] . ': ' . $fallo['mensaje'];
                }
                $this->setFlash('error', 'No se egresaron ' . count($resultado['fallidos']) . ' estudiante(s). '
                    . mb_substr(implode(' | ', $detalle), 0, 900));
            }
        } catch (Throwable $e) {
            error_log('Error en EgresosController::graduarMasivo: ' . $e->getMessage());
            $this->setFlash('error', 'Ocurrió un error interno al intentar procesar el egreso masivo.');
        }

        $this->redirect('index');
    }
}