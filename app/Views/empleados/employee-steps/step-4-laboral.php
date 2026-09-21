<?php
/**
 * Paso 4 - Información laboral.
 * Variable en scope: $cargos (tabla `cargo`).
 * Nota de diseño: la BD aún no tiene tabla de Departamento/Área de adscripción;
 * cuando exista, el <select> nuevo va en este paso (no en la interfaz de usuario).
 */
?>
<div class="form-section__label">Información Laboral</div>

<div class="row g-3">
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
    <input type="date" class="form-control" id="fechaIngreso" name="fecha_ingreso" required>
  </div>
</div>