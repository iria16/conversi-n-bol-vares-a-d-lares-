<?php
/**
 * Vista: usuarios/cuentas/index.php
 * Gestión de Cuentas de Usuario — listado, filtros y accesos rápidos.
 *
 * Espera del controlador:
 * - array $stats                 ['total','activas','inactivas','tendencia','porcentaje_activas','porcentaje_inactivas']
 * - array $usuarios              cada uno: ['id','id_rol','nombre','nombre_usuario','iniciales',
 *                                 'avatar_color','avatar_tipo','avatar_foto','empleado','rol','estado']
 *                                 ('rol' ya viene formateado — ej. "Admin" — desde
 *                                 CuentaUsuarioModel::mapRowToVista(), no se reformatea acá)
 * - array $roles                 cada uno: ['id','nombre'] (CuentaUsuarioModel::getRoles())
 * - array $empleadosDisponibles  cada uno: ['id','nombre'] — solo para el modal de crear
 * - array $filters               ['id_rol' => ..., 'estado' => ..., 'q' => ...] (valores actuales del GET)
 * - array $paginacion            ['desde','hasta','total','pagina_actual','total_paginas']
 * - string $csrfToken            token CSRF de la sesión actual, usado por el switch
 *                                 de estado en cuentas.js (toggleStatus no tiene <form>,
 *                                 así que no puede usar components/csrf-field.php)
 * - int|null $usuarioActualId    id del usuario logueado; se usa para deshabilitar
 *                                 visualmente su propio switch de estado (no puede
 *                                 desactivarse a sí mismo, ver CuentaUsuarioController::toggleStatus())
 * @var array $stats
 * @var array $usuarios
 * @var array $roles
 * @var array $empleadosDisponibles
 * @var array $filters
 * @var array $paginacion
 * @var string $csrfToken
 * @var int|null $usuarioActualId
 */
$pageTitle   = 'Cuentas de Usuario';
$currentNav  = 'cuentas';

