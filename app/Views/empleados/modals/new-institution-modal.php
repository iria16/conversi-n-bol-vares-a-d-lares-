<?php

/**
 * app/Views/empleados/modals/new-institution-modal.php
 * Alta rápida en el catálogo `institucion` desde el paso 5 del wizard.
 * La lógica de guardado (btnGuardarNuevaInstitucion) vive en empleado.js.
 *
 * Variables en scope: $tiposInstitucion (App\Support\Instituciones: aquí solo
 * TIPO_TITULO_ACADEMICO, porque este módulo registra dónde se obtuvo el título)
 */
ob_start();
?>
<div class="mb-3">
  <label class="label-sigde" for="nuevaInstitucionTipo">Tipo de Institución *</label>
  <select class="form-select" id="nuevaInstitucionTipo" required>
    <option value="">Seleccione el tipo...</option>
    <?php foreach ($tiposInstitucion as $tipo): ?>
      <option value="<?= htmlspecialchars($tipo['tipo']) ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div>
  <label class="label-sigde" for="nuevaInstitucionNombre">Nombre de la Institución *</label>
  <input type="text" class="form-control" id="nuevaInstitucionNombre" placeholder="Ej: Universidad Central de Venezuela" required>
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

$modalId       = 'modalNuevaInstitucion';
$modalTitle    = 'Registrar Nueva Institución';
$modalSubtitle = 'Registre una institución educativa o depositaria nueva.';
/* Mismo ancho que «Registrar Nuevo Título» (500px): los dos se abren desde
   el mismo modal del paso 5 y tienen la misma estructura de dos campos. */
$modalSize     = '';
include __DIR__ . '/../../components/modal.php';