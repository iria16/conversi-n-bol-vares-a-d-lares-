<?php
/**
 * Layout: app.php
 * Cascarón de todas las vistas internas (sidebar + header + contenido + footer).
 *
 * La vista de página debe definir ANTES del include:
 * - string $pageTitle
 * - string $pageDescription   (opcional)
 * - string $currentNav
 * - array  $breadcrumbs
 * - string $pageContent       HTML del contenido principal (capturado con ob_start)
 *
 * Opcional:
 * - string $pageModals        HTML de los modales de la vista (capturado con ob_start).
 *                             Se imprime directamente bajo <body>, FUERA de .app-shell,
 *                             para que los modales no hereden overflow ni apilamiento
 *                             del cascarón (recomendación de Bootstrap).
 * - array $extraScripts
 * - bool  $tieneNotificaciones
 */
require_once __DIR__ . '/../helpers/ui.php';

$assetsPath      = BASE_URL . 'assets';
$pageTitle       = $pageTitle ?? 'Panel';
$pageDescription = $pageDescription ?? 'Portal administrativo SIGDE - E.B. Isidro Ramírez.';
$nombreUsuario   = $_SESSION['nombre'] ?? 'Usuario';
$rolSesion       = sigde_rol_clave($_SESSION['rol'] ?? '');
$extraScripts    = $extraScripts ?? [];
if (!in_array('/js/app-shell.js', $extraScripts, true)) {
    $extraScripts[] = '/js/app-shell.js';
}
$pageContent = $pageContent ?? '';
$pageModals  = $pageModals ?? '';

// Notificaciones solo para admin, secretaria y directivo
$tieneNotificaciones = in_array($rolSesion, ['admin', 'secretaria', 'directivo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__ . '/../partials/head.php'; ?>
</head>
<body>

  <div class="app-shell">
    <div class="app-shell__overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <?php include __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="app-shell__main">
      <?php include __DIR__ . '/../partials/app-header.php'; ?>

      <main class="app-shell__content" id="contenido-principal">
        <?php echo $pageContent; ?>
      </main>

      <?php include __DIR__ . '/../partials/app-footer.php'; ?>
    </div>
  </div>

  <?php echo $pageModals; ?>

  <?php include __DIR__ . '/../partials/scripts.php'; ?>

</body>
</html>