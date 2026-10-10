<?php
// public/index.php
// Front Controller: unico punto de entrada del sistema.
// Gestiona sesiones, rutas, permisos y ejecuta el controlador correspondiente.

// 1. CONFIGURACION Y SEGURIDAD DE LA SESION
// Segundos de inactividad permitidos antes de cerrar la sesion (1 hora).
$limiteInactividad = 3600;

// Tiempo tras el cual PHP puede borrar sesiones inactivas (es orientativo;
// el cierre real lo hace la comprobacion de mas abajo).
ini_set('session.gc_maxlifetime', (string) $limiteInactividad);

// Modo estricto: PHP rechaza IDs de sesion que el servidor no creo,
// asi nadie puede imponer un ID propio (fijacion de sesion).
ini_set('session.use_strict_mode', '1');

// Detecta HTTPS tambien detras de un proxy (hostings como InfinityFree),
// donde PHP no siempre recibe HTTPS = 'on'. Se usa en la marca Secure.
$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

// lifetime 0: la cookie se borra al cerrar el navegador. httponly: JS no la lee.
// samesite Strict: no se envia desde otros sitios (frena CSRF). secure: solo por HTTPS.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DENY: ningun otro sitio puede mostrar el sistema en un iframe (clickjacking).
// nosniff: el navegador respeta el tipo de archivo declarado y no lo adivina.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Si paso el limite sin actividad, se vacian los datos de la sesion y se cambia el ID.
// session_regenerate_id(true) tambien borra el archivo de la sesion vieja del servidor.
if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > $limiteInactividad) {
    $_SESSION = [];
    session_regenerate_id(true);
}

// Solo quien inicio sesion renueva el reloj de actividad: asi los visitantes
// del login y los bots no dejan marcas ni sesiones innecesarias.
if (isset($_SESSION['usuario_id'])) {
    $_SESSION['ultima_actividad'] = time();
}


// 1.1 AYUDAS PARA PETICIONES AJAX
// SIGDEUI.enviar() (sigde-ui.js) manda X-Requested-With: XMLHttpRequest. Con esa cabecera
// se sabe que pide el JS y no el navegador, asi que se responde JSON y no un redirect o HTML.
function esPeticionAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// Responde un error en JSON con la forma del resto del sistema (ok, data, mensaje).
// $codigo es opcional: sigde-ui.js reconoce 'sesion_expirada' para enviar al login.
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
// Deduce la carpeta desde donde se sirve el sistema (ej. /sidgev1/public/),
// para que funcione igual en local y en el hosting.
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$scriptDir = rtrim(dirname($scriptName), '/');
$baseUrl = ($scriptDir === '/' || $scriptDir === '') ? '/' : $scriptDir . '/';
define('BASE_URL', $baseUrl);


// 3. OBTENCION Y LIMPIEZA DE LA URI
$uriSolicitada = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriDecodificada = urldecode($uriSolicitada);

// Se quita BASE_URL solo si esta al inicio de la ruta (no en medio).
// Tambien se acepta la ruta sin barra final (/sidgev1/public).
$baseSinBarra = rtrim(BASE_URL, '/');

if ($baseSinBarra !== '' && ($uriDecodificada === $baseSinBarra || strpos($uriDecodificada, BASE_URL) === 0)) {
    $rutaRelativa = substr($uriDecodificada, strlen($baseSinBarra));
} else {
    $rutaRelativa = $uriDecodificada;
}

$rutaRelativa = '/' . ltrim($rutaRelativa, '/');


// 4. PREVENCION DE PATH TRAVERSAL
// Rechaza rutas con ../ o ..\ que intenten salir de las carpetas del sistema.
if (strpos($rutaRelativa, '../') !== false || strpos($rutaRelativa, '..\\') !== false) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 5. LIMPIEZA Y SEPARACION DE SEGMENTOS
// La ruta es /controlador/accion/parametros. Si esta vacia, se usa auth/login.
$uriLimpia = trim($rutaRelativa, '/');
$segmentos = $uriLimpia !== '' ? explode('/', $uriLimpia) : [];
$controllerR = $segmentos[0] ?? 'auth';
$actionR = $segmentos[1] ?? 'login';
$parametrosRuta = array_slice($segmentos, 2);


// 6. SANEAMIENTO DE CONTROLADOR Y ACCION
// Solo se aceptan letras y numeros; cualquier otro caracter se rechaza.
$controller = ctype_alnum($controllerR) ? $controllerR : null;
$action = ctype_alnum($actionR) ? $actionR : null;

if ($controller === null || $action === null) {
    http_response_code(400);
    die("<h1>400 - Solicitud invalida</h1>");
}


