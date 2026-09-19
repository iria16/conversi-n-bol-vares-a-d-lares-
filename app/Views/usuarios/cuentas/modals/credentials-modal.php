<?php
/**
 * Modal: credentials-modal.php
 * Se muestra después de crear un usuario con éxito. cuentas.js llena
 * #credUsuarioNombre y #credPassword vía JS con la respuesta del fetch
 * (nombre del empleado y password_provisional).
 * El usuario NO se muestra aquí: ya lo escribió el propio administrador
 * en el formulario de creación, no es un dato generado por el servidor.
 */
ob_start();
?>
<div class="modal-success text-center">
  <div class="success-icon">
    <span class="success-icon__ring"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
  </div>
  <p class="modal-success__title">¡Usuario creado con éxito!</p>
  <p class="modal-success__text">
    Se generaron las credenciales para <strong id="credUsuarioNombre" aria-live="polite"></strong>.
    Copia la contraseña provisional a continuación.
  </p>

  <div class="credentials-display mb-3">
    <label class="label-sigde d-block text-center">Contraseña provisional</label>
    <div class="credentials-display__box">
      <span class="credentials-display__value" id="credPassword" aria-live="polite"></span>
      <button type="button" class="credentials-display__copy" data-copy-target="credPassword" aria-label="Copiar contraseña">
        <i class="bi bi-clipboard" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  <div class="security-alert">
    <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
    <span>Por seguridad, esta clave no volverá a mostrarse.</span>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-dark" data-bs-dismiss="modal">Entendido</button>
<?php
$modalFooter = ob_get_clean();

$modalId     = 'modalCredencialesUsuario';
$modalTitle  = 'Credenciales generadas';
$modalStatic = true; // no se cierra al hacer click fuera: evita perder la contraseña sin querer
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalStatic);