<?php
/**
 * Modal: Nuevo Elemento
 * Espera del index.php: $catalogoActivo, $catalogoInfo
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formNuevoElemento" novalidate>
  <div class="alert alert-danger d-none" data-form-error></div>

  <!-- Campo oculto con el catálogo activo -->
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">

  <div class="mb-3">
    <label for="nuevoElementoNombre" class="form-label">
      Nombre <span class="text-danger">*</span>
    </label>
    <input
      type="text"
      id="nuevoElementoNombre"
      name="nombre"
      class="form-control"
      required
      maxlength="50"
      autocomplete="off"
    >
    <div class="invalid-feedback">El nombre es obligatorio (máximo 100 caracteres).</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

// ---------- Pie del Modal ----------
ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formNuevoElemento" class="btn btn-primary">
  <span class="spinner-border spinner-border-sm d-none me-1" data-form-spinner aria-hidden="true"></span>
  Guardar
</button>
<?php
$modalFooter = ob_get_clean();

// ---------- Configuración del componente Modal ----------
$modalId       = 'modalNuevoElemento';
$modalTitle    = 'Nuevo elemento — ' . htmlspecialchars($catalogoInfo['nombre'] ?? '');
$modalSubtitle = 'Complete el nombre del nuevo elemento del catálogo.';

include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);