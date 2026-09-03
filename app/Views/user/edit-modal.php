<?php
/**
 * Modal: Editar Usuario
 * Se puebla vía JS antes de abrir el modal.
 * Envía los cambios vía AJAX a userAccount/updateAjax
 * Usa components/modal.php + clases de _modal.scss / _form.scss / _status-badge.scss
 *
 * Campos editables:
 * - nombre_usuario  (texto)
 * - rol_id          (select con $rolesSistema inyectados por el controlador)
 *
 * Variables esperadas en scope (inyectadas por cuentas.php / index()):
 * - array $rolesSistema  [ ['id' => int, 'nombre' => string], ... ]
 */
$rolesSistema = $rolesSistema ?? [];

ob_start();
?>
<form id="formEditarUsuario" novalidate>
  <!-- ID oculto del usuario que se está editando -->
  <input type="hidden" id="editUsuario__id" name="id" value="">

  <div class="form-section__label">Información de cuenta</div>

  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <label for="editUsuario__empleado" class="label-sigde mb-0">Nombre del empleado</label>
        <span class="status-badge status-badge--pending">
          <i class="bi bi-lock-fill"></i> Bloqueado
        </span>
      </div>
      <div class="input-icon-group">
        <i class="bi bi-briefcase"></i>
        <input
          type="text"
          class="form-control"
          id="editUsuario__empleado"
          value=""
          disabled
          aria-label="Empleado (no editable)"
        >
      </div>
    </div>

    <div class="col-md-6">
      <label for="editUsuario__nombreUsuario" class="label-sigde">Nombre de usuario</label>
      <div class="input-icon-group">
        <i class="bi bi-person"></i>
        <input
          type="text"
          class="form-control"
          id="editUsuario__nombreUsuario"
          name="nombre_usuario"
          placeholder="ej. mgarcia"
          required
          minlength="3"
          maxlength="50"
          pattern="[a-zA-Z0-9._\-]+"
          autocomplete="off"
        >
      </div>
      <div class="form-text">
        * El nombre de usuario solo puede modificarse si no existen registros asociados en el sistema.
      </div>
      <div class="invalid-feedback">
        Ingrese un nombre de usuario válido (solo letras, números, puntos y guiones, mínimo 3 caracteres).
      </div>
    </div>
  </div>

  <div class="mb-1">
    <label for="editUsuario__rol" class="label-sigde">Rol de sistema</label>
    <div class="input-icon-group">
      <i class="bi bi-shield-check"></i>
      <select class="form-select" id="editUsuario__rol" name="rol_id" required>
        <option value="" disabled>Seleccione rol...</option>
        <?php foreach ($rolesSistema as $rol): ?>
          <option value="<?= htmlspecialchars((string) $rol['id']) ?>">
            <?= htmlspecialchars($rol['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="invalid-feedback">Seleccione un rol.</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

$modalId       = 'editarUsuarioModal';
$modalTitle    = 'Editar Usuario';
$modalSubtitle = 'Control de Credenciales';
$modalSize     = 'lg';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" form="formEditarUsuario" id="btnGuardarEdicion" class="btn btn-primary">
                    <i class="bi bi-floppy me-1"></i>Guardar cambios
                  </button>';

include __DIR__ . '/../components/modal.php';
?>