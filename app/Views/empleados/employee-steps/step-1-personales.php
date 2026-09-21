<?php
/**
 * Paso 1 - Datos personales.
 * Sin variables externas. El id/name de cada campo lo lee staff.js (validación y resumen).
 */
?>
<div class="wizard-photo-upload mb-4">
  <div class="wizard-photo-upload__circle">
    <div class="wizard-photo-upload__preview" id="fotoPreview">
      <i class="bi bi-person"></i>
    </div>
    <label class="wizard-photo-upload__edit" for="fotoInput">
      <i class="bi bi-pencil-fill"></i>
    </label>
    <input type="file" id="fotoInput" name="foto" accept="image/*" class="d-none">
  </div>
  <span class="wizard-photo-upload__hint">Foto de perfil (Opcional)</span>
</div>

<div class="mb-4">
  <div class="form-section__label">Identificación Oficial</div>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="label-sigde" for="tipoDocumento">Tipo de Documento *</label>
      <select class="form-select" id="tipoDocumento" name="tipo_documento" required>
        <option value="V">Cédula de Identidad (V)</option>
        <option value="E">Extranjero (E)</option>
        <option value="CE">Cédula Escolar / Otro (CE)</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="label-sigde" for="numeroDocumento">N° Cédula / Pasaporte *</label>
      <div class="input-icon-group">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="numeroDocumento" name="numero_documento" placeholder="00.000.000" required>
      </div>
    </div>
    <div class="col-md-4">
      <label class="label-sigde" for="fechaNacimiento">Fecha de Nacimiento *</label>
      <input type="date" class="form-control" id="fechaNacimiento" name="fecha_nacimiento" required>
    </div>
  </div>
</div>

<div class="mb-4">
  <div class="form-section__label">Nombres y Apellidos</div>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="label-sigde" for="primerNombre">Primer Nombre *</label>
      <input type="text" class="form-control" id="primerNombre" name="primer_nombre" placeholder="Ej: María" required>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="segundoNombre">Segundo Nombre (Opcional)</label>
      <input type="text" class="form-control" id="segundoNombre" name="segundo_nombre" placeholder="Ej: Alejandra">
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="primerApellido">Primer Apellido *</label>
      <input type="text" class="form-control" id="primerApellido" name="primer_apellido" placeholder="Ej: García" required>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="segundoApellido">Segundo Apellido (Opcional)</label>
      <input type="text" class="form-control" id="segundoApellido" name="segundo_apellido" placeholder="Ej: López">
    </div>
  </div>
</div>

<div>
  <div class="form-section__label">Sexo *</div>
  <div class="selectable-card-group">
    <label class="selectable-card" data-sexo-card>
      <input type="radio" name="sexo" value="M" required>
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