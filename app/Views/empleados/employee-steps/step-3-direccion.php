<?php

/**
 * Paso 3 - Dirección de habitación.
 * Variable en scope: $estados (tabla `estado`).
 * Municipio y Parroquia se llenan por AJAX en cascada (empleado.js, vía
 * empleados/getUbicacion). Son catálogos grandes (~370 municipios y cientos
 * de parroquias) y no conviene imprimirlos todos.
 * id_parroquia es el único FK real que guarda la tabla `direccion`.
 * @var array $estados
 */
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Dirección de habitación</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2 mb-2">
      <div class="col-md-4">
        <label class="label-sigde" for="direccionEstado">Estado</label>
        <select class="form-select" id="direccionEstado" name="id_estado_ui" required>
          <option value="">Seleccione el estado</option>
          <?php foreach ($estados as $estado): ?>
            <option value="<?= (int) $estado['id_estado'] ?>"><?= htmlspecialchars($estado['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="direccionMunicipio">Municipio</label>
        <select class="form-select" id="direccionMunicipio" name="id_municipio_ui"
                data-depende-de="direccionEstado" required>
          <option value="">Seleccione el municipio</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="label-sigde" for="direccionParroquia">Parroquia *</label>
        <select class="form-select" id="direccionParroquia" name="id_parroquia"
                data-depende-de="direccionMunicipio" required>
          <option value="">Seleccione la parroquia</option>
        </select>
      </div>
    </div>
    <div class="row g-2">
      <div class="col-md-6">
        <label class="label-sigde" for="sectorUrbanizacion">Sector / Urbanización *</label>
        <input
          type="text"
          class="form-control"
          id="sectorUrbanizacion"
          name="sector_urbanizacion"
          placeholder="Ej: Los Dos Caminos"
          minlength="3"
          maxlength="100"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="calleAvenida">Calle / Avenida *</label>
        <input
          type="text"
          class="form-control"
          id="calleAvenida"
          name="calle_avenida"
          placeholder="Ej: Av. Francisco de Miranda"
          minlength="2"
          maxlength="100"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="nroCasaApto">N° Casa / Apto *</label>
        <input
          type="text"
          class="form-control"
          id="nroCasaApto"
          name="nro_casa_apto"
          placeholder="Ej: Edif. Sigde, Apto 48"
          minlength="1"
          maxlength="20"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="punto_referencia">Punto de Referencia</label>
        <input
          type="text"
          class="form-control"
          id="punto_referencia"
          name="punto_referencia"
          maxlength="150"
          placeholder="Ej. Frente a la cancha deportiva"
        >
        <div class="invalid-feedback">El punto de referencia no debe superar los 150 caracteres.</div>
      </div>
    </div>
  </div>
</section>