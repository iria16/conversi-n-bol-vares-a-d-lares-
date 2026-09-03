<?php
/**
 * Vista: cambio-password.php
 * Ruta: app/Views/auth/cambio-password.php
 * Se usa en dos casos: cambio obligatorio de contraseña provisional
 * (primer ingreso) y restablecimiento de contraseña. Ambos comparten
 * el mismo formulario; el título/subtítulo podrían variar según el caso
 * más adelante, por ahora quedan fijos. El <form> envía a
 * auth/updatePassword (AuthController::updatePassword()).
 */

$assetsPath      = BASE_URL . 'assets';
$pageTitle       = 'Nueva Contraseña';
$pageDescription = 'Actualización de contraseña - Sistema SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Por su seguridad, actualice su contraseña provisional antes de continuar.';
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
      <section class="auth-card" aria-labelledby="resetTitle">
        <h1 id="resetTitle" class="auth-card__title">Nueva Contraseña</h1>
        <p class="auth-card__subtitle text-support">
          Por seguridad, debe actualizar su contraseña provisional para continuar con el acceso a su cuenta.
        </p>

        <form novalidate class="needs-validation" autocomplete="off" method="post" action="<?php echo BASE_URL; ?>auth/updatePassword">

          <div class="mb-3">
            <label for="nuevaPassword" class="label-sigde">Nueva contraseña</label>
            <div class="input-icon-group">
              <i class="bi bi-lock" aria-hidden="true"></i>
              <input
                type="password"
                class="form-control"
                id="nuevaPassword"
                name="nueva_password"
                autocomplete="new-password"
                minlength="8"
                maxlength="20"
                pattern="(?=.*[a-zA-Z])(?=.*[0-9]).{8,20}"
                required
                data-password-field
                data-password-requirements-target
                aria-describedby="nuevaPasswordError"
              >
              <button type="button" class="input-icon-group__toggle" data-password-toggle aria-label="Mostrar contraseña">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div id="nuevaPasswordError" class="invalid-feedback">
              La contraseña debe cumplir todos los requisitos de seguridad.
            </div>
          </div>

          <div class="mb-3">
            <label for="confirmarPassword" class="label-sigde">Confirmar nueva contraseña</label>
            <div class="input-icon-group">
              <i class="bi bi-lock" aria-hidden="true"></i>
              <input
                type="password"
                class="form-control"
                id="confirmarPassword"
                name="confirmar_password"
                autocomplete="new-password"
                minlength="8"
                maxlength="20"
                pattern="(?=.*[a-zA-Z])(?=.*[0-9]).{8,20}"
                required
                data-password-field
                data-match-target="#nuevaPassword"
                aria-describedby="confirmarPasswordError"
              >
              <button type="button" class="input-icon-group__toggle" data-password-toggle aria-label="Mostrar contraseña">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div id="confirmarPasswordError" class="invalid-feedback">
              Las contraseñas no coinciden.
            </div>
          </div>

          <p class="label-sigde mb-2">Requisitos de seguridad:</p>
          <ul class="password-requirements list-unstyled" data-password-requirements>
            <li data-requirement="length">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
              Mínimo 8 caracteres
            </li>
            <li data-requirement="letter">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
              Al menos una letra
            </li>
            <li data-requirement="number">
              <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
              Al menos un número
            </li>
          </ul>

          <button type="submit" class="btn btn-primary w-100 text-uppercase" disabled>
            Guardar Contraseña
            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
          </button>
        </form>

        <p class="text-center mt-4 mb-0">
          <a href="<?php echo BASE_URL; ?>auth/logout" class="link-sigde">
            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>
            Cerrar sesión
          </a>
        </p>
      </section>

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