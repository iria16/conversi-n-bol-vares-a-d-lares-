<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BitacoraModel;
use PDO;
use Throwable;

/**
 * Bitácora del Sistema (vista audit/index.php), solo lectura.
 * El listado lo resuelve ListableIndexTrait; exportar() es el único método propio.
 */
class BitacoraController extends ReadOnlyController
{
    protected string $viewPath  = 'auditoria';
    protected string $routeName = 'bitacora';

    /**
     * Modelo con su tipo real (BitacoraModel), para que el editor
     * resuelva sus métodos en vez de verlos sobre `object`.
     */
    private function modelo(): BitacoraModel
    {
        /** @var BitacoraModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new BitacoraModel($pdo);
    }

    /**
     * Filtros del módulo: rango de fechas, acción exacta y texto libre
     * (usuario, módulo, IP). Sustituye al genérico del trait.
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'desde'  => trim($_GET['desde'] ?? ''),
            'hasta'  => trim($_GET['hasta'] ?? ''),
            'accion' => trim($_GET['accion'] ?? ''),
            'search' => trim($_GET['q'] ?? ''),
        ];
    }

    /**
     * Variables extra de la vista: registros, stats, acciones del filtro
     * y rango de fechas activo.
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();

        return [
            'registros'  => $items,
            'stats'      => [
                'actividad_hoy'    => $model->countHoy(),
                'alertas'          => $model->countAlertasSeguridad(),
                'usuarios_activos' => $model->countUsuariosActivosHoy(),
            ],
            // "accion" es texto libre en la BD: las opciones salen de los
            // valores existentes en la tabla, no de una lista fija.
            'acciones'   => $model->getAccionesDisponibles(),
            'fechaDesde' => $filters['desde'] ?? '',
            'fechaHasta' => $filters['hasta'] ?? '',
        ];
    }

    /**
     * Exporta a CSV el listado completo (sin paginar) con los filtros activos.
     * Ruta: bitacora/exportar. Entrega un archivo, no JSON, así que un
     * fallo cae a handleError().
     */
    public function exportar(): void
    {
        try {
            $filters   = $this->getFiltersFromRequest();
            $registros = $this->modelo()->getAllForExport($filters);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="bitacora_' . date('Y-m-d_His') . '.csv"');

            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel no rompa los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Hora', 'Usuario', 'Usuario (login)', 'Acción', 'Módulo', 'IP']);

            foreach ($registros as $r) {
                fputcsv($out, [
                    $r['fecha'],
                    $r['hora'],
                    $r['usuario'],
                    $r['usuario_login'],
                    $r['accion_label'],
                    $r['modulo'],
                    $r['ip'],
                ]);
            }

            fclose($out);
            exit;
        } catch (Throwable $e) {
            $this->handleError($e, 'Bitácora del Sistema');
        }
    }
}