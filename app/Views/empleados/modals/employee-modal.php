<?php
/**
 * app/Views/staff/modals/employee-modal.php
 * Wizard "Registrar Nuevo Empleado" de 6 pasos, armado sobre components/modal.php:
 *   - el stepper entra por $modalHeaderExtra (entre el header y el body)
 *   - el <form> vive en el body y los botones del footer lo referencian con form="..."
 *
 * Para agregar/quitar un paso basta con editar el arreglo $pasos:
 * alimenta el stepper y los paneles a la vez.
 *
 * Variables en scope (definidas en index.php):
 *   $cargos, $estados            -> employee-steps/step-4 y step-3
 *   $tiposInstitucion            -> modals/new-institution-modal.php
 *   $titulos, $instituciones, $gradosAcademicos -> modals/add-title-modal.php
 */
$pasos = [
    1 => ['label' => 'Personales', 'vista' => 'step-1-personales'],
    2 => ['label' => 'Contacto',   'vista' => 'step-2-contacto'],
    3 => ['label' => 'Dirección',  'vista' => 'step-3-direccion'],
    4 => ['label' => 'Laboral',    'vista' => 'step-4-laboral'],
    5 => ['label' => 'Academia',   'vista' => 'step-5-academico'],
    6 => ['label' => 'Confirmar',  'vista' => 'step-6-confirmacion'],
];

// ---------- Stepper ----------
ob_start();
?>
<div class="wizard-steps" id="wizardSteps">
  <?php foreach ($pasos as $numero => $paso): ?>
    <div class="wizard-steps__step <?= $numero === 1 ? 'is-active' : '' ?>" data-step-indicator="<?= $numero ?>">
      <div class="wizard-steps__circle"><?= $numero ?></div>
      <div class="wizard-steps__label"><?= htmlspecialchars($paso['label']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<?php
$modalHeaderExtra = ob_get_clean();

// ---------- Cuerpo: formulario con un panel por paso ----------
ob_start();
?>
<form id="formNuevoEmpleado" novalidate>
  <?php foreach ($pasos as $numero => $paso): ?>
    <div class="wizard-panel <?= $numero === 1 ? 'is-active' : '' ?>" data-step="<?= $numero ?>">
      <?php include __DIR__ . '/../employee-steps/' . $paso['vista'] . '.php'; ?>
    </div>
  <?php endforeach; ?>

  <!-- Acumulador de títulos académicos agregados en el paso 5 -->
  <input type="hidden" name="titulos_json" id="titulosJson" value="[]">
</form>
<?php
$modalBody = ob_get_clean();

// ---------- Footer ----------
ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-outline-secondary d-none" id="btnWizardAtras">
  <i class="bi bi-arrow-left"></i> Atrás
</button>
<button type="button" class="btn btn-primary" id="btnWizardSiguiente">
  Siguiente <i class="bi bi-arrow-right"></i>
</button>
<button type="submit" form="formNuevoEmpleado" class="btn btn-primary d-none" id="btnWizardFinalizar">
  Finalizar Registro <i class="bi bi-check-lg"></i>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'nuevoEmpleadoModal';
$modalTitle    = 'Registrar Nuevo Empleado';
$modalSubtitle = 'Complete los datos del trabajador en 6 pasos.';
$modalSize     = 'lg';
$modalStatic   = true;
include __DIR__ . '/../../components/modal.php';

// ---------- Modales anidados (deben quedar como hermanos inmediatos del wizard,
// ver "Modales anidados" en _form-wizard.scss) ----------
include __DIR__ . '/add-title-modal.php';
include __DIR__ . '/new-title-modal.php';
include __DIR__ . '/new-institution-modal.php';