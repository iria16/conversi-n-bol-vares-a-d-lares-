<?php
/**
 * Vista: gestion_estudiante/ratificacion/ver.php
 * Detalle (solo lectura) de una ratificación ya registrada.
 *
 * Sirve para dos cosas con el mismo archivo, igual que inscripción:
 * - abierta en el navegador, es una página completa con encabezado y acciones;
 * - con la cabecera X-Requested-With, devuelve SOLO el cuerpo del modal.
 *
 * El cuerpo del modal es el que usa el botón "ver" del listado, así que la vista
 * tiene dos formas a propósito: escribirla dos veces haría que el modal y la
 * página mostraran datos distintos en cuanto uno se tocara.
 *
 * Espera del controlador:
 * - array $item con: id, nombre, cedula, iniciales, avatar_img, avatar_color,
 * anio_destino, grado_destino, seccion_destino, turno_destino, tipo_destino,
 * id_matricula_destino, fecha_ratificacion, es_ratificacion, estado.
 *
 * Solo el detalle de la inscripción del año en curso. De dónde venía el alumno
 * no se muestra: en el listado ya se ve de qué grado sale, y para lo que sirve
 * este modal —comprobar qué quedó registrado— el pasado no aporta. Por eso el
 * modelo tampoco lo consulta.
 *
 * @var array $item
*/

$item        = $item ?? [];
$componentes = __DIR__ . '/../../components/';

$esModal = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

$anioEnCurso = $item['anio_destino'] ?? '—';
$nombre      = (string) ($item['nombre'] ?? 'Estudiante');

/* Un salto de "de dónde" a "a dónde" es la ratificación; lo que se muestra en el
 destino depende de si la fila es RATIFICACION (continuidad) o INSCRIPCION
 (primer ingreso), porque no es lo mismo ratificar que inscribir.*/
$esContinuidad = !empty($item['es_ratificacion']);

ob_start();
?>

<?php if (!$esModal): ?>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Detalle de Ratificación</h1>
      <p class="page-header__subtitle">
        Resumen de la ratificación registrada para <?= htmlspecialchars($nombre) ?>.
      </p>
    </div>

    <div class="page-header__actions">
      <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left"></i>
        Volver al listado
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($esModal): ?>
  <?php /* Mismo lenguaje que el modal de inscripción y el resto de modales de
 detalle: cabecera user-detail + bloques wizard-summary armados con los
 componentes. El layout de página en dos columnas se reserva para la vista
 completa.*/ ?>
  <div class="user-detail__header">
    <span class="avatar avatar--xl avatar--<?= htmlspecialchars($item['avatar_color'] ?? 'primary') ?>">
      <?php if (!empty($item['avatar_img'])): ?>
        <img src="<?= htmlspecialchars($item['avatar_img']) ?>" alt="">
      <?php else: ?>
        <?= htmlspecialchars($item['iniciales'] ?? 'ES') ?>
      <?php endif; ?>
    </span>
    <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
    <div class="user-detail__meta">
      <span><?= htmlspecialchars($item['cedula'] ?? '—') ?></span>
      <span class="user-detail__meta-sep">&bull;</span>
      <span>
        <?php if ($esContinuidad): ?>
          <span class="status-badge status-badge--active">
            <i class="bi bi-check-circle"></i> Ratificación registrada
          </span>
        <?php else: ?>
          <?php /* Sin fila RATIFICACION no hay ratificación que mostrar. Se dice, en
 vez de marcarlo como ratificado a un alumno que solo se inscribió.*/ ?>
          <span class="status-badge status-badge--info">
            <i class="bi bi-person-plus"></i> Inscripción del año en curso
          </span>
        <?php endif; ?>
      </span>
    </div>
  </div>

  <?php if (!$esContinuidad): ?>
    <div class="info-alert info-alert--warning">
      <span class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
      <div>
        <p class="info-alert__title">Este estudiante no tiene una ratificación registrada</p>
        <p class="info-alert__text">
          Lo que tiene es una inscripción en <?= htmlspecialchars($anioEnCurso) ?>.
          La ratificación es solo para los alumnos que ya estaban en el plantel.
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="wizard-summary__row mb-3">
      <?php
      $wsIcono  = 'mortarboard';
      $wsTitulo = 'Ubicación en ' . $anioEnCurso;
      $wsCampos = [
          ['Grado',       null, $item['grado_destino']   ?? '—'],
          ['Sección',     null, $item['seccion_destino'] ?? '—'],
          ['Turno',       null, $item['turno_destino']   ?? '—'],
          ['Año escolar', null, $anioEnCurso],
      ];
      include $componentes . 'wizard-summary-section.php';
      ?>
    </div>
  <?php endif; ?>

  <?php
  $badgeTipo = $esContinuidad
      ? '<span class="status-badge status-badge--active">Ratificación</span>'
      : '<span class="status-badge status-badge--info">Inscripción</span>';

  $idMatricula = $item['id_matricula_destino'] ?? null;

  $wsIcono  = 'calendar-check';
  $wsTitulo = 'Registro';
  $wsCampos = [
      ['Fecha de la matrícula', null, $item['fecha_ratificacion'] ?? '—'],
      ['Tipo de la fila',       null, null, $badgeTipo],
      ['N.º de matrícula',      null, $idMatricula !== null ? '#' . $idMatricula : '—'],
  ];
  include $componentes . 'wizard-summary-section.php';
  ?>

