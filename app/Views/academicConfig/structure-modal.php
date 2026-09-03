<?php
$editMode = $editMode ?? false;
$formId = $formId ?? 'formNuevaEstructura';
$anios = $anios ?? [];
$grados = $grados ?? [];
$secciones = $secciones ?? [];
$turnos = $turnos ?? [];
$prefix = $editMode ? 'editar' : 'nuevo';
ob_start();
?>
<form id="<?= htmlspecialchars($formId) ?>" novalidate>
  <?php if ($editMode): ?><input type="hidden" name="id" id="editarEstructuraId"><?php endif; ?>
  <div class="form-section__label">Configuración académica</div>
  <div class="row g-3">
    <?php foreach ([['anio','Año escolar',$anios],['grado','Grado',$grados],['seccion','Sección',$secciones],['turno','Turno',$turnos]] as $field): ?>
      <div class="col-md-6"><label class="label-sigde" for="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>"><?= $field[1] ?></label><select class="form-select" name="id_<?= $field[0] === 'anio' ? 'anio_escolar' : $field[0] ?>" id="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>" required><option value="">Seleccione...</option><?php foreach ($field[2] as $option): ?><option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option><?php endforeach; ?></select><div class="invalid-feedback">Seleccione <?= strtolower($field[1]) ?>.</div></div>
    <?php endforeach; ?>
    <div class="col-md-6"><label class="label-sigde" for="<?= $prefix ?>EstructuraCapacidad">Capacidad máxima</label><input type="number" name="capacidad_maxima" id="<?= $prefix ?>EstructuraCapacidad" class="form-control" min="1" max="999" required><div class="invalid-feedback">Indique la capacidad.</div></div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="' . htmlspecialchars($formId) . '" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../components/modal.php';