$breadcrumbs = [
    ['label' => 'Usuarios', 'href' => null],
    ['label' => 'Cuentas de Usuario', 'href' => null],
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear Usuario
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Cuentas de Usuario';
  $pageSubheading    = 'Gestione las credenciales de acceso y roles de los empleados del sistema.';
  include __DIR__ . '/../../components/page-header.php';
  ?>

<!-- ---------- Stat cards ---------- -->
<?php
  // Los porcentajes vienen ya calculados del controlador (ver docblock de $stats),
  // no se recalculan aquí para evitar divergencias entre backend y vista.
  //
  // statTrendType solo se usa para la tarjeta de "tendencia" real (variación en el
  // tiempo). Para "activas"/"inactivas" el dato es una proporción del total, no una
  // tendencia temporal, así que no se pinta una flecha de subida/bajada que
  // implicaría algo que no se está midiendo. Si stat-card.php no soporta un tercer
  // estado, ajustar aquí a 'neutral' (o el nombre que use el componente) en vez de
  // forzar 'up'/'down'.
  $statsConfig = [
      [
          'label' => 'Total usuarios',
          'value' => (int) $stats['total'],
          'icon'  => 'bi-people-fill',
          'tone'  => 'primary',
          'trend' => $stats['tendencia'],
          'trendType' => 'up',
      ],
      [
          'label' => 'Cuentas activas',
          'value' => (int) $stats['activas'],
          'icon'  => 'bi-person-check-fill',
          'tone'  => 'success',
          'trend' => $stats['porcentaje_activas'] . '% del total',
          'trendType' => 'neutral',
      ],
      [
          'label' => 'Cuentas inactivas',
          'value' => (int) $stats['inactivas'],
          'icon'  => 'bi-person-x-fill',
          'tone'  => 'danger',
          'trend' => $stats['porcentaje_inactivas'] . '% del total',
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
  // name="id_rol" aquí es correcto: coincide con lo que lee
  // CuentaUsuarioController::getFiltersFromRequest() por GET.
  $panelFormAction = BASE_URL . 'cuentaUsuario/index';

  ob_start();
  ?>
  <label for="filtroRol" class="visually-hidden">Rol</label>
  <select id="filtroRol" name="id_rol" class="form-select">
    <option value="">Rol (Todos)</option>
    <?php foreach ($roles as $rol): ?>
      <option value="<?= $rol['id'] ?>" <?= ((int) ($filters['id_rol'] ?? 0)) === (int) $rol['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($rol['nombre']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select">
    <option value="">Estado (Todos)</option>
    <option value="activo"   <?= ($filters['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
    <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $filters['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre, usuario o rol...';

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Usuario</th>
    <th scope="col">Empleado</th>
    <th scope="col">Rol</th>
    <th scope="col" class="text-center">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($usuarios as $u):
    $esInactivo   = $u['estado'] === 'inactivo';
    // No se puede desactivar la propia cuenta (ver toggleStatus()
    // en el controlador, que también lo bloquea del lado del servidor).
    $esPropiaCuenta = $usuarioActualId !== null && (int) $u['id'] === (int) $usuarioActualId;
  ?>
    <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
      <td>
<div class="avatar-group">
  <?php
    $avatarSize     = 'md';
    $avatarSrc      = ($u['avatar_tipo'] ?? 'iniciales') === 'foto'
        ? htmlspecialchars(BASE_URL . $u['avatar_foto'])
        : '';
    $avatarAlt      = $u['nombre'];
    $avatarTone     = $u['avatar_color'] ?? 'primary';
    $avatarInitials = $u['iniciales'] ?? '';
    include __DIR__ . '/../../components/avatar.php';
  ?>
  <span class="avatar-group__name"><?= htmlspecialchars($u['nombre']) ?></span>
</div>
      </td>
      <td class="text-support">
        <?= $u['empleado'] !== '' ? htmlspecialchars($u['empleado']) : '<span class="text-body-tertiary">—</span>' ?>
      </td>
      <td><?= htmlspecialchars($u['rol']) ?></td>
      <td class="text-center">
        <?php
          $badgeLabel  = $esInactivo ? 'Inactivo' : 'Activo';
          $badgeStatus = $esInactivo ? 'inactive' : 'active';
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
                  class="action-btn action-btn--view btn-ver-usuario"
                  title="Ver detalle"
                  data-id="<?= (int) $u['id'] ?>">
            <i class="bi bi-eye"></i>
          </button>
          <button type="button"
                  class="action-btn action-btn--edit btn-editar-usuario"
                  title="Editar"
                  data-id="<?= (int) $u['id'] ?>"
                  data-nombre-usuario="<?= htmlspecialchars($u['nombre_usuario']) ?>"
                  data-empleado="<?= htmlspecialchars($u['empleado']) ?>"
                  data-id-rol="<?= (int) $u['id_rol'] ?>">
            <i class="bi bi-pencil"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0"
               title="<?= $esPropiaCuenta ? 'No puedes desactivar tu propia cuenta' : (($esInactivo ? 'Activar' : 'Desactivar') . ' cuenta') ?>">
            <input
              class="form-check-input toggle-estado-usuario"
              type="checkbox"
              role="switch"
              data-usuario-id="<?= (int) $u['id'] ?>"
              <?= $esInactivo ? '' : 'checked' ?>
              <?= $esPropiaCuenta ? 'disabled' : '' ?>
            >
          </div>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if (empty($usuarios)): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron usuarios con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' usuarios';
  $panelPagination = [
      'pagina_actual'  => $paginacion['pagina_actual'],
      'total_paginas'  => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<!-- ===================== Modales ===================== -->
<?php
require_once __DIR__ . '/modals/create-modal.php';
require_once __DIR__ . '/modals/credentials-modal.php';
require_once __DIR__ . '/modals/show-modal.php';
require_once __DIR__ . '/modals/edit-modal.php';
?>

<?php
$pageContent  = ob_get_clean();
$extraScripts = ['/js/cuentas.js'];
include __DIR__ . '/../../layouts/app.php';