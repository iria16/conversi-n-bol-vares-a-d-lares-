<div class="form-section__label">Información Académica</div>
<p class="text-support mb-4">Asigne el grado, sección y turno para el año escolar vigente.</p>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <label class="form-label" for="gradoCursar">Grado a Cursar *</label>
    <select class="form-select" id="gradoCursar" name="grado_cursar" required>
      <option value="" selected disabled>Seleccione el grado...</option>
      <option value="1">1er Año</option>
      <option value="2">2do Año</option>
      <option value="3">3er Año</option>
      <option value="4">4to Año</option>
      <option value="5">5to Año</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="seccion">Sección *</label>
    <select class="form-select" id="seccion" name="seccion" required>
      <option value="" selected disabled>Seleccione la sección...</option>
      <option value="A">A</option>
      <option value="B">B</option>
      <option value="C">C</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="turno">Turno *</label>
    <select class="form-select" id="turno" name="turno" required>
      <option value="" selected disabled>Seleccione el turno...</option>
      <option value="manana">Mañana</option>
      <option value="tarde">Tarde</option>
      <option value="nocturno">Nocturno</option>
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label" for="anioEscolarAcademico">Año Escolar</label>
    <input type="text" class="form-control" id="anioEscolarAcademico" name="anio_escolar"
           value="2024-2025" readonly>
  </div>
</div>

<div class="wizard-info-note">
  <i class="bi bi-info-circle"></i>
  <span>Esta información es fundamental para la asignación de cupos, planificación pedagógica y el control de estadísticas del Ministerio de Educación.</span>
</div>
