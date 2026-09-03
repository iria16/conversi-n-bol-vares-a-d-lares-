<?php
/**
 * Vista: audit/index.php
 * Bitácora del Sistema — registro detallado de acciones y movimientos.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array  $stats       ['actividad_hoy' => int, 'alertas' => int, 'usuarios_activos' => int]
 * - array  $registros   cada uno: ['fecha','hora','usuario','usuario_login','iniciales',
 *                        'avatar_color','accion' => 'login'|'create'|'update'|'delete'|'query'|'export',
 *                        'accion_label','modulo','ip']
 * - array  $acciones    lista de acciones para el filtro [valor => etiqueta]
 * - array  $paginacion  ['desde','hasta','total','pagina_actual','total_paginas']
 * - string $fechaDesde
 * - string $fechaHasta
 *
 * @var array  $stats
 * @var array  $registros
 * @var array  $acciones
 * @var array  $paginacion
 * @var string $fechaDesde
 * @var string $fechaHasta
 */

$pageTitle       = 'Bitácora del Sistema';
$pageDescription = 'Registro detallado de acciones y movimientos en la plataforma.';
$currentNav      = 'bitacora';

$breadcrumbs = [
    ['label' => 'Administración', 'url' => null],
    ['label' => 'Bitácora', 'url' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Bitácora del Sistema</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>bitacora/exportar" class="btn btn-outline-primary">
      <i class="bi bi-download"></i>
      Exportar
    </a>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <!-- Tarjeta 1: Actividad Hoy (destacada) -->
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card stat-card--highlighted">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-graph-up"></i>
        </div>
      </div>
      <div class="stat-card__label">Actividad Hoy</div>
      <div class="stat-card__value"><?= (int) $stats['actividad_hoy'] ?> Acciones</div>
    </div>
  </div>

  <!-- Tarjeta 2: Alertas de Seguridad -->
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card stat-card--alert">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Alertas de Seguridad</div>
      <div class="stat-card__value"><?= (int) $stats['alertas'] ?></div>
    </div>
  </div>

  <!-- Tarjeta 3: Usuarios Activos -->
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-person-check-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Usuarios Activos</div>
      <div class="stat-card__value"><?= (int) $stats['usuarios_activos'] ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros ===================== -->
<div class="content-card mb-4">
  <form method="get" action="<?= BASE_URL ?>bitacora/index" class="row g-3 align-items-end auto-filters">
    <div class="col-md-5">
      <label class="label-sigde d-block">Rango de fechas</label>
      <div class="d-flex align-items-center gap-2">
        <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($fechaDesde ?? '') ?>">
        <span class="text-support">a</span>
        <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($fechaHasta ?? '') ?>">
      </div>
    </div>

    <div class="col-md-4">
      <label for="filtroAccion" class="label-sigde d-block">Acción</label>
      <select id="filtroAccion" name="accion" class="form-select">
        <option value="">Todas las acciones</option>
        <?php foreach ($acciones as $valor => $etiqueta): ?>
          <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($etiqueta) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-funnel"></i>
        Filtrar
      </button>
    </div>
  </form>
</div>

<!-- ===================== Tabla de bitácora ===================== -->
<div class="data-panel">

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 18%;">
        <col style="width: 26%;">
        <col style="width: 16%;">
        <col style="width: 22%;">
        <col style="width: 18%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Fecha y Hora</th>
          <th scope="col">Usuario</th>
          <th scope="col">Acción</th>
          <th scope="col">Módulo</th>
          <th scope="col">Dirección IP</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($registros as $r): ?>
          <tr>
            <td class="text-support">
              <?= htmlspecialchars($r['fecha']) ?><br>
              <span class="text-body-tertiary"><?= htmlspecialchars($r['hora']) ?></span>
            </td>
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--sm avatar--<?= htmlspecialchars($r['avatar_color']) ?>">
                  <?= htmlspecialchars($r['iniciales']) ?>
                </span>
                <div>
                  <div class="avatar-group__name"><?= htmlspecialchars($r['usuario']) ?></div>
                  <div class="avatar-group__meta"><?= htmlspecialchars($r['usuario_login']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="action-badge action-badge--<?= htmlspecialchars($r['accion']) ?>">
                <?= htmlspecialchars($r['accion_label']) ?>
              </span>
            </td>
            <td class="text-support"><?= htmlspecialchars($r['modulo']) ?></td>
            <td class="text-support"><?= htmlspecialchars($r['ip']) ?></td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($registros)): ?>
          <tr>
            <td colspan="5" class="text-center text-support py-4">
              No se encontraron registros con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= (int) $paginacion['total'] ?> registros
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de la bitácora del sistema';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';