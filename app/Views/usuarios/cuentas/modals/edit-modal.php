<?php
/**
 * Modal: edit-modal.php
 * cuentas.js lo llena con los data-* de .btn-editar-usuario (data-id,
 * data-nombre-usuario, data-empleado, data-id-rol) y setea #euId
 * (hidden) para saber a quién actualizar en el submit.
 *
 * Nombres de campo alineados con CuentaUsuarioController::update():
 * id, nombre_usuario, rol_id.
 *
 * El campo "Empleado asociado" es de SOLO LECTURA: CuentaUsuarioModel::
 * update() solo modifica nombre_usuario e id_rol, no reasigna el
 * id_persona. Por eso va disabled (no se envía en el submit) y solo
 * se muestra como referencia visual.
 *
 * Espera del controlador: array $roles, cada uno ['id','nombre']
 * (mismo array que create-modal.php, viene de CuentaUsuarioModel::getRoles()).
 * @var array $roles
 */
ob_start();
?>
<form id="formEditarUsuario" novalidate>
  <input type="hidden" id="euId" name="id">

  <div class="mb-3">
    <label class="label-sigde" for="euEmpleado">Empleado asociado</label>
    <div class="input-icon-group">
      <i class="bi bi-person" aria-hidden="true"></i>
      <input type="text" class="form-control" id="euEmpleado" disabled aria-describedby="euEmpleadoHelp">
    </div>
    <div class="form-text" id="euEmpleadoHelp">El empleado asociado a la cuenta no se puede cambiar desde aquí.</div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <label class="label-sigde" for="euNombreUsuario">Usuario</label>
      <div class="input-icon-group">
        <i class="bi bi-person-badge" aria-hidden="true"></i>
        <input type="text" class="form-control" id="euNombreUsuario" name="nombre_usuario"
               required minlength="3" maxlength="50" pattern="[a-zA-Z0-9._]+" title="Letras, números, puntos y guiones bajos."
               autocomplete="off" aria-describedby="euNombreUsuarioHelp">
      </div>
      <div class="form-text" id="euNombreUsuarioHelp">Letras minúsculas, números, puntos y guiones bajos.</div>
      <div class="invalid-feedback">Usa de 3 a 50 caracteres: letras, números, puntos (.) o guiones bajos (_).</div>
    </div>

    <div class="col-md-6">
      <label class="label-sigde" for="euRol">Rol</label>
      <div class="input-icon-group">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <select class="form-select" id="euRol" name="rol_id" required>
          <option value="">Seleccione un rol</option>
          <?php foreach ($roles as $rol): ?>
            <option value="<?= (int) $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="invalid-feedback">Selecciona un rol.</div>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formEditarUsuario" class="btn btn-dark">Guardar cambios</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalEditarUsuario';
$modalTitle    = 'Editar Usuario';
$modalSubtitle = 'Actualiza el usuario y el rol de la cuenta.';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);