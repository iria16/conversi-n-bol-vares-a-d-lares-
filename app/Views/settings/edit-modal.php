<?php
$catalogoActivo = $catalogoActivo ?? '';
ob_start();
?>
<form id="formEditarElemento" novalidate>
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">
  <input type="hidden" name="id" id="editarElementoId">

  <div class="mb-3">
    <label for="editarElementoNombre" class="label-sigde">Nombre</label>
    <input type="text" id="editarElementoNombre" name="nombre" class="form-control" required maxlength="100">
    <div class="invalid-feedback">Ingrese un nombre válido.</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalId = 'modalEditarElemento';
$modalTitle = 'Editar elemento';
$modalSubtitle = 'Actualice el nombre del elemento seleccionado.';
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formEditarElemento" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../components/modal.php';
?>
