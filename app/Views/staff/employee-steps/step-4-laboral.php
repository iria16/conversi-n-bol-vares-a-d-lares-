<?php
/** Espera $cargos (tabla `cargo`), ya cargado por el controlador. */
$cargos = $cargos ?? [];
?>
<div class="form-section__label">Información Laboral</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <label class="form-label" for="cargoAsignado">Cargo Asignado *</label>
    <select class="form-select" id="cargoAsignado" name="id_cargo" required>
      <option value="">Seleccione el cargo...</option>
      <?php foreach ($cargos as $cargo): ?>
        <option value="<?= (int) $cargo['id'] ?>"><?= htmlspecialchars($cargo['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="fechaIngreso">Fecha de Ingreso *</label>
    <input type="date" class="form-control" id="fechaIngreso" name="fecha_ingreso" required>
  </div>
</div>

<div class="info-alert">
  <div class="info-alert__icon"><i class="bi bi-info-lg"></i></div>
  <div>
    <p class="info-alert__title">Sin departamento por ahora</p>
    <p class="info-alert__text">
      Tu base de datos aún no tiene una tabla para "Departamento o Área de adscripción",
      así que este registro solo vincula al empleado con su cargo y fecha de ingreso.
      Si la necesitas, avísame y la diseñamos.
    </p>
  </div>
</div>