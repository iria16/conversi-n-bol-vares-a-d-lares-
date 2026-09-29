<?php
/**
 * Paso 7 - Resumen / confirmación.
 * Cada bloque trae un botón "Editar" que devuelve al paso correspondiente
 * (data-editar-paso), y los valores que rellena enrollments.js vía
 * data-resumen.
 */
?>

<div class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person-check" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Confirmar registro</span>
  </div>

  <p class="text-support mb-0">
    Revise la información antes de confirmar. Puede volver a cualquier paso para corregirla.
  </p>
</div>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Datos personales</span>
    <button type="button" class="wizard-summary__edit-btn" data-editar-paso="1">
      <i class="bi bi-pencil" aria-hidden="true"></i> Editar
    </button>
  </div>
  <div class="wizard-summary__grid">
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Nombre completo</div>
      <div class="wizard-summary__value" data-resumen="nombre_completo">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Documento</div>
      <div class="wizard-summary__value" data-resumen="documento">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Fecha de nacimiento</div>
      <div class="wizard-summary__value" data-resumen="fecha_nacimiento">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Sexo</div>
      <div class="wizard-summary__value" data-resumen="sexo">—</div>
    </div>
  </div>
</section>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Procedencia</span>
    <button type="button" class="wizard-summary__edit-btn" data-editar-paso="2">
      <i class="bi bi-pencil" aria-hidden="true"></i> Editar
    </button>
  </div>
  <div class="wizard-summary__grid">
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Tipo de ingreso</div>
      <div class="wizard-summary__value" data-resumen="tipo_ingreso">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Institución de procedencia</div>
      <div class="wizard-summary__value" data-resumen="institucion_anterior">—</div>
    </div>
  </div>
</section>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información académica</span>
    <button type="button" class="wizard-summary__edit-btn" data-editar-paso="3">
      <i class="bi bi-pencil" aria-hidden="true"></i> Editar
    </button>
  </div>
  <div class="wizard-summary__grid">
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Grado</div>
      <div class="wizard-summary__value" data-resumen="grado">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Sección</div>
      <div class="wizard-summary__value" data-resumen="seccion">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Turno</div>
      <div class="wizard-summary__value" data-resumen="turno">—</div>
    </div>
    <div class="wizard-summary__item">
      <div class="wizard-summary__label">Año escolar</div>
      <div class="wizard-summary__value" data-resumen="anio_escolar">—</div>
    </div>
  </div>
</section>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-rulers" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Talla uniforme y ficha médica</span>
    <button type="button" class="wizard-summary__edit-btn" data-editar-paso="4">
      <i class="bi bi-pencil" aria-hidden="true"></i> Editar
    </button>
  </div>
  <div class="wizard-summary__card">
    <div class="wizard-summary__grid">
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Camisa</div>
        <div class="wizard-summary__value" data-resumen="talla_camisa">—</div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Pantalón</div>
        <div class="wizard-summary__value" data-resumen="talla_pantalon">—</div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Calzado</div>
        <div class="wizard-summary__value" data-resumen="talla_zapato">—</div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Tipo de sangre</div>
        <div class="wizard-summary__value" data-resumen="tipo_sangre">—</div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Alergias</div>
        <div class="wizard-summary__value" data-resumen="alergias">—</div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Enfermedades</div>
        <div class="wizard-summary__value" data-resumen="enfermedades">—</div>
      </div>
    </div>
  </div>
</section>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Familiares y representante legal</span>
    <button type="button" class="wizard-summary__edit-btn" data-editar-paso="6">
      <i class="bi bi-pencil" aria-hidden="true"></i> Editar
    </button>
  </div>
  <div id="resumenFamiliares">
    <p class="text-support mb-0">Sin familiares registrados.</p>
  </div>
</section>
