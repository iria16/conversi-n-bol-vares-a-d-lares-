<?php
/**
 * Vista: settings/index.php
 * Gestión de Catálogos del Sistema — selector de catálogo, listado, filtros y alta de elementos.
 *
 * Espera del controlador:
 * @var array  $tiposCatalogo   cada uno: ['clave','nombre','icono','total']
 * @var string $catalogoActivo  clave del catálogo seleccionado actualmente (ej. 'turno')
 * @var array  $elementos       cada uno: ['id','nombre','estado' => 'activo'|'inactivo']
 * @var array  $paginacion      ['desde','hasta','total','pagina_actual','total_paginas']
 */

$pageTitle       = 'Gestión de Catálogos del Sistema';
$pageDescription = 'Configure los elementos que parametrizan el funcionamiento escolar.';
$currentNav      = 'catalogos';
$extraScripts[]  = '/js/catalogos.js';

$breadcrumbs = [
    ['label' => 'Administración', 'url' => null],
    ['label' => 'Catálogos', 'url' => null],
];

$catalogoInfo = null;
foreach ($tiposCatalogo as $tipo) {
    if ($tipo['clave'] === $catalogoActivo) {
        $catalogoInfo = $tipo;
        break;
    }
}
$catalogoInfo = $catalogoInfo ?? $tiposCatalogo[0];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= htmlspecialchars($pageTitle) ?></h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>
</div>

<!-- ===================== Selector de catálogo + acción principal ===================== -->
<div class="data-panel mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3">

    <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 360px;">
      <label for="tipoCatalogo" class="text-support small text-nowrap mb-0">Seleccionar tipo de catálogo:</label>
      <select
        id="tipoCatalogo"
        name="tipo"
        class="form-select form-select-sm"
      >
        <?php foreach ($tiposCatalogo as $tipo): ?>
          <option value="<?= htmlspecialchars($tipo['clave']) ?>" <?= $tipo['clave'] === $catalogoActivo ? 'selected' : '' ?>>
            <?= htmlspecialchars($tipo['nombre']) ?> (<?= (int) $tipo['total'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2"
            data-bs-toggle="modal" data-bs-target="#modalNuevoElemento">
      <i class="bi bi-plus-lg"></i>
      Nuevo Elemento
    </button>
  </div>
</div>

<!-- ===================== Panel del catálogo activo ===================== -->
<div class="data-panel">

  <div class="data-panel__toolbar justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <div class="stat-card__icon stat-card__icon--primary">
        <i class="bi <?= htmlspecialchars($catalogoInfo['icono']) ?>"></i>
      </div>
      <div>
        <div class="stat-card__label mb-0">Catálogo activo</div>
        <div class="content-card__title mb-0"><?= htmlspecialchars($catalogoInfo['nombre']) ?></div>
      </div>
    </div>
    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
      <?= (int) $catalogoInfo['total'] ?> registro<?= $catalogoInfo['total'] === 1 ? '' : 's' ?>
    </span>
  </div>

  <div class="data-panel__toolbar">
    <form method="get" action="<?= BASE_URL ?>catalogo/index" class="data-panel__filters">
      <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">

      <label for="filtroEstado" class="visually-hidden">Estado</label>
      <select id="filtroEstado" name="estado" class="form-select">
        <option value="" <?= empty($_GET['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
        <option value="activo" <?= ($_GET['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
        <option value="inactivo" <?= ($_GET['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
      </select>

      <label for="buscarElemento" class="visually-hidden">Buscar</label>
      <div class="data-panel__search">
        <i class="bi bi-search"></i>
        <input type="search" id="buscarElemento" name="q" class="form-control" placeholder="Buscar por nombre..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" autocomplete="off">
      </div>
    </form>
  </div>

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 50%;">
        <col style="width: 25%;">
        <col style="width: 25%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Nombre</th>
          <th scope="col">Estado</th>
          <th scope="col" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($elementos as $el): ?>
          <?php $esInactivo = $el['estado'] !== 'activo'; ?>
          <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
            <td class="fw-semibold"><?= htmlspecialchars($el['nombre']) ?></td>
            <td>
              <span class="status-badge status-badge--<?= $esInactivo ? 'inactive' : 'active' ?>">
                <?= $esInactivo ? 'Inactivo' : 'Activo' ?>
              </span>
            </td>
            <td class="text-center">
              <div class="data-panel__actions">
                <a href="#"
                   class="action-btn action-btn--edit editar-elemento"
                   data-elemento-id="<?= (int) $el['id'] ?>"
                   data-elemento-nombre="<?= htmlspecialchars($el['nombre']) ?>"
                   title="Editar elemento">
                  <i class="bi bi-pencil"></i>
                </a>
                <div class="form-check form-switch table-switch mb-0">
                  <input class="form-check-input toggle-estado"
                         type="checkbox"
                         role="switch"
                         data-elemento-id="<?= (int) $el['id'] ?>"
                         <?= $esInactivo ? '' : 'checked' ?>
                         aria-label="Activar o desactivar <?= htmlspecialchars($el['nombre']) ?>">
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($elementos)): ?>
          <tr>
            <td colspan="3" class="text-center text-support py-4">
              No se encontraron elementos con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= (int) $paginacion['total'] ?> elementos
    </span>
    <?php
      $paginacionAriaLabel = 'Paginación de catálogos';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<!-- ===================== Modal: Nuevo Elemento ===================== -->
<div class="modal fade app-modal" id="modalNuevoElemento" tabindex="-1" aria-labelledby="modalNuevoElementoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formNuevoElemento">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNuevoElementoLabel">
            Nuevo elemento — <?= htmlspecialchars($catalogoInfo['nombre']) ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">
          <div class="mb-3">
            <label for="nuevoElementoNombre" class="form-label">Nombre</label>
            <input type="text" id="nuevoElementoNombre" name="nombre" class="form-control" required maxlength="100" autofocus>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
include __DIR__ . '/edit-modal.php';
?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';