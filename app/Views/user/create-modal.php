<?php
/**
 * Modal: Crear Nuevo Usuario
 * Usa components/modal.php + input-icon-group (_input-icon-group.scss)
 *
 * Variables esperadas en scope (inyectadas por el controlador desde index()):
 * - array $empleadosDisponibles  [ ['id' => int, 'nombre' => string], ... ]
 * - array $rolesSistema          [ ['id' => int, 'nombre' => string], ... ]
 */
$empleadosDisponibles = $empleadosDisponibles ?? [];
$rolesSistema         = $rolesSistema         ?? [];

ob_start();
?>
<form id="formCrearUsuario" novalidate>

  <div class="form-section__label">Información de cuenta</div>

  <div class="mb-3">
    <label for="crearUsuario__nombre" class="label-sigde">Nombre de usuario</label>
    <div class="input-icon-group">
      <i class="bi bi-person"></i>
      <input
        type="text"
        class="form-control"
        id="crearUsuario__nombre"
        name="usuario"
        placeholder="ej. mgarcia"
        required
        minlength="3"
        maxlength="50"
        pattern="[a-zA-Z0-9._\-]+"
        autocomplete="off"
      >
    </div>
    <div class="invalid-feedback">
      Ingrese un nombre de usuario válido (solo letras, números, puntos y guiones, mínimo 3 caracteres).
    </div>
  </div>

  <div class="mb-3">
    <label for="crearUsuario__empleado" class="label-sigde">Empleado</label>
    <div class="input-icon-group">
      <i class="bi bi-briefcase"></i>
      <select class="form-select" id="crearUsuario__empleado" name="empleado_id" required>
        <option value="" selected disabled>Seleccione empleado...</option>
        <?php foreach ($empleadosDisponibles as $empleado): ?>
          <option value="<?= htmlspecialchars((string) $empleado['id']) ?>">
            <?= htmlspecialchars($empleado['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="invalid-feedback">Seleccione un empleado.</div>
  </div>

  <div class="mb-4">
    <label for="crearUsuario__rol" class="label-sigde">Rol de sistema</label>
    <div class="input-icon-group">
      <i class="bi bi-shield-check"></i>
      <select class="form-select" id="crearUsuario__rol" name="rol_id" required>
        <option value="" selected disabled>Seleccione rol...</option>
        <?php foreach ($rolesSistema as $rol): ?>
          <option value="<?= htmlspecialchars((string) $rol['id']) ?>">
            <?= htmlspecialchars($rol['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="invalid-feedback">Seleccione un rol.</div>
  </div>

  <div class="form-section__label">Seguridad</div>

  <div class="info-alert">
    <span class="info-alert__icon"><i class="bi bi-info-circle-fill"></i></span>
    <div>
      <p class="info-alert__title">Generación automática de contraseña</p>
      <p class="info-alert__text">
        El sistema generará una contraseña provisional segura para este usuario.
        Las credenciales se mostrarán en pantalla al completar el registro y
        no volverán a mostrarse por motivos de seguridad.
      </p>
    </div>
  </div>

</form>
<?php
$modalBody = ob_get_clean();

$modalId       = 'crearUsuarioModal';
$modalTitle    = 'Crear Nuevo Usuario';
$modalSubtitle = 'Asigne credenciales de acceso a un empleado existente.';
$modalSize     = 'lg';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" form="formCrearUsuario" id="btnGuardarUsuario" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Crear usuario
                  </button>';

include __DIR__ . '/../components/modal.php';
?>
