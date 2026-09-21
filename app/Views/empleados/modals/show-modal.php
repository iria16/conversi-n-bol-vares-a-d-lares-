<?php
/**
 * app/Views/staff/modals/show-modal.php
 * Modal "Detalle del Empleado". Se puebla vía JS tras un GET a staff/getByIdAjax?id=X;
 * cada valor se identifica con el id  verEmpleado__<clave>.
 * Usa components/modal.php + clases de user-detail (_user-detail.scss).
 */

// Columnas de detalle: [ etiqueta, clave del id ]. Agregar un dato = agregar una línea.
$columnas = [
    [
        ['icono' => 'bi-person', 'titulo' => 'Información personal', 'campos' => [
            ['Nombre completo',        'nombreCompleto'],
            ['Documento de identidad', 'cedula'],
            ['Fecha de nacimiento',    'nacimiento'],
            ['Sexo',                   'sexo'],
            ['Nacionalidad',           'nacionalidad'],
        ]],
    ],
    [
        ['icono' => 'bi-briefcase', 'titulo' => 'Información laboral', 'campos' => [
            ['Cargo',            'cargoDetalle'],
            ['Fecha de ingreso', 'fechaIngreso'],
        ]],
        ['icono' => 'bi-telephone', 'titulo' => 'Contacto', 'campos' => [
            ['Teléfono principal',  'telefono'],
            ['Correo electrónico',  'correo'],
        ]],
    ],
];

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
  <?php foreach ($columnas as $secciones): ?>
    <div class="col-md-6">
      <?php foreach ($secciones as $i => $seccion): ?>
        <div class="detail-section__label <?= $i > 0 ? 'mt-3' : '' ?>">
          <i class="bi <?= $seccion['icono'] ?>"></i><?= htmlspecialchars($seccion['titulo']) ?>
        </div>
        <?php foreach ($seccion['campos'] as [$etiqueta, $clave]): ?>
          <div class="detail-list__item">
            <div class="label-sigde"><?= htmlspecialchars($etiqueta) ?></div>
            <div class="detail-list__value" id="verEmpleado__<?= $clave ?>">—</div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
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

include __DIR__ . '/../../components/modal.php';