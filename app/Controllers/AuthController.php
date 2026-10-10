<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\BitacoraModel;
use App\Models\LoginModel;
use App\Models\RecoveryModel;
use App\Traits\SecurityTrait;
use function App\Traits\sigde_rol_clave;
use PDO;

/**
 * Controlador de autenticación de SIGDE: login con bloqueo por intentos, cambio de clave provisional,
 * recuperación de acceso (la atiende un administrador) y logout. Los avisos usan SweetAlert2 vía $_SESSION['swal'].
 * El nombre se guarda sin escapar en sesión; el escape (htmlspecialchars) se hace en la vista.
 *
 * Sesión activa en rutas públicas y clave provisional pendiente se controlan en el front controller
 * (public/index.php), no aquí.
*/
class AuthController
{
    use SecurityTrait;

   /**
 * Hash bcrypt señuelo (Costo 12): se verifica cuando el usuario no existe para igualar el tiempo de respuesta.
 * Así no se pueden enumerar cuentas midiendo la latencia.
*/
    private const HASH_SENUELO = '$2y$12$hr6wyFe4V05Soa/O3gkWsOO5m.3hXGxjFgn7t6bjSH0x5WIvC.atW';

    /** Conexión PDO compartida por los tres modelos.*/
    private PDO $db;

    /** Modelo de login: usuarios, intentos fallidos, bloqueo y cambio de clave.*/
    private LoginModel $model;

    /** Modelo de recuperación de acceso: solicitudes pendientes al administrador.*/
    private RecoveryModel $recoveryModel;

    /** Registro de auditoría (LOGIN, LOGOUT, CAMBIO_PASSWORD).*/
    private BitacoraModel $bitacora;

    /**
 * Inicializa la conexión y los modelos.
 * Ya no redirige: el front controller manda al dashboard a quien tenga sesión en rutas públicas
 * y obliga a cambiar la clave provisional antes de llegar a cualquier otra ruta.
*/
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();

        $this->model         = new LoginModel($this->db);
        $this->recoveryModel = new RecoveryModel($this->db);
        $this->bitacora      = new BitacoraModel($this->db);
    }

    /**
 * Muestra el formulario de login (auth/login, GET).
*/
    public function login(): void
    {
        require __DIR__ . '/../Views/auth/login.php';
    }

