<?php
$formId = $formId ?? 'formNuevaAsignacion';
$editMode = $editMode ?? str_starts_with($formId, 'formEditar');
$modalId = $modalId ?? 'nuevaAsignacionModal';
$estructuras = $estructuras ?? [];
$docentes = $docentes ?? [];
$tiposAsignacion = $tiposAsignacion ?? [];
ob_start();
?>
<form id="<?= htmlspecialchars($formId) ?>" novalidate>
  <?php if ($editMode): ?><input type="hidden" name="id_estructura_academica" id="editarAsignacionEstructura"><?php endif; ?>
  <div class="form-section__label">Información de asignación</div>
  <?php if (!$editMode): ?><div class="mb-3"><label class="label-sigde" for="nuevoAsignacionSeccion">Sección académica</label><select name="id_estructura_academica" id="nuevoAsignacionSeccion" class="form-select" required><option value="">Seleccione una sección...</option><?php foreach ($estructuras as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['nombre']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="mb-3"><label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionDocente">Docente</label><select name="id_docente" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionDocente" class="form-select" required><option value="">Seleccione un docente...</option><?php foreach ($docentes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['nombre']) ?><?= $item['cargo'] ? ' · ' . htmlspecialchars($item['cargo']) : '' ?></option><?php endforeach; ?></select></div>
  <div class="mb-3"><label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionTipo">Tipo de asignación</label><select name="id_tipo_asignacion" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionTipo" class="form-select" required><option value="">Seleccione...</option><?php foreach ($tiposAsignacion as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['nombre']) ?></option><?php endforeach; ?></select></div>
  <div><label class="label-sigde" for="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionFecha">Fecha de inicio</label><input type="date" name="fecha_inicio" id="<?= $editMode ? 'editar' : 'nuevo' ?>AsignacionFecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="' . htmlspecialchars($formId) . '" class="btn btn-primary">Guardar asignación</button>';
include __DIR__ . '/../components/modal.php';
