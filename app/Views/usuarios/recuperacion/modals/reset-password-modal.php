<?php
/**
 * Modal: reset-password-modal.php
 * Restablece la clave de CUALQUIER usuario existente, sin pasar por una
 * solicitud de recuperación previa (a diferencia de aprobar-solicitud,
 * que resuelve una solicitud ya creada por el propio empleado).
 *
 * El submit lo maneja recuperacion.js (fetch a resetPassword); si tiene
 * éxito, cierra este modal y abre credentials-modal.php con la
 * contraseña provisional generada — el mismo modal que usa el flujo de
 * aprobar-solicitud.
 *
 * Espera del controlador (vía index.php):
 * - array $usuariosDisponibles  cada uno ['id','nombre','usuario']
 * @var array $usuariosDisponibles
 */
$hayUsuariosDisponibles = !empty($usuariosDisponibles);

ob_start();
?>
<form id="formRestablecerClave" novalidate>

  <div class="form-section__label">Información de cuenta</div>

  <div class="mb-4">
    <label for="restablecerClave__usuario" class="label-sigde">Seleccione el usuario</label>
    <div class="input-icon-group">
      <i class="bi bi-person" aria-hidden="true"></i>
      <select class="form-select" id="restablecerClave__usuario" name="usuario_id" required <?= $hayUsuariosDisponibles ? '' : 'disabled' ?>>
        <option value="" selected disabled>Seleccionar usuario</option>
        <?php foreach ($usuariosDisponibles as $usuario): ?>
          <option value="<?= (int) $usuario['id'] ?>">
            <?= htmlspecialchars($usuario['nombre'] . ' · ' . $usuario['usuario']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($hayUsuariosDisponibles): ?>
      <div class="invalid-feedback">Seleccione un usuario.</div>
    <?php else: ?>
      <div class="form-text text-danger">No hay usuarios disponibles para restablecer contraseña.</div>
    <?php endif; ?>
  </div>

  <div class="form-section__label">Seguridad</div>
  <div class="info-alert">
    <span class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
    <div>
      <p class="info-alert__title">Generación automática</p>
      <p class="info-alert__text">
        El sistema generará una clave provisional de forma automática.
        Esta solo se mostrará una vez al confirmar la acción por motivos de seguridad.
      </p>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formRestablecerClave" id="btnRestablecerClave" class="btn btn-primary" <?= $hayUsuariosDisponibles ? '' : 'disabled' ?>>
  <i class="bi bi-key-fill me-1" aria-hidden="true"></i>Restablecer clave
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'restablecerClaveModal';
$modalTitle    = 'Restablecer Contraseña';
$modalSubtitle = 'Genere una nueva clave provisional para el usuario seleccionado.';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $hayUsuariosDisponibles);