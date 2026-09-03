<?php
/**
 * Vista: user/index.php
 * Gestión de Cuentas de Usuario — listado, filtros y accesos rápidos.
 *
 * Espera del controlador (opcional, con valores de ejemplo como fallback):
 * - array $stats     ['total' => int, 'activas' => int, 'inactivas' => int, 'tendencia' => string]
 * - array $usuarios  cada uno: ['id','nombre','iniciales','avatar_color','empleado','rol','estado' => 'activo'|'inactivo']
 * - array $roles     lista de roles para el filtro
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas'
 * @var array $stats
 * @var array $usuarios
 * @var array $roles
 * @var array $paginacion
 */

$pageTitle       = 'Cuentas de Usuario';
$pageDescription = 'Gestione las credenciales de acceso y roles de los empleados del sistema.';
$currentNav      = 'cuentas';

$breadcrumbs = [
    ['label' => 'Usuarios', 'url' => null],
    ['label' => 'Cuentas de usuario', 'url' => null],
];

ob_start();
?>

<div class="page-header">
  <div>
    <h1 class="page-header__title">Gestión de Cuentas de Usuario</h1>
    <p class="page-header__subtitle"><?= htmlspecialchars($pageDescription) ?></p>
  </div>

  <div class="page-header__actions">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearUsuarioModal">
      <i class="bi bi-plus-lg"></i>
      Crear Usuario
    </button>
  </div>
</div>