// 7. MATRIZ DE RUTAS Y ROLES PERMITIDOS
// Lista blanca: controlador => accion => roles con permiso. 'publico' = sin login.
// Lo que no este aqui responde 404, aunque la clase y el metodo existan.
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
        'index' => ['admin', 'directivo'],
        'store' => ['admin', 'directivo'],
        'update' => ['admin', 'directivo'],
        'iniciar' => ['admin', 'directivo'],
        'cerrar' => ['admin', 'directivo'],
        // Solo la directiva: quien decide el ciclo del año es quien puede revertir un
        // cierre por error, y queda con su nombre y su motivo en la bitácora.
        'reabrir' => ['directivo']
    ],
    'academicStructure' => [
        'index' => ['coordinador', 'directivo'],
        'store' => ['coordinador', 'directivo'],
        'update' => ['coordinador', 'directivo'],
        /* A cuántos alumnos movería la edición de una estructura (GET).
 El frontend lo pregunta antes de enviar para poder pedir confirmación
 con el número real, en vez de un texto genérico. Solo lectura.*/
        'impacto' => ['coordinador', 'directivo']
      ],
    'teacherAssignment' => [
        'index' => ['coordinador', 'directivo'],
        'asignar' => ['coordinador', 'directivo'],
        'actualizar' => ['coordinador', 'directivo'],
        'retirar' => ['coordinador', 'directivo'],
        'getDocentesPorEstructura' => ['coordinador', 'directivo']
    ],
    'withdrawal' => [
        'index'            => ['directivo', 'secretaria'],
        /* Detalle de una solicitud (GET withdrawal/ver/{id}). Lo consume el botón
 "ver" del listado: devuelve SOLO el cuerpo del modal cuando viene por AJAX.*/
        'ver'              => ['directivo', 'secretaria'],
        'buscarEstudiante' => ['secretaria'],
        'store'            => ['secretaria'],
        'updateEstado'     => ['directivo']
    ],
    'estudiantes' => [
        'index' => ['coordinador', 'secretaria', 'docente'],
        'show' => ['coordinador', 'secretaria', 'docente'],
        'exportar' => ['coordinador', 'secretaria', 'docente'],
        /* Solo secretaria: marcar un recaudo como entregado es un asiento sobre un requisito
 de matrícula, no una consulta. La ruta sola no basta, el turno se comprueba en el
 controlador (secretariaTieneAccesoAEstudiante).*/
        'guardarRecaudos' => ['secretaria']
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
          'index'            => ['secretaria'],
          'ver'              => ['secretaria'],
          'datosEstudiante'  => ['secretaria'],
          'getUbicacion'     => ['secretaria'],
          'buscarPersona'    => ['secretaria'],
      /* Secciones del grado en el año destino (JSON). La recarga el selector de
 sección del modal cuando la secretaria cambia el grado.*/
          'secciones'        => ['secretaria'],
          'ratificar'        => ['secretaria'],
          'updateEstudiante' => ['secretaria']
      ],
];

if (!isset($rutasPermitidas[$controller][$action])) {
    http_response_code(404);
    die("<h1>404 - Ruta no encontrada</h1>");
}


// 8. MIDDLEWARE DE AUTENTICACION Y AUTORIZACION
$rolesPermitidos = $rutasPermitidas[$controller][$action];
$esRutaPublica = in_array('publico', $rolesPermitidos, true);

// Ruta privada sin sesion: el JS recibe 401 en JSON (y va al login);
// el navegador normal se redirige al login.
if (!$esRutaPublica && !isset($_SESSION['usuario_id'])) {
    if (esPeticionAjax()) {
        responderJson(401, 'Tu sesión expiró. Inicia sesión de nuevo.', 'sesion_expirada');
    }

    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Ruta privada con sesion: se verifica que el rol tenga permiso (403 si no).
if (!$esRutaPublica) {
    $rolUsuario = $_SESSION['rol'] ?? null;
    if (!in_array($rolUsuario, $rolesPermitidos, true)) {
        if (esPeticionAjax()) {
            responderJson(403, 'No tienes permiso para realizar esta acción.');
        }

        http_response_code(403);
        die("<h1>403 - Acceso denegado</h1>");
    }

    // Con clave provisional solo se puede cambiarla o cerrar sesion; cualquier otra
    // ruta redirige al cambio. Asi no se salta escribiendo la URL a mano.
    $accionesPermitidasProvisional = ['cambiarPasswordProvisional', 'updatePassword', 'logout'];

    if (!empty($_SESSION['password_provisional'])
        && !($controller === 'auth' && in_array($action, $accionesPermitidasProvisional, true))) {
        if (esPeticionAjax()) {
            responderJson(403, 'Debes cambiar tu contraseña provisional.', 'password_provisional');
        }

        header('Location: ' . BASE_URL . 'auth/cambiarPasswordProvisional');
        exit;
    }

    // Las paginas privadas no se guardan en cache: sin esto, el boton "atras"
    // podria mostrar contenido privado despues de cerrar sesion.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Ruta publica con sesion iniciada: no tiene sentido ver el login, va al dashboard.
if ($esRutaPublica && isset($_SESSION['usuario_id'])) {
    $destino = 'dashboard/index';
    header('Location: ' . BASE_URL . $destino);
    exit;
}


// 9. INYECCION DE PARAMETROS EN $_GET
// El primer parametro de la ruta queda en $_GET['id'], los siguientes en param2, param3...
// Se guardan sin escapar: htmlspecialchars se aplica al imprimir en la vista.
if (!empty($parametrosRuta)) {
    foreach ($parametrosRuta as $indice => $valor) {
        $nombreVariable = ($indice === 0) ? 'id' : 'param' . ($indice + 1);
        $_GET[$nombreVariable] = $valor;
    }
}


// 10. AUTOLOADER CON NAMESPACES
// Convierte App\Controllers\XController en app/Controllers/XController.php
// y lo carga solo cuando se necesita, sin require manuales.
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
    // El detalle va al log del servidor, no a la pantalla: mostrar el nombre
    // de la clase revelaria como esta organizado el codigo.
    error_log("[front controller] Falta el controlador: {$nombreClaseControlador}");

    if (esPeticionAjax()) {
        responderJson(500, 'Error interno del servidor.');
    }

    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>Ocurrió un problema. Intenta de nuevo más tarde.</p>");
}

$instanciaControlador = new $nombreClaseControlador();

// is_callable y no method_exists: este ultimo tambien da true para metodos
// protected o private, y llamarlos desde aqui seria un error fatal.
if (!is_callable([$instanciaControlador, $action])) {
    http_response_code(404);
    die("<h1>404 - Recurso no encontrado</h1>");
}

$instanciaControlador->$action();
