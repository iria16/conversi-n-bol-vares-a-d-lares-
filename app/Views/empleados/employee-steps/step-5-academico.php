<?php
/**
 * Paso 5 - Historial académico.
 * La tabla se llena desde empleado.js con los títulos agregados en el modal
 * modals/add-title-modal.php y se serializa en el hidden #titulosJson del wizard.
 */
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Historial académico</span>
    <button type="button" class="btn btn-outline-primary btn-sm" id="btnAbrirAgregarTitulo">
      <i class="bi bi-plus-lg"></i> Agregar título
    </button>
  </div>
  <div class="wizard-summary__card">
    <p class="text-support mb-3">Registre los títulos y certificaciones obtenidos por el empleado.</p>
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
  </div>
</section>