<?php
/**
 * Vista: usuarios/recuperacion/index.php
 * Gestión de Recuperación de Acceso — listado de solicitudes de
 * restablecimiento de contraseña, con filtro por estado y acciones
 * rápidas (aprobar / rechazar / ver detalle).
 *
 * Espera del controlador:
 * - array  $stats               ['total','pendientes','aprobadas','rechazadas']
 * - array  $solicitudes         cada uno: ['id','usuario_id','fecha','hora','nombre','cargo','usuario','avatar_tipo','avatar_foto','avatar_color','iniciales','estado','origen']
 * - array  $estadosMeta         mapa estado => ['label','clase'] (status-badge.php)
 * - array  $usuariosDisponibles cada uno: ['id','nombre','usuario'] — para el modal de restablecer clave manual
 * - array  $filters             ['estado' => ..., 'q' => ...] (valores actuales del GET)
 * - array  $paginacion          ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * 'origen' distingue si la fila nació de una solicitud real del
 * usuario ('solicitud') o de un restablecimiento directo del admin
 * sin solicitud previa ('admin') — ver RecuperacionAccesoModel::mapRow().
 * Las filas con origen 'admin' muestran una etiqueta discreta bajo
 * el badge de estado para no confundirlas con una solicitud atendida.
 *
 * Flujos que terminan mostrando credentials-modal.php (contraseña provisional):
 * 1. aprobar-solicitud (recuperacion.js)   -> aprueba la solicitud pendiente y genera clave.
 * 2. formRestablecerClave (este archivo)   -> restablece la clave de cualquier usuario, sin solicitud previa.
 *
 * @var array  $stats
 * @var array  $solicitudes
 * @var array  $estadosMeta
 * @var array  $usuariosDisponibles
 * @var array  $filters
 * @var array  $paginacion
 */
$pageTitle  = 'Recuperación de Acceso';
$currentNav = 'recuperacion';

$breadcrumbs = [
    ['label' => 'Usuarios', 'href' => null],
    ['label' => 'Recuperación de acceso', 'href' => null],
];

