<?php
/**
 * Vista: students/index.php
 * Consulta de Expedientes — listado y búsqueda avanzada de estudiantes.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats          ['total' => int, 'activos' => int, 'inactivos' => int]
 * - array $estudiantes    cada uno: ['id','nombre','iniciales','avatar_color',
 *                          'avatar_img' (opcional),'cedula_escolar','grado','seccion',
 *                          'estado' => 'activo'|'inactivo']
 * - array $aniosEscolares lista de años escolares para el filtro
 * - array $grados         lista de grados para el filtro
 * - array $secciones      lista de secciones para el filtro
 * - array $filtros        valores actuales de los filtros aplicados (opcional)
 * - array $paginacion     ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $estudiantes
 * @var array $aniosEscolares
 * @var array $grados
 * @var array $secciones
 * @var array $filtros
 * @var array $paginacion
 */

$pageTitle       = 'Consulta de Expedientes';
$pageDescription = 'Gestión y búsqueda avanzada del alumnado de la institución.';
$currentNav      = 'estudiantes';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Estudiantes','href' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Consulta de Expedientes</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>estudiantes/exportar" class="btn btn-outline-primary">
      <i class="bi bi-download"></i>
      Exportar CSV
    </a>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-mortarboard-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Total Estudiantes</div>
      <div class="stat-card__value"><?= number_format((int) $stats['total'], 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <?php $pctActivos = $stats['total'] > 0 ? round(($stats['activos'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-person-check-fill"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--up"><?= $pctActivos ?>% del total</span>
      </div>
      <div class="stat-card__label">Activos</div>
      <div class="stat-card__value"><?= (int) $stats['activos'] ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <?php $pctInactivos = $stats['total'] > 0 ? round(($stats['inactivos'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--danger">
          <i class="bi bi-person-x-fill"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--down"><?= $pctInactivos ?>% del total</span>
      </div>
      <div class="stat-card__label">Inactivos</div>
      <div class="stat-card__value"><?= (int) $stats['inactivos'] ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros ===================== -->
<div class="content-card mb-4">
  <div class="content-card__header">
    <p class="content-card__title">
      <i class="bi bi-funnel me-2"></i>
      Filtros de Búsqueda
    </p>
  </div>

  <form method="get" action="<?= BASE_URL ?>estudiantes/index" class="row g-3 auto-filters">
    <div class="col-md-4">
      <label for="filtroBusqueda" class="label-sigde">Nombre o Cédula</label>
      <div class="input-icon-group">
        <i class="bi bi-search"></i>
        <input
          type="search"
          id="filtroBusqueda"
          name="q"
          class="form-control"
          placeholder="Ej. Carlos Pérez o 28.123.456"
          value="<?= htmlspecialchars($filtros['q'] ?? '') ?>"
        >
      </div>
    </div>

    <div class="col-md-2">
      <label for="filtroAnio" class="label-sigde">Año Escolar</label>
      <select id="filtroAnio" name="anio" class="form-select">
        <?php foreach ($aniosEscolares as $anio): ?>
          <option value="<?= htmlspecialchars($anio) ?>" <?= ($filtros['anio'] ?? '') === $anio ? 'selected' : '' ?>>
            <?= htmlspecialchars($anio) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="filtroGrado" class="label-sigde">Grado</label>
      <select id="filtroGrado" name="grado" class="form-select">
        <option value="">Todos los grados</option>
        <?php foreach ($grados as $grado): ?>
          <option value="<?= htmlspecialchars($grado) ?>" <?= ($filtros['grado'] ?? '') === $grado ? 'selected' : '' ?>>
            <?= htmlspecialchars($grado) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="filtroSeccion" class="label-sigde">Sección</label>
      <select id="filtroSeccion" name="seccion" class="form-select">
        <option value="">Todas</option>
        <?php foreach ($secciones as $seccion): ?>
          <option value="<?= htmlspecialchars($seccion) ?>" <?= ($filtros['seccion'] ?? '') === $seccion ? 'selected' : '' ?>>
            <?= htmlspecialchars($seccion) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label for="filtroEstado" class="label-sigde">Estado</label>
      <select id="filtroEstado" name="estado" class="form-select">
        <option value="">Cualquiera</option>
        <option value="activo" <?= ($filtros['estado'] ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
        <option value="inactivo" <?= ($filtros['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
      </select>
    </div>

    <div class="col-12 d-flex justify-content-end align-items-center gap-3">
      <a href="<?= BASE_URL ?>estudiantes/index" class="link-sigde">Limpiar Filtros</a>
      <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
    </div>
  </form>
</div>

<!-- ===================== Tabla de estudiantes ===================== -->
<div class="data-panel">

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 30%;">
        <col style="width: 20%;">
        <col style="width: 20%;">
        <col style="width: 15%;">
        <col style="width: 15%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Nombre Completo</th>
          <th scope="col">Cédula Escolar</th>
          <th scope="col">Grado y Sección</th>
          <th scope="col" class="text-center">Estado</th>
          <th scope="col" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($estudiantes as $e): ?>
          <?php $esInactivo = $e['estado'] === 'inactivo'; ?>
          <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($e['avatar_color']) ?>">
                  <?php if (!empty($e['avatar_img'])): ?>
                    <img src="<?= htmlspecialchars($e['avatar_img']) ?>" alt="">
                  <?php else: ?>
                    <?= htmlspecialchars($e['iniciales']) ?>
                  <?php endif; ?>
                </span>
                <span class="avatar-group__name"><?= htmlspecialchars($e['nombre']) ?></span>
              </div>
            </td>
            <td class="text-support"><?= htmlspecialchars($e['cedula_escolar']) ?></td>
            <td class="text-support"><?= htmlspecialchars($e['grado']) ?> "<?= htmlspecialchars($e['seccion']) ?>"</td>
            <td class="text-center">
              <span class="status-badge status-badge--<?= $esInactivo ? 'inactive' : 'active' ?>">
                <?= $esInactivo ? 'Inactivo' : 'Activo' ?>
              </span>
            </td>
            <td class="text-center">
              <a href="<?= BASE_URL ?>estudiantes/ficha/<?= (int) $e['id'] ?>"
                 class="action-btn action-btn--view" title="Ver expediente">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($estudiantes)): ?>
          <tr>
            <td colspan="5" class="text-center text-support py-4">
              No se encontraron estudiantes con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= number_format((int) $paginacion['total'], 0, ',', '.') ?> expedientes
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de expedientes de estudiantes';
      require __DIR__ . '/../../partials/pagination.php';
    ?>
  </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';