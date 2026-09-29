<?php
/**
 * Partial: head.php
 * <head> reutilizable para cualquier vista del sistema (auth e internas).
 *
 * Variables que la vista debe definir ANTES del include:
 * - string $assetsPath        Ruta relativa hacia public/assets desde la vista actual. Requerido.
 * - string $pageTitle         Título de la pestaña (sin el sufijo "| SIGDE"). Requerido.
 * - string $pageDescription   Descripción para meta[name=description]. Opcional.
 */

/* Versionado de los estáticos con el mtime del archivo: el servidor no manda
   Cache-Control, ETag ni Last-Modified (mod_expires no está habilitado), así que
   el navegador guarda copia heurística y no tiene forma de saber si el archivo
   cambió. Con ?v=<mtime> cada edición produce una URL distinta y el CSS se
   vuelve a descargar solo, sin depender de que alguien haga Ctrl+Shift+R. */
$versionDe = static function (string $relativo): string {
    /* Desde app/Views/partials hay que subir tres niveles para llegar a la
       raíz del proyecto: partials -> Views -> app -> raíz. */
    $archivo = dirname(__DIR__, 3) . '/public/assets/' . ltrim($relativo, '/');
    $mtime   = is_file($archivo) ? filemtime($archivo) : false;

    // Si el archivo no existe se usa '0': no se inventa una versión que oculte
    // el 404 real.
    return $mtime === false ? '0' : (string) $mtime;
};

$pageDescription = $pageDescription ?? 'Sistema de gestión escolar SIGDE - E.B. Isidro Ramírez.';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<title><?php echo htmlspecialchars($pageTitle); ?> | SIGDE</title>
 
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
 
<link rel="stylesheet" href="<?php echo $assetsPath, '/vendor/bootstrap-icons/bootstrap-icons.css?v=', $versionDe('/vendor/bootstrap-icons/bootstrap-icons.css'); ?>">
<link rel="stylesheet" href="<?php echo $assetsPath, '/css/app.css?v=', $versionDe('/css/app.css'); ?>">

<link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>favicon-16x16.png">
<link rel="manifest" href="<?php echo BASE_URL; ?>site.webmanifest">