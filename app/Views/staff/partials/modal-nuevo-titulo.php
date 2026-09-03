<!-- ── Modal: Crear Nuevo Título (catálogo) ── -->
<div class="modal fade app-modal" id="modalNuevoTitulo" tabindex="-1" aria-labelledby="modalNuevoTituloLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalNuevoTituloLabel">
          <i class="bi bi-plus-circle"></i> Nuevo Título
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="nuevoTituloNombre">Nombre del Título *</label>
          <input type="text" class="form-control" id="nuevoTituloNombre" placeholder="Ej: Licenciado en Informática" maxlength="150">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarNuevoTitulo">
          <i class="bi bi-floppy me-1"></i> Guardar
        </button>
      </div>

    </div>
  </div>
</div>
