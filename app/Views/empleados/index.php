<?php
/**
 * app/Views/staff/index.php
 * Listado de empleados (Personal > Empleados).
 *
 * Esta vista SOLO compone: cada bloque visual sale de un componente de
 * app/Views/components/ y los modales del módulo viven en staff/modals/.
 *
 * Variables que pasa el controlador:
 *   Listado ..: $stats, $empleados, $cargos, $paginacion
 *   Modales ..: $gradosAcademicos, $titulos, $instituciones, $tiposInstitucion, $estados
 */
$pageTitle       = 'Gestión de Empleados';
$pageDescription = 'Administre el personal docente, administrativo y auxiliar de la institución.';
$currentNav      = 'empleados}';
$extraScripts    = ['/js/empleado.js'];
$breadcrumbs     = [['label' => 'Personal', 'url' => null], ['label' => 'Empleados', 'url' => null]];

// ---------- Valores por defecto (contrato de variables de la vista) ----------
$stats            = $stats ?? ['total' => 0, 'activos' => 0, 'inactivos' => 0, 'cargos' => 0];
$empleados        = $empleados ?? [];
$cargos           = $cargos ?? [];
$gradosAcademicos = $gradosAcademicos ?? [];
$titulos          = $titulos ?? [];
$instituciones    = $instituciones ?? [];
$tiposInstitucion = $tiposInstitucion ?? [];
$estados          = $estados ?? [];
$paginacion       = $paginacion ?? ['desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1];

// Filtros activos (se reflejan en los <select> al recargar)
$filtroCargo  = (string) ($_GET['cargo'] ?? '');
$filtroEstado = (string) ($_GET['estado'] ?? '');

ob_start(); // todo lo que sigue se captura en $pageContent

// ---------- Cabecera de página (components/page-header.php) ----------
ob_start();
?>
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoEmpleadoModal">
  <i class="bi bi-person-plus"></i> Registrar Empleado
</button>
<?php
$pageHeading       = $pageTitle;
$pageSubheading    = $pageDescription;
$pageHeaderActions = ob_get_clean();
include __DIR__ . '/../components/page-header.php';

// ---------- Tarjetas de estadísticas (components/stat-card.php) ----------
$tarjetas = [
    ['label' => 'Total empleados',   'value' => $stats['total'],     'icon' => 'bi-people',       'tone' => 'primary', 'trend' => 'Registrados', 'trendType' => 'neutral', 'variant' => ''],
    ['label' => 'Personal activo',   'value' => $stats['activos'],   'icon' => 'bi-person-check', 'tone' => 'success', 'trend' => 'Activos',     'trendType' => 'up',      'variant' => ''],
    ['label' => 'Personal inactivo', 'value' => $stats['inactivos'], 'icon' => 'bi-person-x',     'tone' => 'danger',  'trend' => 'Revisar',     'trendType' => 'down',    'variant' => 'alert'],
];
?>
<div class="row g-3 mb-4">
  <?php foreach ($tarjetas as $tarjeta): ?>
    <div class="col-sm-6 col-lg-4">
      <?php
      $statLabel     = $tarjeta['label'];
      $statValue     = (string) (int) $tarjeta['value'];
      $statIcon      = $tarjeta['icon'];
      $statTone      = $tarjeta['tone'];
      $statVariant   = $tarjeta['variant'];
      $statTrend     = $tarjeta['trend'];
      $statTrendType = $tarjeta['trendType'];
      include __DIR__ . '/../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>
<?php
// ---------- Panel de datos (components/data-panel.php) ----------

// Filtros (<select>)
ob_start();
?>
<select name="cargo" class="form-select" aria-label="Filtrar por cargo">
  <option value="">Tipo (Todos)</option>
  <?php foreach ($cargos as $cargo): ?>
    <option value="<?= (int) $cargo['id_cargo'] ?>" <?= $filtroCargo === (string) $cargo['id_cargo'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($cargo['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>
<select name="estado" class="form-select" aria-label="Filtrar por estado">
  <option value="">Estado (Todos)</option>
  <option value="activo" <?= $filtroEstado === 'activo' ? 'selected' : '' ?>>Activo</option>
  <option value="inactivo" <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
</select>
<?php
$panelFilters = ob_get_clean();

// Anchos de columna
ob_start();
?>
<colgroup>
  <col style="width:30%"><col style="width:18%"><col style="width:22%"><col style="width:14%"><col style="width:16%">
</colgroup>
<?php
$panelColgroup = ob_get_clean();

// Encabezado de la tabla
ob_start();
?>
<tr>
  <th>Empleado</th>
  <th>Cédula</th>
  <th>Cargo</th>
  <th>Estado</th>
  <th class="text-center">Acciones</th>
</tr>
<?php
$panelTableHead = ob_get_clean();

// Filas (fragmento reutilizable, ver partials/employee-rows.php)
ob_start();
include __DIR__ . '/partials/employee-rows.php';
$panelTableBody = ob_get_clean();

$panelFormAction        = BASE_URL . 'empleado/index';
$panelFormId            = 'filtrosEmpleados';
$panelSearchName        = 'q';
$panelSearchValue       = (string) ($_GET['q'] ?? '');
$panelSearchPlaceholder = 'Buscar por nombre, cédula o cargo...';
$panelTableBodyId       = 'tablaEmpleadosBody';
$panelSummary           = sprintf('Mostrando %d a %d de %d empleados', (int) $paginacion['desde'], (int) $paginacion['hasta'], (int) $paginacion['total']);
$panelSummaryId         = 'paginacionInfo';
$panelPagination        = $paginacion;
$panelPaginationLabel   = 'Paginación de empleados';
include __DIR__ . '/../components/data-panel.php';

// ---------- Modales del módulo ----------
include __DIR__ . '/modals/employee-modal.php';   // wizard + modales anidados (título, institución)
include __DIR__ . '/modals/show-modal.php';
include __DIR__ . '/modals/edit-modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';