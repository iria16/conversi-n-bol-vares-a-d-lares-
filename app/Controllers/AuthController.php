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
 * AuthController
 *
 * Controlador de autenticación de SIGDE. Aquí concentro todo el ciclo de
 * acceso al sistema:
 *
 *  - Inicio de sesión (con bloqueo temporal por intentos fallidos).
 *  - Cambio obligatorio de contraseña provisional.
 *  - Solicitud de recuperación de acceso (la atiende un administrador).
 *  - Cierre de sesión.
 *
 * Decisiones que tomé y que conviene recordar:
 *
 *  1. Este controlador se usa también para mostrar el login. Por eso el
 *     constructor redirige al dashboard si el usuario YA tiene sesión, salvo
 *     en las acciones listadas en ACCIONES_EXENTAS.
 *  2. Los mensajes al usuario se muestran con SweetAlert2: guardo el mensaje
 *     en $_SESSION['swal'] y redirijo; la vista lo imprime una sola vez.
 *  3. El nombre del usuario se guarda en sesión SIN escapar. El escape
 *     (htmlspecialchars) se hace en la vista al imprimirlo.
 *  4. Todas las acciones terminan en redirección (o `never`), nunca
 *     siguen ejecutando después de mostrar un error.
 *
 * Dependencias:
 *  - SecurityTrait: aporta redireccionar(), validarPasswordFuerte() y
 *    destruirSesionPorBloqueo().
 *  - LoginModel / RecoveryModel / BitacoraModel: acceso a datos por PDO.
 *
 * @author Logística
 * @package App\Controllers
 */
class AuthController
{
    use SecurityTrait;

    /**
     * Hash señuelo con formato bcrypt válido. Se verifica contra él cuando el
     * usuario no existe, para que el tiempo de respuesta sea equivalente al de
     * un usuario real y no se pueda enumerar cuentas midiendo la latencia.
     */
    private const HASH_SENUELO = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /**
     * Acciones que no deben redirigir aunque ya haya sesión activa.
     * Van en minúsculas porque accionActual() normaliza el segmento con
     * mb_strtolower(). Si agrego una acción nueva que un usuario logueado
     * deba poder abrir en este controlador, tengo que añadirla aquí.
     */
    private const ACCIONES_EXENTAS = [
        'logout',
        'cambiarpasswordprovisional',
        'updatepassword',
    ];

    /** Conexión PDO compartida por los tres modelos de este controlador. */
    private PDO $db;

    /** Modelo de login: usuarios, intentos fallidos, bloqueo y cambio de clave. */
    private LoginModel $model;

    /** Modelo de recuperación de acceso: solicitudes pendientes al administrador. */
    private RecoveryModel $recoveryModel;

    /** Registro de auditoría (LOGIN, LOGOUT, CAMBIO_PASSWORD). */
    private BitacoraModel $bitacora;

    /**
     * Inicializa la conexión y los modelos, y aplica la regla de "quien ya
     * tiene sesión no debería ver el login".
     *
     * Flujo cuando hay sesión activa y la acción NO está exenta:
     *  1. Reviso si la cuenta fue bloqueada mientras estaba logueado; si es así
     *     destruyo la sesión (destruirSesionPorBloqueo termina la ejecución).
     *  2. Si arrastra una clave provisional, lo mando a cambiarla: no puede
     *     navegar a ningún otro lado hasta hacerlo.
     *  3. En cualquier otro caso lo envío al dashboard, que resuelve el
     *     destino según el rol.
     *
     * Si no hay sesión, o la acción es exenta, el constructor simplemente
     * termina y deja correr la acción pedida.
     */
    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();

        $this->model         = new LoginModel($this->db);
        $this->recoveryModel = new RecoveryModel($this->db);
        $this->bitacora      = new BitacoraModel($this->db);

        $accionActual = $this->accionActual();

        if (!isset($_SESSION['usuario_id']) || in_array($accionActual, self::ACCIONES_EXENTAS, true)) {
            return;
        }

        $usuarioId     = (int) $_SESSION['usuario_id'];
        $estadoBloqueo = $this->model->verificarBloqueoPorId($usuarioId);

