<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Models\CuentaUsuarioModel;
use PDO;
use Throwable;

/**
 * CuentaUsuarioController
 *
 * Gestión de cuentas de usuario del sistema: listado con filtros y
 * estadísticas, alta, edición (nombre de usuario y rol), consulta de
 * detalle y activación/desactivación.
 *
 * Extiende CrudController y aprovecha lo heredado:
 *  - index() viene de ListableIndexTrait; aquí solo aporto los filtros
 *    (getFiltersFromRequest) y las variables extra de la vista
 *    (getExtraIndexData).
 *  - Sobrescribo create(), edit(), store(), update() y toggleStatus()
 *    porque este módulo trabaja con modales (AJAX) y tiene reglas propias:
 *    el usuario se crea a partir de un empleado existente, el nombre de
 *    usuario se normaliza y nadie puede desactivar su propia cuenta.
 *
 * Vistas: Views/usuarios/cuentas/ (index.php) y sus modales en
 * Views/usuarios/cuentas/modals/.
 *
 * Rutas: `cuentaUsuario/{accion}`. Si agrego una acción nueva (como
 * getDetalle), debo registrarla en el whitelist `$rutasPermitidas` del
 * front controller (public/index.php), o el router la rechaza.
 *
 * Formato de respuesta: las acciones AJAX responden con jsonResponse()
 * ({ok, mensaje, data}). La excepción es store(), que devuelve con
 * jsonRaw() lo que retorne el modelo tal cual, porque el front necesita
 * ese formato para el modal de credenciales.
 *
 * @author Logística
 * @package App\Controllers
 */
class CuentaUsuarioController extends CrudController
{
    /** Carpeta de vistas del módulo (Views/usuarios/cuentas). */
    protected string $viewPath  = 'usuarios/cuentas';

    /** Segmento de ruta del controlador; lo usa redirect() del BaseController. */
    protected string $routeName = 'cuentaUsuario';

    /**
     * Crea la instancia concreta del modelo cuando sea requerida.
     *
     * @param PDO $pdo Conexión abierta por BaseController::getModel().
     */
    protected function createModel(PDO $pdo): object
    {
        return new CuentaUsuarioModel($pdo);
    }

    /**
     * Lee los filtros del listado desde la URL (gancho de ListableIndexTrait).
     *
     * Uso las mismas claves que el formulario de la vista (`q`, `id_rol`,
     * `estado`) para reutilizar el arreglo tanto en la consulta como para
     * repoblar los campos del filtro, sin traducir nombres.
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
     * Variables extra que necesita la vista de listado.
     *
     * index() ya no se sobrescribe: ListableIndexTrait (vía CrudController)
     * hace exactamente lo mismo (paginación, filtros, require de la vista)
     * y este gancho le añade las variables extra que la vista de cuentas
     * necesita. Se conserva la clave 'usuarios' (en vez de solo 'items')
     * para que la vista index.php no tenga que tocarse.
     *
     * Variables que entrega:
     *  - usuarios:             filas de la página actual.
     *  - stats:                contadores para las tarjetas de resumen.
     *  - roles:                roles disponibles (filtro y formularios).
     *  - empleadosDisponibles: empleados que aún no tienen cuenta.
     *  - usuarioActualId:      ID del usuario logueado (para deshabilitar
     *                          acciones sobre su propia cuenta en la tabla).
     *
     * @param array $items   Filas de la página actual.
     * @param array $filters Filtros aplicados.
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

    /**
     * Devuelve el detalle completo de una cuenta en JSON (modal "Ver detalle").
     * Ruta: cuentaUsuario/getDetalle?id=... (GET).
     *
     * Respuestas: 400 si el ID es inválido, 404 si no existe, 500 si el
     * modelo falla y 200 con los datos si todo sale bien.
     */
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

    /**
     * Formulario de creación (FORMULARIO CREAR).
     * Ruta: cuentaUsuario/create (GET).
     *
     * Reemplaza al create() del padre: en vez de una página completa
     * renderiza el modal Views/usuarios/cuentas/modals/create-modal.php,
     * con la lista de empleados disponibles y de roles.
     */
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

    /**
     * Bloquea la página de edición independiente.
     * Ruta: cuentaUsuario/edit (GET).
     *
     * La edición de cuentas se hace desde un modal (ver update()), así que
     * aquí anulo el edit() del padre: dejo un aviso y vuelvo al listado.
     */
    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no tiene una página de edición independiente.');
        $this->redirect('index');
    }

    /**
     * Crea una cuenta nueva (GUARDAR NUEVO, AJAX Modal).
     * Ruta: cuentaUsuario/store (POST, responde JSON).
     *
     * La cuenta se crea a partir de un empleado ya registrado. Acepto
     * `usuario` o `nombre_usuario`, y `empleado_id` o `persona_id`, para no
     * depender del nombre exacto que use el formulario.
     *
     * Validaciones: nombre de usuario (sanitizado y validado), empleado y
     * rol seleccionados. Si hay errores respondo 422 con la lista y un
     * mensaje unido. Si el modelo devuelve ok = false respondo 400.
     * En éxito devuelvo el resultado del modelo tal cual con jsonRaw().
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
            $resultado = $this->getModel()->crearUsuario($personaId, $rolId, $nombreUsuario);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$resultado['ok']) {
            $this->jsonResponse(false, null, $resultado['mensaje'] ?? 'No se pudo crear el usuario.', 400);
        }

        $this->jsonRaw($resultado);
    }

    /**
     * Actualiza nombre de usuario y rol de una cuenta (ACTUALIZAR, AJAX Modal).
     * Ruta: cuentaUsuario/update (POST, responde JSON).
     *
     * Reemplaza al update() del padre porque aquí hay dos reglas propias:
     * el nombre de usuario se sanitiza y valida, y no puede repetirse en
     * otra cuenta (existeNombreUsuario recibe el ID actual para excluir el
     * propio registro).
     *
     * Solo se editan `nombre_usuario` y `rol_id`; la clave se maneja por
     * otro flujo. En éxito devuelvo el detalle actualizado para que el
     * front refresque la fila sin recargar.
     *
     * Respuestas: 405 si el modelo no es Updatable, 422 por validación o
     * nombre repetido, 400 si el modelo no pudo actualizar, 500 si falla.
     */
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

    /**
     * Activa o desactiva una cuenta (TOGGLE ESTADO, AJAX Switch).
     * Ruta: cuentaUsuario/toggleStatus (POST, responde JSON).
     *
     * Reemplaza al del padre para añadir una protección: un usuario no
     * puede desactivar su propia cuenta (403), así nadie se bloquea a sí
     * mismo por accidente desde el switch de la tabla.
     *
     * Si el modelo devuelve false, respondo ok = false con mensaje de error
     * (con código HTTP 200 por defecto; el front debe revisar el campo `ok`).
     */
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
     *
     * Uso la extensión Transliterator (intl) si está disponible, y como
     * respaldo un reemplazo manual de las vocales acentuadas y la ñ.
     *
     * Ejemplo: "María José" -> "maria.jose".
     *
     * @param string $nombreUsuario Valor crudo recibido del formulario.
     * @return string Nombre de usuario normalizado.
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
     *
     * Reglas: obligatorio, entre 3 y 50 caracteres, y solo [a-z0-9._].
     * Si está vacío devuelvo de inmediato, sin apilar más errores.
     *
     * @param string $nombreUsuario Valor ya pasado por sanitizarNombreUsuario().
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