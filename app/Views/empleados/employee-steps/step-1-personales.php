<?php

/**
 * Paso 1 - Datos personales.
 * Sin variables externas. El id/name de cada campo lo lee empleado.js (validación y resumen).
 */
?>
<div class="wizard-photo-upload mb-3">
  <div class="wizard-photo-upload__circle">
    <div class="wizard-photo-upload__preview" id="fotoPreview">
      <i class="bi bi-person"></i>
    </div>
    <label class="wizard-photo-upload__edit" for="fotoInput">
      <i class="bi bi-pencil-fill"></i>
    </label>
    <input type="file" id="fotoInput" name="foto" accept="image/*" class="d-none">
  </div>
  <div class="text-center">
    <span class="wizard-photo-upload__hint">Foto de perfil (Opcional)</span>
    <div>
      <button type="button" class="btn btn-link btn-sm text-danger p-0" id="btnQuitarFotoRegistro" hidden>
        <i class="bi bi-x-circle me-1"></i>Quitar foto
      </button>
    </div>
  </div>
</div>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Identificación oficial</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2">
      <?php /* El tipo de documento lo pinta el componente compartido por todos
               los formularios de persona: app/Views/components/tipo-documento-select.php */ ?>
<?php
      $tipoDocId  = 'tipoDocumento';
      $tipoDocCol = 'col-3';
      include __DIR__ . '/../../components/tipo-documento-select.php';
      ?>
      <div class="col-5">
        <label class="label-sigde" for="numeroDocumento">Nro documento *</label>
        <div class="input-icon-group">
          <i class="bi bi-person-vcard"></i>
          <input
            type="text"
            class="form-control"
            id="numeroDocumento"
            name="numero_documento"
            placeholder="<?= htmlspecialchars(\App\Support\Documento::PLACEHOLDER_HTML) ?>"
            inputmode="numeric"
            pattern="<?= htmlspecialchars(\App\Support\Documento::PATRON_HTML) ?>"
            maxlength="8"
            title="<?= htmlspecialchars(\App\Support\Documento::TITULO_HTML) ?>"
            required>
        </div>
      </div>
      <div class="col-4">
        <label class="label-sigde" for="fechaNacimiento">Fecha de nacimiento *</label>
        <input
          type="date"
          class="form-control"
          id="fechaNacimiento"
          name="fecha_nacimiento"
          min="<?= date('Y-m-d', strtotime('-75 years')) ?>"
          max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
          title="El empleado debe tener entre 18 y 75 años."
          required>
      </div>
    </div>
  </div>
</section>

<section class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Nombres y apellidos</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="label-sigde" for="primerNombre">Primer nombre *</label>
        <input
          type="text"
          class="form-control"
          id="primerNombre"
          name="primer_nombre"
          placeholder="Ej: María"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="segundoNombre">Segundo nombre (opcional)</label>
        <input
          type="text"
          class="form-control"
          id="segundoNombre"
          name="segundo_nombre"
          placeholder="Ej: Alejandra"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios">
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="primerApellido">Primer apellido *</label>
        <input
          type="text"
          class="form-control"
          id="primerApellido"
          name="primer_apellido"
          placeholder="Ej: García"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="segundoApellido">Segundo apellido (opcional)</label>
        <input
          type="text"
          class="form-control"
          id="segundoApellido"
          name="segundo_apellido"
          placeholder="Ej: López"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios">
      </div>
    </div>
  </div>
</section>

<section class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-gender-ambiguous" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Sexo</span>
  </div>
  <div class="wizard-summary__card">
    <div class="selectable-card-group">
      <label class="selectable-card" data-sexo-card>
        <input
          type="radio"
          name="sexo"
          value="M"
          required>
        <i class="bi bi-gender-male"></i>
        <span>Masculino</span>
      </label>
      <label class="selectable-card" data-sexo-card>
        <input type="radio" name="sexo" value="F" required>
        <i class="bi bi-gender-female"></i>
        <span>Femenino</span>
      </label>
    </div>
  </div>
</section>