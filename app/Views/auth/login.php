<?php
/**
 * Vista: login.php
 * Ruta: app/Views/auth/login.php
 * Pantalla de inicio de sesión (split screen). El <form> envía a
 * auth/authenticate (AuthController); el JS/CSS acá es puramente de
 * presentación.
 */

$assetsPath      = BASE_URL . 'assets';
$pageTitle       = 'Iniciar Sesión';
$pageDescription = 'Acceso al sistema de gestión escolar SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Excelencia en gestión académica para la formación del futuro';
$authFeatures    = [
  ['icon' => 'bi-people',         'label' => 'Personal'],
  ['icon' => 'bi-mortarboard',    'label' => 'Estudiantes'],
  ['icon' => 'bi-clipboard-data', 'label' => 'Reportes'],
];

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
        <section class="auth-card" aria-labelledby="loginTitle">
          <h1 id="loginTitle" class="auth-card__title">Iniciar Sesión</h1>
          <p class="auth-card__subtitle text-support">
            Bienvenido al ecosistema SIGDE. Por favor, identifíquese para continuar.
          </p>

          <form novalidate class="login-form needs-validation" autocomplete="on" method="post" action="<?php echo BASE_URL; ?>auth/authenticate">

            <div class="mb-3">
              <label for="usuario" class="label-sigde">Usuario</label>
              <div class="input-icon-group">
                <i class="bi bi-person" aria-hidden="true"></i>
                <input
                  type="text"
                  class="form-control"
                  id="usuario"
                  name="usuario"
                  placeholder="Ingrese su USUARIO"
                  value="<?php echo htmlspecialchars($_SESSION['last_usuario'] ?? ''); ?>"
                  autocomplete="username"
                  maxlength="20"
                  required
                  aria-describedby="usuarioError"
                >
              </div>
              <div id="usuarioError" class="invalid-feedback">
                Ingrese su usuario para continuar.
              </div>
            </div>

            <div class="mb-2">
              <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="label-sigde mb-0">Contraseña</label>
                <a href="<?php echo BASE_URL; ?>auth/recoverAccess" class="link-sigde">¿Olvidó su contraseña?</a>
              </div>
              <div class="input-icon-group mt-1">
                <i class="bi bi-lock" aria-hidden="true"></i>
                <input
                  type="password"
                  class="form-control"
                  id="password"
                  name="password"
                  placeholder="••••••••••"
                  autocomplete="current-password"
                  maxlength="20"
                  required
                  data-password-field
                  aria-describedby="passwordError"
                >
                <button
                  type="button"
                  class="input-icon-group__toggle"
                  data-password-toggle
                  aria-label="Mostrar contraseña"
                >
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div id="passwordError" class="invalid-feedback">
                Ingrese su contraseña para continuar.
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-4 text-uppercase" disabled>
              Ingresar al Sistema
              <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>
          </form>

          <p class="text-support text-center mt-4 mb-0" style="font-size: 0.78rem;">
            El acceso al sistema es otorgado por el administrador institucional
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

  <?php
  $extraScripts = ['/js/login.js'];
  include __DIR__ . '/../partials/scripts.php';
  ?>

</body>
</html>