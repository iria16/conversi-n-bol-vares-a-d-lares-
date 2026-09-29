<?php
/**
 * Partial: structure-modal.php
 * Modal crear/editar de la Estructura Académica (compartido entre los
 * modales nuevaEstructuraModal y editarEstructuraModal).
 *
 * Variables desde la vista index:
 * - $modalId, $modalTitle, $modalSubtitle, $formId, $editMode
 * - $anios, $grados, $secciones, $turnos (catálogos [id, nombre])
 */
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

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Configuración académica</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <?php foreach ([['anio', 'Año escolar', $anios], ['grado', 'Grado', $grados], ['seccion', 'Sección', $secciones], ['turno', 'Turno', $turnos]] as $field): ?>
          <div class="col-6">
            <label class="label-sigde" for="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>"><?= $field[1] ?></label>
            <select
              class="form-select"
              name="id_<?= $field[0] === 'anio' ? 'anio_escolar' : $field[0] ?>"
              id="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>"
              required
            >
              <option value="">Seleccione...</option>
              <?php foreach ($field[2] as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Seleccione <?= strtolower($field[1]) ?>.</div>
          </div>
        <?php endforeach; ?>
        <div class="col-6">
          <label class="label-sigde" for="<?= $prefix ?>EstructuraCapacidad">Capacidad máxima</label>
          <input type="number" name="capacidad_maxima" id="<?= $prefix ?>EstructuraCapacidad" class="form-control" min="1" max="60" required>
          <div class="invalid-feedback">Indique la capacidad (1 a 60).</div>
        </div>
      </div>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="' . htmlspecialchars($formId) . '" class="btn btn-primary">Guardar</button>';
include __DIR__ . '/../../components/modal.php';
