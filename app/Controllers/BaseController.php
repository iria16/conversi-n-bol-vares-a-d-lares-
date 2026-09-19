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
 * Controlador base que centraliza la sesión, conexión a BD,
 * renderizado de errores y respuestas JSON para los controladores del sistema.
 */
abstract class BaseController
{
    use SecurityTrait, TextoTrait;

    protected ?object $model = null;
    protected ?PDO $pdo = null;
    protected string $viewPath;
    protected string $routeName;

    /**
     * Valida sesión e inicializa ganchos del controlador hijo.
     */
    public function __construct()
    {
        $this->verificarSesion();
        $this->afterConstruct();
    }
    /**
     * Instancia y retorna el modelo principal mediante Lazy Loading.
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
     * Fabrica la instancia del modelo en las clases hijas.
     */
    protected function createModel(PDO $pdo): ?object
    {
        return null;
    }

    /**
     * Hook ejecutable tras la construcción del controlador base.
     */
    protected function afterConstruct(): void {}

    // ---------- Respuestas JSON ----------

    /**
     * Emite una respuesta JSON estructurada (éxito o error) y finaliza la ejecución.
     */
    protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never
    {
        $ok
            ? JsonResponse::success($data, $message, $code)->send()
            : JsonResponse::error($message, $data, $code)->send();
    }

    /**
     * Emite un payload JSON sin la estructura estandarizada.
     */
    protected function jsonRaw(array $payload, int $code = 200): never
    {
        JsonResponse::raw($payload, $code)->send();
    }

    /**
     * Registra el error en log y muestra la vista de error HTTP 500 para peticiones HTML.
     */
    protected function handleError(Throwable $e, string $seccion): never
    {
        error_log(static::class . " [{$seccion}]: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        http_response_code(500);
        require __DIR__ . '/../Views/shared/error.php';
        exit;
    }
    
    /**
     * Registra el error en log y responde un JSON genérico para peticiones AJAX.
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
     * Retorna el ID del usuario autenticado en la sesión actual.
     */
    protected function idUsuarioActual(): ?int
    {
        return isset($_SESSION['usuario_id'])
            ? (int) $_SESSION['usuario_id']
            : (isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null);
    }
    
    /**
     * Obtiene los mensajes flash acumulados y los elimina de la sesión.
     */
    protected function getFlashMessages(): array
    {
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success']);
        return compact('error', 'success');
    }
    /**
     * Almacena un mensaje temporal en la sesión (error o éxito).
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }
    
    /**
     * Redirige a una acción relativa dentro de la ruta actual.
     */
    protected function redirect(string $action, array $params = []): never
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . "{$this->routeName}/{$action}{$query}");
        exit;
    }
}