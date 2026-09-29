<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\JsonResponse;
use App\Traits\SecurityTrait;
use App\Traits\TextoTrait;
use PDO;
use Throwable;

/**
 * Controlador base: centraliza sesión, conexión a BD, errores y respuestas JSON.
 * Los hijos definen $viewPath, $routeName y createModel(); afterConstruct() es opcional.
 * AuthController no lo extiende porque debe mostrar el login sin exigir sesión.
 */
abstract class BaseController
{
    use SecurityTrait, TextoTrait;

    /** Modelo principal; es null hasta la primera llamada a getModel() (carga diferida). */
    protected ?object $model = null;

    /** Conexión PDO, inicializada junto con el modelo en getModel(). */
    protected ?PDO $pdo = null;

    /** Carpeta de vistas del módulo, relativa a app/Views. Cada hijo debe asignarla. */
    protected string $viewPath;

    /** Segmento de ruta del controlador, usado por redirect(). Cada hijo debe asignarlo. */
    protected string $routeName;

    /**
     * Exige sesión activa y luego ejecuta el gancho afterConstruct() del hijo.
     * El orden importa: un visitante sin sesión es redirigido antes de configurar nada.
     */
    public function __construct()
    {
        $this->verificarSesion();
        $this->afterConstruct();
    }

    /**
     * Devuelve el modelo principal con Lazy Loading.
     * La primera llamada abre el PDO y crea el modelo; las siguientes reutilizan la instancia.
     */
    protected function getModel(): ?object
    {
        if ($this->model === null) {
            $this->pdo   = (new Database())->getConnection();
            $this->model = $this->createModel($this->pdo);
        }

        return $this->model;
    }

    /**
     * Devuelve la conexión PDO del request, abriéndola si aún no existe.
     * Los traits que necesitan un segundo modelo la usan para no abrir otra conexión.
     */
    protected function conexion(): PDO
    {
        $this->getModel();

        return $this->pdo;
    }

    /**
     * Fabrica el modelo en las clases hijas (por defecto no crea ninguno).
     * Ejemplo: `return new EstudianteModel($pdo);`
     */
    protected function createModel(PDO $pdo): ?object
    {
        return null;
    }

    /**
     * Gancho vacío que los hijos sobrescriben para asignar $viewPath, $routeName, roles, etc.
     * Evita reescribir el constructor o llamar a parent::__construct().
     */
    protected function afterConstruct(): void {}

    // ---------- Respuestas JSON ----------

    /**
     * Emite una respuesta JSON estándar (éxito o error) y termina la ejecución.
     * Ramifica aquí porque JsonResponse::error() recibe $message y $data en orden invertido.
     */
    protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never
    {
        $ok
            ? JsonResponse::success($data, $message, $code)->send()
            : JsonResponse::error($message, $data, $code)->send();
    }

    /**
     * Emite un payload JSON sin el envoltorio estándar.
     * Útil cuando el consumidor espera un formato propio, como DataTables.
     */
    protected function jsonRaw(array $payload, int $code = 200): never
    {
        JsonResponse::raw($payload, $code)->send();
    }

    /**
     * Registra el error en el log y muestra la vista genérica de error 500 (peticiones HTML).
     * El detalle técnico solo va al log; $seccion identifica la operación que falló.
     */
    protected function handleError(Throwable $e, string $seccion): never
    {
        error_log(static::class . " [{$seccion}]: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        http_response_code(500);
        require __DIR__ . '/../Views/shared/error.php';
        exit;
    }

    /**
     * Equivalente de handleError() para AJAX: mismo log, pero responde un JSON con mensaje neutro.
     * El mensaje incluye la sección para que el front lo muestre en SweetAlert.
     */
    protected function jsonError(Throwable $e, string $seccion, int $code = 500): never
    {
        error_log(static::class . " [{$seccion}]: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        $this->jsonResponse(
            false,
            null,
            "Ocurrió un problema al procesar la solicitud ({$seccion}). Intenta nuevamente.",
            $code
        );
    }

    // ---------- Sesión / flash / navegación ----------

    /**
     * Devuelve el ID del usuario autenticado, o null si no hay sesión.
     * Busca 'usuario_id' y, por compatibilidad, 'id_usuario'.
     */
    protected function idUsuarioActual(): ?int
    {
        return isset($_SESSION['usuario_id'])
            ? (int) $_SESSION['usuario_id']
            : (isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null);
    }

    /**
     * Devuelve los mensajes flash y los elimina de la sesión.
     * Se leen una sola vez, así no reaparecen al recargar la página.
     *
     * @return array{error: ?string, success: ?string}
     */
    protected function getFlashMessages(): array
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        return compact('error', 'success');
    }

    /**
     * Guarda un mensaje flash en sesión antes de una redirección.
     * El tipo debe ser 'error' o 'success', las únicas claves que lee getFlashMessages().
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }

    /**
     * Redirige a una acción del mismo controlador: BASE_URL + $routeName + acción (+ query).
     * Ejemplo: `$this->redirect('index', ['q' => 'perez'])` → `estudiantes/index?q=perez`.
     */
    protected function redirect(string $action, array $params = []): never
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . "{$this->routeName}/{$action}{$query}");
        exit;
    }
}