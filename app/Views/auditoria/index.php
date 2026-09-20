<?php
/**
 * Vista: audit/index.php
 * Bitácora del Sistema — registro detallado de acciones y movimientos.
 *
 * Espera del controlador:
 * @var array  $stats       ['actividad_hoy','alertas','usuarios_activos']
 * @var array  $registros   cada uno: ['fecha','hora','usuario','usuario_login','iniciales',
 *                          'avatar_color','avatar_tipo','avatar_foto',
 *                          'accion' => 'login'|'create'|'update'|'delete'|'query'|'export',
 *                          'accion_label','modulo','ip']
 * @var array  $acciones    lista de acciones para el filtro [valor => etiqueta]
 * @var array  $filters     ['accion' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion  ['desde','hasta','total','pagina_actual','total_paginas']
 * @var string $fechaDesde
 * @var string $fechaHasta
 */

$pageTitle  = 'Bitácora del Sistema';
$currentNav = 'bitacora';

$breadcrumbs = [
    ['label' => 'Administración', 'href' => null],
    ['label' => 'Bitácora', 'href' => null],
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <a href="<?= htmlspecialchars(BASE_URL) ?>bitacora/exportar" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
    <i class="bi bi-download" aria-hidden="true"></i>
    Exportar
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Bitácora del Sistema';
  $pageSubheading    = 'Registro detallado de acciones y movimientos en la plataforma.';
  include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel = 'Actividad Hoy';
      $statValue = (int) $stats['actividad_hoy'] . ' Acciones';
      $statIcon  = 'bi-graph-up';
      $statTone  = 'primary';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel = 'Alertas de Seguridad';
      $statValue = (int) $stats['alertas'];
      $statIcon  = 'bi-exclamation-triangle-fill';
      $statTone  = 'danger';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel = 'Usuarios Activos';
      $statValue = (int) $stats['usuarios_activos'];
      $statIcon  = 'bi-person-check-fill';
      $statTone  = 'success';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'bitacora/index';

  ob_start();
  ?>
  <div class="d-flex align-items-center gap-2">
    <label for="filtroDesde" class="visually-hidden">Desde</label>
    <input type="date" id="filtroDesde" name="desde" class="form-control" value="<?= htmlspecialchars($fechaDesde ?? '') ?>">
    <span class="text-support">a</span>
    <label for="filtroHasta" class="visually-hidden">Hasta</label>
    <input type="date" id="filtroHasta" name="hasta" class="form-control" value="<?= htmlspecialchars($fechaHasta ?? '') ?>">
  </div>

  <label for="filtroAccion" class="visually-hidden">Acción</label>
  <select id="filtroAccion" name="accion" class="form-select">
    <option value="">Acción (Todas)</option>
    <?php foreach ($acciones as $valor => $etiqueta): ?>
      <option value="<?= htmlspecialchars($valor) ?>" <?= ($filters['accion'] ?? '') === $valor ? 'selected' : '' ?>>
        <?= htmlspecialchars($etiqueta) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $filters['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por usuario, módulo o IP...';

  // ---------- Data panel: colgroup ----------
  // Anchos en px, no en %: _tables.scss fija .table en min-width: 720px y, en
  // móvil, un % se calcula sobre esos 720px. Con 16% la columna Acción medía
  // ~115px y su badge (nowrap, ~122px) se salía sobre la columna Módulo.
  // Con table-layout: fixed la tabla crece hasta la suma de las columnas
  // (scroll horizontal en móvil) y en escritorio el espacio sobrante se
  // reparte entre ellas.
  // Orden: Fecha y Hora / Usuario / Acción / Módulo / Dirección IP.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 120px;">
    <col style="width: 220px;">
    <col style="width: 170px;">
    <col style="width: 170px;">
    <col style="width: 150px;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Fecha y Hora</th>
    <th scope="col">Usuario</th>
    <th scope="col">Acción</th>
    <th scope="col">Módulo</th>
    <th scope="col">Dirección IP</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($registros as $r):
  ?>
    <tr>
      <td class="text-support">
        <?= htmlspecialchars($r['fecha']) ?><br>
        <span class="text-body-tertiary"><?= htmlspecialchars($r['hora']) ?></span>
      </td>
      <td>
        <div class="avatar-group">
          <?php
            // El texto alternativo usa el mismo nombre que se muestra en
            // pantalla (usuario), para que coincida lo visual y lo accesible.
            $avatarSize     = 'sm';
            $avatarSrc      = ($r['avatar_tipo'] ?? 'iniciales') === 'foto'
                ? htmlspecialchars(BASE_URL . $r['avatar_foto'])
                : '';
            $avatarAlt      = $r['usuario'];
            $avatarTone     = $r['avatar_color'] ?? 'primary';
            $avatarInitials = $r['iniciales'];
            include __DIR__ . '/../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($r['usuario']) ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($r['usuario_login']) ?></div>
          </div>
        </div>
      </td>
      <td>
        <span class="action-badge action-badge--<?= htmlspecialchars($r['accion']) ?>">
          <?= htmlspecialchars($r['accion_label']) ?>
        </span>
      </td>
      <td class="text-support"><?= htmlspecialchars($r['modulo']) ?></td>
      <td class="text-support"><?= htmlspecialchars($r['ip']) ?></td>
    </tr>
  <?php endforeach; ?>

  <?php if (empty($registros)): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron registros con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' registros';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
?>

<?php
$pageContent  = ob_get_clean();
$extraScripts = ['/js/bitacora.js'];
include __DIR__ . '/../layouts/app.php';