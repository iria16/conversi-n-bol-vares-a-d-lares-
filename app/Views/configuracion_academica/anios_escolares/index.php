<?php
$pageTitle = 'Gestión de Años Escolares';
$pageDescription = 'Administre los períodos académicos, fechas de inicio y cierres institucionales.';
$currentNav = 'anios-escolares';
$extraScripts = ['/js/academic-years.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Años escolares', 'href' => null],
];

$stats = $stats ?? [
    'total' => 0,
    'activos' => 0,
    'inactivos' => 0,
    'actual' => 'Sin período activo',
];
$anios = $anios ?? [];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoAnioModal">
    <i class="bi bi-plus-lg"></i> Nuevo Año Escolar
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Años Escolares';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Total períodos';
      $statValue     = (int) $stats['total'];
      $statIcon      = 'bi-grid-3x3-gap';
      $statTone      = 'primary';
      $statTrend     = '+' . (int) $stats['total'] . ' registrados';
      $statTrendType = 'up';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Período activo';
      $statValue     = $stats['actual'];
      $statIcon      = 'bi-check-circle';
      $statTone      = 'success';
      $statTrend     = 'Activo';
      $statTrendType = 'neutral';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Estados inactivos';
      $statValue     = (int) $stats['inactivos'];
      $statIcon      = 'bi-slash-circle';
      $statTone      = 'danger';
      $statVariant   = 'alert';
      $statTrend     = 'Revisar';
      $statTrendType = 'down';
      include __DIR__ . '/../components/stat-card.php';
    ?>
  </div>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'academicYear/index';

  ob_start();
  ?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="" <?= empty($_GET['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
    <option value="activo"   <?= ($_GET['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
    <option value="inactivo" <?= ($_GET['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por año o estado...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos por columna: Período / Inicio / Cierre / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 25%;">
    <col style="width: 20%;">
    <col style="width: 20%;">
    <col style="width: 17%;">
    <col style="width: 18%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Período escolar</th>
    <th scope="col">Fecha inicio</th>
    <th scope="col">Fecha cierre</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($anios as $anio):
    $activo = $anio['estado'] === 'activo';
  ?>
    <tr class="<?= $activo ? '' : 'is-muted' ?>">
      <td><strong><?= htmlspecialchars($anio['nombre']) ?></strong></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_inicio']))) ?></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_cierre']))) ?></td>
      <td>
        <?php
          $badgeLabel  = $activo ? 'Activo' : 'Inactivo';
          $badgeStatus = $activo ? 'active' : 'inactive';
          include __DIR__ . '/../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
            class="action-btn action-btn--edit editar-anio"
            data-id="<?= (int) $anio['id'] ?>"
            data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
            data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>"
            data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>"
            title="Editar año escolar">
            <i class="bi bi-pencil"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0">
            <input class="form-check-input toggle-anio" type="checkbox" role="switch"
              data-id="<?= (int) $anio['id'] ?>"
              <?= $activo ? 'checked' : '' ?>
              aria-label="Cambiar estado de <?= htmlspecialchars($anio['nombre']) ?>">
          </div>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$anios): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron años escolares.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' períodos registrados';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../components/data-panel.php';
?>

<?php
$modalId = 'nuevoAnioModal';
$modalTitle = 'Nuevo Año Escolar';
$modalSubtitle = 'Registre un nuevo período académico institucional.';
ob_start();
?>
<form id="formAnioEscolar" novalidate>
  <div class="form-section__label">Información del período</div>
  <div class="mb-3">
    <label for="anioNombre" class="label-sigde">Nombre del período</label>
    <input type="text" id="anioNombre" name="nombre" class="form-control" placeholder="Ej. 2024-2025" maxlength="20" required>
    <div class="invalid-feedback">Indique el nombre del período.</div>
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <label for="anioInicio" class="label-sigde">Fecha de inicio</label>
      <input type="date" id="anioInicio" name="fecha_inicio" class="form-control" required>
      <div class="invalid-feedback">Indique la fecha de inicio.</div>
    </div>
    <div class="col-md-6">
      <label for="anioCierre" class="label-sigde">Fecha de cierre</label>
      <input type="date" id="anioCierre" name="fecha_cierre" class="form-control" required>
      <div class="invalid-feedback">Indique la fecha de cierre.</div>
    </div>
  </div>
  <div class="form-section__label mt-4">Estado inicial</div>
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="estado" value="ACTIVO" id="anioActivo">
    <label class="form-check-label" for="anioActivo">Establecer como período activo</label>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formAnioEscolar" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar período</button>';
include __DIR__ . '/../components/modal.php';

$modalId = 'editarAnioModal';
$modalTitle = 'Editar Año Escolar';
$modalSubtitle = 'Actualice la información del período seleccionado.';
ob_start();
?>
<form id="formEditarAnio" novalidate>
  <input type="hidden" name="id" id="editarAnioId">
  <div class="mb-3">
    <label for="editarAnioNombre" class="label-sigde">Nombre del período</label>
    <input type="text" name="nombre" id="editarAnioNombre" class="form-control" maxlength="20" required>
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <label for="editarAnioInicio" class="label-sigde">Fecha de inicio</label>
      <input type="date" name="fecha_inicio" id="editarAnioInicio" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="editarAnioCierre" class="label-sigde">Fecha de cierre</label>
      <input type="date" name="fecha_cierre" id="editarAnioCierre" class="form-control" required>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formEditarAnio" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../components/modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';