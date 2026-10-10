<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\JsonResponse;
use App\Traits\SecurityTrait;
use App\Traits\TextoTrait;
use InvalidArgumentException;
use PDO;
use Throwable;
use function App\Traits\sigde_rol_clave;

/**
 * Controlador base: centraliza sesión, conexión a BD, errores y respuestas JSON.
 * Los hijos definen $viewPath, $routeName y createModel(); afterConstruct() es opcional.
 * AuthController no lo extiende porque debe mostrar el login sin exigir sesión.
*/
abstract class BaseController
{
    use SecurityTrait, TextoTrait;

    /** Modelo principal; es null hasta la primera llamada a getModel() (carga diferida).*/
    protected ?object $model = null;

    /** Conexión PDO, inicializada junto con el modelo en getModel().*/
    protected ?PDO $pdo = null;

    /** Carpeta de vistas del módulo, relativa a app/Views. Cada hijo debe asignarla.*/
    protected string $viewPath;

    /** Segmento de ruta del controlador, usado por redirect(). Cada hijo debe asignarlo.*/
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

    protected function turnoSecretariaActual(): ?int
    {
        if (sigde_rol_clave($_SESSION['rol'] ?? '') !== 'secretaria') {
            return null;
        }

        $stmt = $this->conexion()->prepare(
            'SELECT t.id_turno
               FROM usuario u
               LEFT JOIN empleado e ON e.id_persona = u.id_persona
               LEFT JOIN turno t ON t.id_turno = e.id_turno AND t.estado = \'ACTIVO\'
              WHERE u.id_usuario = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => (int) ($_SESSION['usuario_id'] ?? 0)]);
        $turno = $stmt->fetchColumn();

        return $turno === false || $turno === null ? 0 : (int) $turno;
    }

    /**
 * Id del docente del usuario en sesión, o null si no es docente.
 *
 * Es lo que acota la consulta de expedientes a las estructuras que tiene asignadas.
 * Se resuelve por `empleado → docente` porque es la única cadena que existe: un docente
 * es un empleado con fila en la tabla `docente`, y sus clases están en
 * `asignacion_docente`, una por estructura académica.
*/
    protected function docenteActualId(): ?int
    {
        if (sigde_rol_clave($_SESSION['rol'] ?? '') !== 'docente') {
            return null;
        }

        $stmt = $this->conexion()->prepare(
            'SELECT d.id_docente
               FROM usuario u
               INNER JOIN empleado e ON e.id_persona = u.id_persona
               INNER JOIN docente d ON d.id_empleado = e.id_empleado
              WHERE u.id_usuario = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => (int) ($_SESSION['usuario_id'] ?? 0)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    protected function secretariaTieneAccesoAEstudiante(int $idEstudiante): bool
    {
        $turno = $this->turnoSecretariaActual();
        if ($turno === null) {
            return true;
        }
        if ($turno <= 0) {
            return false;
        }

        $stmt = $this->conexion()->prepare(
            'SELECT ea.id_turno
               FROM matricula m
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
              WHERE m.id_estudiante = :id
              ORDER BY m.id_anio_escolar DESC, m.id_matricula DESC
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstudiante]);

        $idTurno = $stmt->fetchColumn();
        return $idTurno !== false && $idTurno !== null && (int) $idTurno === $turno;
    }

    protected function secretariaTieneAccesoAEstructura(int $idEstructura): bool
    {
        $turno = $this->turnoSecretariaActual();
        if ($turno === null) {
            return true;
        }
        if ($turno <= 0) {
            return false;
        }

        $stmt = $this->conexion()->prepare(
            'SELECT COUNT(*)
               FROM estructura_academica
              WHERE id_estructura_academica = :id AND id_turno = :turno'
        );
        $stmt->execute(['id' => $idEstructura, 'turno' => $turno]);

        return (int) $stmt->fetchColumn() > 0;
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
 * Registra un error en el log del servidor con su ubicación y la traza de llamadas.
 * Solo va al log: la traza puede incluir valores y nunca debe llegar al usuario.
*/
    private function registrarError(Throwable $e, string $seccion): void
    {
        error_log(
            static::class . " [{$seccion}]: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n"
            . $e->getTraceAsString()
        );
    }

    /**
 * Registra el error en el log y muestra la vista genérica de error 500 (peticiones HTML).
 * El detalle técnico solo va al log; $seccion identifica la operación que falló.
*/
    protected function handleError(Throwable $e, string $seccion): never
    {
        $this->registrarError($e, $seccion);
        http_response_code(200); echo str_repeat(' ', 1024);
        echo '==ERROR== ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        exit;
    }

    /**
 * Equivalente de handleError() para AJAX: mismo log, pero responde un JSON con mensaje neutro.
 * El mensaje incluye la sección para que el front lo muestre en SweetAlert.
*/
    protected function jsonError(Throwable $e, string $seccion, int $code = 500): never
    {
        $this->registrarError($e, $seccion);
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
 * Lee 'usuario_id', la clave que guarda AuthController al iniciar sesión.
*/
    protected function idUsuarioActual(): ?int
    {
        return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
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
 * Cualquier otro tipo lanza una excepción, para no perder el mensaje en silencio.
*/
    protected function setFlash(string $type, string $message): void
    {
        if (!in_array($type, ['error', 'success'], true)) {
            throw new InvalidArgumentException("Tipo de flash no válido: {$type}");
        }

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
