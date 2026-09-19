<?php
/**
 * Modal: create-modal.php
 * Formulario para crear un nuevo usuario a partir de un EMPLEADO YA
 * EXISTENTE que todavía no tiene cuenta (no se crea una persona nueva
 * aquí). El submit lo maneja cuentas.js (fetch a store); si tiene
 * éxito, cierra este modal y abre credentials-modal.php con la
 * contraseña provisional.
 *
 * Espera del controlador:
 * - array $empleadosDisponibles  cada uno ['id','nombre'] (CuentaUsuarioModel::getEmpleadosDisponibles())
 * - array $roles                 cada uno ['id','nombre'] (CuentaUsuarioModel::getRoles())
 *
 * Nombres de campo alineados con CuentaUsuarioController::store():
 * empleado_id, usuario, rol_id.
 * @var array $empleadosDisponibles
 * @var array $roles
 */
$hayEmpleadosDisponibles = !empty($empleadosDisponibles);

ob_start();
?>
<form id="formCrearUsuario" novalidate>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <p class="form-section__label mb-0">Datos del empleado</p>
    <?php if ($hayEmpleadosDisponibles): ?>
      <span class="status-badge status-badge--info">
        <?= count($empleadosDisponibles) ?> disponible<?= count($empleadosDisponibles) === 1 ? '' : 's' ?>
      </span>
    <?php endif; ?>
  </div>

  <div class="mb-3">
    <label class="label-sigde" for="nuEmpleado">Empleado</label>
    <div class="input-icon-group">
      <i class="bi bi-person" aria-hidden="true"></i>
      <select class="form-select" id="nuEmpleado" name="empleado_id" required <?= $hayEmpleadosDisponibles ? '' : 'disabled' ?>>
        <option value="">Seleccione un empleado</option>
        <?php foreach ($empleadosDisponibles as $empleado): ?>
          <option value="<?= (int) $empleado['id'] ?>"><?= htmlspecialchars($empleado['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!$hayEmpleadosDisponibles): ?>
      <div class="form-text text-danger">No hay empleados disponibles: todos los empleados activos ya tienen una cuenta.</div>
    <?php endif; ?>
  </div>

  <p class="form-section__label mt-4">Credenciales de acceso</p>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="label-sigde" for="nuUsuario">Usuario</label>
      <div class="input-icon-group">
        <i class="bi bi-person-badge" aria-hidden="true"></i>
        <input type="text" class="form-control" id="nuUsuario" name="usuario" required minlength="3" maxlength="50"
               pattern="[a-z0-9._]+" title="Solo letras minúsculas, números, puntos y guiones bajos" <?= $hayEmpleadosDisponibles ? '' : 'disabled' ?>>
      </div>
      <div class="form-text">Letras minúsculas, números, puntos y guiones bajos.</div>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="nuRol">Rol</label>
      <div class="input-icon-group">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <select class="form-select" id="nuRol" name="rol_id" required>
          <option value="">Seleccione un rol</option>
          <?php foreach ($roles as $rol): ?>
            <option value="<?= (int) $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="info-alert mt-4">
    <span class="info-alert__icon"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
    <div>
      <p class="info-alert__title mb-0">Contraseña generada automáticamente</p>
      <p class="info-alert__text">El sistema creará una contraseña provisional y se mostrará una sola vez al confirmar.</p>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formCrearUsuario" class="btn btn-dark" <?= $hayEmpleadosDisponibles ? '' : 'disabled' ?>>Crear usuario</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalCrearUsuario';
$modalTitle    = 'Crear Usuario';
$modalSubtitle = 'Selecciona un empleado sin cuenta y asigna sus credenciales de acceso.';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $hayEmpleadosDisponibles);