<?php
/**
 * Vista: recuperar-acceso.php
 * Ruta: app/Views/auth/recuperar-acceso.php
 * Pantalla de solicitud de recuperación de acceso (split screen). El
 * <form> envía a auth/requestRecovery (AuthController); el mensaje de
 * éxito/error llega vía SweetAlert desde mostrarSwalYRedirigir(). El
 * cascarón lo provee layouts/auth.php.
 */

$pageTitle       = 'Recuperar Acceso';
$pageDescription = 'Solicitud de recuperación de acceso al sistema SIGDE - E.B. Isidro Ramírez.';
$authTagline     = 'Su solicitud será atendida por el administrador del sistema a la brevedad posible.';
$authCardAriaLabelledby = 'recoverTitle';

ob_start();
?>
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
</form>

<p class="text-center mt-4 mb-0">
  <a href="<?php echo BASE_URL; ?>auth/login" class="link-sigde">
    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
    Volver al inicio de sesión
  </a>
</p>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/auth.php';