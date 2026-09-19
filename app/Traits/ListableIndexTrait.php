<?php

declare(strict_types=1);

namespace App\Traits;

use App\Interfaces\Listable;
use Throwable;

/**
 * Listado paginado con filtros, compartido por cualquier controlador
 * que muestre una tabla — tenga o no acciones de escritura. Vive
 * separado de CrudController para que un módulo de solo lectura
 * (ver ReadOnlyController) no herede store/update/delete/toggleStatus
 * que nunca va a usar: la ausencia del método documenta mejor la
 * intención que un guard `instanceof` en tiempo de ejecución.
 *
 * Requiere que la clase que lo usa (siempre un BaseController) provea
 * getModel(), handleError() y $viewPath. Puede sobrescribir $perPage,
 * getFiltersFromRequest() y getExtraIndexData() según el módulo.
 */
trait ListableIndexTrait
{
    protected int $perPage = 10;

    public function index(): void
    {
        try {
            $model   = $this->getModel();
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            if ($model instanceof Listable) {
                $items = $model->getAll($filters, $page, $this->perPage);
                $total = $model->countAll($filters);
            } else {
                $items = [];
                $total = 0;
            }

            // Única fuente de verdad para la paginación.
            $paginacion = $this->buildPagination($total, $page, $this->perPage);
            $totalPages = $paginacion['total_paginas']; // compatibilidad con vistas existentes

            // Variables adicionales que cada módulo necesite en su vista
            // (stats, catálogos para modales, etc). Con EXTR_SKIP el hijo
            // no puede pisar $items/$paginacion/$filters por accidente.
            extract($this->getExtraIndexData($items, $filters), EXTR_SKIP);

            require __DIR__ . "/../Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    protected function buildPagination(int $total, int $page, int $perPage): array
    {
        return [
            'desde'         => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
            'hasta'         => min($page * $perPage, $total),
            'total'         => $total,
            'pagina_actual' => $page,
            'total_paginas' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    protected function getFiltersFromRequest(): array
    {
        return ['search' => trim($_GET['search'] ?? '')];
    }

    /**
     * Gancho opcional: variables extra para la vista index de cada módulo
     * (stats, catálogos para modales, metadatos de estado, etc).
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [];
    }
}