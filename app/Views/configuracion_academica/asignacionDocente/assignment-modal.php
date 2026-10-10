<?php
/**
 * Partial: assignment-modal.php
 * Modal crear/editar de la Asignación Docente (compartido entre los
 * modales nuevaAsignacionModal y editarAsignacionModal).
 *
 * Variables desde la vista index:
 * - $modalId, $modalTitle, $modalSubtitle, $formId
 * - $estructuras, $docentes, $tiposAsignacion ([id, nombre])
*/
$formId = $formId ?? 'formNuevaAsignacion';
$editMode = $editMode ?? str_starts_with($formId, 'formEditar');
$estructuras = $estructuras ?? [];
$docentes = $docentes ?? [];
$tiposAsignacion = $tiposAsignacion ?? [];
ob_start();
?>
<form id="<?= htmlspecialchars($formId) ?>" novalidate>
  <?php if ($editMode): ?>
    <input type="hidden" name="id_estructura_academica" id="editarAsignacionEstructura">
    <input type="hidden" name="id_asignacion" id="editarAsignacionId">
  <?php endif; ?>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Información de asignación</span>
    </div>
    <div class="wizard-summary__card">
      <?php if (!$editMode): ?>
        <div class="mb-3">
          <label class="label-sigde" for="nuevoAsignacionSeccion">Sección académica</label>
          <select name="id_estructura_academica" id="nuevoAsignacionSeccion" class="form-select" required>
            <option value="">Seleccione una sección...</option>
            <?php foreach ($estructuras as $item): ?>
              <option value="<?= (int) $item['id'] ?>" data-id-turno="<?= (int) $item['id_turno'] ?>"><?= htmlspecialchars($item['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Seleccione la sección académica.</div>
        </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionDocente">Docente</label>
        <select name="id_docente" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionDocente" class="form-select" required>
          <option value=""><?= $editMode ? 'Cargando docentes...' : 'Seleccione primero una sección...' ?></option>
        </select>
        <div class="invalid-feedback">Seleccione el docente.</div>
      </div>
      <div class="mb-3">
        <label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionTipo">Tipo de asignación</label>
        <select name="id_tipo_asignacion" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionTipo" class="form-select" required>
          <option value="">Seleccione...</option>
          <?php foreach ($tiposAsignacion as $item): ?>
            <option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">Seleccione el tipo de asignación.</div>
      </div>
      <div>
        <label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionFecha">Fecha de inicio</label>
        <input type="date" name="fecha_inicio" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionFecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
        <div class="invalid-feedback">Indique la fecha de inicio.</div>
      </div>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="' . htmlspecialchars($formId) . '" class="btn btn-primary">Guardar asignación</button>';
include __DIR__ . '/../../components/modal.php';
