<?php
/**
 * Vista: consulta_academica/estudiantes/index.php
 * Consulta de Expedientes — listado y búsqueda avanzada de estudiantes.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php.
 *
 * Espera del controlador:
 * - array $stats ['total' => int, 'activos' => int, 'inactivos' => int]
 * - array $estudiantes cada uno: ['id','nombre','iniciales','avatar_color',
 * 'avatar_img' (opcional),'cedula_escolar','grado','seccion',
 * 'estado' => 'activo'|'inactivo']
 * - array $aniosEscolares lista de años escolares para el filtro
 * - array $grados lista de grados para el filtro
 * - array $secciones lista de secciones para el filtro
 * - array $filters valores actuales de los filtros aplicados
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $estudiantes
 * @var array $aniosEscolares
 * @var array $grados
 * @var array $secciones
 * @var array $filters
 * @var array $paginacion
*/

$pageTitle       = 'Consulta de Expedientes';
$pageDescription = 'Gestión y búsqueda avanzada del alumnado de la institución.';
$currentNav      = 'estudiantes';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Estudiantes', 'href' => null],
];

$stats          = $stats ?? ['total' => 0, 'activos' => 0, 'inactivos' => 0];
$estudiantes    = $estudiantes ?? [];
$aniosEscolares = $aniosEscolares ?? [];
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$filters        = $filters ?? ['q' => '', 'anio' => '', 'grado' => '', 'seccion' => '', 'estado' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

$totalEstudiantes   = (int) $stats['total'];
$pctActivos         = $totalEstudiantes > 0 ? (int) round(((int) $stats['activos'] / $totalEstudiantes) * 100) : 0;
$pctInactivos       = $totalEstudiantes > 0 ? (int) round(((int) $stats['inactivos'] / $totalEstudiantes) * 100) : 0;

ob_start();

  // ---------- Page header ----------
  // Sin acciones en el header: el módulo es de consulta y navegación.
  $pageHeading    = 'Consulta de Expedientes';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            'label'     => 'Total estudiantes',
            'value'     => number_format($totalEstudiantes, 0, ',', '.'),
            'icon'      => 'bi-mortarboard-fill',
            'tone'      => 'primary',
            'trend'     => 'Matrícula total',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Activos',
            'value'     => (int) $stats['activos'],
            'icon'      => 'bi-person-check-fill',
            'tone'      => 'success',
            'trend'     => $pctActivos . '% del total',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Inactivos',
            'value'     => (int) $stats['inactivos'],
            'icon'      => 'bi-person-x-fill',
            'tone'      => 'danger',
            'trend'     => $pctInactivos . '% del total',
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
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  // El filtrado es automático (main.js, form.data-panel__filters): los
  // <select> y el buscador disparan el panel al cambiar, sin botón de buscar.
  $panelFormAction = BASE_URL . 'estudiantes/index';

  ob_start();
  ?>
  <label for="filtroAnio" class="visually-hidden">Año escolar</label>
  <div class="data-panel__year-filter">
    <i class="bi bi-calendar3" aria-hidden="true"></i>
    <select id="filtroAnio" name="anio" class="form-select" aria-label="Filtrar por año escolar">
      <?php if (!$aniosEscolares): ?>
        <option value="0" selected disabled>No hay años escolares para este turno</option>
      <?php endif; ?>
      <?php foreach ($aniosEscolares as $anio): ?>
        <option value="<?= (int) $anio['id'] ?>" <?= (string) ($filters['anio'] ?? '') === (string) $anio['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($anio['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <label for="filtroGrado" class="visually-hidden">Grado</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $grado): ?>
      <option value="<?= htmlspecialchars($grado) ?>" <?= (string) ($filters['grado'] ?? '') === (string) $grado ? 'selected' : '' ?>>
        <?= htmlspecialchars($grado) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $seccion): ?>
      <option value="<?= htmlspecialchars($seccion) ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion ? 'selected' : '' ?>>
        <?= htmlspecialchars($seccion) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Cualquiera)</option>
    <option value="activo" <?= ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
    <option value="retirado" <?= ($filters['estado'] ?? '') === 'retirado' ? 'selected' : '' ?>>Retirado</option>
    <option value="egresado" <?= ($filters['estado'] ?? '') === 'egresado' ? 'selected' : '' ?>>Egresado</option>
    <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo (retirado o egresado)</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Estudiante / Cédula / Grado y sección / Estado / Acción.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 30%;">
    <col style="width: 20%;">
    <col style="width: 20%;">
    <col style="width: 15%;">
    <col style="width: 15%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Nombre completo</th>
    <th scope="col">Cédula escolar</th>
    <th scope="col">Grado y sección</th>
    <th scope="col" class="text-center">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($estudiantes as $estudiante):
    // El estado viene del ciclo del estudiante (activo, retirado o egresado), no de la
    // persona: retirado y egresado son desenlaces distintos y se distinguen en la tabla.
    $estadoCiclo   = (string) ($estudiante['estado'] ?? 'activo');
    $esInactivo    = $estadoCiclo !== 'activo';
    $estadoEtiqueta = ['activo' => 'Activo', 'retirado' => 'Retirado', 'egresado' => 'Egresado'][$estadoCiclo] ?? 'Activo';
    // El color sale de los modificadores que ya existen en la hoja de estilos: retirado es
    // una salida antes de culminar (rojo, como el retiro) y egresado una culminación (informativo).
    $estadoColor   = ['activo' => 'active', 'retirado' => 'inactive', 'egresado' => 'info'][$estadoCiclo] ?? 'active';
  ?>
    <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $estudiante['avatar_color'] ?? 'primary';
            $avatarSrc      = $estudiante['avatar_img'] ?? '';
            $avatarAlt      = $estudiante['nombre'] ?? '';
            $avatarInitials = $estudiante['iniciales'] ?? 'ES';
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <span class="avatar-group__name"><?= htmlspecialchars($estudiante['nombre'] ?? '') ?></span>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($estudiante['cedula_escolar'] ?? '') ?></td>
      <td class="text-support">
        <?= htmlspecialchars($estudiante['grado'] ?? 'Sin grado') ?>
        <div class="text-support small">Sección <?= htmlspecialchars($estudiante['seccion'] ?? '—') ?></div>
      </td>
      <td class="text-center">
        <?php
          $badgeLabel  = $estadoEtiqueta;
          $badgeStatus = $estadoColor;
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <a href="<?= BASE_URL ?>estudiantes/show/<?= (int) ($estudiante['id'] ?? 0) ?>?anio=<?= (int) ($filters['anio'] ?? 0) ?>"
             class="btn btn-sm btn-outline-primary" title="Ver expediente">
            <i class="bi bi-eye me-1" aria-hidden="true"></i>Ver expediente
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$estudiantes): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        <?php /* Sin asignaciones la tabla vacía es la respuesta correcta, no un fallo: el
 docente solo ve lo que tiene asignado. Se dice explícitamente para que no
 parezca que el listado no funciona.*/ ?>
        <?php if (!empty($sinAsignaciones)): ?>
          <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
          No tiene estructuras asignadas en el año escolar en curso, así que todavía no le
          corresponden expedientes. Pida al coordinador o a la dirección que registren su
          asignación en Configuración → Asignación Docente.
        <?php else: ?>
          No se encontraron estudiantes con los filtros seleccionados.
        <?php endif; ?>
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' expedientes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
