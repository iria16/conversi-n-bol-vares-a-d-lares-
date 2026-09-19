<?php
$pageTitle = 'Retiros Pendientes';
$pageDescription = 'Gestión y procesamiento de solicitudes de retiro escolar.';
$currentNav = 'retiro';
$extraScripts = ['/js/withdrawals.js'];
$breadcrumbs = [
    ['label' => 'Retiros', 'href' => null],
    ['label' => 'Retiros pendientes', 'href' => null],
];
$stats = $stats ?? ['pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
$solicitudes = $solicitudes ?? [];
$aniosEscolares = $aniosEscolares ?? [];
$motivos = $motivos ?? [];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];
ob_start();

  // ---------- Page header ----------
  // Esta vista no tiene botón de acción (a diferencia de las anteriores,
  // que abren un modal de "Nuevo..."), así que no se pasa $pageHeaderActions.
  $pageHeading    = 'Retiros Pendientes';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        ['label' => 'Pendientes', 'value' => (int) $stats['pendientes'], 'icon' => 'bi-hourglass-split',    'tone' => 'warning'],
        ['label' => 'Aprobados',  'value' => (int) $stats['aprobados'],  'icon' => 'bi-check-circle-fill',  'tone' => 'success'],
        ['label' => 'Rechazados', 'value' => (int) $stats['rechazados'], 'icon' => 'bi-x-circle-fill',      'tone' => 'danger'],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel = $card['label'];
        $statValue = $card['value'];
        $statIcon  = $card['icon'];
        $statTone  = $card['tone'];
        include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'withdrawal/index';

  ob_start();
  ?>
  <label for="filtroMotivo" class="visually-hidden">Motivo</label>
  <select id="filtroMotivo" name="motivo" class="form-select" aria-label="Filtrar por motivo">
    <option value="">Motivo (Todos)</option>
    <?php foreach ($motivos as $motivo): ?>
      <option value="<?= (int) $motivo['id'] ?>" <?= (string) ($_GET['motivo'] ?? '') === (string) $motivo['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($motivo['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Todos)</option>
    <option value="pendiente" <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
    <option value="aprobado"  <?= ($_GET['estado'] ?? '') === 'aprobado'  ? 'selected' : '' ?>>Aprobado</option>
    <option value="rechazado" <?= ($_GET['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre, cédula o motivo...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Estudiante / Grado-Sección / Fecha / Motivo / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 22%;">
    <col style="width: 16%;">
    <col style="width: 14%;">
    <col style="width: 22%;">
    <col style="width: 14%;">
    <col style="width: 12%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Estudiante</th>
    <th scope="col">Grado/Sección</th>
    <th scope="col">Fecha solicitud</th>
    <th scope="col">Motivo</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($solicitudes as $item):
    $estudiante     = $item['estudiante'] ?? '';
    $cedula         = $item['cedula'] ?? '';
    $grado          = $item['grado'] ?? '';
    $seccion        = $item['seccion'] ?? '';
    $fechaSolicitud = $item['fecha_solicitud'] ?? '';
    $motivo         = $item['motivo'] ?? '';
    $observaciones  = $item['observaciones'] ?? '';
    $estado         = $item['estado'] ?? 'pendiente';
    $iniciales      = $item['iniciales'] ?? '';

    $estadoClase = $estado === 'aprobado'
        ? 'active'
        : ($estado === 'rechazado' ? 'inactive' : 'pending');
  ?>
    <tr>
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <?php
            $avatarInitials = $iniciales;
            $avatarSize     = 'sm';
            $avatarTone     = 'primary';
            $avatarSrc      = '';
            $avatarAlt      = $estudiante;
            include __DIR__ . '/../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($estudiante) ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($cedula) ?></div>
          </div>
        </div>
      </td>
      <td>
        <?= htmlspecialchars($grado) ?><br>
        <span class="text-support">Sección <?= htmlspecialchars($seccion) ?></span>
      </td>
      <td><?= htmlspecialchars($fechaSolicitud) ?></td>
      <td class="text-support text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($motivo) ?>">
        <?= htmlspecialchars($motivo) ?>
      </td>
      <td>
        <?php
          $badgeLabel  = ucfirst($estado);
          $badgeStatus = $estadoClase;
          include __DIR__ . '/../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
            class="action-btn action-btn--view ver-retiro"
            data-id="<?= (int) $item['id'] ?>"
            data-estudiante="<?= htmlspecialchars($estudiante) ?>"
            data-cedula="<?= htmlspecialchars($cedula) ?>"
            data-grado="<?= htmlspecialchars($grado . ' - Sección ' . $seccion) ?>"
            data-fecha="<?= htmlspecialchars($fechaSolicitud) ?>"
            data-motivo="<?= htmlspecialchars($motivo) ?>"
            data-observaciones="<?= htmlspecialchars($observaciones) ?>"
            data-estado="<?= htmlspecialchars($estado) ?>"
            title="Ver solicitud">
            <i class="bi bi-eye"></i>
          </button>
          <?php if ($estado === 'pendiente'): ?>
            <button type="button" class="action-btn aprobar-retiro" data-id="<?= (int) $item['id'] ?>" title="Aprobar retiro">
              <i class="bi bi-check-circle"></i>
            </button>
            <button type="button" class="action-btn action-btn--danger rechazar-retiro" data-id="<?= (int) $item['id'] ?>" title="Rechazar retiro">
              <i class="bi bi-x-circle"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$solicitudes): ?>
    <tr>
      <td colspan="6" class="text-center text-support py-4">
        No se encontraron solicitudes de retiro.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' solicitudes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
?>

<?php
$modalId       = 'detalleRetiroModal';
$modalTitle    = 'Detalle de Retiro';
$modalSubtitle = 'Información de la solicitud seleccionada.';
ob_start();
?>
<div class="user-detail__header">
  <?php
    $avatarInitials = 'E';
    $avatarSize     = 'xl';
    $avatarTone     = 'primary';
    $avatarSrc      = '';
    $avatarAlt      = '';
    include __DIR__ . '/../components/avatar.php';
  ?>
  <div class="user-detail__name" id="retiroDetalleNombre">—</div>
  <div class="user-detail__meta">
    <span id="retiroDetalleCedula">—</span>
    <span class="user-detail__meta-sep">•</span>
    <span id="retiroDetalleEstado">Pendiente</span>
  </div>
</div>
<div class="row g-4">
  <div class="col-md-6">
    <div class="detail-section__label"><i class="bi bi-person"></i>Estudiante</div>
    <div class="detail-list__item">
      <div class="label-sigde">Grado y sección</div>
      <div class="detail-list__value" id="retiroDetalleGrado">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Fecha de solicitud</div>
      <div class="detail-list__value" id="retiroDetalleFecha">—</div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="detail-section__label"><i class="bi bi-file-text"></i>Motivo</div>
    <div class="detail-list__item">
      <div class="detail-list__value" id="retiroDetalleMotivo">—</div>
    </div>
    <div class="detail-list__item">
      <div class="label-sigde">Observaciones</div>
      <div class="detail-list__value" id="retiroDetalleObservaciones">—</div>
    </div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../components/modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';