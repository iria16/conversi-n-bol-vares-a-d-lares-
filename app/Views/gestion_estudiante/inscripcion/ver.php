<?php
/**
 * Vista: gestion_estudiante/inscripcion/ver.php
 * Detalle (solo lectura) de una inscripción ya registrada.
*/

$item        = $item ?? [];
$componentes = __DIR__ . '/../../components/';

$pNom = trim($item['primer_nombre'] ?? '');
$sNom = trim($item['segundo_nombre'] ?? '');
$pApe = trim($item['primer_apellido'] ?? '');
$sApe = trim($item['segundo_apellido'] ?? '');

$nom2 = $sNom !== '' ? substr($sNom, 0, 1) . '.' : '';
$ape2 = $sApe !== '' ? substr($sApe, 0, 1) . '.' : '';

$estNombreArray = array_filter([$pNom, $nom2, $pApe, $ape2]);
$nombre = implode(' ', $estNombreArray);

if (empty($nombre) && !empty($item['nombre'])) {
    $nombre = $item['nombre'];
}
$nombre = $nombre ?: 'Estudiante';
$nombre = ucwords(strtolower($nombre));

$pageTitle       = 'Detalle de Inscripción';
$pageDescription = 'Resumen de la inscripción registrada para ' . $nombre . '.';
$currentNav      = 'inscripcion';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => BASE_URL . 'inscripciones/index'],
    ['label' => 'Detalle de inscripción', 'href' => null],
];

$esPrimerIngreso = (int) ($item['es_primer_ingreso'] ?? 0) === 1;
$isModal = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

ob_start();
?>

<?php if (!$isModal): ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Detalle de Inscripción</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>inscripciones/index" class="btn btn-outline-primary">
      <i class="bi bi-arrow-left"></i>
      Volver al listado
    </a>
  </div>
</div>
<?php endif; ?>

<?php if ($isModal): ?>
<?php /* Dentro del modal se usa el mismo lenguaje que los demás modales de
 detalle (empleados/cuentas/ratificación): cabecera user-detail + bloques
 wizard-summary armados con los componentes. El layout de página
 (content-card en dos columnas) se reserva para la vista completa.*/ ?>
<div class="user-detail__header">
  <span class="avatar avatar--xl avatar--<?= htmlspecialchars($item['avatar_color'] ?? 'primary') ?>">
    <?php if (!empty($item['avatar_img'])): ?>
      <img src="<?= htmlspecialchars($item['avatar_img']) ?>" alt="">
    <?php else: ?>
      <?= htmlspecialchars($item['iniciales'] ?? 'ES') ?>
    <?php endif; ?>
  </span>
  <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
  <div class="user-detail__meta">
    <span><?= htmlspecialchars($item['cedula'] ?? '—') ?></span>
    <span class="user-detail__meta-sep">&bull;</span>
    <span>
      <span class="status-badge status-badge--active">
        <i class="bi bi-check-circle"></i> Inscripción completada
      </span>
    </span>
  </div>
</div>

<div class="wizard-summary__row mb-3">
  <?php
  $wsIcono  = 'mortarboard';
  $wsTitulo = 'Ubicación en ' . ($item['anio_escolar'] ?? '—');
  $wsCampos = [
      ['Grado',       null, $item['grado']        ?? '—'],
      ['Sección',     null, $item['seccion']      ?? '—'],
      ['Turno',       null, $item['turno']        ?? '—'],
      ['Año escolar', null, $item['anio_escolar'] ?? '—'],
  ];
  include $componentes . 'wizard-summary-section.php';
  ?>
</div>

<?php
$wsIcono  = 'calendar-check';
$wsTitulo = 'Registro';
$wsClass  = 'mb-3';
$wsCampos = [
    ['Fecha de inscripción', null, $item['fecha_inscripcion'] ?? '—'],
    ['N.º de inscripción',   null, '#' . ($item['numero_inscripcion'] ?? '—')],
];
include $componentes . 'wizard-summary-section.php';

$badgeIngreso = $esPrimerIngreso
    ? '<span class="status-badge status-badge--active">Primera vez</span>'
    : '<span class="status-badge status-badge--pending">Traslado</span>';

