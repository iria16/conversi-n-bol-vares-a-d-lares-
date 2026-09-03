<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/AcademicStructureModel.php';

class AcademicStructureController extends BaseController
{
    protected string $viewPath = 'academicConfig';
    protected string $routeName = 'academicStructure';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): AcademicStructureModel
    {
        return new AcademicStructureModel($pdo);
    }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();
            $estructuras = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $anios = $this->model->getOptions('anio_escolar', 'id_anio_escolar', 'nombre_periodo');
            $grados = $this->model->getOptions('grado', 'id_grado', 'nombre');
            $secciones = $this->model->getOptions('seccion', 'id_seccion', 'nombre');
            $turnos = $this->model->getOptions('turno', 'id_turno', 'nombre');
            $paginacion = [
                'desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0,
                'hasta' => min($page * $this->perPage, $total), 'total' => $total,
                'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];
            require APP_PATH . "/Views/{$this->viewPath}/academic-structure.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Estructura académica');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return ['q' => trim($_GET['q'] ?? ''), 'anio' => $_GET['anio'] ?? '', 'grado' => $_GET['grado'] ?? '', 'turno' => $_GET['turno'] ?? ''];
    }

    public function storeAjax(): void { $this->saveResponse($this->model->createStructure($this->data($_POST))); }

    public function updateAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonMessage('ID de estructura inválido.', 400);
        $this->saveResponse($this->model->updateStructure($id, $this->data($_POST)));
    }

    private function data(array $source): array
    {
        $data = [
            'id_anio_escolar' => (int) ($source['id_anio_escolar'] ?? 0),
            'id_grado' => (int) ($source['id_grado'] ?? 0),
            'id_seccion' => (int) ($source['id_seccion'] ?? 0),
            'id_turno' => (int) ($source['id_turno'] ?? 0),
            'capacidad_maxima' => (int) ($source['capacidad_maxima'] ?? 0),
        ];
        $errors = [];
        foreach (['id_anio_escolar' => 'año escolar', 'id_grado' => 'grado', 'id_seccion' => 'sección', 'id_turno' => 'turno'] as $key => $label) if ($data[$key] <= 0) $errors[] = "Debe seleccionar {$label}.";
        if ($data['capacidad_maxima'] <= 0 || $data['capacidad_maxima'] > 999) $errors[] = 'La capacidad máxima debe estar entre 1 y 999.';
        if ($errors) $this->jsonMessage(implode(' ', $errors), 422);
        return $data;
    }

    private function saveResponse(array $result): never
    {
        header('Content-Type: application/json');
        if (!$result['ok']) http_response_code(400);
        echo json_encode($result);
        exit;
    }

    private function jsonMessage(string $message, int $status): never
    {
        header('Content-Type: application/json'); http_response_code($status);
        echo json_encode(['ok' => false, 'mensaje' => $message]); exit;
    }
}
