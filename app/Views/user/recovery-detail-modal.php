<?php
ob_start();
?>
<div class="user-detail__header">
  <span class="avatar avatar--xl avatar--dark" id="recoveryDetail__avatar">
    <span id="recoveryDetail__initials">U</span>
  </span>
  <div class="user-detail__name" id="recoveryDetail__name">—</div>
  <div class="user-detail__meta">
    <span class="status-badge status-badge--pending" id="recoveryDetail__status">Pendiente</span>
    <span class="user-detail__meta-sep">•</span>
    <span id="recoveryDetail__username">—</span>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-person"></i>Información del usuario
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Nombre completo</div>
      <div class="detail-list__value" id="recoveryDetail__fullName">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Cargo</div>
      <div class="detail-list__value" id="recoveryDetail__cargo">—</div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-key"></i>Información de recuperación
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Fecha de solicitud</div>
      <div class="detail-list__value" id="recoveryDetail__requestedAt">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Fecha de atención</div>
      <div class="detail-list__value" id="recoveryDetail__resolvedAt">Pendiente</div>
    </div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();
$modalId = 'recoveryDetailModal';
$modalTitle = 'Detalle de Recuperación';
$modalSubtitle = 'Información de la solicitud de restablecimiento.';
$modalSize = 'lg';
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../components/modal.php';
?>
