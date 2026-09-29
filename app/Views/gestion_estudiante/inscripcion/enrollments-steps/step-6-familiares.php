<?php
/**
 * Paso 6 - Familiares y representante legal.
 *
 * El estudiante es menor de edad, así que necesita un REPRESENTANTE LEGAL:
 * esa persona es la que autoriza los retiros y la que hay que localizar si
 * hay que avisarle algo, por lo que su contacto y su domicilio son
 * obligatorios. Quien lo sea lo elige quien registra en el sub-modal (solo
 * puede quedar uno). Los demás familiares que se agreguen son opcionales y
 * solo necesitan un teléfono principal.
 *
 * Sin variables externas: el JS (enrollments.js) mantiene el acumulador y lo
 * serializa en el hidden #familiaresJson del formulario.
 */
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Familiares y representante legal</span>
  </div>

  <p class="text-support">
    <strong>Obligatorio:</strong> un representante legal.
  </p>

  <div class="d-flex justify-content-end mb-3">
    <button type="button" class="wizard-btn-outline-sm" id="btnAbrirAgregarFamiliar">
      <i class="bi bi-person-plus" aria-hidden="true"></i> <span id="textoBotonFamiliar">Agregar familiar</span>
    </button>
  </div>

  <div class="wizard-summary__card">
    <div class="table-responsive">
      <table class="wizard-titulos-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Parentesco</th>
            <th>Rol</th>
            <th class="text-center">Activo · Editar</th>
          </tr>
        </thead>
        <tbody id="tablaFamiliaresBody">
          <tr id="tablaFamiliaresVacio">
            <td colspan="4" class="text-center text-support py-4">
              Todavía no has agregado ningún familiar.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>
