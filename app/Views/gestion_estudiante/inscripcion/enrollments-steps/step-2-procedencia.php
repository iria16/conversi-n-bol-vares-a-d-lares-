<?php
/**
 * Paso 2 - Procedencia.
 * Variables del controlador: $instituciones array [ ['id'=>..,'nombre'=>..] ]
 * $gradosModal array [ ['id'=>..,'nombre'=>..] ]
 * El bloque de procedencia se oculta desde enrollments.js cuando el alumno
 * ingresa por primera vez.
 *
 * App\Support\Instituciones es una clase sin estado ni base de datos (como
 * Telefonos o Documento), así que se puede usar desde la vista para deducir el
 * número de un grado sin meter un modelo en el HTML.
*/
$instituciones = $instituciones ?? [];

/* Grados de procedencia, del catálogo `grado` y ordenados por su número, no
 por id: en el catálogo el 2do grado es id 6 y el 3er es id 2. Se queda solo
 con los que caen en el rango 1-6 de la primaria, que es el rango que valida
 App\Support\Instituciones para el tipo TIPO_ESCUELA. Antes esta lista estaba
 escrita a mano en el <select> y se le había quedado fuera el 6to grado.*/
$gradosPrimaria = [];
foreach ($gradosModal ?? [] as $grado) {
    $ordinal = \App\Support\Instituciones::ordinalGrado((string) ($grado['nombre'] ?? ''));
    if (\App\Support\Instituciones::esGradoDePrimaria($ordinal)) {
        $gradosPrimaria[$ordinal] = (string) $grado['nombre'];
    }
}
ksort($gradosPrimaria);
?>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Tipo de ingreso</span>
  </div>
  <div class="wizard-summary__card">
    <div class="wizard-toggle-card">
      <div class="wizard-toggle-card__text">
        <strong>Ingreso por primera vez</strong>
        <span>Actívelo si el estudiante no posee antecedentes en el sistema escolar nacional.</span>
      </div>
      <label class="wizard-switch">
        <input type="checkbox" id="ingresoPrimeraVez" name="ingreso_primera_vez">
        <span class="wizard-switch__slider"></span>
      </label>
    </div>
  </div>
</section>

<section class="wizard-summary__section" id="bloqueInstitucionProcedencia">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Procedencia</span>
  </div>
  <div class="wizard-summary__card">
    <p class="text-support">
      Datos de la institución educativa donde el estudiante cursó el año anterior.
    </p>

    <div class="row g-2">
      <div class="col-12">
                <label class="label-sigde" for="institucionProcedencia">Institución de procedencia *</label>
        <?php /* Solo se listan preescolares y escuelas (primarias): de ahí
 viene el estudiante. El botón de al lado da de alta una nueva
 en el modal anidado #modalNuevaInstitucionProcedencia, igual
 que en el formulario de empleados, y el tipo queda acotado a
 esas mismas etapas (App\Support\Instituciones).*/ ?>
        <div class="input-group">
          <select class="form-select" id="institucionProcedencia" name="id_institucion" required>
            <option value="" selected disabled>Seleccione la institución...</option>
            <?php foreach ($instituciones as $inst): ?>
              <option value="<?= (int) $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-primary js-abrir-modal-anidado"
                  data-bs-target="#modalNuevaInstitucionProcedencia" id="btnNuevaInstitucionProcedencia">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Nueva Institución
          </button>
        </div>
        <div class="invalid-feedback">Seleccione la institución de procedencia.</div>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="ultimoGradoCursado">Último grado cursado *</label>
        <?php /* "Educación Inicial" es la etapa anterior a la primaria: sirve
 para quien viene de un preescolar (TIPO_PREESCOLAR), que no
 tiene grados 1-6. Los grados 1-6 salen del catálogo `grado` y
 son los que el servidor compara con el grado de inscripción
 cuando la institución de procedencia es una escuela.*/ ?>
        <select class="form-select" id="ultimoGradoCursado" name="ultimo_grado_cursado" required>
          <option value="" selected disabled>Seleccione grado...</option>
          <option value="Educación Inicial">Educación Inicial</option>
          <?php foreach ($gradosPrimaria as $grado): ?>
            <option value="<?= htmlspecialchars($grado) ?>"><?= htmlspecialchars($grado) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">Seleccione el último grado cursado.</div>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="anioEscolarProcedencia">Año escolar anterior</label>
        <input type="text" class="form-control" id="anioEscolarProcedencia"
               name="anio_escolar_procedencia" placeholder="Ej: 2024-2025" maxlength="9">
      </div>
      <div class="col-12">
        <label class="label-sigde" for="motivoRetiro">Motivo de retiro / Observaciones</label>
        <textarea class="form-control" id="motivoRetiro" name="motivo_retiro" rows="2"
                  maxlength="100" placeholder="Breve descripción del motivo de cambio de institución..."></textarea>
      </div>
    </div>
  </div>
</section>
