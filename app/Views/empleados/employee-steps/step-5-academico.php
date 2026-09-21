<?php
/**
 * Paso 5 - Historial académico.
 * La tabla se llena desde staff.js con los títulos agregados en el modal
 * modals/add-title-modal.php y se serializa en el hidden #titulosJson del wizard.
 */
?>
<div class="form-section__label mb-0">Historial Académico</div>
<div class="d-flex align-items-center justify-content-between mb-3">
  <p class="text-support mb-0">Registre los títulos y certificaciones obtenidos por el empleado.</p>
  <button type="button" class="btn btn-outline-primary btn-sm" id="btnAbrirAgregarTitulo">
    <i class="bi bi-plus-lg"></i> Agregar título
  </button>
</div>

<div class="table-responsive">
  <table class="wizard-titulos-table" id="tablaTitulos">
    <thead>
      <tr>
        <th>Título</th>
        <th>Institución</th>
        <th>Fecha de Obtención</th>
        <th class="text-center">Acciones</th>
      </tr>
    </thead>
    <tbody id="tablaTitulosBody">
      <tr id="tablaTitulosVacio">
        <td colspan="4" class="text-center text-support py-4">Aún no has agregado ningún título.</td>
      </tr>
    </tbody>
  </table>
</div>