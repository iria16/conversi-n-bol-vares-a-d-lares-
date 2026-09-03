<?php
// public/index.php

// Con el servidor PHP integrado, el router también recibe las peticiones de
// CSS, JavaScript e imágenes. Los archivos existentes deben ser servidos tal cual.
if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $staticFile = __DIR__ . '/' . ltrim($requestedPath, '/');
    if (is_file($staticFile)) {
        return false;
    }
}

date_default_timezone_set('America/Caracas');

/*

 FRONT CONTROLLER - Punto de entrada único de la aplicación MVC (PHP puro)

  Este archivo es el "guardián" del sistema: todas las peticiones HTTP
  pasan primero por aquí. Su responsabilidad es:
    1. Configurar el entorno básico (sesiones, rutas).
    2. Interpretar la URL solicitada (enrutamiento).
    3. Verificar que la ruta exista y esté permitida.
    4. Cargar el controlador correspondiente y ejecutar la acción.

*/


// 1. CONFIGURACIÓN Y SEGURIDAD DE LA SESIÓN

// Blindamos la cookie de sesión antes de iniciarla. Esto es fundamental
// para evitar que atacantes puedan robarla o manipularla.
session_set_cookie_params([
    'lifetime' => 3600,          // 1 hora de inactividad (ajustable)
    'path'     => '/',           // Disponible en todo el sitio
    'domain'   => '',            // El dominio actual (mejor no fijarlo aquí)
    'secure'   => false,         // CAMBIAR A true EN PRODUCCIÓN (requiere HTTPS)
    'httponly' => true,          // Evita que JavaScript acceda a la cookie (anti XSS)
    'samesite' => 'Lax'          // 'Strict' provoca problemas de redirección tras login en algunos navegadores
]);

// Solo arrancamos la sesión si no hay una activa
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


// 2.5 RUTA FÍSICA BASE DEL PROYECTO (APP_PATH)

// Apunta a la carpeta app/ (Controllers, Models, Views). Se usa en todo
// el sistema para construir rutas de archivos sin depender de __DIR__
// en cada archivo individual.
define('APP_PATH', __DIR__ . '/../app');


// 3. OBTENCIÓN Y LIMPIEZA DE LA URI (PRETTY URLS)

// Tomamos únicamente la parte de la ruta (sin dominio ni parámetros GET)
$uriSolicitada = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Decodificamos caracteres especiales (%20, etc.) para volver a la ruta original
$uriDecodificada = urldecode($uriSolicitada);


// 4. ELIMINAR LA SUBRUTA (BASE_URL) PARA OBTENER LA RUTA RELATIVA

// Si estamos en una subcarpeta, necesitamos quitarla de la URI para que
// nuestro sistema de rutas evalúe solo lo que viene después.
if (BASE_URL !== '/') {
    $rutaRelativa = str_replace(BASE_URL, '', $uriDecodificada);
} else {
    $rutaRelativa = $uriDecodificada;
}

// Aseguramos que no quede una barra inicial por la operación anterior
$rutaRelativa = '/' . ltrim($rutaRelativa, '/');


// 5. SEGURIDAD: PREVENCIÓN DE ATAQUES DE PATH TRAVERSAL

// Bloqueamos intentos de navegar hacia arriba en el árbol de directorios
if (strpos($rutaRelativa, '../') !== false || strpos($rutaRelativa, '..\\') !== false) {
    http_response_code(400);
    die("<h1>400 - Solicitud inválida</h1><p>Se detectó un intento de acceso no autorizado.</p>");
}


// 6. LIMPIEZA Y SEPARACIÓN DE SEGMENTOS

// Quitamos barras al inicio y final y dividimos la ruta en segmentos
$uriLimpia = trim($rutaRelativa, '/');
$segmentos = $uriLimpia !== '' ? explode('/', $uriLimpia) : [];
// El servidor PHP integrado puede exponer las rutas como /index.php/modulo/accion.
// Se elimina el nombre del front controller antes de resolver la ruta.
if (($segmentos[0] ?? '') === 'index.php') {
    array_shift($segmentos);
}

// Extraemos controlador, acción y posibles parámetros adicionales
$controllerR    = $segmentos[0] ?? 'auth';    // Controlador por defecto
$actionR        = $segmentos[1] ?? 'login';   // Acción por defecto
$parametrosRuta = array_slice($segmentos, 2); // El resto son parámetros


// 7. SANEAMIENTO DE NOMBRES DE CONTROLADOR Y ACCIÓN

// Solo permitimos letras y números. Esto bloquea inyección de caracteres extraños.
$controllerSaneado = ctype_alnum($controllerR) ? $controllerR : null;
$action             = ctype_alnum($actionR)     ? $actionR     : null;

