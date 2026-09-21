<?php
/**
 * app/Views/staff/modals/new-institution-modal.php   (antes: partials/modal-nueva-institucion.php)
 * Alta rápida en el catálogo `institucion` desde el paso 5 del wizard.
 * La lógica de guardado (btnGuardarNuevaInstitucion) vive en staff.js.
 *
 * Variables en scope: $tiposInstitucion (tabla `tipo_institucion`)
 */
ob_start();
?>
<div class="mb-3">
  <label class="label-sigde" for="nuevaInstitucionNombre">Nombre de la Institución *</label>
  <input type="text" class="form-control" id="nuevaInstitucionNombre" placeholder="Ej: Universidad Central de Venezuela" required>
</div>
<div>
  <label class="label-sigde" for="nuevaInstitucionTipo">Tipo de Institución *</label>
  <select class="form-select" id="nuevaInstitucionTipo" required>
    <option value="">Seleccione el tipo...</option>
    <?php foreach ($tiposInstitucion as $tipo): ?>
      <option value="<?= (int) $tipo['id_tipo_institucion'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnGuardarNuevaInstitucion">
  <i class="bi bi-check-lg"></i> Guardar Registro
</button>
<?php
$modalFooter = ob_get_clean();

$modalId    = 'modalNuevaInstitucion';
$modalTitle = 'Registrar Nueva Institución';
$modalSize  = 'sm';
include __DIR__ . '/../../components/modal.php';