<?php
/**
 * Vista: configuracion/catalogo/index.php
 * Gestión de Catálogos del Sistema — selector de catálogo, listado, filtros y alta de elementos.
 *
 * Espera del controlador:
 * @var array  $tiposCatalogo   cada uno: ['clave','nombre','icono','total']
 * @var string $catalogoActivo  clave del catálogo seleccionado actualmente (ej. 'turno')
 * @var array  $elementos       cada uno: ['id','nombre','estado' => 'ACTIVO'|'INACTIVO']
 * @var array  $filters         ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * @var array  $paginacion      ['desde','hasta','total','pagina_actual','total_paginas']
 */

$pageTitle  = 'Gestión de Catálogos del Sistema';
$currentNav = 'catalogos';

$breadcrumbs = [
    ['label' => 'Administración', 'href' => null],
    ['label' => 'Catálogos', 'href' => null],
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

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2"
          data-bs-toggle="modal" data-bs-target="#modalNuevoElemento">
    <i class="bi bi-plus-lg" aria-hidden="true"></i>
    Nuevo Elemento
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Catálogos del Sistema';
  $pageSubheading    = 'Configure los elementos que parametrizan el funcionamiento escolar.';
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Selector de catálogo (equivalente a los stat cards de cuentas) ---------- -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="data-panel">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3">
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 360px;">
          <label for="tipoCatalogo" class="text-support small text-nowrap mb-0">Seleccionar tipo de catálogo:</label>
          <select id="tipoCatalogo" name="tipo" class="form-select form-select-sm">
            <?php foreach ($tiposCatalogo as $tipo): ?>
              <option value="<?= htmlspecialchars($tipo['clave']) ?>" <?= $tipo['clave'] === $catalogoActivo ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo['nombre']) ?> (<?= (int) $tipo['total'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="d-flex align-items-center gap-3 ps-3 border-start">
          <div class="stat-card__icon stat-card__icon--primary d-flex align-items-center justify-content-center">
            <i class="bi <?= htmlspecialchars($catalogoInfo['icono']) ?>" aria-hidden="true"></i>
          </div>
          <div class="d-flex flex-column justify-content-center">
            <div class="stat-card__label mb-0">Catálogo activo</div>
            <div class="content-card__title mb-0"><?= htmlspecialchars($catalogoInfo['nombre']) ?></div>
          </div>
          <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
            <?= (int) $catalogoInfo['total'] ?> registro<?= $catalogoInfo['total'] === 1 ? '' : 's' ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
  // ---------- Data panel: filtros ----------
  // name="tipo" viaja oculto para que el GET conserve el catálogo activo
  // al filtrar/buscar/paginar.
  $panelFormAction = BASE_URL . 'catalogo/index';

  ob_start();
  ?>
  <input type="hidden" name="tipo" value="<?= htmlspecialchars($catalogoActivo) ?>">

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select">
    <option value="" <?= empty($filters['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
    <option value="activo"   <?= ($filters['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
    <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $filters['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre...';

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Nombre</th>
    <th scope="col" class="text-center">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($elementos as $el):
    // La BD guarda el estado en MAYÚSCULA ('ACTIVO' / 'INACTIVO'),
    // ver comentario de CatalogoModel. strtoupper() evita que esto
    // se rompa si algún día se guarda distinto.
    $esInactivo = strtoupper($el['estado']) !== 'ACTIVO';
  ?>
    <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
      <td class="fw-semibold"><?= htmlspecialchars($el['nombre']) ?></td>
      <td class="text-center">
        <?php
          $badgeLabel  = $esInactivo ? 'Inactivo' : 'Activo';
          $badgeStatus = $esInactivo ? 'inactive' : 'active';
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
                  class="action-btn action-btn--edit editar-elemento"
                  data-elemento-id="<?= (int) $el['id'] ?>"
                  data-elemento-nombre="<?= htmlspecialchars($el['nombre']) ?>"
                  title="Editar elemento">
            <i class="bi bi-pencil" aria-hidden="true"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0" title="<?= $esInactivo ? 'Activar' : 'Desactivar' ?> elemento">
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
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' elementos';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<!-- ===================== Modales ===================== -->
<?php
require_once __DIR__ . '/modals/create-modal.php';
require_once __DIR__ . '/modals/edit-modal.php';
?>

<?php
$pageContent  = ob_get_clean();
$extraScripts = ['/js/catalogo.js'];
include __DIR__ . '/../../layouts/app.php';