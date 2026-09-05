<div class="form-section__label">Información Adicional</div>
<p class="text-support mb-4">Datos opcionales sobre tallas de uniforme y ficha médica del estudiante.</p>

<div class="wizard-extra-card">
  <button type="button" class="wizard-extra-card__header" data-extra-toggle="uniforme">
    <span class="wizard-extra-card__icon wizard-extra-card__icon--purple">
      <i class="bi bi-person-standing"></i>
    </span>
    <span class="wizard-extra-card__text">
      <strong>Talla de Uniforme</strong>
      <span>Información sobre vestimenta escolar</span>
    </span>
    <span class="wizard-extra-card__pill" data-extra-pill="uniforme">
      <i class="bi bi-plus-lg"></i> Agregar
    </span>
    <i class="bi bi-chevron-down wizard-extra-card__chevron"></i>
  </button>
  <div class="wizard-extra-card__body" id="extraBody-uniforme" hidden>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="tallaCamisa">Talla de Camisa/Blusa</label>
        <select class="form-select" id="tallaCamisa" name="talla_camisa">
          <option value="" selected disabled>Seleccione...</option>
          <option>4</option><option>6</option><option>8</option><option>10</option>
          <option>12</option><option>14</option><option>16</option>
          <option>S</option><option>M</option><option>L</option><option>XL</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="tallaPantalon">Talla de Pantalón/Falda</label>
        <select class="form-select" id="tallaPantalon" name="talla_pantalon">
          <option value="" selected disabled>Seleccione...</option>
          <option>4</option><option>6</option><option>8</option><option>10</option>
          <option>12</option><option>14</option><option>16</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="tallaCalzado">Talla de Calzado</label>
        <select class="form-select" id="tallaCalzado" name="talla_calzado">
          <option value="" selected disabled>Seleccione...</option>
          <?php for ($t = 28; $t <= 44; $t++): ?>
            <option value="<?= $t ?>"><?= $t ?></option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
  </div>
</div>

<div class="wizard-extra-card">
  <button type="button" class="wizard-extra-card__header" data-extra-toggle="medica">
    <span class="wizard-extra-card__icon wizard-extra-card__icon--pink">
      <i class="bi bi-briefcase-medical"></i>
    </span>
    <span class="wizard-extra-card__text">
      <strong>Ficha Médica</strong>
      <span>Condiciones de salud y requerimientos especiales</span>
    </span>
    <span class="wizard-extra-card__pill" data-extra-pill="medica">
      <i class="bi bi-plus-lg"></i> Agregar
    </span>
    <i class="bi bi-chevron-down wizard-extra-card__chevron"></i>
  </button>
  <div class="wizard-extra-card__body" id="extraBody-medica" hidden>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="tipoSangre">Tipo de Sangre</label>
        <select class="form-select" id="tipoSangre" name="tipo_sangre">
          <option value="" selected disabled>Seleccione...</option>
          <option>O+</option><option>O-</option><option>A+</option><option>A-</option>
          <option>B+</option><option>B-</option><option>AB+</option><option>AB-</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="contactoEmergencia">Contacto de Emergencia</label>
        <input type="text" class="form-control" id="contactoEmergencia"
               name="contacto_emergencia" placeholder="Nombre y teléfono">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="alergias">Alergias</label>
        <textarea class="form-control" id="alergias" name="alergias" rows="2"
                  placeholder="Ej: Penicilina, maní..."></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="condicionesEspeciales">Condiciones / Requerimientos Especiales</label>
        <textarea class="form-control" id="condicionesEspeciales" name="condiciones_especiales" rows="2"
                  placeholder="Ej: Asma, uso de lentes, movilidad reducida..."></textarea>
      </div>
    </div>
  </div>
</div>
