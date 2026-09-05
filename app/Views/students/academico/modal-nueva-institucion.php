<?php
/** Espera $tiposInstitucion (tabla `tipo_institucion`) - agrégalo en el controlador si aún no lo pasas. */
$tiposInstitucion = $tiposInstitucion ?? [];
?>
<div class="modal fade app-modal" id="modalNuevaInstitucion" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-building"></i> Registrar Nueva Institución</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="nuevaInstitucionNombre">Nombre de la Institución *</label>
          <input type="text" class="form-control" id="nuevaInstitucionNombre" placeholder="Ej: Universidad Central de Venezuela" required>
        </div>
        <div>
          <label class="form-label" for="nuevaInstitucionTipo">Tipo de Institución *</label>
          <select class="form-select" id="nuevaInstitucionTipo" required>
            <option value="">Seleccione el tipo...</option>
            <?php foreach ($tiposInstitucion as $tipo): ?>
              <option value="<?= (int) $tipo['id_tipo_institucion'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarNuevaInstitucion">
          <i class="bi bi-check-lg"></i> Guardar Registro
        </button>
      </div>
    </div>
  </div>
</div>