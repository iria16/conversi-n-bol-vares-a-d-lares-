<div class="modal fade app-modal" id="modalNuevoTitulo" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-award"></i> Registrar Nuevo Título</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="nuevoTituloNombre">Nombre del Título *</label>
        <input type="text" class="form-control" id="nuevoTituloNombre" placeholder="Ej: Licenciado en Educación" required>
        <div class="info-alert mt-3">
          <div class="info-alert__icon"><i class="bi bi-info-lg"></i></div>
          <div class="info-alert__text">Este título quedará disponible en el catálogo general para futuros registros.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarNuevoTitulo">
          <i class="bi bi-check-lg"></i> Guardar Registro
        </button>
      </div>
    </div>
  </div>
</div>