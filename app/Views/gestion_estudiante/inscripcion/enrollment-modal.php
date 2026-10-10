<?php
/**
 * app/Views/gestion_estudiante/inscripcion/enrollment-modal.php
 * Modal "Registrar Nueva Inscripción" — wizard de 7 pasos, armado sobre
 * components/modal.php igual que el wizard de empleados
 * (empleados/modals/employee-modal.php):
 * - el stepper entra por $modalHeaderExtra (entre el header y el body)
 * - el <form> vive en el body y los botones del footer lo referencian con
 * form="..."
 * - $modalSize 'lg' y $modalStatic true (no se cierra por error)
 *
 * Para agregar/quitar un paso basta con editar el arreglo $pasos: alimenta el
 * stepper y los paneles a la vez.
 *
 * Variables del controlador (getExtraIndexData):
 * $gradosModal array [ ['id'=>..,'nombre'=>..] ] grados sueltos,
 * los del colegio anterior (paso 2)
 * $anioActivo array ['id'=>..,'nombre'=>..]
 * $academico array con los grados, secciones y turnos de las
 * COMBINACIONES registradas en Estructura Académica
 * para el año activo, con la disponibilidad de
 * cada una (paso 3). Lo produce
 * InscripcionesModel::opcionesPasoAcademico().
 * $tiposInstitucionEstudiante array [ ['tipo'=>..,'nombre'=>..] ] solo
 * preescolar/primaria
*/
$gradosModal   = $gradosModal ?? [];
$anioActivo    = $anioActivo ?? ['id' => '', 'nombre' => ''];

$pasos = [
1 => ['label' => 'Personales',    'vista' => 'step-1-personales'],
2 => ['label' => 'Procedencia',   'vista' => 'step-2-procedencia'],
3 => ['label' => 'Académico',     'vista' => 'step-3-academico'],
4 => ['label' => 'Adicional',     'vista' => 'step-4-adicional'],
5 => ['label' => 'Recaudos',      'vista' => 'step-5-recaudos'],
6 => ['label' => 'Familiares',    'vista' => 'step-6-familiares'],
7 => ['label' => 'Confirmación',  'vista' => 'step-7-resumen'],
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
<form id="formNuevaInscripcion" novalidate>
  <?php foreach ($pasos as $numero => $paso): ?>
    <div class="wizard-panel <?= $numero === 1 ? 'is-active' : '' ?>" data-step="<?= $numero ?>">
      <?php include __DIR__ . '/enrollments-steps/' . $paso['vista'] . '.php'; ?>
    </div>
  <?php endforeach; ?>

  <!-- Acumulador de familiares agregados en el paso 6 -->
  <input type="hidden" name="familiares_json" id="familiaresJson" value="[]">
</form>
<?php
$modalBody = ob_get_clean();

// ---------- Footer ----------
ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-outline-secondary d-none" id="btnWizardAtras">
  <i class="bi bi-arrow-left" aria-hidden="true"></i> Atrás
</button>
<button type="button" class="btn btn-primary" id="btnWizardSiguiente">
  Siguiente <i class="bi bi-arrow-right" aria-hidden="true"></i>
</button>
<button type="submit" form="formNuevaInscripcion" class="btn btn-primary d-none" id="btnWizardFinalizar">
  Confirmar Inscripción <i class="bi bi-check-lg" aria-hidden="true"></i>
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'nuevaInscripcionModal';
$modalTitle    = 'Registrar Nueva Inscripción';
$modalSubtitle = 'Complete los datos del estudiante en 7 pasos.';
$modalSize     = 'lg';
$modalStatic   = true;
include __DIR__ . '/../../components/modal.php';

// ---------- Modales anidados (deben quedar como hermanos inmediatos del wizard,
// ver "Modales anidados" en _form-wizard.scss) ----------
include __DIR__ . '/modal-agregar-familiar.php';
include __DIR__ . '/modal-nueva-institucion-procedencia.php';
