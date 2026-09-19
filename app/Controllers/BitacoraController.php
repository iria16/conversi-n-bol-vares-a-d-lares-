<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BitacoraModel;
use PDO;
use Throwable;

/**
 * Controlador de la Bitácora del Sistema (vista audit/index.php).
 *
 * Extiende ReadOnlyController: este módulo nunca escribe desde el
 * frontend, así que no hay create/store/edit/update/delete/
 * toggleStatus que redirigir ni rechazar — simplemente no existen en
 * la jerarquía. El listado paginado lo resuelve ListableIndexTrait vía
 * getFiltersFromRequest()/getExtraIndexData(); exportar() es el único
 * método propio del módulo.
 */
class BitacoraController extends ReadOnlyController
{
    protected string $viewPath  = 'auditoria';
    protected string $routeName = 'bitacora';

    protected function createModel(PDO $pdo): object
    {
        return new BitacoraModel($pdo);
    }

    /**
     * Filtros propios del módulo: rango de fechas, acción exacta y
     * texto libre (usuario/módulo/IP). Sobrescribe el genérico del
     * trait, que solo maneja "search".
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
     * Variables extra que necesita la vista de auditoría además de
     * $items/$paginacion/$filters: stats, acciones disponibles para el
     * filtro y el rango de fechas activo.
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->getModel();

        return [
            'registros'  => $items,
            'stats'      => [
                'actividad_hoy'    => $model->countHoy(),
                'alertas'          => $model->countAlertasSeguridad(),
                'usuarios_activos' => $model->countUsuariosActivosHoy(),
            ],
            // "accion" es texto libre en la BD (no ENUM), así que las
            // opciones del filtro salen de los valores que existen
            // hoy en la tabla, no de una lista fija en el código.
            'acciones'   => $model->getAccionesDisponibles(),
            'fechaDesde' => $filters['desde'] ?? '',
            'fechaHasta' => $filters['hasta'] ?? '',
        ];
    }

    /**
     * Exporta a CSV el listado completo (sin paginar) respetando
     * los mismos filtros activos en pantalla. Es el destino del
     * botón "Exportar" de la vista (bitacora/exportar). Renderiza
     * un archivo, no HTML ni JSON, por eso no usa jsonResponse()
     * ni jsonError(): en caso de fallo cae a handleError() como
     * cualquier flujo de página completa.
     */
    public function exportar(): void
    {
        try {
            $filters   = $this->getFiltersFromRequest();
            $registros = $this->getModel()->getAllForExport($filters);

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="bitacora_' . date('Y-m-d_His') . '.csv"');

            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel no rompa los acentos al abrir el CSV.
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