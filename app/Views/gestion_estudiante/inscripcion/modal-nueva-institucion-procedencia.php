<?php
/**
 * app/Views/gestion_estudiante/inscripcion/modal-nueva-institucion-procedencia.php
 * Alta rápida de la institución de procedencia desde el paso 2 del wizard.
 * Es el mismo camino que el del wizard de empleados
 * (empleados/modals/new-institution-modal.php) sobre components/modal.php, con
 * dos diferencias: el título es «Institución» y el tipo queda limitado a las
 * etapas del sistema escolar —preescolar y escuela (primaria)—, porque acá solo
 * se registran instituciones de procedencia de un estudiante.
 *
 * El guardado lo maneja enrollments.js (btnGuardarNuevaInstitucionProcedencia)
 * contra inscripciones/guardarInstitucion.
 *
 * Variables en scope: $tiposInstitucionEstudiante (App\Support\Instituciones)
 */
$tipoProcedencia = $tiposInstitucionEstudiante ?? [];

ob_start();
?>
<div class="mb-3">
  <label class="label-sigde" for="nuevaInstitucionProcedenciaTipo">Tipo de Institución *</label>
  <select class="form-select" id="nuevaInstitucionProcedenciaTipo" required>
    <option value="">Seleccione el tipo...</option>
    <?php foreach ($tipoProcedencia as $tipo): ?>
      <option value="<?= htmlspecialchars($tipo['tipo']) ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="text-support mt-1" style="font-size: 0.75rem">
    Solo preescolar o escuela: es la institución de donde viene el estudiante.
  </div>
  <div class="invalid-feedback">Seleccione el tipo de institución.</div>
</div>
<div>
  <label class="label-sigde" for="nuevaInstitucionProcedenciaNombre">Nombre de la Institución *</label>
  <input
    type="text"
    class="form-control"
    id="nuevaInstitucionProcedenciaNombre"
    placeholder="Ej: Escuela Juan Manuel Aristeguieta"
    maxlength="100"
    required
  >
  <div class="invalid-feedback">Escribe el nombre de la institución.</div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnGuardarNuevaInstitucionProcedencia">
  <i class="bi bi-check-lg" aria-hidden="true"></i> Guardar Registro
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalNuevaInstitucionProcedencia';
$modalTitle    = 'Institución';
$modalSubtitle = 'Registre la institución de procedencia del estudiante.';

/* Mismo ancho base (~500px) que «Registrar Nueva Institución» del wizard de
   empleados: los dos tienen la misma estructura de dos campos. */
$modalSize     = '';

include __DIR__ . '/../../components/modal.php';