$wsIcono  = 'signpost-split';
$wsTitulo = 'Procedencia académica';
$wsCampos = [
    ['Tipo de ingreso',            null, null, $badgeIngreso],
    ['Último grado cursado',       null, ($item['ult_grado_cursado'] ?? '') ?: '—'],
    ['Año escolar de procedencia', null, ($item['anio_escolar_pre']  ?? '') ?: '—'],
    ['Motivo de retiro',           null, ($item['motivo_egreso']     ?? '') ?: '—'],
];
include $componentes . 'wizard-summary-section.php';
?>
<?php else: ?>
<div class="row g-4">

  <!-- ==================== Columna principal ==================== -->
  <div class="col-lg-8">

    <div class="content-card mb-4">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-person-lines-fill me-2"></i>
          Estudiante Inscrito
        </p>
      </div>
      <div class="content-card__body">

        <div class="user-detail__header">
          <span class="avatar avatar--xl avatar--<?= htmlspecialchars($item['avatar_color'] ?? 'primary') ?>">
            <?php if (!empty($item['avatar_img'])): ?>
              <img src="<?= htmlspecialchars($item['avatar_img']) ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars($item['iniciales'] ?? 'ES') ?>
            <?php endif; ?>
          </span>
          <div>
            <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
            <div class="user-detail__meta">
              <span><?= htmlspecialchars($item['cedula'] ?? '—') ?></span>
              <span class="user-detail__meta-sep">•</span>
              <span>
                <span class="status-badge status-badge--active">
                  <i class="bi bi-check-circle"></i> Inscripción completada
                </span>
              </span>
            </div>
          </div>
        </div>

        <div class="detail-section__label mt-4"><i class="bi bi-mortarboard"></i>Ubicación Académica</div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="detail-list__item">
              <div class="label-sigde">Grado</div>
              <div class="detail-list__value fw-semibold"><?= htmlspecialchars($item['grado'] ?? '—') ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="detail-list__item">
              <div class="label-sigde">Sección</div>
              <div class="detail-list__value"><?= htmlspecialchars($item['seccion'] ?? '—') ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="detail-list__item">
              <div class="label-sigde">Turno</div>
              <div class="detail-list__value"><?= htmlspecialchars($item['turno'] ?? '—') ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="detail-list__item">
              <div class="label-sigde">Año escolar</div>
              <div class="detail-list__value"><?= htmlspecialchars($item['anio_escolar'] ?? '—') ?></div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="content-card">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-signpost-split me-2"></i>
          Procedencia Académica
        </p>
      </div>
      <div class="content-card__body">
        <div class="detail-list__item">
          <div class="label-sigde">Tipo de ingreso</div>
          <div class="detail-list__value">
            <?php if ($esPrimerIngreso): ?>
              <span class="status-badge status-badge--active">Primera vez</span>
            <?php else: ?>
              <span class="status-badge status-badge--pending">Traslado</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Último grado cursado</div>
          <div class="detail-list__value"><?= htmlspecialchars(($item['ult_grado_cursado'] ?? '') ?: '—') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Año escolar de procedencia</div>
          <div class="detail-list__value"><?= htmlspecialchars(($item['anio_escolar_pre'] ?? '') ?: '—') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Motivo de retiro</div>
          <div class="detail-list__value"><?= htmlspecialchars(($item['motivo_egreso'] ?? '') ?: '—') ?></div>
        </div>
      </div>
    </div>

  </div>

  <!-- ==================== Columna lateral ==================== -->
  <div class="col-lg-4">

    <div class="content-card mb-4">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-calendar-check me-2"></i>
          Registro
        </p>
      </div>
      <div class="content-card__body">
        <div class="detail-list__item">
          <div class="label-sigde">Fecha de inscripción</div>
          <div class="detail-list__value"><?= htmlspecialchars($item['fecha_inscripcion'] ?? '—') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">N.º de inscripción</div>
          <div class="detail-list__value">#<?= htmlspecialchars((string) ($item['numero_inscripcion'] ?? '—')) ?></div>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-list-check me-2"></i>
          Acciones
        </p>
      </div>
      <div class="content-card__body d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>inscripciones/index" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-arrow-left"></i> Volver al listado
        </a>
        <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-clipboard-check"></i> Ir a ratificación
        </a>
      </div>
    </div>

  </div>

</div>
<?php /* fin de la vista de página completa (!$isModal)*/ ?>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
if ($isModal) {
    echo $pageContent;
} else {
    require __DIR__ . '/../../layouts/app.php';
}