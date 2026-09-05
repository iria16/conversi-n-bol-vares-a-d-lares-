<!-- ── Modal: Crear Nuevo Tipo de Institución (catálogo) ── -->
<div class="modal fade app-modal" id="modalNuevoTipoInstitucion" tabindex="-1" aria-labelledby="modalNuevoTipoInstitucionLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalNuevoTipoInstitucionLabel">
          <i class="bi bi-building"></i> Nuevo Tipo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="nuevoTipoInstitucionNombre">Nombre del Tipo *</label>
          <input type="text" class="form-control" id="nuevoTipoInstitucionNombre" placeholder="Ej: Instituto Universitario" maxlength="50">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarNuevoTipoInstitucion">
          <i class="bi bi-floppy me-1"></i> Guardar
        </button>
      </div>

    </div>
  </div>
</div>
