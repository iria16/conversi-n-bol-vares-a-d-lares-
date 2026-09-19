<?php
/**
 * Modal: Editar Elemento
 * Se llena desde JS (catalogo.js) con los data-elemento-id /
 * data-elemento-nombre del botón "editar" presionado.
 * Espera del index.php: $catalogoActivo, $catalogoInfo
 */

// ---------- Cuerpo del Modal ----------
ob_start();
?>
<form id="formEditarElemento" novalidate>
  <div class="alert alert-danger d-none" data-form-error></div>

  <!-- Campos ocultos requeridos para la actualización -->
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">
  <input type="hidden" name="id" id="editarElementoId">

  <div class="mb-3">
    <label for="editarElementoNombre" class="form-label">
      Nombre <span class="text-danger">*</span>
    </label>
    <input
      type="text"
      id="editarElementoNombre"
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
<button type="submit" form="formEditarElemento" class="btn btn-primary">
  <span class="spinner-border spinner-border-sm d-none me-1" data-form-spinner aria-hidden="true"></span>
  Guardar cambios
</button>
<?php
$modalFooter = ob_get_clean();

// ---------- Configuración del componente Modal ----------
$modalId       = 'modalEditarElemento';
$modalTitle    = 'Editar elemento — ' . htmlspecialchars($catalogoInfo['nombre'] ?? '');
$modalSubtitle = 'Modifique la información del registro seleccionado.';

include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);