<?php
/**
 * app/Views/empleados/modals/add-title-modal.php
 * Modal "Agregar Título" del paso 5 del wizard (historial académico).
 * Se abre con el botón #btnAbrirAgregarTitulo (step-5-academico.php) y el
 * JS (empleado.js) pinta la fila en la tabla y serializa #titulosJson.
 *
 * Los botones "+ Nuevo Título" / "+ Nueva Institución" abren las altas
 * rápidas (modals/new-title-modal.php y modals/new-institution-modal.php);
 * esas guardan con guardarTitulo y guardarInstitucion del EmpleadoController.
 *
 * Variables en scope (definidas en index.php vía getExtraIndexData):
 *   $titulos, $instituciones, $gradosAcademicos
 *
 * $instituciones ya llega filtrada desde EmpleadoModel::getCatalogosFormulario()
 * (excluye tipos Preescolar/Escuela/Instituto de Educación Especial), así
 * que este archivo no necesita filtrar nada por su cuenta.
 */
ob_start();
?>
<div class="wizard-summary__section">
  <div class="wizard-summary__card">
    <div class="row g-2">
      <div class="col-12">
        <label class="label-sigde" for="gradoAgregar">Grado académico *</label>
        <select class="form-select" id="gradoAgregar" required>
          <option value="">Seleccione el nivel...</option>
          <?php foreach ($gradosAcademicos as $grado): ?>
            <option value="<?= (int) $grado['id_grado_academico'] ?>"><?= htmlspecialchars($grado['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="text-support mt-1" style="font-size: 0.75rem">
          El listado de títulos se filtra según el grado seleccionado.
        </div>
      </div>

      <div class="col-12">
        <label class="label-sigde" for="tituloAgregar">Título obtenido *</label>
        <div class="input-group">
          <select class="form-select" id="tituloAgregar" required>
            <option value="">Seleccione el título...</option>
            <?php foreach ($titulos as $titulo): ?>
              <option value="<?= (int) $titulo['id_titulo'] ?>" data-id-grado="<?= (int) ($titulo['id_grado_academico'] ?? 0) ?>"><?= htmlspecialchars($titulo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-primary js-abrir-modal-anidado" data-bs-target="#modalNuevoTitulo">
            <i class="bi bi-plus-lg"></i> Nuevo Título
          </button>
        </div>
      </div>

      <div class="col-12">
        <label class="label-sigde" for="institucionAgregar">Institución educativa *</label>
        <div class="input-group">
          <select class="form-select" id="institucionAgregar" required>
            <option value="">Seleccione la institución...</option>
            <?php foreach ($instituciones as $institucion): ?>
              <option value="<?= (int) $institucion['id_institucion'] ?>"><?= htmlspecialchars($institucion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="btn btn-outline-primary js-abrir-modal-anidado" data-bs-target="#modalNuevaInstitucion">
            <i class="bi bi-plus-lg"></i> Nueva Institución
          </button>
        </div>
      </div>

      <div class="col-12">
        <label class="label-sigde" for="fechaObtencionAgregar">Fecha de graduación/obtención *</label>
        <input
          type="date"
          class="form-control"
          id="fechaObtencionAgregar"
          min="1950-01-01"
          max="<?= date('Y-m-d') ?>"
          required
        >
        <div class="text-support mt-1" style="font-size: 0.75rem">
          Debe estar entre 1950 y hoy.
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnConfirmarTitulo">
  Siguiente <i class="bi bi-arrow-right"></i>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalAgregarTitulo';
$modalTitle    = 'Agregar Título Académico';
$modalSubtitle = 'Registre un título obtenido por el empleado.';
$modalSize     = 'lg';
include __DIR__ . '/../../components/modal.php';