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
 * BaseController
 *
 * Controlador base que centraliza la sesión, conexión a BD,
 * renderizado de errores y respuestas JSON para los controladores del sistema.
 *
 * Lo diseñé para que los controladores hijos no repitan lo mismo en cada
 * módulo. Lo que resuelve aquí:
 *
 *  - Seguridad y sesión: el constructor exige sesión activa (verificarSesion,
 *    que viene de SecurityTrait) antes de ejecutar cualquier acción.
 *  - Conexión a BD con carga diferida: el PDO y el modelo solo se crean la
 *    primera vez que se piden con getModel(), no en cada request.
 *  - Manejo de errores: handleError() para vistas HTML y jsonError() para AJAX.
 *    Ambos escriben en el log y no exponen detalles internos al usuario.
 *  - Respuestas JSON con formato uniforme mediante App\Http\JsonResponse.
 *  - Mensajes flash (error/éxito) y redirecciones relativas a la ruta actual.
 *
 * Cómo se usa desde un controlador hijo:
 *  1. Definir $viewPath y $routeName (por ejemplo en afterConstruct()).
 *  2. Sobrescribir createModel() para devolver el modelo del módulo.
 *  3. Opcionalmente sobrescribir afterConstruct() para configurar roles,
 *     rutas u otras propiedades propias.
 *
 * Nota: AuthController NO extiende esta clase, porque necesita mostrar el
 * login sin exigir sesión.
 *
 * @author Logística
 * @package App\Controllers
 */
abstract class BaseController
{
    use SecurityTrait, TextoTrait;

    /**
     * Modelo principal del controlador. Es null hasta que se llama a
     * getModel() por primera vez (carga diferida).
     */
    protected ?object $model = null;

    /**
     * Conexión PDO. Se inicializa junto con el modelo en getModel().
     */
    protected ?PDO $pdo = null;

    /**
     * Carpeta de vistas del módulo, relativa a app/Views
     * (por ejemplo 'consulta_academica/estudiantes').
     * No tiene valor por defecto: cada controlador hijo debe asignarla antes
     * de usarla, o PHP lanzará un Error por propiedad tipada sin inicializar.
     */
    protected string $viewPath;

    /**
     * Segmento de ruta del controlador tal como lo resuelve el router
     * (por ejemplo 'estudiantes'). Lo usa redirect() para armar la URL.
     * Igual que $viewPath, cada hijo debe asignarla.
     */
    protected string $routeName;

    /**
     * Valida sesión e inicializa ganchos del controlador hijo.
     *
     * El orden importa: primero verificarSesion(), para que un visitante sin
     * sesión sea redirigido antes de que el hijo configure nada, y después
     * afterConstruct(), que es el punto de extensión de cada módulo.
     */
    public function __construct()
    {
        $this->verificarSesion();
        $this->afterConstruct();
    }

    /**
     * Instancia y retorna el modelo principal mediante Lazy Loading.
     *
     * La primera llamada abre la conexión PDO y le pide al hijo que fabrique
     * su modelo con createModel(). Las siguientes llamadas devuelven la misma
     * instancia, así se abre una sola conexión por request.
     *
     * @return object|null Modelo del módulo, o null si el hijo no definió createModel().
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
     *
     * Por defecto no crea ningún modelo. Cada hijo lo sobrescribe, por ejemplo:
     * `return new EstudianteModel($pdo);`
     *
     * @param PDO $pdo Conexión ya abierta por getModel().
     */
    protected function createModel(PDO $pdo): ?object
    {
        return null;
    }

    /**
     * Hook ejecutable tras la construcción del controlador base.
     *
     * Vacío a propósito. Los hijos lo sobrescriben para asignar $viewPath,
     * $routeName, roles permitidos, etc., sin tener que reescribir el
     * constructor ni acordarse de llamar a parent::__construct().
     */
    protected function afterConstruct(): void {}

    // ---------- Respuestas JSON ----------

