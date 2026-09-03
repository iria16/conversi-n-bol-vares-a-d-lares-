<?php

require_once __DIR__ . '/../Models/LoginModel.php';
require_once __DIR__ . '/../Models/RecoveryModel.php';
require_once __DIR__ . '/SecurityTrait.php';
require_once __DIR__ . '/../../config/Database.php';

class AuthController
{
    private LoginModel $model;
    private RecoveryModel $recoveryModel;
    use SecurityTrait;

    public function __construct()
    {
        $database = new Database();
        $this->model = new LoginModel($database->getConnection());
        $this->recoveryModel = new RecoveryModel($database->getConnection());

        $uriActual = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $esPeticionDeLogout = strpos($uriActual, 'logout') !== false;

        // Rutas exentas del redirect automático aunque haya sesión activa:
        // el usuario con contraseña provisional TIENE usuario_id en sesión,
        // pero necesita llegar al formulario de cambio antes de ir al dashboard.
        $esCambioPasswordProvisional = strpos($uriActual, 'cambiarPasswordProvisional') !== false
            || strpos($uriActual, 'updatePassword') !== false;

        // Si ya hay sesión activa (y no es logout ni cambio de contraseña
        // provisional), no dejamos volver a ver el login.
        // Aquí solo tenemos el id en sesión, no el array fresco del usuario,
        // por eso se usa verificarBloqueoPorId() (consulta puntual por id)
        // y no verificarBloqueo() (que espera el dato ya cargado).
        if (isset($_SESSION['usuario_id']) && !$esPeticionDeLogout && !$esCambioPasswordProvisional) {
            $estadoBloqueo = $this->model->verificarBloqueoPorId($_SESSION['usuario_id']);
            if ($estadoBloqueo['bloqueado']) {
                $this->destruirSesionPorBloqueo($estadoBloqueo['minutos_restantes']);
            }

            // Mismo punto de verdad que DashboardController::RUTAS_POR_ROL.
            // Antes esto duplicaba la tabla de rutas por rol aquí mismo;
            // ahora se delega siempre a dashboard/index para no tener dos
            // lugares que puedan desincronizarse (ver nota en authenticate()).
            header("Location: " . BASE_URL . 'dashboard/index');
            exit;
        }
    }

    // Muestra la vista de login
    public function login(): void
    {
        require_once __DIR__ . '/../Views/auth/login.php';
    }

