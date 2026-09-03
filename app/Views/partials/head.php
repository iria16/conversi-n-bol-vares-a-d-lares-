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
 
$pageDescription = $pageDescription ?? 'Sistema de gestión escolar SIGDE - E.B. Isidro Ramírez.';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<title><?php echo htmlspecialchars($pageTitle); ?> | SIGDE</title>
 
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
 
<link rel="stylesheet" href="<?php echo $assetsPath; ?>/vendor/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?php echo $assetsPath; ?>/css/app.css">

<link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>favicon-16x16.png">
<link rel="manifest" href="<?php echo BASE_URL; ?>site.webmanifest">