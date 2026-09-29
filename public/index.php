<?php
// public/index.php
// Front Controller: unico punto de entrada del sistema
// Gestiona sesiones, rutas, permisos y ejecuta el controlador correspondiente

// 1. CONFIGURACION Y SEGURIDAD DE LA SESION
// Tiempo de inactividad permitido en segundos (1 hora)
$limiteInactividad = 3600;

ini_set('session.gc_maxlifetime', (string) $limiteInactividad);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si pasa el limite sin actividad, se cierra la sesion
if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limiteInactividad) {
    session_unset();
    session_destroy();

    // Borrar cookie del navegador
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_start();
}
$_SESSION['ultima_actividad'] = time();


// 1.1 AYUDAS PARA PETICIONES AJAX
// SIGDEUI.enviar() (sigde-ui.js) manda siempre X-Requested-With: XMLHttpRequest.
// Con esa cabecera se sabe que quien pide es el JS y no el navegador navegando,
// asi que un redirect o un <h1> no sirven: el JS espera JSON.
function esPeticionAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// Misma forma de respuesta que usa el resto del sistema (ok, data, mensaje).
// $codigo es opcional: 'sesion_expirada' lo reconoce sigde-ui.js para
// mandar al usuario al login.
function responderJson(int $status, string $mensaje, ?string $codigo = null)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    $respuesta = ['ok' => false, 'data' => null, 'mensaje' => $mensaje];
    if ($codigo !== null) {
        $respuesta['codigo'] = $codigo;
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}


// 2. DETECCION AUTOMATICA DE BASE_URL
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$scriptDir = rtrim(dirname($scriptName), '/');
$baseUrl = ($scriptDir === '/' || $scriptDir === '') ? '/' : $scriptDir . '/';
define('BASE_URL', $baseUrl);


// 3. OBTENCION Y LIMPIEZA DE LA URI
$uriSolicitada = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriDecodificada = urldecode($uriSolicitada);

// Solo se quita BASE_URL si esta AL INICIO de la URI (str_replace lo
// quitaba tambien si la misma cadena aparecia mas adelante en la ruta).
if (BASE_URL !== '/' && strpos($uriDecodificada, BASE_URL) === 0) {
    $rutaRelativa = substr($uriDecodificada, strlen(BASE_URL));
} else {
    $rutaRelativa = $uriDecodificada;
}

$rutaRelativa = '/' . ltrim($rutaRelativa, '/');


// 4. PREVENCION DE PATH TRAVERSAL
if (strpos($rutaRelativa, '../') !== false || strpos($rutaRelativa, '..\\') !== false) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 5. LIMPIEZA Y SEPARACION DE SEGMENTOS
$uriLimpia = trim($rutaRelativa, '/');
$segmentos = $uriLimpia !== '' ? explode('/', $uriLimpia) : [];
$controllerR = $segmentos[0] ?? 'auth';
$actionR = $segmentos[1] ?? 'login';
$parametrosRuta = array_slice($segmentos, 2);


// 6. SANEAMIENTO DE CONTROLADOR Y ACCION
$controller = ctype_alnum($controllerR) ? $controllerR : null;
$action = ctype_alnum($actionR) ? $actionR : null;

