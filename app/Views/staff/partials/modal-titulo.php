<!-- ── Modal: Agregar Título Académico (paso 5 del wizard) ── -->
<div class="modal fade app-modal" id="modalAgregarTitulo" tabindex="-1" aria-labelledby="modalAgregarTituloLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalAgregarTituloLabel">
          <i class="bi bi-mortarboard"></i> Agregar Título Académico
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="tituloGradoAcademico">Grado Académico *</label>
          <select class="form-select" id="tituloGradoAcademico">
            <option value="">Seleccione grado</option>
            <?php foreach ($gradosAcademicos as $grado): ?>
              <option value="<?= (int) $grado['id'] ?>"><?= htmlspecialchars($grado['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="tituloSelect">Título *</label>
          <div class="d-flex gap-2">
            <select class="form-select" id="tituloSelect">
              <option value="">Seleccione título</option>
              <?php foreach ($titulos as $titulo): ?>
                <option value="<?= (int) $titulo['id'] ?>"><?= htmlspecialchars($titulo['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="btnAbrirNuevoTitulo" title="Crear nuevo título">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="tituloInstitucion">Institución *</label>
          <div class="d-flex gap-2">
            <select class="form-select" id="tituloInstitucion">
              <option value="">Seleccione institución</option>
              <?php foreach ($instituciones as $inst): ?>
                <option value="<?= (int) $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="btnAbrirNuevaInstitucion" title="Crear nueva institución">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="tituloFechaObtencion">Fecha de Obtención *</label>
          <input type="date" class="form-control" id="tituloFechaObtencion">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnConfirmarTitulo">
          <i class="bi bi-check-lg me-1"></i> Agregar
        </button>
      </div>

    </div>
  </div>
</div>
