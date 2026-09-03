<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/EstudiantesModel.php';

class EstudiantesController extends BaseController
{
    protected string $viewPath = 'students';
    protected string $routeName = 'estudiantes';

    protected function createModel(PDO $pdo)
    {
        return new EstudiantesModel($pdo);
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
        $filtros = $this->getFiltersFromRequest();

        $estudiantes = $this->model->getAll($filtros, $page, $this->perPage);
        $total       = $this->model->countAll($filtros);
        $totalPages  = (int) ceil($total / $this->perPage);

        $stats          = $this->model->getStats();
        $aniosEscolares = $this->model->getAniosEscolares();
        $grados         = $this->model->getGrados();
        $secciones      = $this->model->getSecciones();

        $paginacion = [
            'desde'         => $total > 0 ? (($page - 1) * $this->perPage) + 1 : 0,
            'hasta'         => min($page * $this->perPage, $total),
            'total'         => $total,
            'pagina_actual' => $page,
            'total_paginas' => $totalPages,
        ];

        require_once __DIR__ . "/../Views/{$this->viewPath}/index.php";
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

    public function ficha()
    {
        $id = (int)($_GET['id'] ?? 0);
        $estudiante = $id > 0 ? $this->model->getById($id) : null;
        if (!$estudiante) $this->redirect('index');

        require_once __DIR__ . "/../Views/{$this->viewPath}/record.php";
    }

    public function exportar()
    {
        $filtros = $this->getFiltersFromRequest();
        $estudiantes = $this->model->getAll($filtros, 1, PHP_INT_MAX);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=estudiantes.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Nombre', 'Cédula', 'Grado', 'Sección', 'Estado']);
        foreach ($estudiantes as $e) {
            fputcsv($output, [$e['nombre'], $e['cedula_escolar'], $e['grado'], $e['seccion'], $e['estado']]);
        }
        fclose($output);
        exit;
    }
}