<?php
$pageTitle = 'Asignación Docente';
$pageDescription = 'Asigne un docente titular a las secciones configuradas en la Estructura Académica.';
$currentNav = 'asignacion-docente';
$extraScripts = ['/js/teacher-assignment.js'];
$breadcrumbs = [['label' => 'Configuración académica', 'url' => null], ['label' => 'Asignación docente', 'url' => null]];
$stats = $stats ?? ['total' => 0, 'asignadas' => 0, 'pendientes' => 0, 'docentes' => 0];
$asignaciones = $asignaciones ?? [];
$anios = $anios ?? [];
$grados = $grados ?? [];
$secciones = $secciones ?? [];
$estructuras = $estructuras ?? [];
$docentes = $docentes ?? [];
$tiposAsignacion = $tiposAsignacion ?? [];
$paginacion = $paginacion ?? ['desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1];
ob_start();
?>
<div class="page-header"><div><h1 class="page-header__title">Asignación Docente</h1><p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p></div><div class="page-header__actions"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaAsignacionModal"><i class="bi bi-plus-lg"></i> Nueva Asignación</button></div></div>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4"><article class="stat-card"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--primary"><i class="bi bi-mortarboard"></i></span><span class="stat-card__trend stat-card__trend--up">Asignadas</span></div><p class="stat-card__label">Aulas con docente</p><p class="stat-card__value mb-0"><?= (int) $stats['asignadas'] ?> <small>/ <?= (int) $stats['total'] ?></small></p></article></div>
  <div class="col-sm-6 col-lg-4"><article class="stat-card"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--danger"><i class="bi bi-person-exclamation"></i></span><span class="stat-card__trend stat-card__trend--down">Pendientes</span></div><p class="stat-card__label">Aulas por asignar</p><p class="stat-card__value mb-0"><?= (int) $stats['pendientes'] ?></p></article></div>
  <div class="col-sm-6 col-lg-4"><article class="stat-card stat-card--highlighted"><div class="stat-card__top"><span class="stat-card__icon stat-card__icon--light"><i class="bi bi-people"></i></span><span class="stat-card__trend stat-card__trend--neutral">Disponibles</span></div><p class="stat-card__label">Docentes activos</p><p class="stat-card__value mb-0"><?= (int) $stats['docentes'] ?></p></article></div>
</div>
<div class="data-panel"><div class="data-panel__toolbar"><form method="get" action="<?= BASE_URL ?>teacherAssignment/index" class="data-panel__filters">
  <select name="anio" class="form-select" aria-label="Filtrar por año"><option value="">Año escolar (Todos)</option><?php foreach ($anios as $option): ?><option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['anio'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['nombre']) ?></option><?php endforeach; ?></select>
  <select name="grado" class="form-select" aria-label="Filtrar por grado"><option value="">Grado (Todos)</option><?php foreach ($grados as $option): ?><option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['grado'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['nombre']) ?></option><?php endforeach; ?></select>
  <select name="seccion" class="form-select" aria-label="Filtrar por sección"><option value="">Sección (Todas)</option><?php foreach ($secciones as $option): ?><option value="<?= (int) $option['id'] ?>" <?= (string) ($_GET['seccion'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars($option['nombre']) ?></option><?php endforeach; ?></select>
  <div class="data-panel__search"><i class="bi bi-search"></i><input type="search" name="q" class="form-control" placeholder="Buscar por docente o grado..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off"></div>
</form></div>
<div class="data-panel__body"><table class="data-panel__table"><colgroup><col style="width:18%"><col style="width:18%"><col style="width:24%"><col style="width:18%"><col style="width:22%"></colgroup><thead><tr><th>Grado</th><th>Sección</th><th>Docente asignado</th><th>Estado</th><th class="text-center">Acciones</th></tr></thead><tbody>
<?php foreach ($asignaciones as $item): ?><tr><td class="fw-semibold"><?= htmlspecialchars($item['grado']) ?></td><td><?= htmlspecialchars($item['seccion']) ?><div class="text-support small"><?= htmlspecialchars($item['turno']) ?></div></td><td><?php if ($item['docente']): ?><div class="avatar-group"><span class="avatar avatar--sm avatar--primary"><?= htmlspecialchars(mb_strtoupper(mb_substr($item['docente'], 0, 1))) ?></span><span><?= htmlspecialchars($item['docente']) ?></span></div><?php else: ?><span class="text-support">Por asignar</span><?php endif; ?></td><td><span class="status-badge status-badge--<?= $item['estado'] === 'asignado' ? 'active' : 'pending' ?>"><?= $item['estado'] === 'asignado' ? 'Asignado' : 'Pendiente' ?></span></td><td class="text-center"><div class="data-panel__actions"><button type="button" class="action-btn action-btn--edit editar-asignacion" data-id="<?= (int) $item['estructura_id'] ?>" data-asignacion-id="<?= (int) ($item['id_asignacion'] ?? 0) ?>" title="Asignar o editar docente"><i class="bi bi-pencil"></i></button><?php if (!empty($item['id_asignacion'])): ?><button type="button" class="action-btn action-btn--danger quitar-asignacion" data-id="<?= (int) $item['id_asignacion'] ?>" title="Retirar asignación"><i class="bi bi-trash"></i></button><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$asignaciones): ?><tr><td colspan="5" class="text-center text-support py-4">No se encontraron secciones configuradas.</td></tr><?php endif; ?></tbody></table></div><div class="data-panel__footer"><span>Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?> de <?= (int) $paginacion['total'] ?> secciones registradas</span><?php $paginacionAriaLabel = 'Paginación de asignaciones docentes'; require __DIR__ . '/../partials/pagination.php'; ?></div></div>

<?php $modalId = 'nuevaAsignacionModal'; $modalTitle = 'Nueva Asignación'; $modalSubtitle = 'Asigne un docente a una sección académica.'; $formId = 'formNuevaAsignacion'; include __DIR__ . '/assignment-modal.php'; $modalId = 'editarAsignacionModal'; $modalTitle = 'Editar Asignación'; $modalSubtitle = 'Actualice el docente responsable de la sección.'; $formId = 'formEditarAsignacion'; include __DIR__ . '/assignment-modal.php'; $pageContent = ob_get_clean(); require __DIR__ . '/../layouts/app.php';
