<?php
/**
 * Modal: detalle-modal.php
 * Muestra el detalle de una solicitud ya resuelta (aprobada o
 * rechazada), o de un restablecimiento directo hecho por el admin
 * sin solicitud previa. recuperacion.js llena los campos vía JS con
 * la respuesta de getDetalle, incluyendo 'origen' ('solicitud' |
 * 'admin') para alternar el título/texto del bloque de atención sin
 * tocar este HTML.
 */
ob_start();
?>
<div class="text-center mb-3">
  <p class="fw-bold fs-4 mb-1" id="detalleNombre" aria-live="polite">—</p>
  <p class="text-support small mb-0" id="detalleUsuario" aria-live="polite">—</p>
</div>

<hr class="text-body-tertiary">

<div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-3">
  <div>
    <span class="label-sigde d-block mb-1">Estado del trámite</span>
    <span class="status-badge" id="detalleEstado" aria-live="polite">—</span>
  </div>
  <div class="text-end">
    <span class="fw-semibold" id="detalleFecha" aria-live="polite">—</span>
  </div>
</div>

<hr class="text-body-tertiary">

<div class="info-alert" id="detalleAtencionBloque">
  <span class="info-alert__icon"><i class="bi bi-info-circle" aria-hidden="true" id="detalleAtencionIcono"></i></span>
  <div>
    <p class="info-alert__title mb-0" id="detalleAtencionTitulo">Atención de la solicitud</p>
    <p class="info-alert__text" id="detalleAtencionTexto">
      Atendida el <strong id="detalleFechaAtencion" aria-live="polite">—</strong>
      por <strong id="detalleAdmin" aria-live="polite">—</strong>.
    </p>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalDetalleSolicitud';
$modalTitle    = 'Detalle de la Solicitud';
$modalSubtitle = 'Información detallada de la atención de recuperación.';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);