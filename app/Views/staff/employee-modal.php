<?php
/**
 * app/Views/staff/employee-modal.php
 * Modal "Registrar Nuevo Empleado" - wizard de 6 pasos.
 * No usa components/modal.php (ese es de un solo cuerpo); reutiliza las
 * clases visuales .app-modal para verse igual.
 *
 * Variables que debe pasar el controlador (ajustado al esquema real):
 * $cargos            de la tabla `cargo`
 * $gradosAcademicos  de la tabla `grado_academico`
 * $titulos           de la tabla `titulo`
 * $instituciones     de la tabla `institucion`
 * $tiposInstitucion  de la tabla `tipo_institucion` (nuevo, lo usa modal-nueva-institucion.php)
 * $estados           de la tabla `estado` (municipio/parroquia se cargan por AJAX en cascada)
 *
 * Ya NO se usa $tiposDocumento ni $parroquias precargadas (parroquia depende del municipio elegido).
 */
$pasos = [
    1 => 'Personales',
    2 => 'Contacto',
    3 => 'Dirección',
    4 => 'Laboral',
    5 => 'Academia',
    6 => 'Confirmar',
];
?>
<div class="modal fade app-modal" id="nuevoEmpleadoModal" tabindex="-1" aria-labelledby="nuevoEmpleadoModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="nuevoEmpleadoModalLabel">
            <i class="bi bi-person-plus"></i> Registrar Nuevo Empleado
          </h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <!-- Stepper -->
      <div class="wizard-steps" id="wizardSteps">
        <?php foreach ($pasos as $numero => $label): ?>
          <div class="wizard-steps__step <?= $numero === 1 ? 'is-active' : '' ?>" data-step-indicator="<?= $numero ?>">
            <div class="wizard-steps__circle"><?= $numero ?></div>
            <div class="wizard-steps__label"><?= htmlspecialchars($label) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <form id="formNuevoEmpleado" novalidate>
        <div class="modal-body">

          <div class="wizard-panel is-active" data-step="1">
            <?php include __DIR__ . '/employee-steps/step-1-personales.php'; ?>
          </div>
          <div class="wizard-panel" data-step="2">
            <?php include __DIR__ . '/employee-steps/step-2-contacto.php'; ?>
          </div>
          <div class="wizard-panel" data-step="3">
            <?php include __DIR__ . '/employee-steps/step-3-direccion.php'; ?>
          </div>
          <div class="wizard-panel" data-step="4">
            <?php include __DIR__ . '/employee-steps/step-4-laboral.php'; ?>
          </div>
          <div class="wizard-panel" data-step="5">
            <?php include __DIR__ . '/employee-steps/step-5-academico.php'; ?>
          </div>
          <div class="wizard-panel" data-step="6">
            <?php include __DIR__ . '/employee-steps/step-6-confirmacion.php'; ?>
          </div>

          <!-- Acumulador de títulos académicos agregados en el paso 5 -->
          <input type="hidden" name="titulos_json" id="titulosJson" value="[]">
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-outline-secondary d-none" id="btnWizardAtras">
            <i class="bi bi-arrow-left"></i> Atrás
          </button>
          <button type="button" class="btn btn-primary" id="btnWizardSiguiente">
            Siguiente <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="btn btn-primary d-none" id="btnWizardFinalizar">
            Finalizar Registro <i class="bi bi-check-lg"></i>
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/modal-titulo.php'; ?>
<?php include __DIR__ . '/partials/modal-nuevo-titulo.php'; ?>
<?php include __DIR__ . '/partials/modal-nueva-institucion.php'; ?>
<?php include __DIR__ . '/partials/modal-nuevo-tipo-institucion.php'; ?>