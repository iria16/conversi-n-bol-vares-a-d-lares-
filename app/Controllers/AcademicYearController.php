<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/AcademicYearModel.php';

class AcademicYearController extends BaseController
{
    protected string $viewPath = 'academicConfig';
    protected string $routeName = 'academicYear';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): AcademicYearModel
    {
        return new AcademicYearModel($pdo);
    }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();
            $anios = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $paginacion = [
                'desde' => $total > 0 ? (($page - 1) * $this->perPage) + 1 : 0,
                'hasta' => min($page * $this->perPage, $total),
                'total' => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            require APP_PATH . "/Views/{$this->viewPath}/school-year.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Años escolares');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q' => trim($_GET['q'] ?? ''),
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    public function storeAjax(): void
    {
        $this->jsonResponse($this->model->createYear($this->validatedData($_POST)), 'guardar');
    }

    public function updateAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErrorMessage('ID de año escolar inválido.', 400);
        $this->jsonResponse($this->model->updateYear($id, $this->validatedData($_POST)), 'actualizar');
    }

    public function toggleEstadoAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->jsonErrorMessage('ID de año escolar inválido.', 400);

        try {
            $ok = $this->model->toggleEstado($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Años escolares');
            return;
        }
        $this->jsonResponse(['ok' => $ok, 'mensaje' => $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.'], 'actualizar');
    }

    private function validatedData(array $source): array
    {
        $data = [
            'nombre' => trim($source['nombre'] ?? ''),
            'fecha_inicio' => trim($source['fecha_inicio'] ?? ''),
            'fecha_cierre' => trim($source['fecha_cierre'] ?? ''),
            'estado' => strtoupper($source['estado'] ?? 'INACTIVO'),
        ];
        $errors = $this->validate($data);
        if ($errors) $this->jsonErrorMessage(implode(' ', $errors), 422);
        return $data;
    }

    protected function validate(array $data): array
    {
        $errors = [];
        if ($data['nombre'] === '') $errors[] = 'El nombre del período es obligatorio.';
        if (mb_strlen($data['nombre']) > 20) $errors[] = 'El nombre no puede superar 20 caracteres.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_inicio'])) $errors[] = 'La fecha de inicio no es válida.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_cierre'])) $errors[] = 'La fecha de cierre no es válida.';
        if ($data['fecha_inicio'] !== '' && $data['fecha_cierre'] !== '' && $data['fecha_inicio'] > $data['fecha_cierre']) $errors[] = 'La fecha de cierre debe ser posterior al inicio.';
        if (!in_array($data['estado'], ['ACTIVO', 'INACTIVO'], true)) $errors[] = 'El estado seleccionado no es válido.';
        return $errors;
    }

    private function jsonResponse(array $response, string $action): void
    {
        header('Content-Type: application/json');
        if (!$response['ok']) http_response_code(400);
        echo json_encode($response);
        exit;
    }

    private function jsonErrorMessage(string $message, int $code): never
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['ok' => false, 'mensaje' => $message]);
        exit;
    }
}
