<?php
/**
 * Vista: students/enrollments.php
 * Gestión de Inscripciones — ingreso de nuevos estudiantes y ratificaciones
 * anuales para el periodo escolar vigente.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats          ['inscritosAnio' => int, 'trendInscritos' => int,
 *                          'cuposDisponibles' => int, 'inscripcionesMes' => int]
 * - array $inscripciones  cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 *                          'avatar_img' (opcional),'grado','seccion','fecha_inscripcion',
 *                          'estado' => 'completa'|'incompleta']
 * - array $aniosEscolares lista de periodos escolares para el filtro (ej. '2024-2025')
 * - array $grados         lista de grados para el filtro
 * - array $secciones      lista de secciones para el filtro
 * - array $filtros        valores actuales de los filtros aplicados (opcional)
 * - array $paginacion     ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $inscripciones
 * @var array $aniosEscolares
 * @var array $grados
 * @var array $secciones
 * @var array $filtros
 * @var array $paginacion
 */

$pageTitle       = 'Gestión de Inscripciones';
$pageDescription = 'Supervisa y procesa el ingreso de nuevos estudiantes y ratificaciones anuales para el periodo escolar vigente.';
$currentNav      = 'inscripcion';
$extraScripts    = ['/js/enrollments.js'];

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'url' => null],
    ['label' => 'Inscripciones', 'url' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestión de Inscripciones</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaInscripcionModal">
      <i class="bi bi-person-plus"></i>
      Nueva Inscripción
    </button>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--info">
          <i class="bi bi-people-fill"></i>
        </div>
        <?php if (!empty($stats['trendInscritos'])): ?>
          <span class="stat-card__trend stat-card__trend--up">
            <i class="bi bi-arrow-up-short"></i><?= (int) $stats['trendInscritos'] ?>%
          </span>
        <?php endif; ?>
      </div>
      <div class="stat-card__label">Inscritos Este Año Escolar</div>
      <div class="stat-card__value"><?= number_format((int) $stats['inscritosAnio'], 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Cupos Disponibles Totales</div>
      <div class="stat-card__value"><?= number_format((int) $stats['cuposDisponibles'], 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-calendar-check-fill"></i>
        </div>
      </div>
      <div class="stat-card__label">Inscripciones Este Mes</div>
      <div class="stat-card__value"><?= number_format((int) $stats['inscripcionesMes'], 0, ',', '.') ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros ===================== -->
<div class="data-panel">
  <div class="data-panel__toolbar">
    <form method="get" action="<?= BASE_URL ?>inscripciones/index" id="filtrosInscripciones" class="data-panel__filters">
      <select name="anio" class="form-select" aria-label="Filtrar por año escolar">
        <option value="">Año escolar (Todos)</option>
        <?php foreach ($aniosEscolares as $anio): ?>
          <option value="<?= htmlspecialchars($anio) ?>" <?= ($filtros['anio'] ?? '') === $anio ? 'selected' : '' ?>>
            <?= htmlspecialchars($anio) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="grado" class="form-select" aria-label="Filtrar por grado">
        <option value="">Grado (Todos)</option>
        <?php foreach ($grados as $grado): ?>
          <option value="<?= htmlspecialchars($grado) ?>" <?= ($filtros['grado'] ?? '') === $grado ? 'selected' : '' ?>>
            <?= htmlspecialchars($grado) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="seccion" class="form-select" aria-label="Filtrar por sección">
        <option value="">Sección (Todas)</option>
        <?php foreach ($secciones as $seccion): ?>
          <option value="<?= htmlspecialchars($seccion) ?>" <?= ($filtros['seccion'] ?? '') === $seccion ? 'selected' : '' ?>>
            <?= htmlspecialchars($seccion) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="data-panel__search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Buscar por nombre o cédula..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>" autocomplete="off">
      </div>
    </form>
  </div>

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 28%;">
        <col style="width: 14%;">
        <col style="width: 12%;">
        <col style="width: 18%;">
        <col style="width: 14%;">
        <col style="width: 14%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Estudiante</th>
          <th scope="col">Grado</th>
          <th scope="col">Sección</th>
          <th scope="col">Fecha Inscripción</th>
          <th scope="col">Estado</th>
          <th scope="col" class="text-center">Acción</th>
        </tr>
      </thead>
      <tbody id="tablaInscripcionesBody">
        <?php foreach ($inscripciones as $i): ?>
          <?php $esCompleta = $i['estado'] === 'completa'; ?>
          <tr>
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($i['avatar_color']) ?>">
                  <?php if (!empty($i['avatar_img'])): ?>
                    <img src="<?= htmlspecialchars($i['avatar_img']) ?>" alt="">
                  <?php else: ?>
                    <?= htmlspecialchars($i['iniciales']) ?>
                  <?php endif; ?>
                </span>
                <div>
                  <div class="avatar-group__name"><?= htmlspecialchars($i['nombre']) ?></div>
                  <div class="avatar-group__meta"><?= htmlspecialchars($i['cedula']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-support"><?= htmlspecialchars($i['grado']) ?></td>
            <td class="text-support"><?= htmlspecialchars($i['seccion']) ?></td>
            <td class="text-support"><?= htmlspecialchars($i['fecha_inscripcion']) ?></td>
            <td>
              <span class="status-badge status-badge--<?= $esCompleta ? 'active' : 'pending' ?>">
                <?= $esCompleta ? 'Completa' : 'Incompleta' ?>
              </span>
            </td>
            <td class="text-center">
              <?php if ($esCompleta): ?>
                <a href="<?= BASE_URL ?>inscripciones/ver/<?= (int) $i['id'] ?>"
                   class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-eye"></i>
                  Ver
                </a>
              <?php else: ?>
                <a href="<?= BASE_URL ?>inscripciones/continuar/<?= (int) $i['id'] ?>"
                   class="btn btn-primary btn-sm">
                  Continuar
                  <i class="bi bi-arrow-right"></i>
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($inscripciones)): ?>
          <tr>
            <td colspan="6" class="text-center text-support py-4">
              No se encontraron inscripciones con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span id="paginacionInfo">
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= number_format((int) $paginacion['total'], 0, ',', '.') ?> inscripciones del mes
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de inscripciones';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<?php include __DIR__ . '/enrollment-modal.php'; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';