        if ($estadoBloqueo['bloqueado'] ?? false) {
            $this->destruirSesionPorBloqueo((int)($estadoBloqueo['minutos_restantes'] ?? 0));
        }

        // Si arrastra una clave provisional, no puede navegar a otro lado.
        if (!empty($_SESSION['password_provisional'])) {
            $this->redireccionar('auth/cambiarPasswordProvisional');
        }

        $this->redireccionar('dashboard/index');
    }

    /**
     * Devuelve el segmento de acción de la URL actual (controlador/accion/...),
     * en minúsculas. Comparación exacta por segmento en lugar de strpos sobre
     * la URI completa, que da falsos positivos.
     *
     * Pasos:
     *  1. Tomo solo el path de REQUEST_URI (sin query string).
     *  2. Le quito el prefijo de BASE_URL si la app corre en una subcarpeta.
     *  3. Separo por "/" y descarto segmentos vacíos.
     *  4. El segmento [0] es el controlador y el [1] la acción.
     *
     * @return string Acción en minúsculas, o cadena vacía si no hay.
     */
    private function accionActual(): string
    {
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $base = parse_url(BASE_URL, PHP_URL_PATH) ?? '/';

        if ($base !== '' && $base !== '/' && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }

        $segmentos = array_values(array_filter(explode('/', trim($ruta, '/')), static fn($s) => $s !== ''));

        return mb_strtolower($segmentos[1] ?? '', 'UTF-8');
    }

    /**
     * Muestra el formulario de inicio de sesión.
     * Ruta: auth/login (GET).
     *
     * Si ya hay sesión, el constructor redirige antes de llegar aquí.
     */
    public function login(): void
    {
        require __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Procesa el envío del formulario de login.
     * Ruta: auth/authenticate (POST).
     *
     * Flujo:
     *  1. Solo acepto POST; cualquier otro método vuelve al login.
     *  2. Valido que usuario y contraseña no vengan vacíos.
     *  3. Busco el usuario. Si no existe, verifico la contraseña contra
     *     HASH_SENUELO para igualar el tiempo de respuesta y devuelvo el mismo
     *     mensaje genérico que para una clave incorrecta (evita enumerar cuentas).
     *  4. Si la cuenta está bloqueada, aviso cuántos minutos faltan.
     *  5. Si la contraseña es correcta: reinicio los intentos, regenero el ID
     *     de sesión (contra session fixation), cargo los datos de sesión,
     *     registro el LOGIN en bitácora y redirijo:
     *       - a cambiarPasswordProvisional si la clave es provisional;
     *       - a la URL guardada en redirect_after_login, o al dashboard.
     *  6. Si es incorrecta: registro el intento fallido. Al llegar a
     *     LoginModel::MAX_INTENTOS la cuenta queda bloqueada; si no, aviso
     *     cuántos intentos quedan.
     *
     * En sesión guardo: usuario_id, nombre, rol (normalizado con
     * sigde_rol_clave) y password_provisional. last_usuario sirve solo para
     * repoblar el campo del formulario tras un error.
     *
     * @return void Siempre termina en redirección.
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

        // Verificación de contraseña
        if (password_verify($password, $usuario['password_hash'])) {
            $usuarioId = (int) $usuario['id'];

            $this->model->reiniciarIntentos($usuarioId);
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuarioId;
            // Se guarda el nombre SIN escapar. El escape corresponde a la vista
            // (htmlspecialchars al imprimirlo en el header/partial).
            $_SESSION['nombre'] = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
            $_SESSION['rol']    = sigde_rol_clave($usuario['rol']);
            $_SESSION['password_provisional'] = (bool) $usuario['password_provisional'];

            unset($_SESSION['last_usuario']);

            $this->bitacora->registrar($usuarioId, 'LOGIN', 'Autenticación');

            if ($_SESSION['password_provisional']) {
                $this->redireccionar('auth/cambiarPasswordProvisional');
            }

            $destino = $_SESSION['redirect_after_login'] ?? 'dashboard/index';
            unset($_SESSION['redirect_after_login']);

            $this->redireccionar($destino);
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
     * Muestra el formulario para cambiar la contraseña provisional.
     * Ruta: auth/cambiarPasswordProvisional (GET).
     *
     * Requiere sesión iniciada. Si en base de datos la clave ya no es
     * provisional (por ejemplo, ya la cambió en otra pestaña), limpio la marca
     * de sesión y lo mando al dashboard en lugar de mostrar el formulario.
     * Es una acción exenta en el constructor para evitar un bucle de redirección.
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
     * Guarda la nueva contraseña definitiva.
     * Ruta: auth/updatePassword (POST). Acción exenta en el constructor.
     *
     * Validaciones, en orden:
     *  1. Método POST y sesión activa.
     *  2. Ambos campos completos.
     *  3. Coincidencia entre nueva y confirmación (hash_equals).
     *  4. Fortaleza, con validarPasswordFuerte() de SecurityTrait, que
     *     devuelve true o el mensaje del problema encontrado.
     *
     * Si todo pasa, actualizo la clave (LoginModel se encarga del hash y de
     * quitar la marca de provisional), registro CAMBIO_PASSWORD en bitácora y
     * cierro la sesión a propósito: obligo a iniciar sesión de nuevo con la
     * clave nueva.
     */
    public function updatePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
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

        $usuarioId   = (int) $_SESSION['usuario_id'];
        $actualizado = $this->model->actualizarPassword($usuarioId, $nuevaPassword);

        if (!$actualizado) {
            $this->mostrarSwalYRedirigir('error', 'Error al actualizar', 'No se pudo actualizar la contraseña. Intente nuevamente.', 'auth/cambiarPasswordProvisional');
        }

        $this->bitacora->registrar($usuarioId, 'CAMBIO_PASSWORD', 'Autenticación');

        unset(
            $_SESSION['usuario_id'],
            $_SESSION['nombre'],
            $_SESSION['rol'],
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
     * Muestra el formulario de "recuperar acceso".
     * Ruta: auth/recoverAccess (GET).
     */
    public function recoverAccess(): void
    {
        require __DIR__ . '/../Views/auth/recuperar-acceso.php';
    }

    /**
     * Registra una solicitud de recuperación de acceso para el administrador.
     * Ruta: auth/requestRecovery (POST).
     *
     * El usuario escribe su nombre de usuario o su cédula. No se envía ninguna
     * clave por correo: se crea una solicitud que el administrador atiende
     * desde su panel (y que normalmente termina en una clave provisional).
     *
     * Casos:
     *  - Campo vacío: aviso y vuelvo al formulario.
     *  - Usuario/cédula inexistente: aviso "No encontrado".
     *  - Ya hay una solicitud pendiente: no creo otra, aviso y voy al login.
     *  - Caso normal: creo la solicitud y confirmo el envío.
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

        if ($this->recoveryModel->tieneSolicitudPendiente($usuario['id'])) {
            $this->mostrarSwalYRedirigir('info', 'Solicitud ya enviada', 'Ya existe una solicitud pendiente para este usuario. El administrador la atenderá pronto.', 'auth/login');
        }

        $this->recoveryModel->crearSolicitud($usuario['id']);

        $this->mostrarSwalYRedirigir('success', 'Solicitud enviada', 'El administrador ha sido notificado y atenderá su solicitud a la brevedad.', 'auth/login');
    }

    /**
     * Cierra la sesión del usuario.
     * Ruta: auth/logout. Acción exenta en el constructor.
     *
     * Registro LOGOUT en bitácora (si había sesión), vacío $_SESSION, borro
     * la cookie de sesión con los mismos parámetros con que se creó, destruyo
     * la sesión y abro una nueva. Esa sesión nueva es necesaria para poder
     * guardar el mensaje flash de SweetAlert antes de redirigir al login.
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
     * Guarda un mensaje flash para SweetAlert2 y redirige.
     *
     * Es mi atajo para todos los avisos del controlador: como devuelve
     * `never`, el código que viene después de llamarlo no se ejecuta, así que
     * no hace falta poner return ni exit en cada rama.
     *
     * @param string $icono  Ícono de SweetAlert2: success | error | warning | info.
     * @param string $titulo Título del cuadro de diálogo.
     * @param string $texto  Mensaje descriptivo para el usuario.
     * @param string $ruta   Destino con formato controlador/accion.
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