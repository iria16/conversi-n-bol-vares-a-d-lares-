<?php
/**
 * Vista: students/ratification.php
 * Ratificación de Matrícula — gestión de continuidad escolar y proyección
 * del próximo periodo académico.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats           ['porRatificar' => int, 'yaRatificados' => int, 'avance' => int]
 * - array $ratificaciones  cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 *                           'avatar_img' (opcional),'grado_actual','seccion',
 *                           'proximo_grado','estado' => 'ratificado'|'pendiente']
 * - array $grados          lista de grados para el filtro
 * - array $secciones       lista de secciones para el filtro
 * - array $filtros         valores actuales de los filtros aplicados (opcional)
 * - array $paginacion      ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $ratificaciones
 * @var array $grados
 * @var array $secciones
 * @var array $filtros
 * @var array $paginacion
 */

$pageTitle       = 'Ratificación de Matrícula';
$pageDescription = 'Gestione la continuidad escolar y proyecte el próximo periodo académico.';
$currentNav      = 'ratificacion';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'url' => null],
    ['label' => 'Ratificación de Matrícula', 'url' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Ratificación de Matrícula</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>ratificacion/exportar" class="btn btn-outline-primary">
      <i class="bi bi-download"></i>
      Exportar Listado
    </a>
    <button type="button" id="btnRatificacionMasiva" class="btn btn-primary" disabled>
      <i class="bi bi-check2-square"></i>
      Ratificación Masiva
    </button>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-clipboard-check"></i>
        </div>
      </div>
      <div class="stat-card__label">Por Ratificar</div>
      <div class="stat-card__value"><?= number_format((int) $stats['porRatificar'], 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Ya Ratificados</div>
      <div class="stat-card__value"><?= (int) $stats['yaRatificados'] ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--warning">
          <i class="bi bi-graph-up-arrow"></i>
        </div>
      </div>
      <div class="stat-card__label">Avance del Proceso</div>
      <div class="stat-card__value"><?= (int) $stats['avance'] ?>%</div>
      <div class="stat-card__bar">
        <span style="width: <?= (int) $stats['avance'] ?>%;"></span>
      </div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros ===================== -->
<div class="content-card mb-4">
  <form method="get" action="<?= BASE_URL ?>ratificacion/index" class="row g-3 align-items-end auto-filters">
    <div class="col-md-3">
      <label for="filtroGrado" class="label-sigde">Grado Actual</label>
      <select id="filtroGrado" name="grado" class="form-select">
        <option value="">Todos los grados</option>
        <?php foreach ($grados as $grado): ?>
          <option value="<?= htmlspecialchars($grado) ?>" <?= ($filtros['grado'] ?? '') === $grado ? 'selected' : '' ?>>
            <?= htmlspecialchars($grado) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
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

    <div class="col-md-4">
      <label for="filtroBusqueda" class="label-sigde">Búsqueda Rápida</label>
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
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-funnel"></i>
        Buscar
      </button>
    </div>
  </form>
</div>

<!-- ===================== Tabla de ratificación ===================== -->
<div class="data-panel">

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 5%;">
        <col style="width: 27%;">
        <col style="width: 14%;">
        <col style="width: 12%;">
        <col style="width: 14%;">
        <col style="width: 13%;">
        <col style="width: 15%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col" class="text-center">
            <input type="checkbox" id="checkAllRatificacion" class="form-check-input" aria-label="Seleccionar todos">
          </th>
          <th scope="col">Estudiante</th>
          <th scope="col">Grado Actual</th>
          <th scope="col">Sección</th>
          <th scope="col">Próximo Grado</th>
          <th scope="col">Estado</th>
          <th scope="col" class="text-center">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ratificaciones as $r): ?>
          <?php $esRatificado = $r['estado'] === 'ratificado'; ?>
          <tr>
            <td class="text-center">
              <input
                type="checkbox"
                class="form-check-input check-ratificacion"
                value="<?= (int) $r['id'] ?>"
                <?= $esRatificado ? 'disabled' : '' ?>
                aria-label="Seleccionar <?= htmlspecialchars($r['nombre']) ?>"
              >
            </td>
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($r['avatar_color']) ?>">
                  <?php if (!empty($r['avatar_img'])): ?>
                    <img src="<?= htmlspecialchars($r['avatar_img']) ?>" alt="">
                  <?php else: ?>
                    <?= htmlspecialchars($r['iniciales']) ?>
                  <?php endif; ?>
                </span>
                <div>
                  <div class="avatar-group__name"><?= htmlspecialchars($r['nombre']) ?></div>
                  <div class="avatar-group__meta"><?= htmlspecialchars($r['cedula']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-support"><?= htmlspecialchars($r['grado_actual']) ?></td>
            <td class="text-support">Sección <?= htmlspecialchars($r['seccion']) ?></td>
            <td class="fw-bold" style="color: var(--bs-primary);"><?= htmlspecialchars($r['proximo_grado']) ?></td>
            <td>
              <span class="status-badge status-badge--<?= $esRatificado ? 'active' : 'pending' ?>">
                <?= $esRatificado ? 'Ratificado' : 'Pendiente' ?>
              </span>
            </td>
            <td class="text-center">
              <div class="d-flex align-items-center justify-content-center gap-2">
                <?php if ($esRatificado): ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Completado</button>
                <?php else: ?>
                  <form method="post" action="<?= BASE_URL ?>ratificacion/ratificar/<?= (int) $r['id'] ?>" class="d-inline">
                    <button type="submit" class="btn btn-primary btn-sm">Ratificar</button>
                  </form>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>estudiantes/ficha/<?= (int) $r['id'] ?>"
                   class="action-btn action-btn--view" title="Ver expediente">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($ratificaciones)): ?>
          <tr>
            <td colspan="7" class="text-center text-support py-4">
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
      de <?= number_format((int) $paginacion['total'], 0, ',', '.') ?> estudiantes
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de ratificación';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const checkAll  = document.getElementById('checkAllRatificacion');
  const checks    = document.querySelectorAll('.check-ratificacion:not(:disabled)');
  const btnMasivo = document.getElementById('btnRatificacionMasiva');

  function actualizarBotonMasivo() {
    const seleccionados = document.querySelectorAll('.check-ratificacion:checked').length;
    btnMasivo.disabled = seleccionados === 0;
  }

  checkAll.addEventListener('change', function () {
    checks.forEach(function (c) { c.checked = checkAll.checked; });
    actualizarBotonMasivo();
  });

  checks.forEach(function (c) {
    c.addEventListener('change', actualizarBotonMasivo);
  });

  btnMasivo.addEventListener('click', function () {
    const ids = Array.from(document.querySelectorAll('.check-ratificacion:checked')).map(function (c) {
      return c.value;
    });
    if (ids.length === 0) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= BASE_URL ?>ratificacion/ratificarMasivo';

    ids.forEach(function (id) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ids[]';
      input.value = id;
      form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  });
});
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';