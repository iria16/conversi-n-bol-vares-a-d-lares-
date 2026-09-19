<?php
$pageTitle = 'Asignación Docente';
$pageDescription = 'Asigne un docente titular a las secciones configuradas en la Estructura Académica.';
$currentNav = 'asignacion-docente';
$extraScripts = ['/js/teacher-assignment.js'];
$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Asignación docente', 'href' => null],
];
$stats = $stats ?? ['total' => 0, 'asignadas' => 0, 'pendientes' => 0, 'docentes' => 0];
$asignaciones = $asignaciones ?? [];
$anios = $anios ?? [];
$grados = $grados ?? [];
$secciones = $secciones ?? [];
$estructuras = $estructuras ?? [];
$docentes = $docentes ?? [];
$tiposAsignacion = $tiposAsignacion ?? [];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaAsignacionModal">
    <i class="bi bi-plus-lg"></i> Nueva Asignación
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Asignación Docente';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            'label'     => 'Aulas con docente',
            'value'     => (int) $stats['asignadas'] . ' / ' . (int) $stats['total'],
            'icon'      => 'bi-mortarboard',
            'tone'      => 'primary',
            'trend'     => 'Asignadas',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Aulas por asignar',
            'value'     => (int) $stats['pendientes'],
            'icon'      => 'bi-person-exclamation',
            'tone'      => 'danger',
            'trend'     => 'Pendientes',
            'trendType' => 'down',
        ],
        [
            'label'     => 'Docentes activos',
            'value'     => (int) $stats['docentes'],
            'icon'      => 'bi-people',
            'tone'      => 'light',
            'variant'   => 'highlighted',
            'trend'     => 'Disponibles',
            'trendType' => 'neutral',
        ],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel     = $card['label'];
        $statValue     = $card['value'];
        $statIcon      = $card['icon'];
        $statTone      = $card['tone'];
        $statTrend     = $card['trend'];
        $statTrendType = $card['trendType'];
        $statVariant   = $card['variant'] ?? null;
        include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'teacherAssignment/index';

  ob_start();
  ?>
  <label for="filtroAnio" class="visually-hidden">Año escolar</label>
  <select id="filtroAnio" name="anio" class="form-select" aria-label="Filtrar por año">
    <option value="">Año escolar (Todos)</option>
    <?php foreach ($anios as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['anio'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroGrado" class="visually-hidden">Grado</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['grado'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $option): ?>
      <option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['seccion'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($option['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por docente o grado...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Grado / Sección / Docente asignado / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 18%;">
    <col style="width: 18%;">
    <col style="width: 24%;">
    <col style="width: 18%;">
    <col style="width: 22%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Grado</th>
    <th scope="col">Sección</th>
    <th scope="col">Docente asignado</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($asignaciones as $item):
    $grado         = $item['grado'] ?? '';
    $seccion       = $item['seccion'] ?? '';
    $turno         = $item['turno'] ?? '';
    $docente       = $item['docente'] ?? '';
    $estado        = $item['estado'] ?? 'pendiente';
    $estructuraId  = (int) ($item['estructura_id'] ?? 0);
    $asignacionId  = (int) ($item['id_asignacion'] ?? 0);
    $asignado      = $estado === 'asignado';
  ?>
    <tr>
      <td class="fw-semibold"><?= htmlspecialchars($grado) ?></td>
      <td>
        <?= htmlspecialchars($seccion) ?>
        <div class="text-support small"><?= htmlspecialchars($turno) ?></div>
      </td>
      <td>
        <?php if ($docente !== ''): ?>
          <div class="avatar-group d-flex align-items-center gap-2">
            <?php
              $avatarSize     = 'sm';
              $avatarTone     = 'primary';
              $avatarSrc      = '';
              $avatarAlt      = $docente;
              $avatarInitials = mb_strtoupper(mb_substr($docente, 0, 1));
              include __DIR__ . '/../components/avatar.php';
            ?>
            <span><?= htmlspecialchars($docente) ?></span>
          </div>
        <?php else: ?>
          <span class="text-support">Por asignar</span>
        <?php endif; ?>
      </td>
      <td>
        <?php
          $badgeLabel  = $asignado ? 'Asignado' : 'Pendiente';
          $badgeStatus = $asignado ? 'active' : 'pending';
          include __DIR__ . '/../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
            class="action-btn action-btn--edit editar-asignacion"
            data-id="<?= $estructuraId ?>"
            data-asignacion-id="<?= $asignacionId ?>"
            title="Asignar o editar docente">
            <i class="bi bi-pencil"></i>
          </button>
          <?php if ($asignacionId): ?>
            <button type="button"
              class="action-btn action-btn--danger quitar-asignacion"
              data-id="<?= $asignacionId ?>"
              title="Retirar asignación">
              <i class="bi bi-trash"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$asignaciones): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron secciones configuradas.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' secciones registradas';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
?>

<?php
$modalId = 'nuevaAsignacionModal';
$modalTitle = 'Nueva Asignación';
$modalSubtitle = 'Asigne un docente a una sección académica.';
$formId = 'formNuevaAsignacion';
include __DIR__ . '/assignment-modal.php';

$modalId = 'editarAsignacionModal';
$modalTitle = 'Editar Asignación';
$modalSubtitle = 'Actualice el docente responsable de la sección.';
$formId = 'formEditarAsignacion';
include __DIR__ . '/assignment-modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';