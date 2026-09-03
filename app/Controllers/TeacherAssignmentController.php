<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/TeacherAssignmentModel.php';

class TeacherAssignmentController extends BaseController
{
    protected string $viewPath = 'academicConfig';
    protected string $routeName = 'teacherAssignment';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): TeacherAssignmentModel
    {
        return new TeacherAssignmentModel($pdo);
    }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();
            $asignaciones = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $anios = $this->model->getOptions('anio_escolar', 'id_anio_escolar', 'nombre_periodo');
            $grados = $this->model->getOptions('grado', 'id_grado', 'nombre');
            $secciones = $this->model->getOptions('seccion', 'id_seccion', 'nombre');
            $estructuras = $this->model->getStructures();
            $docentes = $this->model->getTeachers();
            $tiposAsignacion = $this->model->getTypes();
            $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
            require APP_PATH . "/Views/{$this->viewPath}/teacher-assignment.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Asignación docente');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return ['q' => trim($_GET['q'] ?? ''), 'anio' => $_GET['anio'] ?? '', 'grado' => $_GET['grado'] ?? '', 'seccion' => $_GET['seccion'] ?? ''];
    }

    public function assignAjax(): void
    {
        $data = $this->data($_POST);
        $this->respond($this->model->assign($data));
    }

    public function removeAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de asignación inválido.', 400);
        $this->respond(['ok' => $this->model->remove($id), 'mensaje' => 'Asignación retirada correctamente.']);
    }

    private function data(array $source): array
    {
        $data = ['id_docente' => (int) ($source['id_docente'] ?? 0), 'id_estructura_academica' => (int) ($source['id_estructura_academica'] ?? 0), 'id_tipo_asignacion' => (int) ($source['id_tipo_asignacion'] ?? 0), 'fecha_inicio' => trim($source['fecha_inicio'] ?? '')];
        $errors = [];
        foreach (['id_docente' => 'docente', 'id_estructura_academica' => 'sección', 'id_tipo_asignacion' => 'tipo de asignación'] as $key => $label) if ($data[$key] <= 0) $errors[] = "Debe seleccionar {$label}.";
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_inicio'])) $errors[] = 'La fecha de inicio no es válida.';
        if ($errors) $this->message(implode(' ', $errors), 422);
        return $data;
    }

    private function respond(array $result): never
    {
        header('Content-Type: application/json');
        if (!$result['ok']) http_response_code(400);
        echo json_encode($result); exit;
    }

    private function message(string $message, int $code): never
    {
        header('Content-Type: application/json'); http_response_code($code); echo json_encode(['ok' => false, 'mensaje' => $message]); exit;
    }
}
