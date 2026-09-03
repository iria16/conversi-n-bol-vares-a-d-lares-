<!-- ── Modal: Crear Nueva Institución (catálogo) ── -->
<div class="modal fade app-modal" id="modalNuevaInstitucion" tabindex="-1" aria-labelledby="modalNuevaInstitucionLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="modalNuevaInstitucionLabel">
          <i class="bi bi-building-add"></i> Nueva Institución
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="nuevaInstitucionNombre">Nombre *</label>
          <input type="text" class="form-control" id="nuevaInstitucionNombre" placeholder="Ej: Universidad Central de Venezuela" maxlength="150">
        </div>
        <div class="mb-3">
          <label class="form-label" for="nuevaInstitucionTipo">Tipo de Institución *</label>
          <select class="form-select" id="nuevaInstitucionTipo">
            <option value="">Seleccione tipo</option>
            <?php foreach ($tiposInstitucion ?? [] as $tipo): ?>
              <option value="<?= (int) $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarNuevaInstitucion">
          <i class="bi bi-floppy me-1"></i> Guardar
        </button>
      </div>

    </div>
  </div>
</div>
