<?php
/**
 * Vista: user/access-recovery.php
 * Gestión de Recuperación de Acceso — listado, filtros y accesos rápidos.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats        ['total' => int, 'pendientes' => int, 'aprobadas' => int, 'rechazadas' => int]
 * - array $solicitudes  cada una: ['id','nombre','iniciales','avatar_color','cargo','usuario',
 *                         'fecha','hora','estado' => 'pendiente'|'aprobada'|'rechazada']
 * - array $paginacion   ['desde','hasta','total','pagina_actual','total_paginas']
 */

$pageTitle       = 'Recuperación de Acceso';
$pageDescription = 'Gestione las solicitudes de restablecimiento de contraseña.';
$currentNav      = 'recuperacion';
$extraScripts[]  = '/js/access-recovery.js';

$breadcrumbs = [
    ['label' => 'Usuarios', 'url' => null],
    ['label' => 'Recuperación de acceso', 'url' => null],
];

// ---------- Datos de respaldo (usar los que envíe el controlador) ----------
$stats = $stats ?? [
    'total'      => 135,
    'pendientes' => 8,
    'aprobadas'  => 124,
    'rechazadas' => 3,
];

$solicitudes = $solicitudes ?? [
    ['id' => 1, 'nombre' => 'Ricardo Morales', 'iniciales' => 'R', 'avatar_color' => 'primary', 'cargo' => 'Director, Administración', 'usuario' => 'rmorales_adm', 'fecha' => '26 Jul, 2025', 'hora' => '10:32 am', 'estado' => 'pendiente'],
    ['id' => 2, 'nombre' => 'Sofía Vargas',    'iniciales' => 'S', 'avatar_color' => 'success', 'cargo' => 'Secretaria',                'usuario' => 'svargas_adm',  'fecha' => '22 Jul, 2025', 'hora' => '9:10 am',  'estado' => 'aprobada'],
    ['id' => 3, 'nombre' => 'Lucía Guzmán',    'iniciales' => 'L', 'avatar_color' => 'primary', 'cargo' => 'Docente',                   'usuario' => 'lguzman_doc',  'fecha' => '20 Jul, 2025', 'hora' => '4:55 pm',  'estado' => 'rechazada'],
];

$paginacion = $paginacion ?? [
    'desde' => 1, 'hasta' => 10, 'total' => 135, 'pagina_actual' => 1, 'total_paginas' => 14,
];

