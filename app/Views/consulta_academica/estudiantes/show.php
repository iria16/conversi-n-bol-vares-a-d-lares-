<?php
/**
 * Vista: estudiantes/show.php
 * Expediente (ficha) completo de un estudiante.
 *
 * Espera del controlador:
 * - array $estudiante  datos de EstudianteModel::getById()
 */

$pageTitle       = 'Expediente del Estudiante';
$pageDescription = 'Información completa del expediente académico y personal.';
$currentNav      = 'estudiantes';

$estudiante = $estudiante ?? [];
$nombre     = $estudiante['nombre'] ?? 'Estudiante';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => BASE_URL . 'estudiantes/index'],
    ['label' => 'Expediente', 'href' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Expediente del Estudiante</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <a href="<?= BASE_URL ?>estudiantes/index" class="btn btn-outline-primary">
      <i class="bi bi-arrow-left"></i>
      Volver al listado
    </a>
  </div>
</div>

<div class="row g-4">

  <!-- ==================== Columna principal ==================== -->
  <div class="col-lg-8">

    <div class="content-card mb-4">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-person-lines-fill me-2"></i>
          Datos Personales
        </p>
      </div>
      <div class="content-card__body">
        <div class="user-detail__header">
          <span class="avatar avatar--xl avatar--<?= htmlspecialchars($estudiante['avatar_color'] ?? 'primary') ?>">
            <?php if (!empty($estudiante['avatar_img'])): ?>
              <img src="<?= htmlspecialchars($estudiante['avatar_img']) ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars($estudiante['iniciales'] ?? 'ES') ?>
            <?php endif; ?>
          </span>
          <div>
            <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
            <div class="user-detail__meta">
              <span><?= htmlspecialchars($estudiante['tipo_documento'] ?? '') ?>-<?= htmlspecialchars($estudiante['numero_documento'] ?? '') ?></span>
              <span class="user-detail__meta-sep">•</span>
              <span>
                <span class="status-badge status-badge--<?= ($estudiante['estado'] ?? 'activo') === 'inactivo' ? 'inactive' : 'active' ?>">
                  <?= ucfirst($estudiante['estado'] ?? 'activo') ?>
                </span>
              </span>
            </div>
          </div>
        </div>

        <div class="row g-4 mt-1">
          <div class="col-md-6">
            <div class="detail-section__label"><i class="bi bi-card-heading"></i>Identificación</div>
            <div class="detail-list__item">
              <div class="label-sigde">Documento</div>
              <div class="detail-list__value"><?= htmlspecialchars($estudiante['tipo_documento'] ?? '—') ?>-<?= htmlspecialchars($estudiante['numero_documento'] ?? '—') ?></div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Fecha de nacimiento</div>
              <div class="detail-list__value">
                <?= !empty($estudiante['fecha_nacimiento']) ? date('d/m/Y', strtotime($estudiante['fecha_nacimiento'])) : '—' ?>
              </div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Sexo</div>
              <div class="detail-list__value"><?= ($estudiante['sexo'] ?? 'M') === 'F' ? 'Femenino' : 'Masculino' ?></div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="detail-section__label"><i class="bi bi-telephone"></i>Contacto</div>
            <div class="detail-list__item">
              <div class="label-sigde">Correo electrónico</div>
              <div class="detail-list__value"><?= htmlspecialchars($estudiante['correo_electronico'] ?? '—') ?></div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Teléfono principal</div>
              <div class="detail-list__value"><?= htmlspecialchars($estudiante['telefono'] ?? '—') ?></div>
            </div>
            <div class="detail-list__item">
              <div class="label-sigde">Teléfono alternativo</div>
              <div class="detail-list__value"><?= htmlspecialchars($estudiante['telefono_alternativo'] ?? '—') ?></div>
            </div>
          </div>
        </div>

        <div class="detail-section__label mt-4"><i class="bi bi-geo-alt"></i>Dirección del Domicilio</div>
        <div class="detail-list__item">
          <div class="label-sigde">Sector / Urbanización</div>
          <div class="detail-list__value"><?= htmlspecialchars($estudiante['sector_urbanizacion'] ?? '—') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Calle / Avenida y N°</div>
          <div class="detail-list__value">
            <?= htmlspecialchars(
                trim(
                    ($estudiante['calle_avenida'] ?? '') .
                    (($estudiante['nro_casa_apto'] ?? '') !== '' ? ' ' . $estudiante['nro_casa_apto'] : '')
                ) ?: '—'
            ) ?>
          </div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Punto de referencia</div>
          <div class="detail-list__value"><?= htmlspecialchars($estudiante['punto_referencia'] ?? '—') ?></div>
        </div>
      </div>
    </div>

  </div>

  <!-- ==================== Columna lateral ==================== -->
  <div class="col-lg-4">

    <div class="content-card mb-4">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-mortarboard-fill me-2"></i>
          Posición Académica
        </p>
      </div>
      <div class="content-card__body">
        <div class="detail-list__item">
          <div class="label-sigde">Grado</div>
          <div class="detail-list__value fw-semibold"><?= htmlspecialchars($estudiante['grado'] ?? 'Sin grado') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Sección</div>
          <div class="detail-list__value"><?= htmlspecialchars($estudiante['seccion'] ?? 'Sin sección') ?></div>
        </div>
        <div class="detail-list__item">
          <div class="label-sigde">Año escolar</div>
          <div class="detail-list__value"><?= htmlspecialchars($estudiante['anio_escolar'] ?? 'Sin período asignado') ?></div>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="content-card__header">
        <p class="content-card__title">
          <i class="bi bi-folder2-open me-2"></i>
          Acciones del Expediente
        </p>
      </div>
      <div class="content-card__body d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>inscripciones/index" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-person-plus"></i> Inscripciones
        </a>
        <a href="<?= BASE_URL ?>ratificacion/index" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-clipboard-check"></i> Ratificación
        </a>
        <a href="<?= BASE_URL ?>egresos/index" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-mortarboard"></i> Egresos
        </a>
      </div>
    </div>

  </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
