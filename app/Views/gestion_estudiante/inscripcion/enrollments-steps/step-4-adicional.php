<?php
/**
 * Paso 4 - Información adicional del estudiante: talla uniforme y ficha médica.
 *
 * Decisión de registro: el estudiante es menor de edad, así que NO se le
 * pide teléfono, correo ni dirección. Esos datos son los de su representante
 * legal y se capturan en el paso 6 (Representante legal), que es donde el JS
 * bloque de contacto y domicilio únicamente para quien esté marcado como
 * muestra el bloque.
 *
 * Las dos tarjetas son plegables (wizard-extra-card) y se abren desde
 * enrollments.js. Los campos van a las tablas `talla_uniforme` y
 * `ficha_medica`, ambas por estudiante (id_estudiante).
 */
?>
<div class="info-alert mb-3">
  <div class="info-alert__icon"><i class="bi bi-info-lg"></i></div>
  <p class="info-alert__text">
    El estudiante es menor de edad: aquí solo se registra su talla uniforme y su ficha
    médica. El teléfono, el correo y la dirección se cargan del representante legal
    en el paso <strong>6. Representante legal</strong>.
  </p>
</div>

<div class="wizard-extra-card">
  <button type="button" class="wizard-extra-card__header" data-extra-toggle="talla">
    <span class="wizard-extra-card__icon wizard-extra-card__icon--primary">
      <i class="bi bi-person-standing" aria-hidden="true"></i>
    </span>
    <span class="wizard-extra-card__text">
      <strong>Talla uniforme</strong>
      <span>Medidas para la confección del uniforme</span>
    </span>
    <span class="wizard-extra-card__pill" data-extra-pill="talla">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar
    </span>
    <i class="bi bi-chevron-down wizard-extra-card__chevron" aria-hidden="true"></i>
  </button>
  <div class="wizard-extra-card__body" id="extraBody-talla" hidden>
    <div class="row g-2">
      <div class="col-4">
        <label class="label-sigde" for="tallaCamisa">Talla de camisa</label>
        <input type="text" class="form-control" id="tallaCamisa" name="talla_camisa"
               list="sigdeTallasCamisa" maxlength="10" placeholder="Ej: M">
        <datalist id="sigdeTallasCamisa">
          <option value="S"></option>
          <option value="M"></option>
          <option value="L"></option>
          <option value="XL"></option>
          <option value="XXL"></option>
        </datalist>
      </div>
      <div class="col-4">
        <label class="label-sigde" for="tallaPantalon">Talla de pantalón</label>
        <input type="text" class="form-control" id="tallaPantalon" name="talla_pantalon"
               list="sigdeTallasPantalon" maxlength="10" placeholder="Ej: 32">
        <datalist id="sigdeTallasPantalon">
          <option value="4"></option><option value="6"></option><option value="8"></option>
          <option value="10"></option><option value="12"></option><option value="14"></option>
          <option value="16"></option><option value="18"></option><option value="20"></option>
          <option value="22"></option><option value="24"></option><option value="26"></option>
          <option value="28"></option><option value="30"></option><option value="32"></option>
          <option value="34"></option><option value="36"></option><option value="38"></option>
          <option value="40"></option>
        </datalist>
      </div>
      <div class="col-4">
        <label class="label-sigde" for="tallaZapato">Talla de calzado</label>
        <input type="text" class="form-control" id="tallaZapato" name="talla_zapato"
               list="sigdeTallasZapato" maxlength="10" placeholder="Ej: 38">
        <datalist id="sigdeTallasZapato">
          <option value="16"></option><option value="17"></option><option value="18"></option>
          <option value="19"></option><option value="20"></option><option value="21"></option>
          <option value="22"></option><option value="23"></option><option value="24"></option>
          <option value="25"></option><option value="26"></option><option value="27"></option>
          <option value="28"></option><option value="29"></option><option value="30"></option>
          <option value="32"></option><option value="34"></option><option value="36"></option>
          <option value="38"></option><option value="40"></option><option value="42"></option>
        </datalist>
      </div>
      <div class="col-12">
        <label class="label-sigde" for="tallaObservaciones">Observaciones de la talla</label>
        <textarea class="form-control" id="tallaObservaciones" name="talla_observaciones" rows="2"
                  maxlength="200"
                  placeholder="Ej: Requiere talla especial, uniforme del colegio..."></textarea>
      </div>
    </div>
  </div>
</div>

<div class="wizard-extra-card">
  <button type="button" class="wizard-extra-card__header" data-extra-toggle="medica">
    <span class="wizard-extra-card__icon wizard-extra-card__icon--pink">
      <i class="bi bi-briefcase-medical" aria-hidden="true"></i>
    </span>
    <span class="wizard-extra-card__text">
      <strong>Ficha médica</strong>
      <span>Condiciones de salud y requerimientos especiales</span>
    </span>
    <span class="wizard-extra-card__pill" data-extra-pill="medica">
      <i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar
    </span>
    <i class="bi bi-chevron-down wizard-extra-card__chevron" aria-hidden="true"></i>
  </button>
  <div class="wizard-extra-card__body" id="extraBody-medica" hidden>
    <div class="row g-2">
      <div class="col-6">
        <label class="label-sigde" for="tipoSangre">Tipo de sangre</label>
        <select class="form-select" id="tipoSangre" name="tipo_sangre">
          <option value="" selected disabled>Seleccione...</option>
          <option>O+</option><option>O-</option>
          <option>A+</option><option>A-</option>
          <option>B+</option><option>B-</option>
          <option>AB+</option><option>AB-</option>
        </select>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="medicamentos">Medicamentos de uso regular</label>
        <input type="text" class="form-control" id="medicamentos" name="medicamentos"
               placeholder="Ej: Broncodilatador cada 8h...">
      </div>
      <div class="col-6">
        <label class="label-sigde" for="alergias">Alergias</label>
        <textarea class="form-control" id="alergias" name="alergias" rows="2"
                  placeholder="Ej: Penicilina, maní..."></textarea>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="enfermedades">Enfermedades relevantes</label>
        <input type="text" class="form-control" id="enfermedades" name="enfermedades"
               placeholder="Ej: Asma, epilepsia controlada...">
      </div>
      <div class="col-12">
        <label class="label-sigde" for="condicionesEspeciales">Condiciones / requerimientos especiales</label>
        <textarea class="form-control" id="condicionesEspeciales" name="condiciones_especiales" rows="2"
                  placeholder="Ej: Asma, uso de lentes, movilidad reducida..."></textarea>
      </div>
      <div class="col-12">
        <label class="label-sigde" for="observacionesMedicas">Observaciones generales</label>
        <textarea class="form-control" id="observacionesMedicas" name="observaciones_medicas" rows="2"
                  placeholder="Notas de la ficha médica..."></textarea>
      </div>
    </div>
  </div>
</div>