// Metadatos de presentación por estado (clase del badge + etiqueta)
$estadosMeta = [
    'pendiente' => ['label' => 'Pendiente', 'clase' => 'pending'],
    'aprobada'  => ['label' => 'Aprobada',  'clase' => 'approved'],
    'rechazada' => ['label' => 'Rechazada', 'clase' => 'rejected'],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestión de Recuperación de Acceso</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#restablecerClaveModal">
      <i class="bi bi-key-fill"></i>
      Restablecer
    </button>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <!-- Tarjeta 1: Solicitudes Pendientes -->
  <div class="col-sm-6 col-lg-4">
    <?php $pctPendientes = $stats['total'] > 0 ? round(($stats['pendientes'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--warning">
          <i class="bi bi-clock-history"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--neutral">
          <?= $pctPendientes ?>% del total
        </span>
      </div>
      <div class="stat-card__label">Solicitudes Pendientes</div>
      <div class="stat-card__value"><?= (int) $stats['pendientes'] ?></div>
    </div>
  </div>

  <!-- Tarjeta 2: Solicitudes Aprobadas -->
  <div class="col-sm-6 col-lg-4">
    <?php $pctAprobadas = $stats['total'] > 0 ? round(($stats['aprobadas'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-shield-check"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--up">
          <?= $pctAprobadas ?>% del total
        </span>
      </div>
      <div class="stat-card__label">Solicitudes Aprobadas</div>
      <div class="stat-card__value"><?= (int) $stats['aprobadas'] ?></div>
    </div>
  </div>

  <!-- Tarjeta 3: Solicitudes Rechazadas -->
  <div class="col-sm-6 col-lg-4">
    <?php $pctRechazadas = $stats['total'] > 0 ? round(($stats['rechazadas'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--danger">
          <i class="bi bi-slash-circle"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--down">
          <?= $pctRechazadas ?>% del total
        </span>
      </div>
      <div class="stat-card__label">Solicitudes Rechazadas</div>
      <div class="stat-card__value"><?= (int) $stats['rechazadas'] ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros + tabla ===================== -->
<div class="data-panel">

  <div class="data-panel__toolbar">
    <form method="get" action="<?= BASE_URL ?>accessRecovery/index" class="data-panel__filters">
      <label for="filtroEstado" class="visually-hidden">Estado</label>
      <select id="filtroEstado" name="estado" class="form-select">
        <option value="">Estado (Todos)</option>
        <option value="pendiente">Pendiente</option>
        <option value="aprobada">Aprobada</option>
        <option value="rechazada">Rechazada</option>
      </select>

      <label for="buscarSolicitud" class="visually-hidden">Buscar</label>
      <div class="data-panel__search">
        <i class="bi bi-search"></i>
        <input
          type="search"
          id="buscarSolicitud"
          name="q"
          class="form-control"
          placeholder="Buscar por nombre o usuario..."
        >
      </div>
    </form>
  </div>

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 16%;">
        <col style="width: 28%;">
        <col style="width: 20%;">
        <col style="width: 14%;">
        <col style="width: 22%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Fecha / Hora</th>
          <th scope="col">Empleado</th>
          <th scope="col">Usuario</th>
          <th scope="col" class="text-center">Estado</th>
          <th scope="col" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($solicitudes as $s): ?>
          <?php $meta = $estadosMeta[$s['estado']] ?? $estadosMeta['pendiente']; ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($s['fecha']) ?></div>
              <div class="text-support small"><?= htmlspecialchars($s['hora']) ?></div>
            </td>
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($s['avatar_color']) ?>">
                  <?= htmlspecialchars($s['iniciales']) ?>
                </span>
                <div>
                  <div class="avatar-group__name"><?= htmlspecialchars($s['nombre']) ?></div>
                  <div class="text-support small"><?= htmlspecialchars($s['cargo']) ?></div>
                </div>
              </div>
            </td>
            <td class="text-support"><?= htmlspecialchars($s['usuario']) ?></td>
            <td class="text-center">
              <span class="status-badge status-badge--<?= $meta['clase'] ?>">
                <?= htmlspecialchars($meta['label']) ?>
              </span>
            </td>
            <td class="text-center">
              <div class="data-panel__actions">
                <?php if ($s['estado'] === 'pendiente'): ?>
                  <button type="button"
                     class="action-btn action-btn--toggle aprobar-solicitud"
                     data-solicitud-id="<?= (int) $s['id'] ?>"
                    data-usuario-id="<?= (int) $s['usuario_id'] ?>"
                     data-usuario-nombre="<?= htmlspecialchars($s['nombre'] . ' · ' . $s['usuario']) ?>"
                     title="Aprobar solicitud">
                    <i class="bi bi-check-lg"></i>
                  </button>
                  <button type="button"
                     class="action-btn action-btn--danger rechazar-solicitud"
                     data-solicitud-id="<?= (int) $s['id'] ?>"
                     title="Rechazar solicitud">
                    <i class="bi bi-x-lg"></i>
                  </button>
                <?php elseif ($s['estado'] === 'aprobada'): ?>
                  <button type="button" class="action-btn action-btn--view ver-solicitud"
                    data-solicitud-id="<?= (int) $s['id'] ?>" title="Ver detalle">
                    <i class="bi bi-eye"></i>
                  </button>
                <?php else: ?>
                  <button type="button" class="action-btn action-btn--view ver-solicitud"
                    data-solicitud-id="<?= (int) $s['id'] ?>" title="Ver detalle">
                    <i class="bi bi-eye"></i>
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($solicitudes)): ?>
          <tr>
            <td colspan="5" class="text-center text-support py-4">
              No se encontraron solicitudes con los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= (int) $paginacion['total'] ?> resultados
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de recuperación de acceso';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<?php
$usuariosDisponibles = $usuariosDisponibles ?? [];
ob_start();
?>
<form id="formRestablecerClave" novalidate>
  <div class="form-section__label">Información de cuenta</div>

  <div class="mb-4">
    <label for="restablecerClave__usuario" class="label-sigde">Seleccione el usuario</label>
    <div class="input-icon-group">
      <i class="bi bi-person"></i>
      <select class="form-select" id="restablecerClave__usuario" name="usuario_id" required>
        <option value="" selected disabled>Seleccionar empleado</option>
        <?php foreach ($usuariosDisponibles as $usuario): ?>
          <option value="<?= (int) $usuario['id'] ?>">
            <?= htmlspecialchars($usuario['nombre'] . ' · ' . $usuario['usuario']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="invalid-feedback">Seleccione un usuario.</div>
  </div>

  <div class="form-section__label">Seguridad</div>

  <div class="info-alert">
    <span class="info-alert__icon"><i class="bi bi-info-circle-fill"></i></span>
    <div>
      <p class="info-alert__title">Generación automática</p>
      <p class="info-alert__text">
        El sistema generará una clave provisional de forma automática.
        Esta solo se mostrará una vez al confirmar la acción por motivos de seguridad.
      </p>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalId = 'restablecerClaveModal';
$modalTitle = 'Restablecer Contraseña';
$modalSubtitle = 'Genere una nueva clave provisional para el usuario seleccionado.';
$modalSize = 'lg';
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formRestablecerClave" id="btnRestablecerClave" class="btn btn-primary">
                  <i class="bi bi-key-fill me-1"></i>Restablecer clave
                </button>';
include __DIR__ . '/../components/modal.php';

$credencialUsuario = '';
$credencialPassword = '';
include __DIR__ . '/credentials-modal.php';
include __DIR__ . '/recovery-detail-modal.php';
?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';