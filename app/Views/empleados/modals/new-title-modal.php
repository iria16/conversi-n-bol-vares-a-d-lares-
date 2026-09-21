<?php
/**
 * app/Views/staff/modals/new-title-modal.php   (antes: partials/modal-nuevo-titulo.php)
 * Alta rápida en el catálogo `titulo` desde el paso 5 del wizard.
 * La lógica de guardado (btnGuardarNuevoTitulo) vive en staff.js.
 */
ob_start();
?>
<label class="label-sigde" for="nuevoTituloNombre">Nombre del Título *</label>
<input type="text" class="form-control" id="nuevoTituloNombre" placeholder="Ej: Licenciado en Educación" required>
<div class="info-alert mt-3">
  <div class="info-alert__icon"><i class="bi bi-info-lg"></i></div>
  <p class="info-alert__text">Este título quedará disponible en el catálogo general para futuros registros.</p>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnGuardarNuevoTitulo">
  <i class="bi bi-check-lg"></i> Guardar Registro
</button>
<?php
$modalFooter = ob_get_clean();

$modalId    = 'modalNuevoTitulo';
$modalTitle = 'Registrar Nuevo Título';
$modalSize  = 'sm';
include __DIR__ . '/../../components/modal.php';