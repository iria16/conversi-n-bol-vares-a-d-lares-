<?php
$pageTitle = 'Gestión de Años Escolares';
$pageDescription = 'Administre los períodos académicos, fechas de inicio y cierres institucionales.';
$currentNav = 'anios-escolares';
$extraScripts = ['/js/academic-years.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'url' => null],
    ['label' => 'Años escolares', 'url' => null],
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
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestión de Años Escolares</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>
  <div class="page-header__actions">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoAnioModal">
      <i class="bi bi-plus-lg"></i> Nuevo Año Escolar
    </button>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <article class="stat-card">
      <div class="stat-card__top">
        <span class="stat-card__icon stat-card__icon--primary"><i class="bi bi-grid-3x3-gap"></i></span>
        <span class="stat-card__trend stat-card__trend--up">+<?= (int) $stats['total'] ?> registrados</span>
      </div>
      <p class="stat-card__label">Total períodos</p>
      <p class="stat-card__value mb-0"><?= (int) $stats['total'] ?></p>
    </article>
  </div>
  <div class="col-sm-6 col-lg-4">
    <article class="stat-card">
      <div class="stat-card__top">
        <span class="stat-card__icon stat-card__icon--success"><i class="bi bi-check-circle"></i></span>
        <span class="stat-card__trend stat-card__trend--neutral">Activo</span>
      </div>
      <p class="stat-card__label">Período activo</p>
      <p class="stat-card__value mb-0 stat-card__value--compact"><?= htmlspecialchars($stats['actual']) ?></p>
    </article>
  </div>
  <div class="col-sm-6 col-lg-4">
    <article class="stat-card stat-card--alert">
      <div class="stat-card__top">
        <span class="stat-card__icon stat-card__icon--danger"><i class="bi bi-slash-circle"></i></span>
        <span class="stat-card__trend stat-card__trend--down">Revisar</span>
      </div>
      <p class="stat-card__label">Estados inactivos</p>
      <p class="stat-card__value mb-0"><?= (int) $stats['inactivos'] ?></p>
    </article>
  </div>
</div>

<div class="data-panel">
  <div class="data-panel__toolbar">
    <form method="get" action="<?= BASE_URL ?>academicYear/index" class="data-panel__filters">
      <select name="estado" class="form-select" aria-label="Filtrar por estado">
        <option value="" <?= empty($_GET['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
        <option value="activo" <?= ($_GET['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
        <option value="inactivo" <?= ($_GET['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
      </select>
      <div class="data-panel__search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Buscar por año o estado..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off">
      </div>
    </form>
  </div>

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup><col style="width: 25%"><col style="width: 20%"><col style="width: 20%"><col style="width: 17%"><col style="width: 18%"></colgroup>
      <thead><tr><th>Período escolar</th><th>Fecha inicio</th><th>Fecha cierre</th><th>Estado</th><th class="text-center">Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($anios as $anio): ?>
          <?php $activo = $anio['estado'] === 'activo'; ?>
          <tr class="<?= $activo ? '' : 'is-muted' ?>">
            <td><strong><?= htmlspecialchars($anio['nombre']) ?></strong></td>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_inicio']))) ?></td>
            <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_cierre']))) ?></td>
            <td><span class="status-badge status-badge--<?= $activo ? 'active' : 'inactive' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
            <td class="text-center"><div class="data-panel__actions">
              <button type="button" class="action-btn action-btn--edit editar-anio" data-id="<?= (int) $anio['id'] ?>" data-nombre="<?= htmlspecialchars($anio['nombre']) ?>" data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>" data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>" title="Editar año escolar"><i class="bi bi-pencil"></i></button>
              <div class="form-check form-switch table-switch mb-0"><input class="form-check-input toggle-anio" type="checkbox" role="switch" data-id="<?= (int) $anio['id'] ?>" <?= $activo ? 'checked' : '' ?> aria-label="Cambiar estado de <?= htmlspecialchars($anio['nombre']) ?>"></div>
            </div></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$anios): ?><tr><td colspan="5" class="text-center text-support py-4">No se encontraron años escolares.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="data-panel__footer"><span>Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?> de <?= (int) $paginacion['total'] ?> períodos registrados</span><?php $paginacionAriaLabel = 'Paginación de años escolares'; require __DIR__ . '/../partials/pagination.php'; ?></div>
</div>

<?php
$modalId = 'nuevoAnioModal';
$modalTitle = 'Nuevo Año Escolar';
$modalSubtitle = 'Registre un nuevo período académico institucional.';
ob_start();
?>
<form id="formAnioEscolar" novalidate>
  <div class="form-section__label">Información del período</div>
  <div class="mb-3"><label for="anioNombre" class="label-sigde">Nombre del período</label><input type="text" id="anioNombre" name="nombre" class="form-control" placeholder="Ej. 2024-2025" maxlength="20" required><div class="invalid-feedback">Indique el nombre del período.</div></div>
  <div class="row g-3"><div class="col-md-6"><label for="anioInicio" class="label-sigde">Fecha de inicio</label><input type="date" id="anioInicio" name="fecha_inicio" class="form-control" required><div class="invalid-feedback">Indique la fecha de inicio.</div></div><div class="col-md-6"><label for="anioCierre" class="label-sigde">Fecha de cierre</label><input type="date" id="anioCierre" name="fecha_cierre" class="form-control" required><div class="invalid-feedback">Indique la fecha de cierre.</div></div></div>
  <div class="form-section__label mt-4">Estado inicial</div>
  <div class="form-check"><input class="form-check-input" type="checkbox" name="estado" value="ACTIVO" id="anioActivo"><label class="form-check-label" for="anioActivo">Establecer como período activo</label></div>
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
<form id="formEditarAnio" novalidate><input type="hidden" name="id" id="editarAnioId"><div class="mb-3"><label for="editarAnioNombre" class="label-sigde">Nombre del período</label><input type="text" name="nombre" id="editarAnioNombre" class="form-control" maxlength="20" required></div><div class="row g-3"><div class="col-md-6"><label for="editarAnioInicio" class="label-sigde">Fecha de inicio</label><input type="date" name="fecha_inicio" id="editarAnioInicio" class="form-control" required></div><div class="col-md-6"><label for="editarAnioCierre" class="label-sigde">Fecha de cierre</label><input type="date" name="fecha_cierre" id="editarAnioCierre" class="form-control" required></div></div></form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formEditarAnio" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../components/modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
