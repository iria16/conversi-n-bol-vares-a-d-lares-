<?php
/**
 * Vista: Gestión de Grados y Secciones (estructura académica)
 * Usa los componentes: page-header, stat-card, data-panel, modal (structure-modal.php)
 */

$pageTitle       = 'Gestión de Grados y Secciones';
$pageDescription = 'Administre la distribución de grados, secciones y turnos académicos.';
$currentNav      = 'estructura-academica';
$extraScripts    = ['/js/academic-structure.js'];
$breadcrumbs     = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Estructura académica',     'href' => null],
];

$stats       = $stats ?? ['total' => 0, 'grados' => 0, 'secciones' => 0, 'capacidad' => 0];
$estructuras = $estructuras ?? [];
$anios       = $anios ?? [];
$grados      = $grados ?? [];
$secciones   = $secciones ?? [];
$turnos      = $turnos ?? [];
$paginacion  = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

// ---------- Page header ----------
ob_start();
?>
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaEstructuraModal">
  <i class="bi bi-plus-lg"></i> Nuevo Registro
</button>
<?php
$pageHeaderActions = ob_get_clean();
$pageHeading       = 'Gestión de Grados y Secciones';
$pageSubheading    = $pageDescription;
include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Grados y secciones';
      $statValue     = (int) $stats['total'];
      $statIcon      = 'bi-diagram-3';
      $statTone      = 'primary';
      $statTrend     = 'Configurados';
      $statTrendType = 'neutral';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Secciones disponibles';
      $statValue     = (int) $stats['secciones'];
      $statIcon      = 'bi-collection';
      $statTone      = 'success';
      $statTrend     = 'Activos';
      $statTrendType = 'neutral';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Cupos configurados';
      $statValue     = (int) $stats['capacidad'];
      $statIcon      = 'bi-people';
      $statTone      = 'warning';
      $statTrend     = 'Capacidad';
      $statTrendType = 'neutral';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
</div>

<?php
// ---------- Data panel: filtros ----------
$panelFormAction = BASE_URL . 'academicStructure/index';

ob_start();
?>
<label for="filtroAnio" class="visually-hidden">Año escolar</label>
<select id="filtroAnio" name="anio" class="form-select" aria-label="Filtrar por año escolar">
  <option value="">Año escolar (Todos)</option>
  <?php foreach ($anios as $anio): ?>
    <option value="<?= (int) $anio['id'] ?>" <?= (string) ($_GET['anio'] ?? '') === (string) $anio['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($anio['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroGrado" class="visually-hidden">Grado</label>
<select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
  <option value="">Grado (Todos)</option>
  <?php foreach ($grados as $grado): ?>
    <option value="<?= (int) $grado['id'] ?>" <?= (string) ($_GET['grado'] ?? '') === (string) $grado['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($grado['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroTurno" class="visually-hidden">Turno</label>
<select id="filtroTurno" name="turno" class="form-select" aria-label="Filtrar por turno">
  <option value="">Turno (Todos)</option>
  <?php foreach ($turnos as $turno): ?>
    <option value="<?= (int) $turno['id'] ?>" <?= (string) ($_GET['turno'] ?? '') === (string) $turno['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($turno['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>
<?php
$panelFilters           = ob_get_clean();
$panelSearchName        = 'q';
$panelSearchValue       = $_GET['q'] ?? '';
$panelSearchPlaceholder = 'Buscar por grado o sección...';

// ---------- Data panel: colgroup ----------
// Anchos fijos: Grado / Sección / Turno / Año escolar / Capacidad / Acciones.
ob_start();
?>
<colgroup>
  <col style="width: 18%;">
  <col style="width: 16%;">
  <col style="width: 16%;">
  <col style="width: 22%;">
  <col style="width: 12%;">
  <col style="width: 16%;">
</colgroup>
<?php
$panelColgroup = ob_get_clean();

// ---------- Data panel: thead ----------
ob_start();
?>
<tr>
  <th scope="col">Grado</th>
  <th scope="col">Sección</th>
  <th scope="col">Turno</th>
  <th scope="col">Año escolar</th>
  <th scope="col">Capacidad</th>
  <th scope="col" class="text-center">Acciones</th>
</tr>
<?php
$panelTableHead = ob_get_clean();

// ---------- Data panel: tbody ----------
ob_start();
foreach ($estructuras as $estructura):
?>
  <tr>
    <td class="fw-semibold"><?= htmlspecialchars($estructura['grado']) ?></td>
    <td><?= htmlspecialchars($estructura['seccion']) ?></td>
    <td><?= htmlspecialchars($estructura['turno']) ?></td>
    <td><?= htmlspecialchars($estructura['anio']) ?></td>
    <td><?= (int) $estructura['capacidad_maxima'] ?> cupos</td>
    <td class="text-center">
      <div class="data-panel__actions">
        <button type="button"
          class="action-btn action-btn--edit editar-estructura"
          data-id="<?= (int) $estructura['id'] ?>"
          data-anio="<?= (int) $estructura['id_anio_escolar'] ?>"
          data-grado="<?= (int) $estructura['id_grado'] ?>"
          data-seccion="<?= (int) $estructura['id_seccion'] ?>"
          data-turno="<?= (int) $estructura['id_turno'] ?>"
          data-capacidad="<?= (int) $estructura['capacidad_maxima'] ?>"
          title="Editar estructura">
          <i class="bi bi-pencil"></i>
        </button>
      </div>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$estructuras): ?>
  <tr>
    <td colspan="6" class="text-center text-support py-4">
      No se encontraron estructuras académicas.
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
// ---------- Modales (crear / editar) ----------
$modalId       = 'nuevaEstructuraModal';
$modalTitle    = 'Nuevo Registro';
$modalSubtitle = 'Configure una nueva sección académica.';
$formId        = 'formNuevaEstructura';
$editMode      = false;
include __DIR__ . '/structure-modal.php';

$modalId       = 'editarEstructuraModal';
$modalTitle    = 'Editar Registro';
$modalSubtitle = 'Actualice la configuración académica.';
$formId        = 'formEditarEstructura';
$editMode      = true;
include __DIR__ . '/structure-modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';