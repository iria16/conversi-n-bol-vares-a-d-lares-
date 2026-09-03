<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/AccessRecoveryModel.php';

/**
 * @property AccessRecoveryModel $model
 */
class AccessRecoveryController extends BaseController
{
    protected string $viewPath  = 'user';
    protected string $routeName = 'accessRecovery';
    protected array $allowedRoles = ['admin'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): AccessRecoveryModel
    {
        return new AccessRecoveryModel($pdo);
    }

    // -------------------------------------------------------------
    // LISTADO (tarjetas + filtros + tabla)
    // -------------------------------------------------------------
    public function index(): void
    {
        try {
            $paginaActual = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $solicitudes = $this->model->getAll($filters, $paginaActual, $this->perPage);
            $total       = $this->model->countAll($filters);
            $stats       = $this->model->getStats();
            $usuariosDisponibles = $this->model->getUsuariosDisponibles();

            $paginacion = [
                'desde'         => $total > 0 ? (($paginaActual - 1) * $this->perPage) + 1 : 0,
                'hasta'         => min($paginaActual * $this->perPage, $total),
                'total'         => $total,
                'pagina_actual' => $paginaActual,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            require APP_PATH . "/Views/{$this->viewPath}/access-recovery.php";
        } catch (Throwable $e) {
            // Antes esto logueaba bien, pero además hacía echo del
            // mensaje REAL de la excepción al usuario final
            // (htmlspecialchars($e->getMessage())) — eso puede filtrar
            // nombres de tablas/columnas o detalles de la consulta SQL.
            // Se delega al manejo centralizado del padre, que solo
            // muestra un mensaje genérico y guarda el detalle en el log.
            $this->handleError($e, 'Recuperación de acceso');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim($_GET['q'] ?? ''),
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    // -------------------------------------------------------------
    // VER DETALLE (ícono 👁)
    // -------------------------------------------------------------
    public function show(): void
    {
        try {
            $id = (int)($_GET['id'] ?? 0);
            $solicitud = $id > 0 ? $this->model->getDetalleById($id) : null;
        } catch (Throwable $e) {
            $this->handleError($e, 'Recuperación de acceso');
            return;
        }

        if (!$solicitud) {
            $this->setFlash('error', 'La solicitud indicada no existe.');
            $this->redirect('index');
            return;
        }

        require_once APP_PATH . "/Views/{$this->viewPath}/access-recovery.php";
    }

    public function getDetalleAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'ID de solicitud inválido.']);
            exit;
        }

        try {
            $solicitud = $this->model->getDetalleById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de acceso');
            return;
        }

        if (!$solicitud) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensaje' => 'Solicitud no encontrada.']);
            exit;
        }

        echo json_encode(['ok' => true, 'datos' => $solicitud]);
        exit;
    }

    // -------------------------------------------------------------
    // FORMULARIO "+ Restablecer" (reseteo manual sin solicitud previa)
    // -------------------------------------------------------------
    public function manual(): void
    {
        try {
            $usuariosDisponibles = $this->model->getUsuariosDisponibles();
            require_once APP_PATH . "/Views/{$this->viewPath}/access-recovery.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Recuperación de acceso');
        }
    }

    public function storeManual(): void
    {
        $data = $this->extractData($_POST);
        $errors = $this->validate($data);

        if (empty($errors)) {
            try {
                $resultado = $this->model->resetManual($data['usuario_id']);
            } catch (Throwable $e) {
                $this->handleError($e, 'Recuperación de acceso');
                return;
            }

            if ($resultado['ok']) {
                $_SESSION['swal'] = [
                    'icon'  => 'success',
                    'title' => 'Contraseña temporal generada',
                    'text'  => 'Comparta esta contraseña con el usuario: ' . $resultado['password_temporal'],
                ];
            } else {
                $_SESSION['swal'] = [
                    'icon'  => 'error',
                    'title' => 'No se pudo restablecer el acceso',
                    'text'  => 'Intente nuevamente.',
                ];
            }

            $this->redirect('index');
            return;
        }

        $_SESSION['errors'] = $errors;
        require_once APP_PATH . "/Views/{$this->viewPath}/access-recovery.php";
    }

    public function storeManualAjax(): void
    {
        header('Content-Type: application/json');

        $data = $this->extractData($_POST);
        $errors = $this->validate($data);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'ok'      => false,
                'mensaje' => implode(' ', $errors),
                'errores' => $errors,
            ]);
            exit;
        }

        try {
            $resultado = $this->model->resetManual($data['usuario_id']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de acceso');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'mensaje' => 'No se pudo restablecer la contraseña.',
            ]);
            exit;
        }

        echo json_encode($resultado);
        exit;
    }

    // -------------------------------------------------------------
    // APROBAR SOLICITUD (AJAX) — ícono ✓ en filas pendientes
    // -------------------------------------------------------------
    public function aprobarAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'ID de solicitud inválido.']);
            exit;
        }

        try {
            $resultado = $this->model->aprobar($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de acceso');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo aprobar la solicitud.']);
            exit;
        }

        echo json_encode($resultado);
        exit;
    }

    // -------------------------------------------------------------
    // RECHAZAR SOLICITUD (AJAX) — ícono ✕ en filas pendientes
    // -------------------------------------------------------------
    public function rechazarAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'ID de solicitud inválido.']);
            exit;
        }

        try {
            $ok = $this->model->rechazar($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de acceso');
            return;
        }

        if (!$ok) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo rechazar la solicitud.']);
            exit;
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Solicitud rechazada correctamente.']);
        exit;
    }

    // -------------------------------------------------------------
    // REENVIAR CONTRASEÑA TEMPORAL (AJAX) — filas aprobadas
    // -------------------------------------------------------------
    public function reenviarAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'ID de solicitud inválido.']);
            exit;
        }

        try {
            $resultado = $this->model->reenviarTemporal($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de acceso');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
            echo json_encode([
                'ok'      => false,
                'mensaje' => $resultado['mensaje'] ?? 'No se pudo generar una nueva contraseña temporal.',
            ]);
            exit;
        }

        echo json_encode($resultado);
        exit;
    }

    // -------------------------------------------------------------
    // Requeridos por BaseController (usados en storeManual())
    // -------------------------------------------------------------
    protected function extractData(array $source): array
    {
        return [
            'usuario_id' => (int)($source['usuario_id'] ?? 0),
        ];
    }

    protected function validate(array $data): array
    {
        $errores = [];
        if ($data['usuario_id'] <= 0) $errores[] = 'Debe seleccionar un usuario';
        return $errores;
    }
}