<?php
/**
 * Modal: Ver Detalle de Empleado
 * Se puebla vía JS tras hacer GET a staff/getByIdAjax?id=X
 * Usa components/modal.php + clases del sistema de diseño del proyecto.
 */
ob_start();
?>
<div class="user-detail__header">
  <span class="avatar avatar--xl avatar--primary" id="verEmpleado__avatar">
    <span id="verEmpleado__iniciales">EE</span>
  </span>
  <div class="user-detail__name" id="verEmpleado__nombre">—</div>
  <div class="user-detail__meta">
    <span class="status-badge status-badge--active" id="verEmpleado__estadoBadge">—</span>
    <span class="user-detail__meta-sep">•</span>
    <span id="verEmpleado__cargo">—</span>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-person"></i>Información personal
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Nombre completo</div>
      <div class="detail-list__value" id="verEmpleado__nombreCompleto">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Documento de identidad</div>
      <div class="detail-list__value" id="verEmpleado__cedula">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Fecha de nacimiento</div>
      <div class="detail-list__value" id="verEmpleado__nacimiento">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Sexo</div>
      <div class="detail-list__value" id="verEmpleado__sexo">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Nacionalidad</div>
      <div class="detail-list__value" id="verEmpleado__nacionalidad">—</div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="detail-section__label">
      <i class="bi bi-briefcase"></i>Información laboral
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Cargo</div>
      <div class="detail-list__value" id="verEmpleado__cargoDetalle">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Fecha de ingreso</div>
      <div class="detail-list__value" id="verEmpleado__fechaIngreso">—</div>
    </div>
    <div class="detail-section__label mt-3">
      <i class="bi bi-telephone"></i>Contacto
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Teléfono principal</div>
      <div class="detail-list__value" id="verEmpleado__telefono">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Correo electrónico</div>
      <div class="detail-list__value" id="verEmpleado__correo">—</div>
    </div>
  </div>
</div>

<div class="user-detail__status-bar">
  <div>
    <div class="label-sigde mb-1">Estado</div>
    <div class="user-detail__status-value" id="verEmpleado__estadoValor">—</div>
  </div>
  <div class="text-md-end">
    <div class="label-sigde mb-1">Formación académica</div>
    <div class="detail-list__value" id="verEmpleado__formacion">—</div>
  </div>
</div>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'verEmpleadoModal';
$modalTitle    = 'Detalle del Empleado';
$modalSubtitle = 'Vista detallada del perfil institucional';
$modalSize     = 'lg';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';

include __DIR__ . '/../components/modal.php';
