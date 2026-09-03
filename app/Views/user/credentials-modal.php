<?php
/**
 * Modal: Credenciales Generadas
 * Se muestra tras crear el usuario. Usa modal-success, success-icon,
 * credentials-display y security-alert (_modal.scss).
 *
 * - string $credencialUsuario   nombre de usuario creado
 * - string $credencialPassword  contraseña provisional generada
 */
$credencialUsuario  = $credencialUsuario ?? '';
$credencialPassword = $credencialPassword ?? '';

ob_start();
?>
<div class="modal-success text-center">
  <div class="success-icon">
    <span class="success-icon__ring">
      <i class="bi bi-check-lg"></i>
    </span>
  </div>

  <p class="modal-success__title">¡Usuario Creado con Éxito!</p>
  <p class="modal-success__text">
    Se han generado las credenciales para el usuario.<br>
    Por favor, copie la contraseña provisional a continuación.
  </p>

  <p class="modal-success__user">
    Usuario: <strong id="credencialUsuarioSpan"><?php echo htmlspecialchars($credencialUsuario); ?></strong>
  </p>

  <div class="credentials-display mb-3">
    <p class="label-sigde text-center d-block mb-2">Contraseña Provisional</p>
    <div class="credentials-display__box">
      <span class="credentials-display__value" id="passwordProvisionalValue">
        <?php echo htmlspecialchars($credencialPassword); ?>
      </span>
      <button type="button" class="credentials-display__copy" id="btnCopiarPassword" data-clipboard-text="<?php echo htmlspecialchars($credencialPassword); ?>" aria-label="Copiar contraseña" title="Copiar al portapapeles">
        <i class="bi bi-clipboard"></i>
      </button>
    </div>
  </div>

  <div class="security-alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>Por seguridad, esta clave no volverá a mostrarse.</span>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

$modalId       = 'credencialesGeneradasModal';
$modalTitle    = 'Credenciales Generadas';
$modalSubtitle = 'Guarde la contraseña provisional antes de cerrar esta ventana.';
$modalSize     = 'lg';
$modalStatic   = true; // no se cierra al hacer click fuera: fuerza a copiar/leer la clave
$modalFooter   = '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>';

include __DIR__ . '/../components/modal.php';
?>