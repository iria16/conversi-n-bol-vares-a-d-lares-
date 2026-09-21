<?php
/**
 * Paso 3 - Dirección de habitación.
 * Variable en scope: $estados (tabla `estado`).
 * Municipio y Parroquia se llenan por AJAX en cascada (staff.js): son catálogos
 * grandes (~370 municipios y cientos de parroquias) y no conviene imprimirlos todos.
 * id_parroquia es el único FK real que guarda la tabla `direccion`.
 */
?>
<div class="form-section__label">Dirección de Habitación</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <label class="label-sigde" for="direccionEstado">Estado</label>
    <select class="form-select" id="direccionEstado" name="id_estado_ui">
      <option value="">Seleccione estado</option>
      <?php foreach ($estados as $estado): ?>
        <option value="<?= (int) $estado['id_estado'] ?>"><?= htmlspecialchars($estado['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="label-sigde" for="direccionMunicipio">Municipio</label>
    <select class="form-select" id="direccionMunicipio" name="id_municipio_ui" disabled>
      <option value="">Seleccione municipio</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="label-sigde" for="direccionParroquia">Parroquia *</label>
    <select class="form-select" id="direccionParroquia" name="id_parroquia" disabled required>
      <option value="">Seleccione parroquia</option>
    </select>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <label class="label-sigde" for="sectorUrbanizacion">Sector / Urbanización *</label>
    <input type="text" class="form-control" id="sectorUrbanizacion" name="sector_urbanizacion" placeholder="Ej: Los Dos Caminos" required>
  </div>
  <div class="col-md-6">
    <label class="label-sigde" for="calleAvenida">Calle / Avenida *</label>
    <input type="text" class="form-control" id="calleAvenida" name="calle_avenida" placeholder="Ej: Av. Francisco de Miranda" required>
  </div>
  <div class="col-md-6">
    <label class="label-sigde" for="nroCasaApto">N° Casa / Apto *</label>
    <input type="text" class="form-control" id="nroCasaApto" name="nro_casa_apto" placeholder="Ej: Edif. Sigde, Apto 48" required>
  </div>
  <div class="col-md-6">
    <label class="label-sigde" for="puntoReferencia">Punto de Referencia</label>
    <input type="text" class="form-control" id="puntoReferencia" name="punto_referencia" placeholder="Ej: Detrás del Centro Comercial">
  </div>
</div>