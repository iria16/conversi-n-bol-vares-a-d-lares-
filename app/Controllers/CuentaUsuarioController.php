<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Models\CuentaUsuarioModel;
use PDO;
use Throwable;

class CuentaUsuarioController extends CrudController
{
    protected string $viewPath  = 'usuarios/cuentas';
    protected string $routeName = 'cuentaUsuario';

    /**
     * Crea la instancia concreta del modelo cuando sea requerida.
     */
    protected function createModel(PDO $pdo): object
    {
        return new CuentaUsuarioModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim($_GET['q'] ?? ''),
            'id_rol' => (int) ($_GET['id_rol'] ?? 0),
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    /**
     * index() ya no se sobrescribe: ListableIndexTrait (vía CrudController)
     * hace exactamente lo mismo (paginación, filtros, require de la vista)
     * y este gancho le añade las variables extra que la vista de cuentas
     * necesita. Se conserva la clave 'usuarios' (en vez de solo 'items')
     * para que la vista index.php no tenga que tocarse.
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->getModel();

        return [
            'usuarios'             => $items,
            'stats'                => $model->getStats(),
            'roles'                => $model->getRoles(),
            'empleadosDisponibles' => $model->getEmpleadosDisponibles(),
            'usuarioActualId'      => $this->idUsuarioActual(),
        ];
    }

    public function getDetalle(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID de usuario inválido.', 400);
        }

        try {
            $datos = $this->getModel()->getDetalleCompletoById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$datos) {
            $this->jsonResponse(false, null, 'Usuario no encontrado.', 404);
        }

        $this->jsonResponse(true, $datos);
    }

    // FORMULARIO CREAR
    public function create(): void
    {
        try {
            $model                = $this->getModel();
            $empleadosDisponibles = $model->getEmpleadosDisponibles();
            $roles                = $model->getRoles();

            require __DIR__ . "/../Views/{$this->viewPath}/modals/create-modal.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no tiene una página de edición independiente.');
        $this->redirect('index');
    }

    // GUARDAR NUEVO (AJAX Modal)
    public function store(): void
    {
        $rawUsuario    = $_POST['usuario'] ?? $_POST['nombre_usuario'] ?? '';
        $nombreUsuario = $this->sanitizarNombreUsuario($rawUsuario);
        $personaId     = (int) ($_POST['empleado_id'] ?? $_POST['persona_id'] ?? 0);
        $rolId         = (int) ($_POST['rol_id'] ?? 0);

        $errores = $this->validarNombreUsuario($nombreUsuario);

        if ($personaId <= 0) {
            $errores[] = 'Debe seleccionar un empleado.';
        }
        if ($rolId <= 0) {
            $errores[] = 'Debe seleccionar un rol.';
        }

        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            $resultado = $this->getModel()->crearUsuario($personaId, $rolId, $nombreUsuario);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$resultado['ok']) {
            $this->jsonResponse(false, null, $resultado['mensaje'] ?? 'No se pudo crear el usuario.', 400);
        }

        $this->jsonRaw($resultado);
    }

    // ACTUALIZAR (AJAX Modal)
    public function update(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de cuentas.', 405);
        }

        $id            = (int) ($_POST['id'] ?? 0);
        $rawUsuario    = $_POST['nombre_usuario'] ?? '';
        $nombreUsuario = $this->sanitizarNombreUsuario($rawUsuario);
        $rolId         = (int) ($_POST['rol_id'] ?? 0);

        $errores = [];
        if ($id <= 0) {
            $errores[] = 'ID de usuario inválido.';
        }

        $errores = array_merge($errores, $this->validarNombreUsuario($nombreUsuario));

        if ($rolId <= 0) {
            $errores[] = 'Debe seleccionar un rol.';
        }

        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            if ($model->existeNombreUsuario($nombreUsuario, $id)) {
                $this->jsonResponse(false, null, "El nombre de usuario \"{$nombreUsuario}\" ya está en uso.", 422);
            }

            $ok = $model->update($id, ['rol_id' => $rolId, 'nombre_usuario' => $nombreUsuario]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$ok) {
            $this->jsonResponse(false, null, 'No se pudo actualizar el usuario.', 400);
        }

        $datos = $model->getDetalleById($id);
        $this->jsonResponse(true, $datos, 'Usuario actualizado correctamente.');
    }

    // TOGGLE ESTADO (AJAX Switch)
    public function toggleStatus(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Toggleable) {
            $this->jsonResponse(false, null, 'Esta sección no admite cambio de estado.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        if ($id === $this->idUsuarioActual()) {
            $this->jsonResponse(false, null, 'No puedes desactivar tu propia cuenta.', 403);
        }

        try {
            $ok = $model->toggleStatus($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        $this->jsonResponse($ok, null, $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.');
    }

    /**
     * Normaliza el nombre de usuario antes de validar/guardar: minúsculas,
     * sin tildes/ñ y sin espacios internos. Los datos de origen vienen de
     * nombres de personas reales (docentes, administrativos, etc.), así
     * que "María José" no puede terminar generando un login con espacio
     * o con mayúsculas inconsistentes entre alta y login.
     */
    private function sanitizarNombreUsuario(string $nombreUsuario): string
    {
        $nombreUsuario = trim(mb_strtolower($nombreUsuario, 'UTF-8'));

        if (class_exists('Transliterator')) {
            $nombreUsuario = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()')->transliterate($nombreUsuario);
        } else {
            $search  = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ä', 'ë', 'ï', 'ö', 'ü'];
            $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
            $nombreUsuario = str_replace($search, $replace, $nombreUsuario);
        }

        // Colapsa cualquier espacio interno ("maria jose" -> "maria.jose")
        // en vez de solo recortar los extremos: un nombre compuesto no
        // puede colarse con espacio hasta acá.
        $nombreUsuario = preg_replace('/\s+/', '.', $nombreUsuario);

        return $nombreUsuario;
    }

    /**
     * Valida longitud y formato del nombre de usuario ya sanitizado.
     * Solo minúsculas, números, puntos y guiones bajos: cubre el
     * formato típico de usuarios institucionales (nombre.apellido,
     * nombre_apellido) sin permitir espacios ni caracteres especiales.
     */
    private function validarNombreUsuario(string $nombreUsuario): array
    {
        $errores = [];

        if ($nombreUsuario === '') {
            $errores[] = 'El nombre de usuario es obligatorio.';
            return $errores;
        }

        $longitud = mb_strlen($nombreUsuario, 'UTF-8');
        if ($longitud < 3 || $longitud > 50) {
            $errores[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        }

        if (!preg_match('/^[a-z0-9._]+$/', $nombreUsuario)) {
            $errores[] = 'El nombre de usuario solo puede contener letras minúsculas, números, puntos y guiones bajos.';
        }

        return $errores;
    }
}