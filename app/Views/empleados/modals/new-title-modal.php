<?php

/**
 * app/Views/empleados/modals/new-title-modal.php
 * Alta rápida en el catálogo `titulo` desde el paso 5 del wizard.
 * La lógica de guardado (btnGuardarNuevoTitulo) vive en empleado.js.
 *
 * Como `titulo.id_grado_academico` es NOT NULL, todo título nuevo debe
 * pertenecer a un grado académico: se elige aquí (empleado.js lo prellena
 * con el grado seleccionado en #gradoAgregar).
 *
 * Variables en scope: $gradosAcademicos (tabla `grado_academico`)
 */
ob_start();
?>
<div class="mb-3">
  <label class="label-sigde" for="nuevoTituloGrado">Grado académico *</label>
  <select class="form-select" id="nuevoTituloGrado" required>
    <option value="">Seleccione el grado...</option>
    <?php foreach ($gradosAcademicos as $grado): ?>
      <option value="<?= (int) $grado['id_grado_academico'] ?>"><?= htmlspecialchars($grado['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div>
  <label class="label-sigde" for="nuevoTituloNombre">Nombre del Título *</label>
  <input
    type="text"
    class="form-control"
    id="nuevoTituloNombre"
    placeholder="Ej: Licenciado en Educación"
    required>
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

$modalId       = 'modalNuevoTitulo';
$modalTitle    = 'Registrar Nuevo Título';
$modalSubtitle = 'Registre un título académico reutilizable en los expedientes.';
$modalSize     = '';
include __DIR__ . '/../../components/modal.php';