<?php
/**
 * Modal: Ver Detalle de Usuario
 * Se puebla vía JS tras hacer GET a userAccount/getDetalleAjax?id=X
 * Usa components/modal.php + clases de _modal.scss / _user-detail.scss / _status-badge.scss
 */
ob_start();
?>
<div class="user-detail__header">
  <span class="avatar avatar--xl avatar--dark" id="verUsuario__avatar">
    <span id="verUsuario__iniciales">U</span>
  </span>
  <div class="user-detail__name" id="verUsuario__nombre">—</div>
  <div class="user-detail__meta">
    <span class="status-badge status-badge--active status-badge--role" id="verUsuario__rolBadge">—</span>
    <span class="user-detail__meta-sep">•</span>
    <span id="verUsuario__documento">ID: —</span>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-person"></i>Información personal
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Nombre completo</div>
      <div class="detail-list__value" id="verUsuario__nombreCompleto">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Documento de identidad</div>
      <div class="detail-list__value" id="verUsuario__cedula">—</div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-person-vcard"></i>Información de cuenta
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Nombre de usuario</div>
      <div class="detail-list__value" id="verUsuario__nombreUsuario">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Último acceso</div>
      <div class="detail-list__value" id="verUsuario__ultimoAcceso">—</div>
    </div>
  </div>
</div>

<div class="user-detail__status-bar">
  <div>
    <div class="label-sigde mb-1">Estado del sistema</div>
    <div class="user-detail__status-value" id="verUsuario__estadoValor">—</div>
  </div>
  <div class="text-md-end">
    <div class="label-sigde mb-1">Miembro desde</div>
    <div class="detail-list__value" id="verUsuario__miembroDesde">—</div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

$modalId       = 'verUsuarioModal';
$modalTitle    = 'Detalles del Usuario';
$modalSubtitle = 'Vista detallada del perfil institucional';
$modalSize     = 'lg';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';

include __DIR__ . '/../components/modal.php';
?>