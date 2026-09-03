<?php

require_once __DIR__ . '/SecurityTrait.php';

/**
 * BaseController
 * ---------------------------------------------------------------
 * Clase abstracta que centraliza el flujo CRUD genérico (Template
 * Method Pattern): define el "esqueleto" de index/create/store/
 * edit/update/delete/toggleStatus, y cada controlador hijo solo
 * rellena lo que lo hace distinto (su modelo, su vista, sus reglas
 * de negocio propias).
 *
 * Todo controlador del sistema (UserAccountController,
 * CatalogoController, BitacoraController, etc.) debe extender
 * esta clase.
 */
abstract class BaseController
{
    use SecurityTrait;

    /**
     * Instancia del modelo asociado a este controlador.
     * Se crea en el constructor vía createModel(), que cada hijo
     * implementa para devolver su propio modelo (UserAccountModel,
     * CatalogoModel, etc.)
     * @var object
     */
    protected $model;

    /**
     * Nombre de la carpeta dentro de app/Views/ donde viven las
     * vistas de este módulo (ej. 'usuario', 'catalogo').
     * Debe coincidir exactamente con el nombre de carpeta real.
     */
    protected string $viewPath;

    /**
     * Nombre de la ruta base usado por redirect() para armar la
     * URL de retorno (ej. 'userAccount' -> BASE_URL/userAccount/index).
     */
    protected string $routeName;

    /** Cantidad de registros por página en el listado (index()). */
    protected int $perPage = 10;

    /**
     * Roles permitidos para acceder a este controlador.
     * Vacío ([]) = solo exige que exista sesión activa, sin
     * restringir por rol. Ej: ['admin'] o ['admin', 'docente'].
     */
    protected array $allowedRoles = [];

    /**
     * Constructor: se ejecuta automáticamente en TODOS los
     * controladores hijos apenas el front controller los instancia.
     * Orden de ejecución (importante, no alterar):
     *   1. Seguridad (sesión + rol) -> corta la ejecución si falla
     *   2. Conexión a BD + creación del modelo del hijo
     *   3. Gancho afterConstruct() para validaciones extra propias
     *      de cada controlador (ej. chequeo de bloqueo en tiempo real)
     */
    public function __construct()
    {
        // 1. Seguridad genérica: exige sesión y, si aplica, rol permitido
        $this->verificarSesion();
        if (!empty($this->allowedRoles)) {
            $this->verificarRol($this->allowedRoles);
        }

        // 2. Conexión + modelo (cada hijo decide cuál modelo instanciar)
        require_once __DIR__ . '/../../config/Database.php';
        $pdo = (new Database())->getConnection();
        $this->model = $this->createModel($pdo);

        // 3. Gancho para validaciones EXTRA propias de cada controlador
        //    (ej. AdminController lo usa para chequear bloqueo en tiempo real)
        $this->afterConstruct();
    }

    /**
     * Cada hijo DEBE implementar este método y devolver su propio
     * modelo, ya inyectado con la conexión PDO.
     * Ej: return new UserAccountModel($pdo);
     */
    abstract protected function createModel(PDO $pdo);

    /**
     * Arma el array de datos a partir del origen recibido
     * (normalmente $_POST) para create()/update().
     *
     * Implementación por defecto: vacía. Los controladores que SÍ
     * manejan formularios de creación/edición (ej. UserAccountController)
     * la sobrescriben con sus campos reales. Los controladores de solo
     * lectura (ej. BitacoraController) pueden ignorarla por completo.
     */
    protected function extractData(array $source): array
    {
        return [];
    }

    /**
     * Valida los datos extraídos por extractData() y retorna un
     * array de mensajes de error (vacío = datos válidos).
     *
     * Implementación por defecto: sin errores. Los hijos que
     * manejan escritura la sobrescriben con sus reglas propias.
     */
    protected function validate(array $data): array
    {
        return [];
    }

    /**
     * Hook que no hace nada por defecto. El hijo lo sobrescribe
     * cuando necesita ejecutar lógica adicional justo después de
     * construirse (ej. AdminController revalida bloqueo de cuenta
     * por si otro admin lo bloqueó en otra pestaña).
     */
    protected function afterConstruct(): void {}

