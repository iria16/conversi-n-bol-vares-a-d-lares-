<?php
/**
 * Modal principal de Ratificación
 */
ob_start();
?>
<?php /* El año al que se ratifica es FIJO: no hay nada que elegir, es el mismo para
   toda la pantalla. Por eso son dos campos y no uno.

   El texto (readonly) es para que la secretaria lo lea, y el oculto lleva el id que
   necesita el backend. No se manda solo el texto porque un <input readonly> se puede
   falsear igual en el POST, y "2026-2027" escrito a mano no distingue un período de
   otro ni se puede validar contra la base. El modelo contrasta el id recibido con el
   año destino que él resuelve, así que aunque el campo oculto se manipule no se
   escribe en otro período.

   El año se imprime desde $anioDestino, que es el ACTIVO y, si no hay ninguno
   abierto, el siguiente PLANIFICADO para que la pantalla no quede muda. */ ?>
<input type="hidden" name="id_anio_escolar" id="ratificarAnioDestinoId"
       value="<?= (int) ($anioDestino['id'] ?? 0) ?>"
       data-nombre="<?= htmlspecialchars($anioDestino['nombre'] ?? '—') ?>">

<form id="formRatificar" action="<?= BASE_URL ?>ratificacion/ratificar" method="POST">
  <input type="hidden" name="id" id="ratificarEstudianteId">

  <?php /* Los filtros del listado viajan con el POST para que ratificar no los
     borre: en el cierre de año se ratifica de a grupos con un filtro puesto, y
     perderlo en cada ratificación obliga a escribirlo de nuevo. Solo los campos que el
     listado ya tiene; el JS los rellena con lo que hay en la URL. */ ?>
  <?php foreach (['q', 'grado', 'seccion', 'turno', 'page'] as $campoFiltro): ?>
    <input type="hidden" name="<?= htmlspecialchars($campoFiltro) ?>" id="ratificarFiltro<?= htmlspecialchars($campoFiltro) ?>" value="">
  <?php endforeach; ?>
  
  <div class="mb-4">
    <div class="d-flex align-items-center mb-3">
      <div class="bg-warning rounded" style="width: 4px; height: 16px; margin-right: 8px;"></div>
      <h6 class="mb-0 fw-bold">Estado Actual</h6>
    </div>
    
    <div class="border rounded p-3">
      <div class="d-flex align-items-center mb-3">
        <div class="avatar avatar--lg avatar--primary me-3" id="ratificarAvatar">
          <img src="" alt="" class="d-none" id="ratificarAvatarImg">
          <span id="ratificarAvatarInitials"></span>
        </div>
        <div class="flex-grow-1">
          <div class="fw-bold text-dark" id="ratificarNombre"></div>
          <div class="row mt-1">
            <div class="col-6">
              <div class="text-muted small">CÉDULA ID</div>
              <div class="fw-medium" id="ratificarCedula"></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">ESTATUS ACTUAL</div>
              <div class="fw-medium text-success" id="ratificarEstatus"></div>
            </div>
            <div class="col-6 mt-2">
              <div class="text-muted small">AÑO ESCOLAR</div>
              <div class="fw-medium" id="ratificarAnioActual"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 pt-3 border-top">
        <button type="button" class="btn btn-link p-0 text-decoration-none shadow-none btn-editar-datos" style="color: #0b2253;">
          <i class="bi bi-pencil me-1"></i> Editar datos del estudiante o familiares
        </button>
      </div>
    </div>
  </div>

  <div>
    <div class="d-flex align-items-center mb-3">
      <div class="bg-warning rounded" style="width: 4px; height: 16px; margin-right: 8px;"></div>
      <h6 class="mb-0 fw-bold">Nueva Matrícula</h6>
    </div>
    <?php /* El AÑO es fijo y lo imprime el servidor: es el año escolar activo, el
       mismo para toda la lista, y no hay nada que elegir. Grado, sección y turno
       sí son <select> y sí se eligen: se mandan en el POST y el modelo los respeta
       (estructuraDestino()). Lo que no se mande se deduce de la matrícula de
       origen, como antes.

       Las opciones salen de $combinaciones (las combinaciones registradas en el
       año activo). No se ofrecen secciones sueltas del catálogo: si una
       combinación no está registrada, esa sección no existe para este periodo. */ ?>

    <div class="border rounded p-3">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label text-muted small mb-1" for="ratificarAnioEnCurso">Año Escolar</label>
          <?php /* Campo bloqueado con candado: el año al que se ratifica es el año
             escolar de destino y no hay nada que elegir. Va como readonly (y no
             disabled) para que siga siendo un input del formulario, legible y
             copiable, como el campo del año en el paso 3 del wizard de
             inscripción. El id no viaja por aquí sino por el campo oculto de
             arriba. */ ?>
          <div class="input-icon-group">
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
            <input type="text" class="form-control" id="ratificarAnioEnCurso"
                   value="<?= htmlspecialchars($anioEnCurso ?? '—') ?>" readonly>
          </div>
          <?php if (($anioDestino['estado'] ?? '') !== 'ACTIVO'): ?>
            <?php /* Sin período abierto el año se muestra igual, pero avisando que no
               se puede ratificar: si no, la secretaria llenaría el formulario y
               solo se entera al final, cuando el servidor lo rechaza. */ ?>
            <div class="form-text text-warning-emphasis">
              <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
              <?= ($anioDestino['id'] ?? 0) > 0
                    ? 'Este período aún no está abierto: se puede ver, pero no ratificar hasta que se abra.'
                    : 'No hay ningún año escolar configurado.' ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label text-muted small mb-1" for="ratificarGradoNuevo">Grado a Cursar *</label>
          <?php /* Predictivo pero editable: el servidor deja marcado el grado
             siguiente al que cursa el alumno, y la secretaria lo cambia si repite o
             si va a otro. El <select> va vacío a propósito —lo puebla el JS con las
             combinaciones del año destino— para no tener dos fuentes de verdad. */ ?>
          <select class="form-select" id="ratificarGradoNuevo" name="id_grado" required></select>
          <div class="form-text" id="ratificarGradoAviso" hidden></div>
        </div>
        <div class="col-md-6">
          <label class="form-label text-muted small mb-1" for="ratificarSeccionNuevo">Sección *</label>
          <?php /* Se recarga por AJAX cuando cambia el grado (ratificacion.js), y
             solo trae las secciones que ese grado tiene en el año fijo. Por eso no
             trae nada impreso: la lista depende del grado, que es precisamente lo
             que cambia. */ ?>
          <select class="form-select" id="ratificarSeccionNuevo" name="id_seccion" required></select>
          <div class="form-text" id="ratificarSeccionAviso" hidden></div>
        </div>
        <div class="col-md-6">
          <label class="form-label text-muted small mb-1" for="ratificarTurnoNuevo">Turno *</label>
          <select class="form-select" id="ratificarTurnoNuevo" name="id_turno" required></select>
        </div>
      </div>
      <?php /* Justificación del salto de grado. Se muestra siempre y no se marca como
             obligatoria en el HTML porque solo hace falta cuando el destino está más de
             un nivel por encima del origen; de eso avisa el servidor con un mensaje que
             explica el motivo. Ocultarla o mostrarla según el valor del <select> dejaría
             el mensaje desincronizado con lo que el formulario permite. */ ?>
      <div class="mt-3">
        <label class="form-label text-muted small mb-1" for="ratificarMotivo">
          Justificación si se adelanta más de un grado
        </label>
        <textarea class="form-control" id="ratificarMotivo" name="motivo" rows="2" maxlength="200"
                  placeholder="Ej.: Ingresó convalidación de estudios equivalentes hasta cuarto grado."></textarea>
        <div class="form-text">
          Continuar al siguiente grado no necesita justificación, y repetir el mismo grado tampoco.
          Retroceder de nivel no está permitido. Si se adelanta más de un grado, este texto queda
          registrado en la matrícula del estudiante.
        </div>
      </div>
      <?php if (!$combinaciones): ?>
        <div class="alert alert-warning mt-3 mb-0" role="alert">
          <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
          No hay combinaciones de grado, sección y turno registradas para
          <?= htmlspecialchars($anioDestino['nombre'] ?? 'el año escolar de destino') ?>. Regístrelas en
          Configuración → Estructura Académica para poder ratificar.
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formRatificar" class="btn btn-primary" style="background-color: #0b2253; border-color: #0b2253;">
  <i class="bi bi-person-check me-1"></i> Ratificar Matrícula
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalRatificar';
$modalTitle    = 'Ratificación de Matrícula';
$modalSubtitle = '';
$modalSize     = 'lg'; // Or md? Image 2 looks like md or lg.
include __DIR__ . '/../../../components/modal.php';