<?php else: ?>
  <?php /* Vista de página completa: los mismos datos en content-card de dos columnas.*/ ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="content-card mb-4">
        <div class="content-card__header">
          <p class="content-card__title"><i class="bi bi-person-lines-fill me-2"></i> Estudiante ratificado</p>
        </div>
        <div class="content-card__body">
          <div class="user-detail__header">
            <span class="avatar avatar--xl avatar--<?= htmlspecialchars($item['avatar_color'] ?? 'primary') ?>">
              <?php if (!empty($item['avatar_img'])): ?>
                <img src="<?= htmlspecialchars($item['avatar_img']) ?>" alt="">
              <?php else: ?>
                <?= htmlspecialchars($item['iniciales'] ?? 'ES') ?>
              <?php endif; ?>
            </span>
            <div>
              <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
              <div class="user-detail__meta">
                <span><?= htmlspecialchars($item['cedula'] ?? '—') ?></span>
                <span class="user-detail__meta-sep">•</span>
                <span>
                  <?php if ($esContinuidad): ?>
                    <span class="status-badge status-badge--active">
                      <i class="bi bi-check-circle"></i> Ratificación registrada
                    </span>
                  <?php else: ?>
                    <span class="status-badge status-badge--info">
                      <i class="bi bi-person-plus"></i> Inscripción del año en curso
                    </span>
                  <?php endif; ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($esContinuidad): ?>
        <div class="content-card mb-4">
          <div class="content-card__header">
            <p class="content-card__title"><i class="bi bi-mortarboard me-2"></i> Ubicación</p>
          </div>
          <div class="content-card__body">
            <div class="detail-section__label">En <?= htmlspecialchars($anioEnCurso) ?></div>
            <div class="detail-list__item">
              <div class="label-sigde">Grado y sección</div>
              <div class="detail-list__value fw-semibold">
                <?= htmlspecialchars(($item['grado_destino'] ?? '—') . ' · ' . ($item['seccion_destino'] ?? '—')) ?>
              </div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Turno</div>
              <div class="detail-list__value"><?= htmlspecialchars($item['turno_destino'] ?? '—') ?></div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Año escolar</div>
              <div class="detail-list__value"><?= htmlspecialchars($anioEnCurso) ?></div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-4">
      <div class="content-card mb-4">
        <div class="content-card__header">
          <p class="content-card__title"><i class="bi bi-calendar-check me-2"></i> Registro</p>
        </div>
        <div class="content-card__body">
          <div class="detail-list__item">
            <div class="label-sigde">Fecha de la matrícula</div>
            <div class="detail-list__value"><?= htmlspecialchars($item['fecha_ratificacion'] ?? '—') ?></div>
          </div>
          <div class="detail-list__item">
            <div class="label-sigde">N.º de matrícula</div>
            <div class="detail-list__value">
              <?= htmlspecialchars(($item['id_matricula_destino'] ?? null) !== null ? '#' . $item['id_matricula_destino'] : '—') ?>
            </div>
          </div>
        </div>
      </div>

      <div class="content-card">
        <div class="content-card__header">
          <p class="content-card__title"><i class="bi bi-list-check me-2"></i> Acciones</p>
        </div>
        <div class="content-card__body d-flex flex-column gap-2">
          <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al listado
          </a>
          <a href="<?= BASE_URL ?>estudiantes/show/<?= (int) ($item['id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-lines-fill"></i> Ver expediente
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();

if ($esModal) {
    echo $pageContent;
} else {
    require __DIR__ . '/../../layouts/app.php';
}