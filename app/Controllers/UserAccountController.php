<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/UserAccountModel.php';


class UserAccountController extends BaseController
{
    protected string $viewPath  = 'user';
    protected string $routeName = 'userAccount';
    protected array $allowedRoles = ['admin'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo)
    {
        return new UserAccountModel($pdo);
    }

    // LISTADO (tabla + tarjetas + filtros + modales)
    public function index(): void
    {
        try {
            $paginaActual = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $usuarios             = $this->model->getAll($filters, $paginaActual, $this->perPage);
            $total                = $this->model->countAll($filters);
            $stats                = $this->model->getStats();
            $roles                = $this->model->getRolesDisponibles();
            $empleadosDisponibles = $this->model->getEmpleadosDisponibles();
            $rolesSistema         = $this->model->getRolesSistema();

            $paginacion = [
                'desde'         => $total > 0 ? (($paginaActual - 1) * $this->perPage) + 1 : 0,
                'hasta'         => min($paginaActual * $this->perPage, $total),
                'total'         => $total,
                'pagina_actual' => $paginaActual,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            require APP_PATH . "/Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            // Delegado al manejo centralizado del padre: log completo
            // en servidor, mensaje genérico y seguro para el usuario.
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim($_GET['q'] ?? ''),
            'rol'    => $_GET['rol'] ?? '',
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    // VER DETALLE (ícono 👁, versión de página completa — sin usar
    // actualmente, ya que el modal usa getDetalleAjax() en su lugar)
    public function show(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $usuario = $id > 0 ? $this->model->getDetalleById($id) : null;

        if (!$usuario) {
            $this->setFlash('error', 'El usuario solicitado no existe.');
            $this->redirect('index');
            return;
        }

        require_once APP_PATH . "/Views/{$this->viewPath}/show.php";
    }

    // VER DETALLE (AJAX) — usado por el modal "Ver Detalle de Usuario"
    public function getDetalleAjax(): void
    {
        header('Content-Type: application/json');

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'ID de usuario inválido.']);
            exit;
        }

        try {
            $datos = $this->model->getDetalleCompletoById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
            return;
        }

        if (!$datos) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensaje' => 'Usuario no encontrado.']);
            exit;
        }

