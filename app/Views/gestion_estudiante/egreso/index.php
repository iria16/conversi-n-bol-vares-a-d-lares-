<?php
/**
 * Vista: estudiantes/egresos.php
 * Egresos / Graduación — gestión de culminación académica y graduación masiva.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats        ['total' => int, 'graduados' => int, 'pendientes' => int]
 * - array $candidatos   cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 *                        'avatar_img' (opcional),'grado','seccion',
 *                        'estado' => 'graduado'|'pendiente']
 * - array $secciones    lista de secciones para el filtro
 * - array $filtros      valores actuales de los filtros aplicados (opcional)
 * - array $paginacion   ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $candidatos
 * @var array $secciones
 * @var array $filtros
 * @var array $paginacion
 */

$pageTitle       = 'Graduación';
$pageDescription = 'Gestione la culminación académica de los estudiantes y el registro de egresos por periodo lectivo.';
$currentNav      = 'egresos';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Egresos y Graduación', 'href' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Egresos / Graduación</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>egresos/exportar" class="btn btn-outline-primary">
      <i class="bi bi-download"></i>
      Exportar Listado
    </a>
    <button type="button" id="btnGraduacionMasiva" class="btn btn-primary" disabled>
      <i class="bi bi-mortarboard-fill"></i>
      Graduación Masiva
    </button>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-people-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Total Candidatos</div>
      <div class="stat-card__value"><?= number_format((int) $stats['total'], 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Graduados</div>
      <div class="stat-card__value"><?= (int) $stats['graduados'] ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--warning">
          <i class="bi bi-hourglass-split"></i>
        </div>
      </div>
      <div class="stat-card__label">Pendientes</div>
      <div class="stat-card__value"><?= (int) $stats['pendientes'] ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros ===================== -->
<div class="content-card mb-4">
  <form method="get" action="<?= BASE_URL ?>egresos/index" class="row g-3 align-items-end auto-filters">
    <div class="col-md-4">
      <label for="filtroSeccion" class="label-sigde">Sección</label>
      <select id="filtroSeccion" name="seccion" class="form-select">
        <option value="">Todas las secciones</option>
        <?php foreach ($secciones as $seccion): ?>
          <option value="<?= htmlspecialchars($seccion) ?>" <?= ($filtros['seccion'] ?? '') === $seccion ? 'selected' : '' ?>>
            Sección <?= htmlspecialchars($seccion) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label for="filtroBusqueda" class="label-sigde">Buscar Estudiante</label>
      <div class="input-icon-group">
        <i class="bi bi-search"></i>
        <input
          type="search"
          id="filtroBusqueda"
          name="q"
          class="form-control"
          placeholder="Nombre o cédula..."
          value="<?= htmlspecialchars($filtros['q'] ?? '') ?>"
        >
      </div>
    </div>

    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100" title="Aplicar filtros">
        <i class="bi bi-funnel"></i>
      </button>
    </div>
  </form>
</div>

<!-- ===================== Tabla de candidatos ===================== -->
<div class="data-panel">

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 5%;">
        <col style="width: 30%;">
        <col style="width: 15%;">
        <col style="width: 15%;">
        <col style="width: 15%;">
        <col style="width: 20%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col" class="text-center">
            <input type="checkbox" id="checkAllEgresos" class="form-check-input" aria-label="Seleccionar todos">
          </th>
          <th scope="col">Estudiante</th>
          <th scope="col">Grado</th>
          <th scope="col">Sección</th>
          <th scope="col">Estado</th>
          <th scope="col" class="text-center">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($candidatos as $c): ?>
          <?php $esGraduado = $c['estado'] === 'graduado'; ?>
          <tr>
            <td class="text-center">
              <input
                type="checkbox"
                class="form-check-input check-egreso"
                value="<?= (int) $c['id'] ?>"
                <?= $esGraduado ? 'disabled' : '' ?>
                aria-label="Seleccionar <?= htmlspecialchars($c['nombre']) ?>"
              >
            </td>
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($c['avatar_color']) ?>">
                  <?php if (!empty($c['avatar_img'])): ?>
                    <img src="<?= htmlspecialchars($c['avatar_img']) ?>" alt="">
                  <?php else: ?>
                    <?= htmlspecialchars($c['iniciales']) ?>
                  <?php endif; ?>
                </span>
                <div>
                  <div class="avatar-group__name"><?= htmlspecialchars($c['nombre']) ?></div>
                  <div class="avatar-group__meta"><?= htmlspecialchars($c['cedula']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-support"><?= htmlspecialchars($c['grado']) ?></td>
            <td class="text-support">Sección <?= htmlspecialchars($c['seccion']) ?></td>
            <td>
              <span class="status-badge status-badge--<?= $esGraduado ? 'active' : 'pending' ?>">
                <?= $esGraduado ? 'Graduado' : 'Pendiente' ?>
              </span>
            </td>
            <td class="text-center">
              <div class="d-flex align-items-center justify-content-center gap-2">
                <?php if ($esGraduado): ?>
                  <button type="button" class="btn btn-primary btn-sm" disabled>Graduar</button>
                <?php else: ?>
                  <form method="post" action="<?= BASE_URL ?>egresos/graduar/<?= (int) $c['id'] ?>" class="d-inline">
                    <button type="submit" class="btn btn-primary btn-sm">Graduar</button>
                  </form>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>estudiantes/ficha/<?= (int) $c['id'] ?>"
                   class="action-btn action-btn--view" title="Ver expediente">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($candidatos)): ?>
          <tr>
            <td colspan="6" class="text-center text-support py-4">
              No se encontraron candidatos con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= number_format((int) $paginacion['total'], 0, ',', '.') ?> candidatos
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de egresos';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';