    /**
     * Emite una respuesta JSON estructurada (éxito o error) y finaliza la ejecución.
     *
     * Es el formato estándar que consumen mis scripts de front (fetch).
     * Ojo con el orden de argumentos: en éxito el payload va en $data y el
     * texto en $message; JsonResponse::error() los recibe invertidos, por eso
     * la ramificación está aquí y no en cada controlador.
     *
     * @param bool        $ok      true para éxito, false para error.
     * @param mixed       $data    Datos a devolver (o detalle del error).
     * @param string|null $message Mensaje legible para el usuario.
     * @param int         $code    Código HTTP de la respuesta.
     */
    protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never
    {
        $ok
            ? JsonResponse::success($data, $message, $code)->send()
            : JsonResponse::error($message, $data, $code)->send();
    }

    /**
     * Emite un payload JSON sin la estructura estandarizada.
     *
     * Lo uso cuando el consumidor espera un formato propio (por ejemplo
     * librerías de terceros como DataTables) y no el envoltorio ok/data/message.
     *
     * @param array $payload Contenido exacto a serializar.
     * @param int   $code    Código HTTP de la respuesta.
     */
    protected function jsonRaw(array $payload, int $code = 200): never
    {
        JsonResponse::raw($payload, $code)->send();
    }

    /**
     * Registra el error en log y muestra la vista de error HTTP 500 para peticiones HTML.
     *
     * El detalle técnico (mensaje, archivo y línea) solo va al log del
     * servidor, junto con la clase hija que lo originó (static::class) y la
     * sección indicada. Al usuario solo le muestro la vista genérica.
     *
     * @param Throwable $e       Excepción capturada.
     * @param string    $seccion Nombre corto de la operación que falló, para ubicarla en el log.
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
     *
     * Es el equivalente de handleError() para endpoints JSON: mismo log, pero
     * la respuesta es un JSON de error con un mensaje neutro que incluye la
     * sección, para que el front pueda mostrarlo en SweetAlert.
     *
     * @param Throwable $e       Excepción capturada.
     * @param string    $seccion Nombre corto de la operación que falló.
     * @param int       $code    Código HTTP de la respuesta (500 por defecto).
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
     *
     * Busco primero 'usuario_id' (la clave que guarda AuthController al
     * iniciar sesión) y, por compatibilidad, 'id_usuario'.
     *
     * @return int|null ID del usuario, o null si no hay sesión.
     */
    protected function idUsuarioActual(): ?int
    {
        return isset($_SESSION['usuario_id'])
            ? (int) $_SESSION['usuario_id']
            : (isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null);
    }

    /**
     * Obtiene los mensajes flash acumulados y los elimina de la sesión.
     *
     * Se leen una sola vez: después de llamarlo ya no quedan en $_SESSION,
     * así el aviso no reaparece al recargar la página.
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
     * Almacena un mensaje temporal en la sesión (error o éxito).
     *
     * Se usa antes de una redirección, y la vista destino lo recupera con
     * getFlashMessages(). El tipo debe ser 'error' o 'success', porque
     * getFlashMessages() solo lee esas dos claves.
     *
     * @param string $type    'error' o 'success'.
     * @param string $message Texto a mostrar.
     */
    protected function setFlash(string $type, string $message): void
    {
        $_SESSION["flash_{$type}"] = $message;
    }

    /**
     * Redirige a una acción relativa dentro de la ruta actual.
     *
     * Arma la URL como BASE_URL + $routeName + acción (+ query string) y
     * termina la ejecución. Requiere que el hijo haya definido $routeName.
     *
     * Ejemplo: en el controlador `estudiantes`,
     * `$this->redirect('index', ['q' => 'perez'])` lleva a `estudiantes/index?q=perez`.
     *
     * @param string $action Acción destino dentro del mismo controlador.
     * @param array  $params Parámetros para la query string (opcional).
     */
    protected function redirect(string $action, array $params = []): never
    {
        $query = $params ? '?' . http_build_query($params) : '';
        header('Location: ' . BASE_URL . "{$this->routeName}/{$action}{$query}");
        exit;
    }
}