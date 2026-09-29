<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Updatable;
use App\Models\CuentaUsuarioModel;
use App\Traits\DetalleJsonTrait;
use PDO;
use Throwable;

/**
 * Gestión de cuentas de usuario: listado, alta desde un empleado, edición
 * (usuario y rol), detalle y activación. Todo va por modales AJAX (vistas en
 * Views/usuarios/cuentas/). Toda acción nueva debe registrarse en
 * `$rutasPermitidas` de public/index.php, o el router la rechaza.
 *
 * @author Logística
 * @package App\Controllers
 */
class CuentaUsuarioController extends CrudController
{
    use DetalleJsonTrait;

    /** Carpeta de vistas del módulo (Views/usuarios/cuentas). */
    protected string $viewPath  = 'usuarios/cuentas';

    /** Segmento de ruta del controlador; lo usa redirect(). */
    protected string $routeName = 'cuentaUsuario';

    /**
     * Modelo con su tipo real (CuentaUsuarioModel), para que el editor
     * resuelva sus métodos en vez de verlos sobre `object`.
     */
    private function modelo(): CuentaUsuarioModel
    {
        /** @var CuentaUsuarioModel $model */
        $model = $this->getModel();

        return $model;
    }

    /** Crea el modelo del módulo. */
    protected function createModel(PDO $pdo): object
    {
        return new CuentaUsuarioModel($pdo);
    }

    /**
     * Filtros del listado desde la URL, con las mismas claves que el
     * formulario de la vista (`q`, `id_rol`, `estado`).
     *
     * @return array{q: string, id_rol: int, estado: string}
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim($_GET['q'] ?? ''),
            'id_rol' => (int) ($_GET['id_rol'] ?? 0),
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    /**
     * Variables extra de la vista: filas como $usuarios, stats, roles,
     * empleados sin cuenta y el id del usuario logueado (para bloquear
     * acciones sobre su propia cuenta).
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();

        return [
            'usuarios'             => $items,
            'stats'                => $model->getStats(),
            'roles'                => $model->getRoles(),
            'empleadosDisponibles' => $model->getEmpleadosDisponibles(),
            'usuarioActualId'      => $this->idUsuarioActual(),
        ];
    }

    /**
     * Detalle completo de una cuenta en JSON (modal "Ver detalle").
     * Ruta: cuentaUsuario/getDetalle?id=... (GET). El flujo 400/404/500/200
     * lo resuelve responderDetalle() de DetalleJsonTrait.
     */
    public function getDetalle(): void
    {
        $this->responderDetalle(
            'getDetalleCompletoById',
            'ID de usuario inválido.',
            'Usuario no encontrado.',
            'Cuentas de usuario'
        );
    }

    /**
     * Renderiza el modal de creación (create-modal.php) con los empleados
     * disponibles y los roles, en vez de una página completa.
     * Ruta: cuentaUsuario/create (GET).
     */
    public function create(): void
    {
        try {
            $model                = $this->modelo();
            $empleadosDisponibles = $model->getEmpleadosDisponibles();
            $roles                = $model->getRoles();

            require __DIR__ . "/../Views/{$this->viewPath}/modals/create-modal.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    /**
     * Sin página de edición: se edita desde el modal (ver update()).
     * Ruta: cuentaUsuario/edit (GET).
     */
    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no tiene una página de edición independiente.');
        $this->redirect('index');
    }

    /**
     * Crea una cuenta a partir de un empleado. Ruta: cuentaUsuario/store (POST, JSON).
     * Acepta `usuario`/`nombre_usuario` y `empleado_id`/`persona_id`. Responde
     * 422 si hay errores, 400 si el modelo falla y, en éxito, su resultado
     * tal cual con jsonRaw() (lo necesita el modal de credenciales).
     */
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
            $resultado = $this->modelo()->crearUsuario($personaId, $rolId, $nombreUsuario);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$resultado['ok']) {
            $this->jsonResponse(false, null, $resultado['mensaje'] ?? 'No se pudo crear el usuario.', 400);
        }

        $this->jsonRaw($resultado);
    }

    /**
     * Actualiza nombre de usuario y rol. Ruta: cuentaUsuario/update (POST, JSON).
     * Sustituye al del padre: sanitiza el usuario y revisa que no esté en uso
     * en otra cuenta. Devuelve el detalle actualizado. Responde 404, 405,
     * 422 (validación, repetido o inactiva), 400 o 500.
     */
    public function update(): void
    {
        $model = $this->modelo();

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
            $usuarioActual = $model->getDetalleById($id);
            if (!$usuarioActual) {
                $this->jsonResponse(false, null, 'Usuario no encontrado.', 404);
            }

            if (($usuarioActual['estado'] ?? '') === 'inactivo') {
                $this->jsonResponse(false, null, 'No se puede modificar una cuenta de usuario inactiva. Debe activarla primero.', 422);
            }

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

    /**
     * Gancho de toggleStatus(): nadie puede desactivar su propia cuenta (403).
     * El resto del flujo lo resuelve el padre.
     */
    protected function beforeToggleStatus(int $id): void
    {
        if ($id === $this->idUsuarioActual()) {
            $this->jsonResponse(false, null, 'No puedes desactivar tu propia cuenta.', 403);
        }
    }

    /**
     * Normaliza el nombre de usuario: minúsculas, sin tildes/ñ y con los
     * espacios internos convertidos en puntos ("María José" -> "maria.jose").
     * Usa Transliterator (intl) si existe; si no, un reemplazo manual.
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

        // Los espacios internos pasan a punto para que un nombre compuesto no llegue con espacio.
        $nombreUsuario = preg_replace('/\s+/', '.', $nombreUsuario);

        return $nombreUsuario;
    }

    /**
     * Valida el nombre de usuario ya sanitizado: obligatorio, de 3 a 50
     * caracteres y solo [a-z0-9._]. Si está vacío devuelve de inmediato.
     *
     * @return string[] Mensajes de error; vacío si es válido.
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