    // Procesa el intento de autenticación
    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redireccionar('auth/login');
        }

        // Validación de csrf_token: el formulario de login.php lo envía
        // como campo hidden.
        if (!$this->validarCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->mostrarSwalYRedirigir('error', 'Sesión inválida', 'Por favor, intente nuevamente.', 'auth/login');
        }

        // El input del formulario de login.php se llama "usuario", no
        // "nombre_usuario" — se lee tal cual llega del POST.
        $nombreUsuario = trim($_POST['usuario'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nombreUsuario) || empty($password)) {
            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir('warning', 'Campos incompletos', 'Por favor, ingrese su usuario y contraseña.', 'auth/login');
        }

        $usuario = $this->model->obtenerPorUsuario($nombreUsuario);

        if (!$usuario) {
            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir('error', 'Credenciales inválidas', 'Usuario o contraseña incorrectos.', 'auth/login');
            return;
        }

        // PRIMERO: bloqueo. Si está bloqueado, ni miramos la contraseña.
        // Aquí SÍ usamos verificarBloqueo() con el dato ya cargado en
        // $usuario['bloqueado_hasta'] — no hace falta otra consulta.
        $estadoBloqueo = $this->model->verificarBloqueo($usuario['bloqueado_hasta']);

        if ($estadoBloqueo['bloqueado']) {
            $_SESSION['last_usuario'] = $nombreUsuario;
            $this->mostrarSwalYRedirigir(
                'error',
                'Cuenta bloqueada',
                "Demasiados intentos fallidos. Intente nuevamente en {$estadoBloqueo['minutos_restantes']} minuto(s).",
                'auth/login'
            );
            return;
        }

        // SEGUNDO: contraseña.
        // Nota: si llegamos aquí, ya sabemos que el usuario NO está
        // bloqueado (se validó arriba con el mismo bloqueado_hasta), por
        // lo que no hace falta volver a comprobarlo dentro de este bloque.
        if (password_verify($password, $usuario['password_hash'])) {
            // reiniciarIntentos() recibe un único parámetro: el esquema
            // real de `usuario.estado` es ENUM('ACTIVO','INACTIVO') y no
            // admite un valor de bloqueo, así que el modelo ya no
            // necesita (ni acepta) el estado actual para decidir nada.
            $this->model->reiniciarIntentos($usuario['id']);
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $this->sanitizarTexto($usuario['nombre'] . ' ' . $usuario['apellido']);
            $_SESSION['rol'] = sigde_rol_clave($usuario['rol']);

            unset($_SESSION['last_usuario']);

            // Registrar el acceso en la bitácora
            try {
                $database = new Database();
                $pdo = $database->getConnection();
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $pdo->prepare(
                    "INSERT INTO bitacora (id_usuario, fecha, hora, accion, modulo, direccion_ip)
                     VALUES (:id, CURDATE(), CURTIME(), 'LOGIN', 'Autenticación', :ip)"
                )->execute(['id' => $usuario['id'], 'ip' => $ip]);
            } catch (Throwable $e) {
                error_log('AuthController: no se pudo registrar login en bitácora: ' . $e->getMessage());
            }

            // Si la contraseña es provisional, forzamos cambio antes de
            // dejarlo entrar al dashboard.
            if ((bool)$usuario['password_provisional']) {
                header("Location: " . BASE_URL . 'auth/cambiarPasswordProvisional');
                exit;
            }

            // IMPORTANTE: el destino por defecto es SIEMPRE dashboard/index,
            // nunca una ruta armada a mano por rol aquí. dashboard/index ya
            // resuelve "admin" -> dashboard/admin, "docente" -> dashboard/docente,
            // etc. usando DashboardController::RUTAS_POR_ROL, que es la
            // ÚNICA tabla de rutas por rol que debe existir en el sistema.
            //
            // Antes había un caso especial:
            //   $destinoDefault = (rol === 'admin') ? 'admin/index' : 'dashboard/index';
            // pero "admin/index" no es una ruta real (el router no la
            // resuelve), así que el admin quedaba en una URL muerta hasta
            // que volvía "atrás" y recargaba dashboard/admin manualmente.
            $destinoDefault = BASE_URL . 'dashboard/index';
            $destino = $_SESSION['redirect_after_login'] ?? $destinoDefault;
            unset($_SESSION['redirect_after_login']);

            header("Location: " . $destino);
            exit;
        }

        // Contraseña incorrecta: registrar intento fallido
        $_SESSION['last_usuario'] = $nombreUsuario;
        $intentosActuales = $usuario['intentos_fallidos'] ?? 0;
        $nuevosIntentos = $this->model->registrarIntentoFallido($usuario['id'], $intentosActuales);

        if ($nuevosIntentos >= LoginModel::MAX_INTENTOS) {
            // El modelo acaba de aplicar el bloqueo — consultamos los minutos frescos
            $estadoFresco = $this->model->verificarBloqueoPorId($usuario['id']);
            $minutos = $estadoFresco['minutos_restantes'] > 0 ? $estadoFresco['minutos_restantes'] : LoginModel::MINUTOS_BLOQUEO;
            $this->mostrarSwalYRedirigir(
                'error',
                'Cuenta bloqueada',
                "Ha superado el límite de intentos. Su cuenta ha sido bloqueada por {$minutos} minuto(s).",
                'auth/login'
            );
        } else {
            $restantes = LoginModel::MAX_INTENTOS - $nuevosIntentos;
            $this->mostrarSwalYRedirigir(
                'error',
                'Credenciales inválidas',
                "Usuario o contraseña incorrectos. Le quedan {$restantes} intento(s) antes del bloqueo.",
                'auth/login'
            );
        }
    }

    // Muestra el formulario de cambio de contraseña provisional
    public function cambiarPasswordProvisional(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }

        require_once __DIR__ . '/../Views/auth/cambio-password.php';
    }

    // Procesa el cambio de contraseña provisional
    public function updatePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redireccionar('auth/login');
        }

        if (!isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }

        $nuevaPassword     = $_POST['nueva_password'] ?? '';
        $confirmarPassword = $_POST['confirmar_password'] ?? '';

        if (empty($nuevaPassword) || empty($confirmarPassword)) {
            $this->mostrarSwalYRedirigir('warning', 'Campos incompletos', 'Debe completar ambos campos.', 'auth/cambiarPasswordProvisional');
        }

        if ($nuevaPassword !== $confirmarPassword) {
            $this->mostrarSwalYRedirigir('error', 'Las contraseñas no coinciden', 'Verifique que ambos campos sean iguales.', 'auth/cambiarPasswordProvisional');
        }

        // Validación de fuerza de contraseña centralizada en SecurityTrait,
        // alineada con el checklist visual del frontend (main.js:
        // initPasswordRequirementsChecklist): longitud mínima, letra y número.
        $validacion = $this->validarPasswordFuerte($nuevaPassword);
        if ($validacion !== true) {
            $this->mostrarSwalYRedirigir('error', 'Contraseña débil', $validacion, 'auth/cambiarPasswordProvisional');
        }

        $actualizado = $this->model->actualizarPassword($_SESSION['usuario_id'], $nuevaPassword);

        if (!$actualizado) {
            $this->mostrarSwalYRedirigir('error', 'Error al actualizar', 'No se pudo actualizar la contraseña. Intente nuevamente.', 'auth/cambiarPasswordProvisional');
            return;
        }

        // Cerramos la sesión de autenticación (el usuario debe volver a
        // iniciar sesión con la nueva contraseña) pero mantenemos la MISMA
        // sesión de PHP activa para poder mostrar el SweetAlert en login.php.
        unset($_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['rol'], $_SESSION['last_usuario']);

        // Regenera el id de sesión por seguridad, invalidando el anterior.
        session_regenerate_id(true);

        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Contraseña actualizada',
            'text' => 'Su contraseña fue cambiada exitosamente. Por favor, inicie sesión nuevamente.'
        ];

        header("Location: " . BASE_URL . 'auth/login');
        exit;
    }

    // Muestra el formulario de recuperación de acceso
    public function recoverAccess(): void
    {
        require_once __DIR__ . '/../Views/auth/recuperar-acceso.php';
    }

    // Procesa la solicitud: crea la notificación interna para el admin
    public function requestRecovery(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redireccionar('auth/recoverAccess');
        }

        $valor = trim($_POST['usuario_cedula'] ?? '');

        if (empty($valor)) {
            $this->mostrarSwalYRedirigir('warning', 'Campo requerido', 'Ingrese su usuario o cédula.', 'auth/recoverAccess');
        }

        $usuario = $this->recoveryModel->buscarPorUsuarioOCedula($valor);

        if (!$usuario) {
            $this->mostrarSwalYRedirigir('error', 'No encontrado', 'No encontramos ese usuario o cédula.', 'auth/recoverAccess');
            return;
        }

        if ($this->recoveryModel->tieneSolicitudPendiente($usuario['id'])) {
            $this->mostrarSwalYRedirigir('info', 'Solicitud ya enviada', 'Ya existe una solicitud pendiente para este usuario. El administrador la atenderá pronto.', 'auth/login');
            return;
        }

        $this->recoveryModel->crearSolicitud($usuario['id']);

        $this->mostrarSwalYRedirigir('success', 'Solicitud enviada', 'El administrador ha sido notificado y atenderá su solicitud a la brevedad.', 'auth/login');
    }

    // Cerrar sesión
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }

        session_destroy();

        $this->redireccionar('auth/login');
    }

    private function mostrarSwalYRedirigir(string $icono, string $titulo, string $texto, string $ruta): void
    {
        $_SESSION['swal'] = [
            'icon' => $icono,
            'title' => $titulo,
            'text' => $texto
        ];
        $this->redireccionar($ruta);
    }
}