        echo json_encode(['ok' => true, 'datos' => $datos]);
        exit;
    }

    // FORMULARIO CREAR
    public function create(): void
    {
        try {
            $empleadosDisponibles = $this->model->getEmpleadosDisponibles();
            $rolesSistema         = $this->model->getRolesSistema();
            require_once APP_PATH . "/Views/{$this->viewPath}/create-modal.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    // GUARDAR NUEVO (AJAX) — usado por el modal de creación
    public function storeAjax(): void
    {
        header('Content-Type: application/json');

        $nombreUsuario = trim($_POST['usuario'] ?? $_POST['nombre_usuario'] ?? '');
        $personaId     = (int)($_POST['empleado_id'] ?? $_POST['persona_id'] ?? 0);
        $rolId         = (int)($_POST['rol_id'] ?? 0);

        $errores = [];
        if ($nombreUsuario === '') {
            $errores[] = 'El nombre de usuario es obligatorio.';
        } elseif (mb_strlen($nombreUsuario) < 3 || mb_strlen($nombreUsuario) > 50) {
            $errores[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $nombreUsuario)) {
            $errores[] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }

        if ($personaId <= 0) {
            $errores[] = 'Debe seleccionar un empleado.';
        }

        if ($rolId <= 0) {
            $errores[] = 'Debe seleccionar un rol.';
        }

        if (!empty($errores)) {
            http_response_code(422);
            echo json_encode([
                'ok'      => false,
                'mensaje' => implode(' ', $errores),
                'errores' => $errores,
            ]);
            exit;
        }

        try {
            $resultado = $this->model->crearUsuario($personaId, $rolId, $nombreUsuario);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
        }

        echo json_encode($resultado);
        exit;
    }

    // GUARDAR NUEVO (Síncrono de respaldo)
    public function store(): void
    {
        $data = $this->extractData($_POST);
        $errors = $this->validate($data);

        if (empty($errors)) {
            try {
                $res = $this->model->crearUsuario($data['persona_id'], $data['rol_id'], $data['nombre_usuario']);
            } catch (Throwable $e) {
                $this->handleError($e, 'Cuentas de usuario');
                return;
            }

            if ($res['ok']) {
                $this->setFlash('success', 'Usuario creado correctamente.');
                $this->redirect('index');
                return;
            }
            $errors[] = $res['mensaje'];
        }

        $_SESSION['errors'] = $errors;
        $empleadosDisponibles = $this->model->getEmpleadosDisponibles();
        $rolesSistema         = $this->model->getRolesSistema();
        require_once APP_PATH . "/Views/{$this->viewPath}/create-modal.php";
    }

    // FORMULARIO EDITAR (versión de página completa — sin usar
    // actualmente, ya que el modal usa updateAjax() en su lugar)
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $usuario = $id > 0 ? $this->model->getDetalleById($id) : null;

        if (!$usuario) {
            $this->setFlash('error', 'El usuario solicitado no existe.');
            $this->redirect('index');
            return;
        }

        require_once APP_PATH . "/Views/{$this->viewPath}/edit.php";
    }

    // ACTUALIZAR (Síncrono de respaldo)
    public function update(): void
    {
        $id            = (int)($_POST['id'] ?? 0);
        $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
        $rolId         = (int)($_POST['rol_id'] ?? 0);

        $errores = [];
        if ($id <= 0)               $errores[] = 'Usuario inválido.';
        if ($nombreUsuario === '')  $errores[] = 'El nombre de usuario es obligatorio.';
        if ($rolId <= 0)            $errores[] = 'Debe seleccionar un rol.';

        if (!empty($errores)) {
            $_SESSION['errors'] = $errores;
            $this->redirect('edit', ['id' => $id]);
            return;
        }

        // existeNombreUsuario() y update() sí pueden lanzar una excepción
        // (a diferencia de crearUsuario(), este update() del modelo NO
        // atrapa sus propios errores), así que ambas llamadas van dentro
        // del mismo try/catch — igual que ya hace updateAjax().
        try {
            if ($this->model->existeNombreUsuario($nombreUsuario, $id)) {
                $_SESSION['errors'] = ['El nombre de usuario "' . $nombreUsuario . '" ya está en uso.'];
                $this->redirect('edit', ['id' => $id]);
                return;
            }

            $this->model->update($id, ['rol_id' => $rolId, 'nombre_usuario' => $nombreUsuario]);
            $this->setFlash('success', 'Usuario actualizado correctamente.');
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
            return;
        }

        $this->redirect('index');
    }

    // ACTUALIZAR (AJAX) — usado por el modal de edición
    public function updateAjax(): void
    {
        header('Content-Type: application/json');

        $id            = (int)($_POST['id'] ?? 0);
        $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
        $rolId         = (int)($_POST['rol_id'] ?? 0);

        $errores = [];
        if ($id <= 0) {
            $errores[] = 'ID de usuario inválido.';
        }
        if ($nombreUsuario === '') {
            $errores[] = 'El nombre de usuario es obligatorio.';
        } elseif (mb_strlen($nombreUsuario) < 3 || mb_strlen($nombreUsuario) > 50) {
            $errores[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $nombreUsuario)) {
            $errores[] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }
        if ($rolId <= 0) {
            $errores[] = 'Debe seleccionar un rol.';
        }

        if (!empty($errores)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => implode(' ', $errores), 'errores' => $errores]);
            exit;
        }

        try {
            // Verificar que el nombre de usuario no esté tomado por otro usuario
            if ($this->model->existeNombreUsuario($nombreUsuario, $id)) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'mensaje' => 'El nombre de usuario "' . $nombreUsuario . '" ya está en uso.']);
                exit;
            }

            $ok = $this->model->update($id, ['rol_id' => $rolId, 'nombre_usuario' => $nombreUsuario]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
            return;
        }

        if (!$ok) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo actualizar el usuario.']);
            exit;
        }

        $datos = $this->model->getDetalleById($id);
        echo json_encode(['ok' => true, 'mensaje' => 'Usuario actualizado correctamente.', 'datos' => $datos]);
        exit;
    }

    // TOGGLE ESTADO (AJAX) — usado por el switch de la tabla
    public function toggleEstadoAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $ok = $this->model->toggleStatus($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
            return;
        }

        echo json_encode(['ok' => $ok]);
        exit;
    }

    // Requeridos por BaseController (usados en store())
    // Nota: solo se incluyen los campos que crearUsuario() realmente
    // consume (persona_id, rol_id, nombre_usuario). La contraseña
    // provisional la genera el propio modelo/flujo de creación, no
    // se recibe por formulario.
    protected function extractData(array $source): array
    {
        return [
            'persona_id'     => (int)($source['empleado_id'] ?? $source['persona_id'] ?? 0),
            'rol_id'         => (int)($source['rol_id'] ?? 0),
            'nombre_usuario' => trim($source['usuario'] ?? $source['nombre_usuario'] ?? ''),
        ];
    }

    protected function validate(array $data): array
    {
        $errores = [];
        if ($data['persona_id'] <= 0) $errores[] = 'Debe seleccionar un empleado';
        if ($data['rol_id'] <= 0)     $errores[] = 'Debe seleccionar un rol';
        if (empty($data['nombre_usuario'])) $errores[] = 'El usuario es obligatorio';
        return $errores;
    }
}