/**
 * Procesa el login (auth/authenticate, POST) y siempre termina en redirección.
 * Usa mensaje genérico y HASH_SENUELO si el usuario no existe, controla el bloqueo y registra intentos fallidos.
 * Si acierta: reinicia intentos, regenera el ID de sesión, guarda datos en sesión y registra LOGIN en bitácora.
*/
    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redireccionar('auth/login');
        }

        $nombreUsuario = trim((string)($_POST['usuario'] ?? ''));
        $password      = (string)($_POST['password'] ?? '');

        if ($nombreUsuario === '' || $password === '') {
            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir('warning', 'Campos incompletos', 'Por favor, ingrese su usuario y contraseña.', 'auth/login');
        }

        $usuario = $this->model->obtenerPorUsuario($nombreUsuario);

        if (!$usuario) {
            // Se verifica igual contra un hash señuelo para igualar tiempos.
            password_verify($password, self::HASH_SENUELO);

            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir('error', 'Credenciales inválidas', 'Usuario o contraseña incorrectos.', 'auth/login');
        }

        // Verificación de bloqueo previo
        $estadoBloqueo = $this->model->verificarBloqueo($usuario['bloqueado_hasta']);

        if ($estadoBloqueo['bloqueado']) {
            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir(
                'error',
                'Cuenta bloqueada',
                "Demasiados intentos fallidos. Intente nuevamente en {$estadoBloqueo['minutos_restantes']} minuto(s).",
                'auth/login'
            );
        }

        // Bloqueo vencido: se limpia el contador para que un solo fallo no bloquee de nuevo.
        $bloqueoVencidoLimpiado = false;
        if (!empty($usuario['bloqueado_hasta'])) {
            $this->model->reiniciarIntentos((int) $usuario['id']);
            $usuario['intentos_fallidos'] = 0;
            $bloqueoVencidoLimpiado = true;
        }

        // Verificación de contraseña
        if (password_verify($password, $usuario['password_hash'])) {
            $usuarioId = (int) $usuario['id'];
            $rol = sigde_rol_clave($usuario['rol']);

            if ($rol === 'secretaria' && (int) ($usuario['id_turno'] ?? 0) <= 0) {
                $_SESSION['last_usuario'] = $nombreUsuario;
                $this->mostrarSwalYRedirigir(
                    'error',
                    'Turno no asignado',
                    'Solicite al directivo que asigne un turno a su empleado antes de ingresar.',
                    'auth/login'
                );
            }

            // Se reinicia en la BD solo si hay fallos previos acumulados 
            // Y si NO lo acabamos de limpiar un par de líneas arriba.
            if (!$bloqueoVencidoLimpiado && (int) $usuario['intentos_fallidos'] > 0) {
                $this->model->reiniciarIntentos($usuarioId);
            }

            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuarioId;
            // Se guarda el nombre SIN escapar. El escape corresponde a la vista.
            $_SESSION['nombre'] = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
            $_SESSION['rol']    = $rol;
            if ($rol === 'secretaria') {
                $_SESSION['id_turno'] = (int) $usuario['id_turno'];
            } else {
                unset($_SESSION['id_turno']);
            }
            $_SESSION['password_provisional'] = (bool) $usuario['password_provisional'];

            unset($_SESSION['last_usuario']);

            $this->bitacora->registrar($usuarioId, 'LOGIN', 'Autenticación');

            if ($_SESSION['password_provisional']) {
                $this->redireccionar('auth/cambiarPasswordProvisional');
            }

            $this->redireccionar('dashboard/index');
        }

        // Manejo de intento fallido
        $_SESSION['last_usuario'] = $nombreUsuario;
        $intentosActuales = (int)($usuario['intentos_fallidos'] ?? 0);
        $nuevosIntentos   = $this->model->registrarIntentoFallido((int) $usuario['id'], $intentosActuales);

        if ($nuevosIntentos >= LoginModel::MAX_INTENTOS) {
            $estadoFresco = $this->model->verificarBloqueoPorId((int) $usuario['id']);
            $minutos = $estadoFresco['minutos_restantes'] > 0
                ? $estadoFresco['minutos_restantes']
                : LoginModel::MINUTOS_BLOQUEO;

            $this->mostrarSwalYRedirigir(
                'error',
                'Cuenta bloqueada',
                "Ha superado el límite de intentos. Su cuenta ha sido bloqueada por {$minutos} minuto(s).",
                'auth/login'
            );
        }

        $restantes = LoginModel::MAX_INTENTOS - $nuevosIntentos;
        $this->mostrarSwalYRedirigir(
            'error',
            'Credenciales inválidas',
            "Usuario o contraseña incorrectos. Le quedan {$restantes} intento(s) antes del bloqueo.",
            'auth/login'
        );
    }

    /**
 * Muestra el formulario de cambio de clave provisional (auth/cambiarPasswordProvisional, GET).
 * Si en BD la clave ya no es provisional, limpia la marca de sesión y va al dashboard.
*/
    public function cambiarPasswordProvisional(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }

        $usuario = $this->model->obtenerPorId((int) $_SESSION['usuario_id']);

        if ($usuario && !(bool)($usuario['password_provisional'] ?? false)) {
            unset($_SESSION['password_provisional']);
            $this->redireccionar('dashboard/index');
        }

        require __DIR__ . '/../Views/auth/cambio-password.php';
    }

    /**
 * Guarda la nueva contraseña (auth/updatePassword, POST).
 * Solo funciona dentro del flujo de clave provisional: si la sesión no tiene esa marca, va al dashboard.
 * Valida método, sesión, campos completos, coincidencia (hash_equals), fortaleza (validarPasswordFuerte())
 * y que la nueva clave no sea igual a la actual.
 * Si todo pasa, actualiza la clave, registra CAMBIO_PASSWORD y cierra la sesión para exigir un nuevo login.
*/
    public function updatePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }

        // Sin clave provisional pendiente no hay nada que cambiar por esta vía:
        // evita cambiar la clave con solo tener la sesión abierta.
        if (empty($_SESSION['password_provisional'])) {
            $this->redireccionar('dashboard/index');
        }

        $nuevaPassword     = (string)($_POST['nueva_password'] ?? '');
        $confirmarPassword = (string)($_POST['confirmar_password'] ?? '');

        if ($nuevaPassword === '' || $confirmarPassword === '') {
            $this->mostrarSwalYRedirigir('warning', 'Campos incompletos', 'Debe completar ambos campos.', 'auth/cambiarPasswordProvisional');
        }

        if (!hash_equals($nuevaPassword, $confirmarPassword)) {
            $this->mostrarSwalYRedirigir('error', 'Las contraseñas no coinciden', 'Verifique que ambos campos sean iguales.', 'auth/cambiarPasswordProvisional');
        }

        $validacion = $this->validarPasswordFuerte($nuevaPassword);
        if ($validacion !== true) {
            $this->mostrarSwalYRedirigir('error', 'Contraseña débil', $validacion, 'auth/cambiarPasswordProvisional');
        }

        $usuarioId = (int) $_SESSION['usuario_id'];

        // La nueva clave no puede ser la provisional. Solo se comprueba si el modelo
        // devuelve password_hash en obtenerPorId(); si no lo devuelve, se omite.
        $usuarioActual = $this->model->obtenerPorId($usuarioId);
        if (isset($usuarioActual['password_hash']) && password_verify($nuevaPassword, $usuarioActual['password_hash'])) {
            $this->mostrarSwalYRedirigir('error', 'Contraseña no válida', 'La nueva contraseña debe ser distinta a la actual.', 'auth/cambiarPasswordProvisional');
        }

        $actualizado = $this->model->actualizarPassword($usuarioId, $nuevaPassword);

        if (!$actualizado) {
            $this->mostrarSwalYRedirigir('error', 'Error al actualizar', 'No se pudo actualizar la contraseña. Intente nuevamente.', 'auth/cambiarPasswordProvisional');
        }

        $this->bitacora->registrar($usuarioId, 'CAMBIO_PASSWORD', 'Autenticación');

        unset(
            $_SESSION['usuario_id'],
            $_SESSION['nombre'],
            $_SESSION['rol'],
            $_SESSION['id_turno'],
            $_SESSION['last_usuario'],
            $_SESSION['password_provisional']
        );
        session_regenerate_id(true);

        $this->mostrarSwalYRedirigir(
            'success',
            'Contraseña actualizada',
            'Su contraseña fue cambiada exitosamente. Por favor, inicie sesión nuevamente.',
            'auth/login'
        );
    }

    /**
 * Muestra el formulario de recuperar acceso (auth/recoverAccess, GET).
*/
    public function recoverAccess(): void
    {
        require __DIR__ . '/../Views/auth/recuperar-acceso.php';
    }

    /**
 * Registra una solicitud de recuperación para el administrador (auth/requestRecovery, POST).
 * El usuario indica su usuario o cédula; no se envía clave por correo, el administrador la atiende.
 * Avisa si el campo está vacío, no existe el usuario o ya hay una solicitud pendiente.
*/
    public function requestRecovery(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redireccionar('auth/recoverAccess');
        }

        $valor = trim((string)($_POST['usuario_cedula'] ?? ''));

        if ($valor === '') {
            $this->mostrarSwalYRedirigir('warning', 'Campo requerido', 'Ingrese su usuario o cédula.', 'auth/recoverAccess');
        }

        $usuario = $this->recoveryModel->buscarPorUsuarioOCedula($valor);

        if (!$usuario) {
            $this->mostrarSwalYRedirigir('error', 'No encontrado', 'No encontramos ese usuario o cédula.', 'auth/recoverAccess');
        }

        $usuarioId = (int) $usuario['id'];

        if ($this->recoveryModel->tieneSolicitudPendiente($usuarioId)) {
            $this->mostrarSwalYRedirigir('info', 'Solicitud ya enviada', 'Ya existe una solicitud pendiente para este usuario. El administrador la atenderá pronto.', 'auth/login');
        }

        $this->recoveryModel->crearSolicitud($usuarioId);

        $this->mostrarSwalYRedirigir('success', 'Solicitud enviada', 'El administrador ha sido notificado y atenderá su solicitud a la brevedad.', 'auth/login');
    }

    /**
 * Cierra la sesión (auth/logout): registra LOGOUT, vacía $_SESSION y borra la cookie.
 * Luego destruye la sesión y abre una nueva para poder guardar el mensaje flash de SweetAlert.
*/
    public function logout(): void
    {
        $usuarioId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;

        if ($usuarioId > 0) {
            $this->bitacora->registrar($usuarioId, 'LOGOUT', 'Autenticación');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
        session_start();

        $this->mostrarSwalYRedirigir('info', 'Sesión cerrada', 'Ha cerrado su sesión de manera segura.', 'auth/login');
    }

    /**
 * Guarda un mensaje flash de SweetAlert2 (success | error | warning | info) y redirige a 'controlador/accion'.
 * Devuelve `never`, así que el código posterior no se ejecuta y no hace falta return ni exit.
*/
    private function mostrarSwalYRedirigir(string $icono, string $titulo, string $texto, string $ruta): never
    {
        $_SESSION['swal'] = [
            'icon'  => $icono,
            'title' => $titulo,
            'text'  => $texto,
        ];

        $this->redireccionar($ruta);
    }
}