<div class="form-section__label">Tipo de Ingreso</div>
<p class="text-support mb-4">Indique si el estudiante ingresa por primera vez al sistema escolar.</p>

<div class="wizard-toggle-card mb-4">
  <div class="wizard-toggle-card__text">
    <strong>Ingreso por primera vez</strong>
    <span>Active esta opción si el estudiante no posee antecedentes en el sistema escolar nacional.</span>
  </div>
  <label class="wizard-switch">
    <input type="checkbox" id="ingresoPrimeraVez" name="ingreso_primera_vez">
    <span class="wizard-switch__slider"></span>
  </label>
</div>

<div id="bloqueInstitucionProcedencia">
  <div class="form-section__label">Institución de Procedencia</div>
  <p class="text-support mb-4">Institución educativa donde el estudiante cursó el año anterior.</p>

  <div class="row g-3">
    <div class="col-12">
      <label class="form-label" for="institucionProcedencia">Nombre de la Institución *</label>
      <div class="input-icon-group">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="institucionProcedencia"
               name="institucion_procedencia" placeholder="Busque o escriba el nombre del plantel..." required>
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="ultimoGradoCursado">Último Grado Cursado *</label>
      <select class="form-select" id="ultimoGradoCursado" name="ultimo_grado_cursado" required>
        <option value="" selected disabled>Seleccione grado...</option>
        <option value="inicial">Educación Inicial</option>
        <option value="1">1er Año</option>
        <option value="2">2do Año</option>
        <option value="3">3er Año</option>
        <option value="4">4to Año</option>
        <option value="5">5to Año</option>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label" for="anioEscolarProcedencia">Año Escolar</label>
      <input type="text" class="form-control" id="anioEscolarProcedencia"
             name="anio_escolar_procedencia" placeholder="Ej: 2023-2024">
    </div>
    <div class="col-12">
      <label class="form-label" for="motivoRetiro">Motivo de Retiro / Observaciones</label>
      <textarea class="form-control" id="motivoRetiro" name="motivo_retiro" rows="3"
                placeholder="Breve descripción del motivo de cambio de institución..."></textarea>
    </div>
  </div>
</div>
