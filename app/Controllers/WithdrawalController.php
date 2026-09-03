<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/WithdrawalModel.php';

class WithdrawalController extends BaseController
{
    protected string $viewPath = 'students';
    protected string $routeName = 'withdrawal';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): WithdrawalModel { return new WithdrawalModel($pdo); }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();
            $solicitudes = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $aniosEscolares = $this->model->getYears();
            $motivos = $this->model->getReasons();
            $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
            require APP_PATH . "/Views/{$this->viewPath}/withdrawal.php";
        } catch (Throwable $e) { $this->handleError($e, 'Retiros pendientes'); }
    }

    protected function getFiltersFromRequest(): array
    {
        return ['q' => trim($_GET['q'] ?? ''), 'estado' => $_GET['estado'] ?? '', 'anio' => $_GET['anio'] ?? '', 'motivo' => $_GET['motivo'] ?? ''];
    }

    public function updateStatusAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0); $status = strtoupper($_POST['estado'] ?? '');
        if ($id <= 0 || !in_array($status, ['APROBADO', 'RECHAZADO'], true)) $this->message('Datos de retiro inválidos.', 422);
        try { $ok = $this->model->updateStatus($id, $status); } catch (Throwable $e) { $this->jsonError($e, 'Retiros pendientes'); return; }
        $this->respond(['ok' => $ok, 'mensaje' => $ok ? 'Solicitud actualizada correctamente.' : 'No se pudo actualizar la solicitud.']);
    }

    private function respond(array $result): never { header('Content-Type: application/json'); if (!$result['ok']) http_response_code(400); echo json_encode($result); exit; }
    private function message(string $message, int $code): never { header('Content-Type: application/json'); http_response_code($code); echo json_encode(['ok' => false, 'mensaje' => $message]); exit; }
}
