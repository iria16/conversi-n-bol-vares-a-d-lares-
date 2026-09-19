<?php
/**
 * Vista: recuperar-acceso.php
 * Ruta: app/Views/auth/recuperar-acceso.php
 * Pantalla de solicitud de recuperación de acceso (split screen). El
 * <form> envía a auth/requestRecovery (AuthController); el mensaje de
 * éxito/error llega vía SweetAlert desde mostrarSwalYRedirigir().
 */

$assetsPath      = BASE_URL . 'assets';
$pageTitle       = 'Recuperar Acceso';
$pageDescription = 'Solicitud de recuperación de acceso al sistema SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Su solicitud será atendida por el administrador del sistema a la brevedad posible.';
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
        <section class="auth-card" aria-labelledby="recoverTitle">
        <h1 id="recoverTitle" class="auth-card__title">Recuperar Acceso</h1>
        <p class="auth-card__subtitle text-support">
          Ingrese su usuario o cédula y notificaremos al administrador.
        </p>

        <form novalidate class="needs-validation" autocomplete="on" method="post" action="<?php echo BASE_URL; ?>auth/requestRecovery">

          <div class="mb-3">
            <label for="usuarioCedula" class="label-sigde">Usuario o cédula</label>
            <div class="input-icon-group">
              <i class="bi bi-person-vcard" aria-hidden="true"></i>
              <input
                type="text"
                class="form-control"
                id="usuarioCedula"
                name="usuario_cedula"
                placeholder="Ingrese su usuario o cédula de identidad"
                autocomplete="username"
                maxlength="20"
                required
                aria-describedby="usuarioCedulaError"
              >
            </div>
            <div id="usuarioCedulaError" class="invalid-feedback">
              Ingrese un usuario o cédula válidos (mínimo 4 caracteres).
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 mt-4 text-uppercase" disabled>
            Enviar Solicitud
          </button>

          <div class="auth-alert" id="recoverError" role="alert" aria-live="polite">
            <span class="auth-alert__icon" aria-hidden="true">
              <i class="bi bi-x-lg"></i>
            </span>
            <span class="auth-alert__text">
              No encontramos ese usuario o cédula. Verifique e intente de nuevo.
            </span>
          </div>
        </form>

        <p class="text-center mt-4 mb-0">
          <a href="<?php echo BASE_URL; ?>auth/login" class="link-sigde">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Volver al inicio de sesión
          </a>
        </p>
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