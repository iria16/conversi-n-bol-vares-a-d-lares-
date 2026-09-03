<?php
$pageTitle = 'Gestión de Grados y Secciones';
$pageDescription = 'Administre la distribución de grados, secciones y turnos académicos.';
$currentNav = 'estructura-academica';
$extraScripts = ['/js/academic-structure.js'];
$breadcrumbs = [['label' => 'Configuración académica', 'url' => null], ['label' => 'Estructura académica', 'url' => null]];
$stats = $stats ?? ['total' => 0, 'grados' => 0, 'secciones' => 0, 'capacidad' => 0];
$estructuras = $estructuras ?? [];
$anios = $anios ?? [];
$grados = $grados ?? [];
$secciones = $secciones ?? [];
$turnos = $turnos ?? [];
$paginacion = $paginacion ?? ['desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1];
ob_start();
?>
<div class="page-header"><div><h1 class="page-header__title">Gestión de Grados y Secciones</h1><p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p></div><div class="page-header__actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaEstructuraModal"><i class="bi bi-plus-lg"></i> Nuevo Registro</button></div></div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4"><article class="stat-card"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--primary"><i class="bi bi-diagram-3"></i></span><span class="stat-card__trend stat-card__trend--neutral">Configurados</span></div><p class="stat-card__label">Grados y secciones</p><p class="stat-card__value mb-0"><?= (int) $stats['total'] ?></p></article></div>
  <div class="col-sm-6 col-lg-4"><article class="stat-card"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--success"><i class="bi bi-collection"></i></span><span class="stat-card__trend stat-card__trend--up">Activos</span></div><p class="stat-card__label">Secciones disponibles</p><p class="stat-card__value mb-0"><?= (int) $stats['secciones'] ?></p></article></div>
  <div class="col-sm-6 col-lg-4"><article class="stat-card"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--warning"><i class="bi bi-people"></i></span><span class="stat-card__trend stat-card__trend--neutral">Capacidad</span></div><p class="stat-card__label">Cupos configurados</p><p class="stat-card__value mb-0"><?= (int) $stats['capacidad'] ?></p></article></div>
</div>

<div class="data-panel">
  <div class="data-panel__toolbar"><form method="get" action="<?= BASE_URL ?>academicStructure/index" class="data-panel__filters">
    <select name="anio" class="form-select" aria-label="Filtrar por año escolar"><option value="">Año escolar (Todos)</option><?php foreach ($anios as $anio): ?><option value="<?= (int) $anio['id'] ?>" <?= (string) ($_GET['anio'] ?? '') === (string) $anio['id'] ? 'selected' : '' ?>><?= htmlspecialchars($anio['nombre']) ?></option><?php endforeach; ?></select>
    <select name="grado" class="form-select" aria-label="Filtrar por grado"><option value="">Grado (Todos)</option><?php foreach ($grados as $grado): ?><option value="<?= (int) $grado['id'] ?>" <?= (string) ($_GET['grado'] ?? '') === (string) $grado['id'] ? 'selected' : '' ?>><?= htmlspecialchars($grado['nombre']) ?></option><?php endforeach; ?></select>
    <select name="turno" class="form-select" aria-label="Filtrar por turno"><option value="">Turno (Todos)</option><?php foreach ($turnos as $turno): ?><option value="<?= (int) $turno['id'] ?>" <?= (string) ($_GET['turno'] ?? '') === (string) $turno['id'] ? 'selected' : '' ?>><?= htmlspecialchars($turno['nombre']) ?></option><?php endforeach; ?></select>
    <div class="data-panel__search"><i class="bi bi-search"></i><input type="search" name="q" class="form-control" placeholder="Buscar por grado o sección..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off"></div>
  </form></div>
  <div class="data-panel__body"><table class="data-panel__table"><colgroup><col style="width:18%"><col style="width:16%"><col style="width:16%"><col style="width:22%"><col style="width:12%"><col style="width:16%"></colgroup><thead><tr><th>Grado</th><th>Sección</th><th>Turno</th><th>Año escolar</th><th>Capacidad</th><th class="text-center">Acciones</th></tr></thead><tbody>
  <?php foreach ($estructuras as $estructura): ?><tr><td class="fw-semibold"><?= htmlspecialchars($estructura['grado']) ?></td><td><?= htmlspecialchars($estructura['seccion']) ?></td><td><?= htmlspecialchars($estructura['turno']) ?></td><td><?= htmlspecialchars($estructura['anio']) ?></td><td><?= (int) $estructura['capacidad_maxima'] ?> cupos</td><td class="text-center"><button type="button" class="action-btn action-btn--edit editar-estructura" data-id="<?= (int) $estructura['id'] ?>" data-anio="<?= (int) $estructura['id_anio_escolar'] ?>" data-grado="<?= (int) $estructura['id_grado'] ?>" data-seccion="<?= (int) $estructura['id_seccion'] ?>" data-turno="<?= (int) $estructura['id_turno'] ?>" data-capacidad="<?= (int) $estructura['capacidad_maxima'] ?>" title="Editar estructura"><i class="bi bi-pencil"></i></button></td></tr><?php endforeach; ?>
  <?php if (!$estructuras): ?><tr><td colspan="6" class="text-center text-support py-4">No se encontraron estructuras académicas.</td></tr><?php endif; ?></tbody></table></div>
  <div class="data-panel__footer"><span>Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?> de <?= (int) $paginacion['total'] ?> registros</span><?php $paginacionAriaLabel = 'Paginación de estructura académica'; require __DIR__ . '/../partials/pagination.php'; ?></div>
</div>

<?php
$modalId = 'nuevaEstructuraModal'; $modalTitle = 'Nuevo Registro'; $modalSubtitle = 'Configure una nueva sección académica.'; $formId = 'formNuevaEstructura'; $editMode = false; include __DIR__ . '/structure-modal.php';
$modalId = 'editarEstructuraModal'; $modalTitle = 'Editar Registro'; $modalSubtitle = 'Actualice la configuración académica.'; $formId = 'formEditarEstructura'; $editMode = true; include __DIR__ . '/structure-modal.php';
$pageContent = ob_get_clean(); require __DIR__ . '/../layouts/app.php';
