<?php
/**
 * Vista: gestion_estudiante/inscripcion/index.php
 * Gestión de Inscripciones — ingreso de nuevos estudiantes y ratificaciones
 * anuales para el periodo escolar vigente.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php. El wizard vive en enrollment-modal.php y su
 * JS en public/assets/js/enrollments.js ($extraScripts).
 *
 * El filtrado y la paginación los aplica automáticamente main.js sobre el
 * form del panel (initAutoFilters), igual que en el resto de módulos: por
 * eso la tabla y el resumen se renderizan siempre desde PHP.
 *
 * Espera del controlador:
 * - array $stats          ['inscritosAnio' => int, 'trendInscritos' => int,
 *                          'cuposDisponibles' => int, 'inscripcionesMes' => int]
 * - array $inscripciones  cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 *                          'avatar_img' (opcional),'grado','seccion','fecha_inscripcion',
 *                          'estado' => 'completa'|'incompleta']
 * - string $anioEnCurso  periodo en curso, que se muestra como dato fijo
 * - array $grados         lista de grados para el filtro
 * - array $secciones      lista de secciones para el filtro
 * - array $filters        valores actuales de los filtros aplicados
 * - array $paginacion     ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $inscripciones
 * @var string $anioEnCurso
 * @var array $grados
 * @var array $secciones
 * @var array $filters
 * @var array $paginacion
 */

$pageTitle       = 'Gestión de Inscripciones';
/* Esta pantalla es SOLO del primer ingreso. La inscripción ocurre una sola vez en
   la vida del alumno en el plantel, así que lo que se lista son los ingresos.
   Antes la descripción decía "y ratificaciones anuales", y ya no corresponde: las
   ratificaciones se procesan en su propio módulo y llegan por otra vía. Con las
   dos cosas juntas en la misma pantalla, en el cierre de año la secretaria no
   sabía cuál usar para un alumno que ya estaba del año anterior. */
$pageDescription = 'Registra el ingreso de nuevos estudiantes al plantel. Un alumno se inscribe una sola vez; '
    . 'los que ya estaban en años anteriores se ratifican en el módulo de Ratificación.';
$currentNav      = 'inscripcion';
$extraScripts    = ['/js/enrollments.js'];

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Inscripciones', 'href' => null],
];

$stats          = $stats ?? ['inscritosAnio' => 0, 'trendInscritos' => 0, 'cuposDisponibles' => 0, 'inscripcionesMes' => 0];
$inscripciones  = $inscripciones ?? [];
$anioEnCurso = $anioEnCurso ?? '';   // se muestra, no se elige
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$filters        = $filters ?? ['q' => '', 'anio' => '', 'grado' => '', 'seccion' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

$trendInscritos = (int) ($stats['trendInscritos'] ?? 0);

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaInscripcionModal">
    <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Nueva Inscripción
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Inscripciones';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        [
            /* Dice "ingresos" y no "inscritos" a propósito: la inscripción es un
               registro único por alumno, así que este número cuenta a quién le
               tocó pasar por aquí este año, no cuántos alumnos hay en el
               plantel. Con "inscritos" se leía como el total de alumnos, que es
               un número distinto y mucho más grande. */
            'label'     => 'Ingresos de este año escolar',
            'value'     => number_format((int) $stats['inscritosAnio'], 0, ',', '.'),
            'icon'      => 'bi-people-fill',
            'tone'      => 'info',
            'trend'     => $trendInscritos > 0 ? '+' . $trendInscritos . '%' : '',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Cupos disponibles',
            'value'     => number_format((int) $stats['cuposDisponibles'], 0, ',', '.'),
            'icon'      => 'bi-check-circle-fill',
            'tone'      => 'success',
            'trend'     => 'Disponibles',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Inscripciones este mes',
            'value'     => number_format((int) $stats['inscripcionesMes'], 0, ',', '.'),
            'icon'      => 'bi-calendar-check-fill',
            'tone'      => 'primary',
            'trend'     => 'Del mes en curso',
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
  // El filtrado y la paginación los aplica main.js (initAutoFilters) sobre
  // este form: no hay botón de buscar, los <select> y el input disparan el
  // panel al cambiar.
  $panelFormAction = BASE_URL . 'inscripciones/index';

  ob_start();
  ?>
  <?php /* El año no se elige: es el que está en curso y donde vive toda la
           estructura. Antes había un <select> aquí que, además de no servir —la
           lista ya es de este año—, dejaba cambiar el alcance por URL.

           Lleva la misma clase `form-select` que los filtros de al lado para que
           se vea igual dentro de la barra, y se le quita la flecha con la variable
           que la dibuja. No hay que inventar una clase nueva: se agrega CSS a
           app.css, que está minificado y se regenera, así que el estilo se
           perdería en la próxima compilación. */ ?>
  <span class="form-select d-inline-flex align-items-center gap-1"
        style="--bs-form-select-bg-img:none;padding-right:.9rem;cursor:default;"
        title="Año escolar en curso. No se puede cambiar: es el período en el que se trabaja toda la estructura.">
    <i class="bi bi-calendar-event"></i><?= htmlspecialchars($anioEnCurso ?: 'sin año en curso') ?>
  </span>

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
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Estudiante / Grado / Sección / Fecha / Estado / Acción.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 28%;">
    <col style="width: 14%;">
    <col style="width: 12%;">
    <col style="width: 18%;">
    <col style="width: 14%;">
    <col style="width: 14%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Estudiante</th>
    <th scope="col">Grado</th>
    <th scope="col">Sección</th>
    <th scope="col">Fecha inscripción</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($inscripciones as $inscripcion):
    $esCompleta = ($inscripcion['estado'] ?? '') === 'completa';
    $inscripcionId = (int) ($inscripcion['id'] ?? 0);
  ?>
    <tr>
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $inscripcion['avatar_color'] ?? 'primary';
            $avatarSrc      = $inscripcion['avatar_img'] ?? '';
            $avatarAlt      = $inscripcion['nombre'] ?? '';
            $avatarInitials = $inscripcion['iniciales'] ?? 'ES';
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($inscripcion['nombre'] ?? '') ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($inscripcion['cedula'] ?? '') ?></div>
          </div>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['grado'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['seccion'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($inscripcion['fecha_inscripcion'] ?? '') ?></td>
      <td>
        <?php
          $badgeLabel  = $esCompleta ? 'Completa' : 'Incompleta';
          $badgeStatus = $esCompleta ? 'active' : 'pending';
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <?php if ($esCompleta): ?>
            <a href="<?= BASE_URL ?>inscripciones/ver/<?= $inscripcionId ?>"
               class="action-btn action-btn--view" title="Ver inscripción">
              <i class="bi bi-eye"></i>
            </a>
          <?php else: ?>
            <a href="<?= BASE_URL ?>inscripciones/continuar/<?= $inscripcionId ?>"
               class="action-btn action-btn--edit" title="Continuar inscripción">
              <i class="bi bi-arrow-right"></i>
            </a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$inscripciones): ?>
    <tr>
      <td colspan="6" class="text-center text-support py-4">
        No se encontraron inscripciones con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' inscripciones';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

ob_start();
include __DIR__ . '/enrollment-modal.php';
$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