    /**
     * Obtiene y limpia los mensajes flash guardados en sesión
     * (error/success), reutilizable en cualquier index() que
     * necesite mostrar un aviso tras una redirección.
     */
    protected function getFlashMessages(): array
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        return compact('error', 'success');
    }

    /**
     * Guarda un mensaje flash en sesión para mostrarlo después de
     * la próxima redirección (patrón Post/Redirect/Get).
     *
     * @param string $type    'success' | 'error'
     * @param string $message Texto a mostrar al usuario.
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }

    /**
     * Listado paginado con filtros. Comportamiento genérico:
     * cualquier hijo que no necesite datos extra (stats, catálogos
     * para selects, etc.) puede heredarlo tal cual sin sobrescribirlo.
     */
    public function index()
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $items = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $totalPages = (int) ceil($total / $this->perPage);

            require_once __DIR__ . "/../Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /** Muestra el formulario de creación (vista create.php del módulo). */
    public function create()
    {
        require_once __DIR__ . "/../Views/{$this->viewPath}/create.php";
    }

    /**
     * Procesa el envío del formulario de creación:
     * extrae datos -> valida -> si es válido, crea y redirige;
     * si no, guarda errores en sesión y vuelve a mostrar el form.
     */
    public function store()
    {
        $data = $this->extractData($_POST);
        $errors = $this->validate($data);

        if (empty($errors)) {
            try {
                $this->model->create($data);
                $this->setFlash('success', 'Registro creado correctamente.');
                $this->redirect('index');
                return;
            } catch (Throwable $e) {
                $this->handleError($e, static::class);
                return;
            }
        }

        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $_POST; // repuebla el formulario tras el error
        require_once __DIR__ . "/../Views/{$this->viewPath}/create.php";
    }

    /**
     * Muestra el formulario de edición cargado con los datos
     * actuales del registro (por id). Redirige al listado si el
     * id es inválido o el registro no existe.
     */
    public function edit()
    {
        $id = (int)($_GET['id'] ?? 0);
        $item = $id > 0 ? $this->model->getById($id) : null;

        if (!$item) {
            $this->setFlash('error', 'El registro solicitado no existe.');
            $this->redirect('index');
            return;
        }

        require_once __DIR__ . "/../Views/{$this->viewPath}/edit.php";
    }

    /**
     * Procesa el envío del formulario de edición: extrae datos,
     * valida y actualiza solo si el id y los datos son válidos.
     * En caso de error, vuelve al formulario de edición con los
     * mensajes correspondientes en vez de redirigir a ciegas al
     * listado (los hijos con reglas distintas pueden sobrescribir
     * este método, como hace UserAccountController).
     */
    public function update()
    {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $this->setFlash('error', 'Registro inválido.');
            $this->redirect('index');
            return;
        }

        $data = $this->extractData($_POST);
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect('edit', ['id' => $id]);
            return;
        }

        try {
            $this->model->update($id, $data);
            $this->setFlash('success', 'Registro actualizado correctamente.');
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
            return;
        }

        $this->redirect('index');
    }

    /** Elimina un registro por id y redirige al listado. */
    public function delete()
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id > 0) {
            try {
                $this->model->delete($id);
                $this->setFlash('success', 'Registro eliminado correctamente.');
            } catch (Throwable $e) {
                $this->handleError($e, static::class);
                return;
            }
        }

        $this->redirect('index');
    }

    /**
     * Alterna el estado (activo/inactivo) de un registro por id.
     * Nota: para acciones vía AJAX (que responden JSON en vez de
     * redirigir), el hijo debe sobrescribir con su propio método,
     * como hace UserAccountController::toggleEstadoAjax().
     */
    public function toggleStatus()
    {
        $id = (int)($_GET['id'] ?? 0);

        if ($id > 0) {
            try {
                $this->model->toggleStatus($id);
                $this->setFlash('success', 'Estado actualizado correctamente.');
            } catch (Throwable $e) {
                $this->handleError($e, static::class);
                return;
            }
        }

        $this->redirect('index');
    }

    /**
     * Filtros por defecto para index(): solo búsqueda de texto libre.
     * Los hijos con filtros propios (rol, estado, etc.) lo sobrescriben,
     * como hace UserAccountController::getFiltersFromRequest().
     */
    protected function getFiltersFromRequest(): array
    {
        return ['search' => trim($_GET['search'] ?? '')];
    }

    /**
     * Redirige a otra acción dentro de la misma ruta base
     * ($routeName) y detiene la ejecución del script.
     *
     * @param string $action Acción destino (ej. 'index', 'edit').
     * @param array  $params Parámetros opcionales para la query string
     *                       (ej. ['id' => 5] -> ?id=5).
     */
    protected function redirect(string $action, array $params = []): void
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header("Location: " . BASE_URL . "{$this->routeName}/{$action}{$query}");
        exit;
    }

    /**
     * Manejo centralizado de errores no controlados dentro de un
     * método de controlador (usado en el catch de un try/catch en
     * index(), store(), update(), delete() y toggleStatus()).
     *
     * - Registra el detalle REAL del error en el log del servidor
     *   (nunca visible para el usuario final).
     * - Muestra al usuario una vista de error genérica y segura,
     *   manteniendo el mismo estilo visual del resto de la app,
     *   sin filtrar detalles internos (nombres de tablas, rutas, SQL).
     *
     * @param Throwable $e        Excepción/error capturado.
     * @param string    $seccion  Nombre legible de la sección que
     *                            falló, para el título mostrado al
     *                            usuario (ej. 'Cuentas de usuario').
     */
    protected function handleError(Throwable $e, string $seccion): void
    {
        // Detalle completo solo en el log del servidor
        error_log(static::class . ": {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

        // Vista de error genérica y segura para el usuario final,
        // manteniendo el mismo layout/estilo del resto del sitio.
        http_response_code(500);
        require __DIR__ . '/../Views/shared/error.php'; // recibe $seccion
        exit;
    }

    /**
     * Igual que handleError(), pero para endpoints AJAX que responden
     * JSON en vez de HTML (ej. storeAjax(), updateAjax(), toggleEstadoAjax()).
     *
     * - Registra el detalle REAL del error en el log del servidor.
     * - Responde con un JSON genérico y seguro, con el mismo formato
     *   {ok, mensaje} que ya usan tus respuestas AJAX manuales.
     *
     * Uso típico en un hijo:
     *   } catch (Throwable $e) {
     *       $this->jsonError($e, 'Cuentas de usuario');
     *   }
     *
     * @param Throwable $e       Excepción/error capturado.
     * @param string    $seccion Nombre legible de la sección que falló.
     * @param int       $code    Código HTTP a devolver (por defecto 500).
     */
    protected function jsonError(Throwable $e, string $seccion, int $code = 500): void
    {
        error_log(static::class . ": {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'ok'      => false,
            'mensaje' => "Ocurrió un problema al procesar la solicitud ({$seccion}). Intenta nuevamente.",
        ]);
        exit;
    }
}