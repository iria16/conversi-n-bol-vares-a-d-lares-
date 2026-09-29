<?php
/**
 * Vista: gestion_estudiante/egreso/index.php
 * Egresos / Graduación — gestión de culminación académica y graduación masiva.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php. El JS vive en public/assets/js/egresos.js
 * ($extraScripts), no en un <script> dentro de la vista.
 *
 * Espera del controlador:
 * - array $stats        ['total' => int, 'graduados' => int, 'pendientes' => int]
 * - array $candidatos   cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 *                        'avatar_img' (opcional),'grado','seccion',
 *                        'estado' => 'graduado'|'pendiente']
 * - array $secciones    lista de secciones para el filtro
 * - array $filters      valores actuales de los filtros aplicados
 * - array $paginacion   ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $candidatos
 * @var array $secciones
 * @var array $filters
 * @var array $paginacion
 */

$pageTitle       = 'Graduación';
$pageDescription = 'Gestione la culminación académica de los estudiantes y el registro de egresos por periodo lectivo.';
$currentNav      = 'egreso';
$extraScripts    = ['/js/egresos.js'];

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Egresos y Graduación', 'href' => null],
];

$stats      = $stats ?? ['total' => 0, 'graduados' => 0, 'pendientes' => 0];
$candidatos = $candidatos ?? [];
$secciones  = $secciones ?? [];
$filters    = $filters ?? ['seccion' => '', 'q' => ''];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

$totalCandidatos = (int) $stats['total'];
$pctGraduados    = $totalCandidatos > 0 ? (int) round(((int) $stats['graduados'] / $totalCandidatos) * 100) : 0;
$pctPendientes   = $totalCandidatos > 0 ? (int) round(((int) $stats['pendientes'] / $totalCandidatos) * 100) : 0;

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" id="btnGraduacionMasiva" class="btn btn-primary" disabled>
    <i class="bi bi-mortarboard-fill me-1" aria-hidden="true"></i> Graduación Masiva
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Egresos / Graduación';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            'label' => 'Total candidatos',
            'value' => number_format($totalCandidatos, 0, ',', '.'),
            'icon'  => 'bi-people-fill',
            'tone'  => 'primary',
            'trend' => 'Candidatos',
            'trendType' => 'neutral',
        ],
        [
            'label' => 'Graduados',
            'value' => (int) $stats['graduados'],
            'icon'  => 'bi-mortarboard-fill',
            'tone'  => 'success',
            'trend' => $pctGraduados . '% del total',
            'trendType' => 'up',
        ],
        [
            'label' => 'Pendientes',
            'value' => (int) $stats['pendientes'],
            'icon'  => 'bi-hourglass-split',
            'tone'  => 'warning',
            'trend' => $pctPendientes . '% del total',
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
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  // El filtrado es automático (main.js, form.data-panel__filters): el <select>
  // y el buscador disparan el panel al cambiar, sin botón de buscar.
  $panelFormAction = BASE_URL . 'egresos/index';

  ob_start();
  ?>
  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $seccion): ?>
      <option value="<?= htmlspecialchars($seccion) ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion ? 'selected' : '' ?>>
        <?= htmlspecialchars($seccion) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: selección / Estudiante / Grado / Sección / Estado / Acción.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 5%;">
    <col style="width: 30%;">
    <col style="width: 15%;">
    <col style="width: 15%;">
    <col style="width: 15%;">
    <col style="width: 20%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col" class="text-center">
      <input type="checkbox" id="checkAllEgresos" class="form-check-input" aria-label="Seleccionar todos">
    </th>
    <th scope="col">Estudiante</th>
    <th scope="col">Grado</th>
    <th scope="col">Sección</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($candidatos as $candidato):
    $esGraduado  = ($candidato['estado'] ?? '') === 'graduado';
    $estudianteId = (int) ($candidato['id'] ?? 0);
  ?>
    <tr>
      <td class="text-center">
        <input
          type="checkbox"
          class="form-check-input check-egreso"
          value="<?= $estudianteId ?>"
          <?= $esGraduado ? 'disabled' : '' ?>
          aria-label="Seleccionar <?= htmlspecialchars($candidato['nombre'] ?? '') ?>"
        >
      </td>
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $candidato['avatar_color'] ?? 'primary';
            $avatarSrc      = $candidato['avatar_img'] ?? '';
            $avatarAlt      = $candidato['nombre'] ?? '';
            $avatarInitials = $candidato['iniciales'] ?? 'ES';
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($candidato['nombre'] ?? '') ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($candidato['cedula'] ?? '') ?></div>
          </div>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($candidato['grado'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($candidato['seccion'] ?? '') ?></td>
      <td>
        <?php
          $badgeLabel  = $esGraduado ? 'Graduado' : 'Pendiente';
          $badgeStatus = $esGraduado ? 'active' : 'pending';
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <?php if ($esGraduado): ?>
            <button type="button" class="action-btn" disabled title="Ya está graduado">
              <i class="bi bi-check-lg"></i>
            </button>
          <?php else: ?>
            <form method="post" action="<?= BASE_URL ?>egresos/graduar/<?= $estudianteId ?>" class="d-inline">
              <button type="submit" class="action-btn action-btn--toggle" title="Registrar egreso">
                <i class="bi bi-check-lg"></i>
              </button>
            </form>
          <?php endif; ?>

          <a href="<?= BASE_URL ?>estudiantes/show/<?= $estudianteId ?>"
             class="action-btn action-btn--view" title="Ver expediente">
            <i class="bi bi-eye"></i>
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$candidatos): ?>
    <tr>
      <td colspan="6" class="text-center text-support py-4">
        No se encontraron candidatos con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' candidatos';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
