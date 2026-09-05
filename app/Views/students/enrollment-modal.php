<?php
/**
 * app/Views/students/enrollment-modal.php
 * Modal "Registrar Nueva Inscripción" — wizard de 7 pasos.
 * Misma estructura HTML que staff/employee-modal.php: NO usa components/modal.php,
 * reutiliza las clases .app-modal para verse idéntico.
 *
 * Variables del controlador:
 * $grados            array [ ['id'=>..,'nombre'=>..], ... ]
 * $secciones         array [ ['id'=>..,'nombre'=>..], ... ]
 * $turnos            array [ ['id'=>..,'nombre'=>..], ... ]
 * $anioEscolarActivo array ['id'=>..,'nombre'=>..]
 */
$grados            = $grados            ?? [];
$secciones         = $secciones         ?? [];
$turnos            = $turnos            ?? [];
$anioEscolarActivo = $anioEscolarActivo ?? ['id' => '', 'nombre' => ''];

$pasos = [
    1 => 'Personales',
    2 => 'Procedencia',
    3 => 'Académico',
    4 => 'Adicional',
    5 => 'Recaudos',
    6 => 'Familiares',
    7 => 'Resumen',
];
?>
<div class="modal fade app-modal" id="nuevaInscripcionModal" tabindex="-1"
     aria-labelledby="nuevaInscripcionModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="nuevaInscripcionModalLabel">
            <i class="bi bi-person-plus"></i> Registrar Nueva Inscripción
          </h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <!-- Stepper -->
      <div class="wizard-steps" id="wizardStepsInscripcion">
        <?php foreach ($pasos as $numero => $label): ?>
          <div class="wizard-steps__step <?= $numero === 1 ? 'is-active' : '' ?>"
               data-step-indicator="<?= $numero ?>">
            <div class="wizard-steps__circle"><?= $numero ?></div>
            <div class="wizard-steps__label"><?= htmlspecialchars($label) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <form id="formNuevaInscripcion" novalidate>
        <div class="modal-body">

          <!-- Paso 1: Datos Personales -->
          <div class="wizard-panel is-active" data-step="1">
            <?php include __DIR__ . '/enrollments-steps/step-1-personales.php'; ?>
          </div>

          <!-- Paso 2: Procedencia -->
          <div class="wizard-panel" data-step="2">
            <?php include __DIR__ . '/enrollments-steps/step-2-procedencia.php'; ?>
          </div>

          <!-- Paso 3: Información Académica -->
          <div class="wizard-panel" data-step="3">
            <?php include __DIR__ . '/enrollments-steps/step-3-academico.php'; ?>
          </div>

          <!-- Paso 4: Información Adicional -->
          <div class="wizard-panel" data-step="4">
            <?php include __DIR__ . '/enrollments-steps/step-4-adicional.php'; ?>
          </div>

          <!-- Paso 5: Recaudos -->
          <div class="wizard-panel" data-step="5">
            <?php include __DIR__ . '/enrollments-steps/step-5-recaudos.php'; ?>
          </div>

          <!-- Paso 6: Grupo Familiar -->
          <div class="wizard-panel" data-step="6">

            <div class="form-section__label">Grupo Familiar y Representación</div>
            <p class="text-support mb-4">Registre al menos un familiar que actúe como representante legal ante la institución.</p>

            <div class="d-flex justify-content-end mb-3">
              <button type="button" class="wizard-btn-outline-sm" id="btnAbrirAgregarFamiliar">
                <i class="bi bi-person-plus"></i> Agregar familiar
              </button>
            </div>

            <table class="wizard-titulos-table">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Parentesco</th>
                  <th>Rol</th>
                  <th class="text-center">Acción</th>
                </tr>
              </thead>
              <tbody id="tablaFamiliaresBody">
                <tr id="tablaFamiliaresVacio">
                  <td colspan="4" class="text-center text-support py-4">
                    Aún no has agregado ningún familiar.
                  </td>
                </tr>
              </tbody>
            </table>

            <input type="hidden" id="familiaresJson" name="familiares_json" value="[]">
          </div>

          <!-- Paso 7: Resumen -->
          <div class="wizard-panel" data-step="7">

            <div class="form-section__label">Confirmar Registro</div>
            <p class="text-support mb-4">Revisa la información antes de confirmar. Puedes volver a cualquier paso para corregir.</p>

            <div class="wizard-summary__section">
              <div class="wizard-summary__section-header">
                <span class="wizard-summary__icon"><i class="bi bi-person"></i></span>
                <span class="wizard-summary__section-title">Datos Personales</span>
                <button type="button" class="wizard-summary__edit-btn" data-editar-paso="1">
                  <i class="bi bi-pencil"></i> Editar
                </button>
              </div>
              <div class="wizard-summary__grid">
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Nombre completo</div>
                  <div class="wizard-summary__value" data-resumen="nombre_completo">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Documento</div>
                  <div class="wizard-summary__value" data-resumen="documento">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Fecha de nacimiento</div>
                  <div class="wizard-summary__value" data-resumen="fecha_nacimiento">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Sexo</div>
                  <div class="wizard-summary__value" data-resumen="sexo">—</div>
                </div>
              </div>
            </div>

            <div class="wizard-summary__section">
              <div class="wizard-summary__section-header">
                <span class="wizard-summary__icon"><i class="bi bi-signpost-split"></i></span>
                <span class="wizard-summary__section-title">Procedencia</span>
                <button type="button" class="wizard-summary__edit-btn" data-editar-paso="2">
                  <i class="bi bi-pencil"></i> Editar
                </button>
              </div>
              <div class="wizard-summary__grid">
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Tipo de ingreso</div>
                  <div class="wizard-summary__value" data-resumen="tipo_ingreso">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Institución anterior</div>
                  <div class="wizard-summary__value" data-resumen="institucion_anterior">—</div>
                </div>
              </div>
            </div>

            <div class="wizard-summary__section">
              <div class="wizard-summary__section-header">
                <span class="wizard-summary__icon"><i class="bi bi-mortarboard"></i></span>
                <span class="wizard-summary__section-title">Información Académica</span>
                <button type="button" class="wizard-summary__edit-btn" data-editar-paso="3">
                  <i class="bi bi-pencil"></i> Editar
                </button>
              </div>
              <div class="wizard-summary__grid">
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Grado</div>
                  <div class="wizard-summary__value" data-resumen="grado">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Sección</div>
                  <div class="wizard-summary__value" data-resumen="seccion">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Turno</div>
                  <div class="wizard-summary__value" data-resumen="turno">—</div>
                </div>
                <div class="wizard-summary__item">
                  <div class="wizard-summary__label">Año escolar</div>
                  <div class="wizard-summary__value" data-resumen="anio_escolar">—</div>
                </div>
              </div>
            </div>

            <div class="wizard-summary__section">
              <div class="wizard-summary__section-header">
                <span class="wizard-summary__icon"><i class="bi bi-people"></i></span>
                <span class="wizard-summary__section-title">Grupo Familiar</span>
                <button type="button" class="wizard-summary__edit-btn" data-editar-paso="6">
                  <i class="bi bi-pencil"></i> Editar
                </button>
              </div>
              <div id="resumenFamiliares">
                <p class="text-support mb-0">Sin familiares registrados.</p>
              </div>
            </div>

          </div><!-- /paso 7 -->

        </div><!-- /modal-body -->

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-outline-secondary d-none" id="btnWizardAtras">
            <i class="bi bi-arrow-left"></i> Atrás
          </button>
          <button type="button" class="btn btn-primary" id="btnWizardSiguiente">
            Siguiente <i class="bi bi-arrow-right"></i>
          </button>
          <button type="submit" class="btn btn-primary d-none" id="btnWizardFinalizar">
            Confirmar Inscripción <i class="bi bi-check-lg"></i>
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/enrollments-steps/step-7-familiar.php'; ?>