// Si la validación falla, paramos de inmediato
if ($controllerSaneado === null || $action === null) {
    http_response_code(400);
    die("<h1>400 - Solicitud inválida</h1><p>El controlador o acción contienen caracteres no permitidos.</p>");
}

// Normalizamos a camelCase real (primera letra minúscula) para que la URL
// no dependa de que el usuario escriba mayúsculas exactas: /useraccount/,
// /UserAccount/ y /userAccount/ resuelven todas a la misma clave 'userAccount'
// de la whitelist, sin necesidad de duplicar entradas.
$controller = lcfirst($controllerSaneado);


// 8. LISTA BLANCA (WHITELIST) DE RUTAS PERMITIDAS
// Único lugar que hay que tocar cuando agreguemos un controlador o método
// nuevo. Nombres en camelCase (sin guiones) porque ctype_alnum no permite "-".

$rutasPermitidas = [
    'auth' => [
        'login',
        'authenticate',
        'logout',
        'cambiarPasswordProvisional',
        'updatePassword',
        'recoverAccess',
        'requestRecovery',
    ],
    'dashboard' => [
        'index',
        'admin',
        'docente',
        'coordinador',
        'directivo',
        'secretaria',
    ],
    'userAccount' => [
        'index',
        'show',
        'create',
        'store',
        'storeAjax',
        'edit',
        'update',
        'updateAjax',
        'toggleEstadoAjax',
        'getDetalleAjax',
    ],
    'accessRecovery' => [
        'index',
        'show',
        'getDetalleAjax',
        'manual',
        'storeManual',
        'storeManualAjax',
        'aprobarAjax',
        'rechazarAjax',
        'reenviarAjax',
    ],
    'catalogo' => [
        'index',
        'storeAjax',
        'toggleEstadoAjax',
        'updateAjax',
    ],
    'academicYear' => [
        'index',
        'storeAjax',
        'updateAjax',
        'toggleEstadoAjax',
    ],
    'academicStructure' => [
        'index',
        'storeAjax',
        'updateAjax',
    ],
    'teacherAssignment' => [
        'index',
        'assignAjax',
        'removeAjax',
    ],
    'withdrawal' => [
        'index',
        'updateStatusAjax',
    ],
    'staff' => [
        'index',
        'listAjax',
        'storeAjax',
        'updateAjax',
        'toggleStatusAjax',
        'getByIdAjax',
        'municipiosPorEstadoAjax',
        'parroquiasPorMunicipioAjax',
    ],
    'bitacora' => [
    'index',
    'exportar',
    ],
    'estudiantes' => [
    'index',
    'ficha',
    'exportar',
],
];

// Comprobamos que el controlador y la acción estén en la lista blanca
if (!isset($rutasPermitidas[$controller]) ||
    !in_array($action, $rutasPermitidas[$controller], true)) {
    http_response_code(404);
    die("<h1>404 - Ruta no encontrada</h1><p>La página solicitada no existe o no tienes acceso.</p>");
}


// 9. INYECCIÓN DE PARÁMETROS DE RUTA EN $_GET

if (!empty($parametrosRuta)) {
    foreach ($parametrosRuta as $indice => $valor) {
        // El primer parámetro extra se asigna a 'id', los siguientes a 'param2', 'param3'...
        $nombreVariable = ($indice === 0) ? 'id' : 'param' . ($indice + 1);

        // Sanitizamos para evitar XSS si se imprimiera directamente en una vista
        $_GET[$nombreVariable] = htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    }
}


// 10. CARGA Y EJECUCIÓN DEL CONTROLADOR

/*
 Construimos el nombre de la clase siguiendo la convención:
   Controlador: primera letra mayúscula + sufijo "Controller"
   Archivo: mismo nombre que la clase en la carpeta app/Controllers/

 Ejemplo: userAccount → UserAccountController → app/Controllers/UserAccountController.php
 */
$nombreClaseControlador = ucfirst($controller) . 'Controller';
$rutaArchivoControlador = APP_PATH . "/Controllers/{$nombreClaseControlador}.php";

// Verificamos que el archivo exista físicamente
if (!file_exists($rutaArchivoControlador)) {
    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>Falta el archivo del controlador: <code>{$nombreClaseControlador}.php</code></p>");
}

// Incluimos la clase (solo si no se ha cargado antes con un autoloader)
require_once $rutaArchivoControlador;

// Instanciamos el controlador (sin pasar dependencias; en proyectos sencillos es aceptable)
$instanciaControlador = new $nombreClaseControlador();

// Comprobamos que el método exista en la clase
if (!method_exists($instanciaControlador, $action)) {
    http_response_code(500);
    die("<h1>500 - Error interno</h1><p>El método <code>{$action}()</code> no está definido en <code>{$nombreClaseControlador}</code>.</p>");
}

// Finalmente, ejecutamos la acción solicitada
$instanciaControlador->$action();