$usuariosDisponibles = $usuariosDisponibles ?? [];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#restablecerClaveModal">
    <i class="bi bi-key-fill me-1" aria-hidden="true"></i>Restablecer Contraseña
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Recuperación de Acceso';
  $pageSubheading    = 'Gestione las solicitudes de restablecimiento de contraseña.';
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<?php
  // Los porcentajes son proporciones del total, no tendencias en el tiempo,
  // así que las tres tarjetas usan 'neutral' (ver misma discusión en
  // usuarios/cuentas/index.php sobre statTrendType).
  $statsConfig = [
      [
          'label' => 'Solicitudes Pendientes',
          'value' => (int) $stats['pendientes'],
          'icon'  => 'bi-clock-history',
          'tone'  => 'warning',
          'trend' => ($stats['total'] > 0 ? round(($stats['pendientes'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
      [
          'label' => 'Solicitudes Aprobadas',
          'value' => (int) $stats['aprobadas'],
          'icon'  => 'bi-shield-check',
          'tone'  => 'success',
          'trend' => ($stats['total'] > 0 ? round(($stats['aprobadas'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
      [
          'label' => 'Solicitudes Rechazadas',
          'value' => (int) $stats['rechazadas'],
          'icon'  => 'bi-slash-circle',
          'tone'  => 'danger',
          'trend' => ($stats['total'] > 0 ? round(($stats['rechazadas'] / $stats['total']) * 100) : 0) . '% del total',
          'trendType' => 'neutral',
      ],
  ];
?>
<div class="row g-3 mb-3">
  <?php foreach ($statsConfig as $cfg): ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel     = $cfg['label'];
        $statValue     = $cfg['value'];
        $statIcon      = $cfg['icon'];
        $statTone      = $cfg['tone'];
        $statTrend     = $cfg['trend'];
        $statTrendType = $cfg['trendType'];
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  // Único filtro de esta vista: estado. name="estado" coincide con lo que lee
  // RecuperacionAccesoController::getFiltersFromRequest() por GET.
  $panelFormAction = BASE_URL . 'recuperacionAcceso/index';

  ob_start();
  ?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select">
    <option value="">Estado (Todos)</option>
    <option value="pendiente" <?= ($filters['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
    <option value="aprobado"  <?= ($filters['estado'] ?? '') === 'aprobado'  ? 'selected' : '' ?>>Aprobado</option>
    <option value="rechazado" <?= ($filters['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $filters['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre o usuario...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos por columna: Fecha / Empleado / Usuario / Estado / Acciones.
  // Ajusta los porcentajes a ojo si hace falta.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 15%;">
    <col style="width: 32%;">
    <col style="width: 12%;">
    <col style="width: 18%;">
    <col style="width: 23%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Fecha</th>
    <th scope="col">Empleado</th>
    <th scope="col" class="ps-4">Usuario</th>
    <th scope="col" class="text-center">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($solicitudes as $s):
    $meta   = $estadosMeta[$s['estado']] ?? $estadosMeta['pendiente'];
    $origen = $s['origen'] ?? 'solicitud';
  ?>
    <tr>
      <td>
        <div class="fw-semibold"><?= htmlspecialchars($s['fecha']) ?></div>
        <div class="text-support small"><?= htmlspecialchars($s['hora']) ?></div>
      </td>
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <div class="flex-shrink-0">
            <?php
              // Si avatar_tipo es 'foto', se pinta la imagen real del empleado
              // (BASE_URL + ruta relativa ya normalizada por AvatarTrait::resolverAvatar).
              // Si no, cae a iniciales+color por rol.
              $avatarSize     = 'md';
              $avatarSrc      = ($s['avatar_tipo'] ?? 'iniciales') === 'foto'
                  ? htmlspecialchars(BASE_URL . $s['avatar_foto'])
                  : '';
              $avatarAlt      = $s['nombre'];
              $avatarTone     = $s['avatar_color'] ?? 'primary';
              $avatarInitials = $s['iniciales'] ?? '';
              include __DIR__ . '/../../components/avatar.php';
            ?>
          </div>
          <div class="text-truncate">
            <div class="avatar-group__name text-truncate"><?= htmlspecialchars($s['nombre']) ?></div>
          </div>
        </div>
      </td>
      <td class="text-support ps-4"><?= htmlspecialchars($s['usuario']) ?></td>
      <td class="text-center">
        <?php
          $badgeLabel  = $meta['label'];
          $badgeStatus = $meta['clase'];
          include __DIR__ . '/../../components/status-badge.php';
        ?>
        <?php if ($origen === 'admin'): ?>
          <div class="text-support small mt-1">
            <i class="bi bi-person-gear" aria-hidden="true"></i> Restablecido por admin
          </div>
        <?php endif; ?>
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
              <i class="bi bi-check-lg" aria-hidden="true"></i>
            </button>
            <button type="button"
              class="action-btn action-btn--danger rechazar-solicitud"
              data-solicitud-id="<?= (int) $s['id'] ?>"
              title="Rechazar solicitud">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          <?php else: ?>
            <button type="button" class="action-btn action-btn--view ver-solicitud"
              data-bs-toggle="modal"
              data-bs-target="#modalDetalleSolicitud"
              data-solicitud-id="<?= (int) $s['id'] ?>"
              title="Ver detalle">
              <i class="bi bi-eye" aria-hidden="true"></i>
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
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' resultados';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
include __DIR__ . '/modals/reset-password-modal.php';
include __DIR__ . '/modals/credentials-modal.php';
include __DIR__ . '/modals/detalle-modal.php';
?>
<?php

$pageContent  = ob_get_clean();
$extraScripts = ['/js/recuperacion.js'];
include __DIR__ . '/../../layouts/app.php';