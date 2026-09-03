<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/StaffModel.php';

class StaffController extends BaseController
{
    protected string $viewPath = 'staff';
    protected string $routeName = 'staff';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): StaffModel { return new StaffModel($pdo); }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = ['q' => trim($_GET['q'] ?? ''), 'cargo' => $_GET['cargo'] ?? '', 'estado' => $_GET['estado'] ?? ''];
            $empleados = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $cargos = $this->model->getOptions('cargo', 'id_cargo', 'nombre');
            $tiposDocumento = $this->model->getOptions('tipo_documento', 'id_tipo_documento', 'nombre');
            $gradosAcademicos = $this->model->getOptions('grado_academico', 'id_grado_academico', 'nombre');
            $titulos = $this->model->getOptions('titulo', 'id_titulo', 'nombre');
            $instituciones = $this->model->getOptions('institucion', 'id_institucion', 'nombre');
            $estados = $this->model->getEstados();
            $tiposInstitucion = $this->model->getOptions('tipo_institucion', 'id_tipo_institucion', 'nombre');
            $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
            require APP_PATH . "/Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) { $this->handleError($e, 'Gestión de empleados'); }
    }

    public function listAjax(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = ['q' => trim($_GET['q'] ?? ''), 'cargo' => $_GET['cargo'] ?? '', 'estado' => $_GET['estado'] ?? ''];
        try {
            $empleados  = $this->model->getAll($filters, $page, $this->perPage);
            $total      = $this->model->countAll($filters);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'empleados' => $empleados, 'paginacion' => $paginacion]);
        exit;
    }

    public function municipiosPorEstadoAjax(): void
    {
        $id = (int) ($_GET['id_estado'] ?? 0);
        if ($id <= 0) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'municipios' => []]); exit; }
        try {
            $municipios = $this->model->getMunicipiosPorEstado($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'municipios' => $municipios]);
        exit;
    }

    public function parroquiasPorMunicipioAjax(): void
    {
        $id = (int) ($_GET['id_municipio'] ?? 0);
        if ($id <= 0) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'parroquias' => []]); exit; }
        try {
            $parroquias = $this->model->getParroquiasPorMunicipio($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'parroquias' => $parroquias]);
        exit;
    }

    public function storeAjax(): void
    {
        $data = $this->data($_POST);
        try { $result = $this->model->createStaff($data); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    public function toggleStatusAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        try { $ok = $this->model->toggleStatus($id); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond(['ok' => $ok, 'mensaje' => $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.']);
    }

    public function getByIdAjax(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        try {
            $row = $this->model->getById($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        if (!$row) $this->message('Empleado no encontrado.', 404);
        $this->respond(['ok' => true, 'datos' => $row]);
    }

    public function updateAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        $data = $this->data($_POST);
        try { $result = $this->model->updateStaff($id, $data); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    private function data(array $source): array
    {
        $data = [
            'persona' => ['tipo_documento' => trim($source['tipo_documento'] ?? ''), 'numero_documento' => trim($source['numero_documento'] ?? ''), 'fecha_nacimiento' => trim($source['fecha_nacimiento'] ?? ''), 'primer_nombre' => trim($source['primer_nombre'] ?? ''), 'segundo_nombre' => trim($source['segundo_nombre'] ?? ''), 'primer_apellido' => trim($source['primer_apellido'] ?? ''), 'segundo_apellido' => trim($source['segundo_apellido'] ?? ''), 'sexo' => trim($source['sexo'] ?? ''), 'nacionalidad' => trim($source['nacionalidad'] ?? 'Venezolano')],
            'fecha_ingreso' => trim($source['fecha_ingreso'] ?? ''), 'id_cargo' => (int) ($source['id_cargo'] ?? 0),
            'contacto' => ['codigo_principal' => trim($source['codigo_telefono_principal'] ?? '0412'), 'numero_principal' => trim($source['numero_telefono_principal'] ?? ''), 'codigo_alternativo' => trim($source['codigo_telefono_alternativo'] ?? '') ?: null, 'numero_alternativo' => trim($source['numero_telefono_alternativo'] ?? '') ?: null, 'correo' => trim($source['correo_electronico'] ?? '')],
            'direccion' => ['sector' => trim($source['sector_urbanizacion'] ?? ''), 'calle' => trim($source['calle_avenida'] ?? ''), 'casa' => trim($source['nro_casa_apto'] ?? ''), 'referencia' => trim($source['punto_referencia'] ?? ''), 'parroquia' => (int) ($source['id_parroquia'] ?? 0)],
            'formacion' => ['id_titulo' => (int) ($source['id_titulo'] ?? 0), 'id_grado_academico' => (int) ($source['id_grado_academico'] ?? 0), 'id_institucion' => (int) ($source['id_institucion'] ?? 0), 'fecha_obtencion' => trim($source['fecha_obtencion'] ?? '')],
        ];
        $errors = [];
        foreach (['tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento', 'fecha_nacimiento' => 'fecha de nacimiento', 'primer_nombre' => 'primer nombre', 'primer_apellido' => 'primer apellido', 'sexo' => 'sexo'] as $key => $label) if ($data['persona'][$key] === '') $errors[] = "El {$label} es obligatorio.";
        if ($data['fecha_ingreso'] === '') $errors[] = 'La fecha de ingreso es obligatoria.';
        if ($data['contacto']['numero_principal'] === '') $errors[] = 'El teléfono principal es obligatorio.';
        if ($errors) $this->message(implode(' ', $errors), 422);
        return $data;
    }

    private function respond(array $result): never { header('Content-Type: application/json'); if (!$result['ok']) http_response_code(400); echo json_encode($result); exit; }
    private function message(string $message, int $code): never { header('Content-Type: application/json'); http_response_code($code); echo json_encode(['ok' => false, 'mensaje' => $message]); exit; }
}
