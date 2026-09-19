<?php

// 1. CONFIGURACION Y SEGURIDAD DE LA SESION
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// 2. DETECCIÓN AUTOMÁTICA Y SEGURA DE LA URL BASE (BASE_URL)

/*
  Calculamos la ruta base exclusivamente a partir del script físico que se
  está ejecutando ($_SERVER['SCRIPT_NAME']). Esto es infalible y evita
  cualquier falso positivo al comparar con la URI solicitada.
 */
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']); // Normaliza barras de Windows
$scriptDir  = rtrim(dirname($scriptName), '/');                // Directorio sin el nombre del archivo
$baseUrl    = ($scriptDir === '/' || $scriptDir === '') ? '/' : $scriptDir . '/';

define('BASE_URL', $baseUrl); // Queda disponible en toda la aplicación (Controladores y Vistas)

// 3. OBTENCION Y LIMPIEZA DE LA URI
$uriSolicitada = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriDecodificada = urldecode($uriSolicitada);

if (BASE_URL !== '/') {
    $rutaRelativa = str_replace(BASE_URL, '', $uriDecodificada);
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
        'index'                      => ['publico'], 
        'login'                      => ['publico'],
        'authenticate'               => ['publico'],
        'logout'                     => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'cambiarPasswordProvisional' => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'updatePassword'             => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'recoverAccess'              => ['publico'],
        'requestRecovery'            => ['publico'], 
    ],
'dashboard' => [
        
        'index'       => ['admin', 'docente', 'coordinador', 'directivo', 'secretaria'],
        'admin'       => ['admin'],
        'docente'     => ['docente'],
        'coordinador' => ['coordinador'],
        'directivo'   => ['directivo'],
        'secretaria'  => ['secretaria'],
    ],
     'cuentaUsuario' => [
        'index'         => ['admin'],
        'create'        => ['admin'],
        'store'         => ['admin'],
        'edit'          => ['admin'],
        'update'        => ['admin'],
        'toggleStatus'  => ['admin'],
        'getDetalle'    => ['admin'],
    ],
    'bitacora' => [
        'index' => ['admin'], 
    ],
    'recuperacionAcceso' => [
        'index'        => ['admin'],
        'getDetalle'   => ['admin'],
        'updateEstado' => ['admin'],
        'resetPassword'=> ['admin'],
    ],
    'catalogo' => [
        'index'        => ['admin'],
        'store'        => ['admin'],
        'edit'         => ['admin'],
        'update'       => ['admin'],
        'toggleStatus' => ['admin'],
    ],
];

// 8. MIDDLEWARE DE AUTENTICACION Y AUTORIZACION
$rolesPermitidos = $rutasPermitidas[$controller][$action];
$esRutaPublica = in_array('publico', $rolesPermitidos, true);

// Si no es publica y no hay sesion, redirige al login
if (!$esRutaPublica && !isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_after_login'] = $rutaRelativa;
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Si no es publica, verifica que el rol tenga permiso
if (!$esRutaPublica) {
    $rolUsuario = $_SESSION['rol'] ?? null;
    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
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
if (!empty($parametrosRuta)) {
    foreach ($parametrosRuta as $indice => $valor) {
        $nombreVariable = ($indice === 0) ? 'id' : 'param' . ($indice + 1);
        $_GET[$nombreVariable] = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
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

// 11. CARGA Y EJECUCION DEL CONTROLADO
$nombreClaseControlador = 'App\\Controllers\\' . ucfirst($controller) . 'Controller';

if (!class_exists($nombreClaseControlador)) {
    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>Falta el controlador: {$nombreClaseControlador}</p>");
}

$instanciaControlador = new $nombreClaseControlador();

if (!method_exists($instanciaControlador, $action)) {
    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>El metodo {$action} no existe en {$nombreClaseControlador}</p>");
}

$instanciaControlador->$action();
