<?php

/**
 * Paso 4 - Información laboral.
 * Variable en scope: $cargos (tabla `cargo`).
 * Nota de diseño: la BD aún no tiene tabla de Departamento/Área de adscripción;
 * cuando exista, el <select> nuevo va en este paso (no en la interfaz de usuario).
 */
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información laboral</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="label-sigde" for="cargoAsignado">Cargo Asignado *</label>
        <select class="form-select" id="cargoAsignado" name="id_cargo" required>
          <option value="">Seleccione el cargo...</option>
          <?php foreach ($cargos as $cargo): ?>
            <option value="<?= (int) $cargo['id_cargo'] ?>"><?= htmlspecialchars($cargo['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="fechaIngreso">Fecha de Ingreso *</label>
        <input
          type="date"
          class="form-control"
          id="fechaIngreso"
          name="fecha_ingreso"
          min="1980-01-01"
          max="<?= date('Y-m-d') ?>"
          required>
      </div>
    </div>
  </div>
</section>