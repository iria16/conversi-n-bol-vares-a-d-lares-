<?php
/**
 * Layout: auth.php
 * Cascarón completo para las pantallas de autenticación (login,
 * recuperar-acceso, cambio-password). Entrega: <head>, split screen,
 * panel de marca, tarjeta de formulario, footer, swal-data y scripts.
 *
 * Variables que la vista debe definir ANTES del include:
 * - string $pageTitle                   Título de la pestaña. Requerido.
 * - string $pageDescription             Descripción meta. Opcional.
 * - string $authTagline                 Texto bajo el nombre del sistema. Requerido.
 * - array  $authFeatures                Íconos del panel de marca. Opcional.
 * - string $pageContent                 HTML interior de .auth-card. Requerido.
 * - string $authCardAriaLabelledby      id del encabezado para aria-labelledby. Opcional.
 * - array  $extraScripts                Scripts extra (relativos a $assetsPath). Opcional.
 */
$assetsPath              = BASE_URL . 'assets';
$extraScripts            = $extraScripts ?? [];
$authCardAriaLabelledby  = $authCardAriaLabelledby ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <?php include __DIR__ . '/../partials/head.php'; ?>
</head>
<body>

  <div class="auth-split row g-0 vh-100">

    <?php include __DIR__ . '/../partials/auth-brand-panel.php'; ?>

    <main class="col-12 col-lg-7 auth-split__form">
      <div class="auth-split__center">
        <section class="auth-card"<?php if ($authCardAriaLabelledby): ?> aria-labelledby="<?php echo htmlspecialchars($authCardAriaLabelledby); ?>"<?php endif; ?>>
          <?php echo $pageContent; ?>
        </section>
      </div>

      <?php include __DIR__ . '/../partials/auth-footer.php'; ?>
    </main>

  </div>

  <!-- Elemento oculto que transporta los datos del Controlador al JS externo para SweetAlert -->
  <?php if (isset($_SESSION['swal'])): ?>
      <div id="swal-data"
           data-icon="<?= htmlspecialchars($_SESSION['swal']['icon'], ENT_QUOTES, 'UTF-8') ?>"
           data-title="<?= htmlspecialchars($_SESSION['swal']['title'], ENT_QUOTES, 'UTF-8') ?>"
           data-text="<?= htmlspecialchars($_SESSION['swal']['text'], ENT_QUOTES, 'UTF-8') ?>"
           style="display: none;">
      </div>
      <?php
      // ¡CRÍTICO! Limpiamos la sesión para que la alerta no se muestre de nuevo al recargar (F5)
      unset($_SESSION['swal']);
      ?>
  <?php endif; ?>

  <?php include __DIR__ . '/../partials/scripts.php'; ?>

</body>
</html>