<!-- ===================== Tarjetas de resumen ===================== -->
<div class="row g-3 mb-4">

  <!-- Tarjeta 1: Total Usuarios -->
  <div class="col-sm-6 col-lg-4">
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--primary">
          <i class="bi bi-people-fill"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--up">
          <?= htmlspecialchars($stats['tendencia']) ?>
        </span>
      </div>
      <div class="stat-card__label">Total Usuarios</div>
      <div class="stat-card__value"><?= (int) $stats['total'] ?></div>
    </div>
  </div>

  <!-- Tarjeta 2: Cuentas Activas -->
  <div class="col-sm-6 col-lg-4">
    <?php $pctActivas = $stats['total'] > 0 ? round(($stats['activas'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--success">
          <i class="bi bi-person-check-fill"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--up">
          <?= $pctActivas ?>% del total
        </span>
      </div>
      <div class="stat-card__label">Cuentas Activas</div>
      <div class="stat-card__value"><?= (int) $stats['activas'] ?></div>
    </div>
  </div>

  <!-- Tarjeta 3: Cuentas Inactivas -->
  <div class="col-sm-6 col-lg-4">
    <?php $pctInactivas = $stats['total'] > 0 ? round(($stats['inactivas'] / $stats['total']) * 100) : 0; ?>
    <div class="stat-card">
      <div class="stat-card__top">
        <div class="stat-card__icon stat-card__icon--danger">
          <i class="bi bi-person-x-fill"></i>
        </div>
        <span class="stat-card__trend stat-card__trend--down">
          <?= $pctInactivas ?>% del total
        </span>
      </div>
      <div class="stat-card__label">Cuentas Inactivas</div>
      <div class="stat-card__value"><?= (int) $stats['inactivas'] ?></div>
    </div>
  </div>

</div>

<!-- ===================== Panel de filtros + tabla ===================== -->
<div class="data-panel">

  <div class="data-panel__toolbar">
    <form method="get" action="<?= BASE_URL ?>userAccount/index" class="data-panel__filters">
      <label for="filtroRol" class="visually-hidden">Rol</label>
      <select id="filtroRol" name="rol" class="form-select">
        <option value="">Rol (Todos)</option>
        <?php foreach ($roles as $rol): ?>
          <option value="<?= htmlspecialchars($rol) ?>" <?= ($filters['rol'] ?? '') === $rol ? 'selected' : '' ?>>
            <?= htmlspecialchars($rol) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="filtroEstado" class="visually-hidden">Estado</label>
      <select id="filtroEstado" name="estado" class="form-select">
        <option value="">Estado (Todos)</option>
        <option value="activo"   <?= ($filters['estado'] ?? '') === 'activo'   ? 'selected' : '' ?>>Activo</option>
        <option value="inactivo" <?= ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
      </select>

      <label for="buscarUsuario" class="visually-hidden">Buscar</label>
      <div class="data-panel__search">
        <i class="bi bi-search"></i>
        <input
          type="search"
          id="buscarUsuario"
          name="q"
          class="form-control"
          placeholder="Buscar por nombre, usuario o rol..."
          value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
        >
      </div>
    </form>
  </div>

  <div class="data-panel__body">
    <table class="data-panel__table">
      <colgroup>
        <col style="width: 30%;">
        <col style="width: 16%;">
        <col style="width: 18%;">
        <col style="width: 14%;">
        <col style="width: 22%;">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Usuario</th>
          <th scope="col">Empleado</th>
          <th scope="col">Rol</th>
          <th scope="col" class="text-center">Estado</th>
          <th scope="col" class="text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u): ?>
          <?php $esInactivo = $u['estado'] === 'inactivo'; ?>
          <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
            <td>
              <div class="avatar-group">
                <span class="avatar avatar--md avatar--<?= htmlspecialchars($u['avatar_color']) ?>">
                  <?= htmlspecialchars($u['iniciales']) ?>
                </span>
                <span class="avatar-group__name"><?= htmlspecialchars($u['nombre']) ?></span>
              </div>
            </td>
            <td class="text-support">
              <?php if (empty($u['empleado']) || trim((string) $u['empleado']) === '' || $u['empleado'] === '—'): ?>
                <span class="text-body-tertiary">—</span>
              <?php else: ?>
                <?= htmlspecialchars($u['empleado']) ?>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(ucwords(mb_strtolower($u['rol'], 'UTF-8'))) ?></td>
            <td class="text-center">
              <span class="status-badge status-badge--<?= $esInactivo ? 'inactive' : 'active' ?>">
                <?= $esInactivo ? 'Inactivo' : 'Activo' ?>
              </span>
            </td>
            <td class="text-center">
              <div class="data-panel__actions">
                <button type="button"
                        class="action-btn action-btn--view btn-ver-usuario"
                        title="Ver detalle"
                        data-id="<?= (int) $u['id'] ?>"
                        data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                        data-usuario="<?= htmlspecialchars($u['nombre_usuario']) ?>"
                        data-iniciales="<?= htmlspecialchars($u['iniciales']) ?>"
                        data-avatar-color="<?= htmlspecialchars($u['avatar_color']) ?>"
                        data-empleado="<?= htmlspecialchars($u['empleado']) ?>"
                        data-rol="<?= htmlspecialchars($u['rol']) ?>"
                        data-estado="<?= htmlspecialchars($u['estado']) ?>">
                  <i class="bi bi-eye"></i>
                </button>
                <button type="button"
                        class="action-btn action-btn--edit btn-editar-usuario"
                        title="Editar"
                        data-id="<?= (int) $u['id'] ?>"
                        data-nombre-usuario="<?= htmlspecialchars($u['nombre_usuario']) ?>"
                        data-empleado="<?= htmlspecialchars($u['empleado']) ?>"
                        data-rol="<?= htmlspecialchars($u['rol']) ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <div class="form-check form-switch table-switch mb-0" title="<?= $esInactivo ? 'Activar' : 'Desactivar' ?> cuenta">
                  <input
                    class="form-check-input toggle-estado-usuario"
                    type="checkbox"
                    role="switch"
                    data-usuario-id="<?= (int) $u['id'] ?>"
                    <?= $esInactivo ? '' : 'checked' ?>
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
      </tbody>
    </table>
  </div>

  <div class="data-panel__footer">
    <span>
      Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
      de <?= (int) $paginacion['total'] ?> empleados
    </span>

    <?php
      $paginacionAriaLabel = 'Paginación de cuentas de usuario';
      require __DIR__ . '/../partials/pagination.php';
    ?>
  </div>

</div>

<!-- ===================== Modales de Crear, Credenciales, Ver y Editar Usuario ===================== -->
<?php
require __DIR__ . '/create-modal.php';
require __DIR__ . '/credentials-modal.php';
require __DIR__ . '/show-modal.php';
require __DIR__ . '/edit-modal.php';
?>

<?php
$pageContent  = ob_get_clean();
$extraScripts = ['/js/accounts.js'];
require __DIR__ . '/../layouts/app.php';