if ($controller === null || $action === null) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 7. MATRIZ DE RUTAS Y ROLES PERMITIDOS
$rutasPermitidas = [
    'auth' => [
        'login' => ['publico'],
        'authenticate' => ['publico'],
        'logout' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'cambiarPasswordProvisional' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'updatePassword' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'recoverAccess' => ['publico'],
        'requestRecovery' => ['publico']
    ],
    'dashboard' => [
        'index' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'admin' => ['admin'],
        'docente' => ['docente'],
        'coordinador' => ['coordinador'],
        'directivo' => ['directivo'],
        'secretaria' => ['secretaria']
    ],
    'cuentaUsuario' => [
        'index' => ['admin'],
        'create' => ['admin'],
        'store' => ['admin'],
        'edit' => ['admin'],
        'update' => ['admin'],
        'toggleStatus' => ['admin'],
        'getDetalle' => ['admin']
    ],
    'bitacora' => [
        'index' => ['admin'],
        'exportar' => ['admin']
    ],
    'recuperacionAcceso' => [
        'index' => ['admin'],
        'getDetalle' => ['admin'],
        'updateEstado' => ['admin'],
        'resetPassword' => ['admin']
    ],
    'catalogo' => [
        'index' => ['admin'],
        'store' => ['admin'],
        'update' => ['admin'],
        'toggleStatus' => ['admin']
    ],
    'empleado' => [
        'index' => ['directivo'],
        'create' => ['directivo'],
        'show' => ['directivo'],
        'edit' => ['directivo'],
        'store' => ['directivo'],
        'update' => ['directivo'],
        'toggleStatus' => ['directivo'],
        'getUbicacion' => ['directivo'],
        'guardarTitulo' => ['directivo'],
        'guardarInstitucion' => ['directivo']
    ],
    'academicYear' => [
        'index' => ['directivo'],
        'store' => ['directivo'],
        'update' => ['directivo'],
        'toggleStatus' => ['directivo']
    ],
    'academicStructure' => [
        'index' => ['coordinador', 'directivo'],
        'store' => ['coordinador', 'directivo'],
        'update' => ['coordinador', 'directivo']
    ],
    'teacherAssignment' => [
        'index' => ['coordinador', 'directivo'],
        'asignar' => ['coordinador', 'directivo'],
        'retirar' => ['coordinador', 'directivo']
    ],
    'withdrawal' => [
        'index' => ['directivo', 'secretaria'],
        'store' => ['secretaria'],
        'updateEstado' => ['directivo']
    ],
    'estudiantes' => [
        'index' => ['coordinador', 'secretaria', 'docente'],
        'show' => ['coordinador', 'secretaria', 'docente']
    ],
    'inscripciones' => [
        'index' => ['secretaria'],
        'store' => ['secretaria'],
        'ver' => ['secretaria'],
        'continuar' => ['secretaria'],
        'buscarPersona' => ['secretaria'],
        'guardarInstitucion' => ['secretaria'],
        'getUbicacion' => ['secretaria']
    ],
    'egresos' => [
        'index' => ['secretaria'],
        'graduar' => ['secretaria'],
        'graduarMasivo' => ['secretaria']
    ],
    'ratificacion' => [
        'index' => ['secretaria'],
        'ratificar' => ['secretaria']
    ]
];

if (!isset($rutasPermitidas[$controller][$action])) {
    http_response_code(404);
    die("<h1>404 - Ruta no encontrada</h1>");
}


// 8. MIDDLEWARE DE AUTENTICACION Y AUTORIZACION
$rolesPermitidos = $rutasPermitidas[$controller][$action];
$esRutaPublica = in_array('publico', $rolesPermitidos, true);

// Si no es publica y no hay sesion: el JS recibe JSON 401 (y va al login);
// el navegador normal se redirige al login como siempre.
if (!$esRutaPublica && !isset($_SESSION['usuario_id'])) {
    if (esPeticionAjax()) {
        responderJson(401, 'Tu sesión expiró. Inicia sesión de nuevo.', 'sesion_expirada');
    }

    $_SESSION['redirect_after_login'] = $rutaRelativa;
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Si no es publica, verifica que el rol tenga permiso
if (!$esRutaPublica) {
    $rolUsuario = $_SESSION['rol'] ?? null;
    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
        if (esPeticionAjax()) {
            responderJson(403, 'No tienes permiso para realizar esta acción.');
        }

        http_response_code(403);
        die("<h1>403 - Acceso denegado</h1>");
    }
}

// Si es publica y ya hay sesion, redirige al dashboard (excepto logout)
if ($esRutaPublica && isset($_SESSION['usuario_id']) && $action !== 'logout') {
    $destino = 'dashboard/index';
    header('Location: ' . BASE_URL . $destino);
    exit;
}


// 9. INYECCION DE PARAMETROS EN $_GET
// Se guardan tal cual llegan: el escape (htmlspecialchars) se aplica al
// IMPRIMIR en la vista, no al recibir, para no corromper valores con & o comillas.
if (!empty($parametrosRuta)) {
    foreach ($parametrosRuta as $indice => $valor) {
        $nombreVariable = ($indice === 0) ? 'id' : 'param' . ($indice + 1);
        $_GET[$nombreVariable] = $valor;
    }
}


// 10. AUTOLOADER CON NAMESPACES
spl_autoload_register(function ($clase) {
    $prefijo = 'App\\';
    $dir_base = __DIR__ . '/../app/';
    $longitud = strlen($prefijo);

    if (strncmp($prefijo, $clase, $longitud) !== 0) {
        return;
    }

    $clase_relativa = substr($clase, $longitud);
    $archivo = $dir_base . str_replace('\\', '/', $clase_relativa) . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    }
});


// 11. CARGA Y EJECUCION DEL CONTROLADOR
$nombreClaseControlador = 'App\\Controllers\\' . ucfirst($controller) . 'Controller';

if (!class_exists($nombreClaseControlador)) {
    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>Falta el controlador: {$nombreClaseControlador}</p>");
}

$instanciaControlador = new $nombreClaseControlador();

// is_callable (y no method_exists): method_exists tambien da true para
// metodos protected o private, y llamarlos desde aqui seria un error fatal.
if (!is_callable([$instanciaControlador, $action])) {
    http_response_code(404);
    die("<h1>404 - Recurso no encontrado</h1>");
}

$instanciaControlador->$action();