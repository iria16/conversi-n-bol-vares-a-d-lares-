<?php
/**
 * Vista: students/record.php
 * Ficha Estudiantil — expediente individual del estudiante.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $estudiante  ['id','nombre_completo','nombres','apellidos','foto' (opcional),
 *                       'iniciales','avatar_color','cedula_nacimiento','fecha_nacimiento',
 *                       'edad','nacionalidad','genero','grado','seccion','turno',
 *                       'estado' => 'activo'|'inactivo']
 * - array $historialAcademico  cada uno: ['periodo','grado_seccion','turno','estado_final',
 *                       'es_actual' => bool]
 *
 * @var array $estudiante
 * @var array $historialAcademico
 */

$pageTitle  = 'Ficha Estudiantil';
$currentNav = 'estudiantes';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'url' => null],
    ['label' => 'Estudiantes', 'url' => BASE_URL . 'estudiantes/index'],
    ['label' => $estudiante['nombre_completo'], 'url' => null],
];

$esInactivo = $estudiante['estado'] === 'inactivo';

ob_start();
?>

<!-- ===================== Encabezado del expediente ===================== -->
<div class="record-header mb-4">
  <div class="record-header__profile">
    <span class="record-header__photo">
      <?php if (!empty($estudiante['foto'])): ?>
        <img src="<?= htmlspecialchars($estudiante['foto']) ?>" alt="">
      <?php else: ?>
        <span class="avatar avatar--lg avatar--<?= htmlspecialchars($estudiante['avatar_color']) ?>">
          <?= htmlspecialchars($estudiante['iniciales']) ?>
        </span>
      <?php endif; ?>
    </span>

    <div>
      <div class="record-header__name"><?= htmlspecialchars($estudiante['nombre_completo']) ?></div>
      <div class="record-header__meta">
        <span><i class="bi bi-person-vcard"></i> <?= htmlspecialchars($estudiante['cedula_nacimiento']) ?></span>
        <span><i class="bi bi-cake2"></i> <?= (int) $estudiante['edad'] ?> años</span>
        <span><i class="bi bi-bookmark"></i> <?= htmlspecialchars($estudiante['grado']) ?> "<?= htmlspecialchars($estudiante['seccion']) ?>"</span>
        <span><i class="bi bi-clock"></i> Turno <?= htmlspecialchars($estudiante['turno']) ?></span>
      </div>
      <span class="status-badge status-badge--<?= $esInactivo ? 'inactive' : 'active' ?> mt-2">
        <?= $esInactivo ? 'Inactivo' : 'Activo' ?>
      </span>
    </div>
  </div>

  <div class="record-header__actions">
    <a href="<?= BASE_URL ?>estudiantes/exportarFicha/<?= (int) $estudiante['id'] ?>" class="btn btn-light">
      <i class="bi bi-file-earmark-arrow-down"></i>
      Exportar Ficha
    </a>
  </div>
</div>

<!-- ===================== Tabs ===================== -->
<ul class="nav record-tabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-datos-personales" type="button" role="tab">
      <i class="bi bi-person"></i> Datos Personales
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-info-adicional" type="button" role="tab">
      <i class="bi bi-file-text"></i> Información Adicional
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-familiares" type="button" role="tab">
      <i class="bi bi-people"></i> Familiares
    </button>
  </li>
</ul>

<div class="tab-content">

  <!-- ---------- Tab: Datos Personales ---------- -->
  <div class="tab-pane fade show active" id="tab-datos-personales" role="tabpanel">
    <div class="row g-3">

      <div class="col-lg-7">
        <div class="content-card mb-3">
          <div class="content-card__header">
            <p class="content-card__title">Información Básica</p>
            <a href="<?= BASE_URL ?>estudiantes/editar/<?= (int) $estudiante['id'] ?>" class="content-card__link">
              <i class="bi bi-pencil"></i> Editar
            </a>
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <span class="label-sigde d-block">Nombres Completos</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['nombres']) ?></p>
            </div>
            <div class="col-sm-6">
              <span class="label-sigde d-block">Apellidos Completos</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['apellidos']) ?></p>
            </div>
            <div class="col-sm-6">
              <span class="label-sigde d-block">Cédula de Nacimiento</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['cedula_nacimiento']) ?></p>
            </div>
            <div class="col-sm-6">
              <span class="label-sigde d-block">Fecha de Nacimiento</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['fecha_nacimiento']) ?></p>
            </div>
            <div class="col-sm-6">
              <span class="label-sigde d-block">Nacionalidad</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['nacionalidad']) ?></p>
            </div>
            <div class="col-sm-6">
              <span class="label-sigde d-block">Género</span>
              <p class="mb-0"><?= htmlspecialchars($estudiante['genero']) ?></p>
            </div>
          </div>
        </div>

        <div class="info-alert">
          <div class="info-alert__icon">
            <i class="bi bi-geo-alt"></i>
          </div>
          <div>
            <p class="info-alert__title">Dirección y teléfono</p>
            <p class="info-alert__text">
              Información de contacto principal.
              <a href="<?= BASE_URL ?>familiares/show/<?= (int) $estudiante['id'] ?>" class="link-sigde">
                Ver/Editar en Familiar
              </a>
            </p>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="content-card">
          <div class="content-card__header">
            <p class="content-card__title">Historial Académico</p>
          </div>

          <div class="timeline">
            <?php foreach ($historialAcademico as $h): ?>
              <div class="timeline__item <?= !empty($h['es_actual']) ? 'timeline__item--current' : '' ?>">
                <div class="timeline__period"><?= htmlspecialchars($h['periodo']) ?> · <?= htmlspecialchars($h['grado_seccion']) ?></div>
                <div class="timeline__meta">Turno <?= htmlspecialchars($h['turno']) ?></div>
                <span class="status-badge status-badge--active mt-2"><?= htmlspecialchars($h['estado_final']) ?></span>
              </div>
            <?php endforeach; ?>

            <?php if (empty($historialAcademico)): ?>
              <p class="text-support mb-0">Sin historial académico registrado.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ---------- Tab: Información Adicional ---------- -->
  <div class="tab-pane fade" id="tab-info-adicional" role="tabpanel">
    <div class="content-card">
      <div class="content-card__empty">
        <i class="bi bi-file-earmark-text"></i>
        <p>Aún no hay información adicional registrada para este estudiante.</p>
      </div>
    </div>
  </div>

  <!-- ---------- Tab: Familiares ---------- -->
  <div class="tab-pane fade" id="tab-familiares" role="tabpanel">
    <div class="content-card">
      <div class="content-card__empty">
        <i class="bi bi-people"></i>
        <p>Aún no hay familiares asociados a este estudiante.</p>
      </div>
    </div>
  </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';