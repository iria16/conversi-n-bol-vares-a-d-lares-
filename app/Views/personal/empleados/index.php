<?php
$pageTitle = 'Gestión de Empleados';
$pageDescription = 'Administre el personal docente, administrativo y auxiliar de la institución.';
$currentNav = 'empleados';
$extraScripts = ['/js/staff.js'];
$breadcrumbs = [
    ['label' => 'Personal', 'href' => null],
    ['label' => 'Empleados', 'href' => null],
];
$stats = $stats ?? ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'cargos' => 0];
$empleados = $empleados ?? [];
$cargos = $cargos ?? [];
$tiposDocumento = $tiposDocumento ?? [];
$gradosAcademicos = $gradosAcademicos ?? [];
$titulos = $titulos ?? [];
$instituciones = $instituciones ?? [];
$tiposInstitucion = $tiposInstitucion ?? [];
$parroquias = $parroquias ?? [];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoEmpleadoModal">
    <i class="bi bi-person-plus"></i> Registrar Empleado
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Empleados';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            'label'     => 'Total empleados',
            'value'     => (int) $stats['total'],
            'icon'      => 'bi-people',
            'tone'      => 'primary',
            'trend'     => 'Registrados',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Personal activo',
            'value'     => (int) $stats['activos'],
            'icon'      => 'bi-person-check',
            'tone'      => 'success',
            'trend'     => 'Activos',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Personal inactivo',
            'value'     => (int) $stats['inactivos'],
            'icon'      => 'bi-person-x',
            'tone'      => 'danger',
            'variant'   => 'alert',
            'trend'     => 'Revisar',
            'trendType' => 'down',
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
  $panelFormAction = BASE_URL . 'staff/index';
  // staff.js se engancha a este id para el filtrado/paginado AJAX,
  // igual que a #tablaEmpleadosBody y #paginacionInfo más abajo.
  $panelFormId = 'filtrosEmpleados';

  ob_start();
  ?>
  <label for="filtroCargo" class="visually-hidden">Cargo</label>
  <select id="filtroCargo" name="cargo" class="form-select" aria-label="Filtrar por cargo">
    <option value="">Tipo (Todos)</option>
    <?php foreach ($cargos as $cargo): ?>
      <option value="<?= (int) $cargo['id'] ?>" <?= (string) ($_GET['cargo'] ?? '') === (string) $cargo['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($cargo['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Todos)</option>
    <option value="activo"   <?= ($_GET['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
    <option value="inactivo" <?= ($_GET['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre, cédula o cargo...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Empleado / Cédula / Cargo / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 30%;">
    <col style="width: 18%;">
    <col style="width: 22%;">
    <col style="width: 14%;">
    <col style="width: 16%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Empleado</th>
    <th scope="col">Cédula</th>
    <th scope="col">Cargo</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($empleados as $empleado):
    $nombre    = $empleado['nombre'] ?? '';
    $cedula    = $empleado['cedula'] ?? '';
    $cargo     = $empleado['cargo'] ?? '';
    $iniciales = $empleado['iniciales'] ?? '';
    $estado    = $empleado['estado'] ?? 'inactivo';
    $activo    = $estado === 'activo';
  ?>
    <tr class="<?= $activo ? '' : 'is-muted' ?>">
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <?php
            $avatarInitials = $iniciales;
            $avatarSize     = 'md';
            $avatarTone     = 'primary';
            $avatarSrc      = !empty($empleado['foto']) ? rtrim(BASE_URL, '/') . $empleado['foto'] : '';
            $avatarAlt      = $nombre;
            include __DIR__ . '/../components/avatar.php';
          ?>
          <span class="avatar-group__name"><?= htmlspecialchars($nombre) ?></span>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($cedula) ?></td>
      <td class="text-support"><?= htmlspecialchars($cargo) ?></td>
      <td>
        <?php
          $badgeLabel  = ucfirst($estado);
          $badgeStatus = $activo ? 'active' : 'inactive';
          include __DIR__ . '/../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button" class="action-btn action-btn--view btn-ver-empleado" title="Ver detalle" data-id="<?= (int) $empleado['id'] ?>">
            <i class="bi bi-eye"></i>
          </button>
          <button type="button" class="action-btn action-btn--edit btn-editar-empleado" title="Editar empleado" data-id="<?= (int) $empleado['id'] ?>">
            <i class="bi bi-pencil"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0">
            <input class="form-check-input toggle-empleado" type="checkbox" role="switch"
              data-id="<?= (int) $empleado['id'] ?>"
              <?= $activo ? 'checked' : '' ?>
              aria-label="Cambiar estado de <?= htmlspecialchars($nombre) ?>">
          </div>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$empleados): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron empleados registrados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();
  // staff.js reemplaza solo las filas de este tbody tras filtrar/paginar por AJAX.
  $panelTableBodyId = 'tablaEmpleadosBody';

  $panelSummary   = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                  . ' de ' . (int) $paginacion['total'] . ' empleados';
  // staff.js actualiza este span tras cada respuesta AJAX.
  $panelSummaryId = 'paginacionInfo';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
?>

<?php
include __DIR__ . '/modals/';
include __DIR__ . '/modals/';
include __DIR__ . '/modals/';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';