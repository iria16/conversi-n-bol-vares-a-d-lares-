<?php
/**
 * Vista: login.php
 * Ruta: app/Views/auth/login.php
 * Pantalla de inicio de sesión (split screen). El <form> envía a
 * auth/authenticate (AuthController); el JS/CSS acá es puramente de
 * presentación. El cascarón lo provee layouts/auth.php.
 */

$pageTitle       = 'Iniciar Sesión';
$pageDescription = 'Acceso al sistema de gestión escolar SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Excelencia en gestión académica para la formación del futuro';
$authFeatures    = [
  ['icon' => 'bi-people',         'label' => 'Personal'],
  ['icon' => 'bi-mortarboard',    'label' => 'Estudiantes'],
  ['icon' => 'bi-clipboard-data', 'label' => 'Reportes'],
];
$authCardAriaLabelledby = 'loginTitle';

ob_start();
?>
<h1 id="loginTitle" class="auth-card__title">Iniciar Sesión</h1>
<p class="auth-card__subtitle text-support">
  Bienvenido al ecosistema SIGDE. Por favor, identifíquese para continuar.
</p>

<?php /* autocomplete="off" en el <form> y "new-password" en la clave: el
         navegador deja de ofrecer guardar la contraseña y deja de RELLENARLA al
         volver. Con autocomplete="on" / "current-password", una secretaria
         volvía a entrar creyendo que no le habían pedido nada, porque el
         navegador le escribía la clave. Para una app administrativa interna es lo
         correcto: la contraseña no debe quedarse guardada en el equipo.
         (El campo de usuario conserva autocomplete="username": el nombre de
         usuario no es un secreto y ahorra trabajo.)

         Antes esto era "one-time-code", que también frenaba el autocompletado. Se
         cambió por "new-password" por un motivo concreto: el navegador pinta su
         PROPIO botón de ojo para revelar la clave en los campos `type=password`, y
         con "one-time-code" lo seguía mostrando. Quedaban dos ojos, el que está
         hecho a mano y el del navegador, y el segundo no es del formulario.

         "new-password" da lo mismo en privacidad —Chrome no lo autocompleta ni
         ofrece guardarlo— y además no dibuja su ojo, porque para el navegador un
         campo marcado así es una clave que se está creando, no una que se está
         recuperando. Es el mismo valor que ya usan los campos de
         `auth/cambio-password.php`, así que no se inventó un valor raro.

         Ojo: esto depende de que el navegador respete el valor. Si en algún
         equipo siguiera apareciendo el ojo nativo, no hay forma de esconderlo con
         CSS: Chrome no expone ningún pseudo-elemento para ese botón. La salida
         sería dejar el campo como `type="text"` con la clave enmascarada por
         JavaScript, que ya es lo que hace el toggle del formulario. */ ?>
<form novalidate class="login-form needs-validation" autocomplete="off" method="post" action="<?php echo BASE_URL; ?>auth/authenticate">

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
        autocomplete="new-password"
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
<?php
$pageContent = ob_get_clean();
$extraScripts = ['/js/login.js'];
include __DIR__ . '/../layouts/auth.php';