<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/BitacoraModel.php';

class BitacoraController extends BaseController
{
    protected string $viewPath = 'audit';
    protected string $routeName = 'bitacora';

    protected array $allowedRoles = ['administrador'];

    protected function createModel(PDO $pdo)
    {
        return new BitacoraModel($pdo);
    }

    protected function extractData(array $source): array
    {
        return [];
    }

    protected function validate(array $data): array
    {
        return [];
    }

    public function index()
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $filters = $this->getFiltersFromRequest();

        $registros  = $this->model->getAll($filters, $page, $this->perPage);
        $total      = $this->model->countAll($filters);
        $totalPages = (int) ceil($total / $this->perPage);
        $stats      = $this->model->getStats();
        $acciones   = $this->model->getAcciones();

        $paginacion = [
            'desde'         => $total > 0 ? (($page - 1) * $this->perPage) + 1 : 0,
            'hasta'         => min($page * $this->perPage, $total),
            'total'         => $total,
            'pagina_actual' => $page,
            'total_paginas' => $totalPages,
        ];

        $fechaDesde = $filters['desde'];
        $fechaHasta = $filters['hasta'];

        require_once __DIR__ . "/../Views/{$this->viewPath}/index.php";
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'desde'  => trim($_GET['desde'] ?? ''),
            'hasta'  => trim($_GET['hasta'] ?? ''),
            'accion' => trim($_GET['accion'] ?? ''),
        ];
    }

    public function exportar()
    {
        $filters = $this->getFiltersFromRequest();
        $registros = $this->model->getAllSinPaginar($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bitacora.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Fecha', 'Hora', 'Usuario', 'Login', 'Acción', 'Módulo', 'IP']);

        foreach ($registros as $r) {
            fputcsv($output, [
                $r['fecha'], $r['hora'], $r['usuario'], $r['usuario_login'],
                $r['accion_label'], $r['modulo'], $r['ip'],
            ]);
        }

        fclose($output);
        exit;
    }
}