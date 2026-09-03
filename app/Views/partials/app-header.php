<?php
/**
 * Partial: app-header.php
 * Barra superior: menú móvil, breadcrumb, notificaciones y perfil.
 *
 * Variables esperadas:
 * - array  $breadcrumbs     Lista de ['label' => ..., 'href' => ...] (último = activo)
 * - string $nombreUsuario
 * - string $rolSesion
 * - bool   $tieneNotificaciones  Opcional. Muestra el punto rojo.
 */
$breadcrumbs          = $breadcrumbs ?? [];
$nombreUsuario        = $nombreUsuario ?? 'Usuario';
$rolSesion            = $rolSesion ?? '';
$tieneNotificaciones  = $tieneNotificaciones ?? in_array($rolSesion, ['admin', 'secretaria', 'directivo']);
$iniciales            = sigde_iniciales($nombreUsuario);
$rolEtiqueta          = sigde_rol_etiqueta($rolSesion);
?>
<header class="app-header">
  <div class="app-header__left">
    <button
      type="button"
      class="app-header__toggle"
      id="sidebarToggle"
      aria-controls="appSidebar"
      aria-expanded="false"
      aria-label="Abrir menú de navegación"
    >
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>

    <?php if (!empty($breadcrumbs)): ?>
      <nav aria-label="Miga de pan">
        <ol class="breadcrumb app-header__breadcrumb mb-0">
          <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <?php $esUltimo = ($i === array_key_last($breadcrumbs)); ?>
            <li class="breadcrumb-item<?php echo $esUltimo ? ' active' : ''; ?>">
              <?php if (!$esUltimo && !empty($crumb['href'])): ?>
                <a href="<?php echo htmlspecialchars($crumb['href']); ?>">
                  <?php echo htmlspecialchars($crumb['label']); ?>
                </a>
              <?php else: ?>
                <?php echo htmlspecialchars($crumb['label']); ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
  </div>

  <div class="app-header__right">
    <?php if ($tieneNotificaciones): ?>
    <div class="dropdown">
      <button
        type="button"
        class="app-header__notify"
        id="notifyDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Notificaciones"
      >
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($tieneNotificaciones): ?>
          <span class="app-header__notify-dot" aria-hidden="true"></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end notify-dropdown" aria-labelledby="notifyDropdown">
        <div class="notify-dropdown__head">
          <span class="notify-dropdown__title">Notificaciones</span>
          <span class="notify-dropdown__badge">3</span>
        </div>

        <div class="notify-dropdown__list">
          <a class="notify-dropdown__item" href="#">
            <span class="notify-dropdown__icon notify-dropdown__icon--warning">
              <i class="bi bi-key" aria-hidden="true"></i>
            </span>
            <div class="notify-dropdown__body">
              <p class="notify-dropdown__text"><strong>3 recuperaciones</strong> de acceso pendientes de aprobación.</p>
              <time class="notify-dropdown__time">Hace 12 min</time>
            </div>
          </a>

          <a class="notify-dropdown__item" href="#">
            <span class="notify-dropdown__icon notify-dropdown__icon--danger">
              <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            </span>
            <div class="notify-dropdown__body">
              <p class="notify-dropdown__text"><strong>Solicitud de retiro</strong> requiere revisión del directivo.</p>
              <time class="notify-dropdown__time">Hace 1 h</time>
            </div>
          </a>

          <a class="notify-dropdown__item" href="#">
            <span class="notify-dropdown__icon notify-dropdown__icon--info">
              <i class="bi bi-person-plus" aria-hidden="true"></i>
            </span>
            <div class="notify-dropdown__body">
              <p class="notify-dropdown__text">Inscripción registrada en <strong>4to grado</strong>.</p>
              <time class="notify-dropdown__time">Hace 2 h</time>
            </div>
          </a>
        </div>

        <a class="notify-dropdown__footer" href="#">
          Ver todas las notificaciones
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <div class="dropdown">
      <button
        type="button"
        class="app-header__profile"
        id="profileDropdown"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Menú de perfil"
      >
        <div class="app-header__profile-info">
          <div class="app-header__profile-name"><?php echo htmlspecialchars($nombreUsuario); ?></div>
          <div class="app-header__profile-role"><?php echo htmlspecialchars($rolEtiqueta); ?></div>
        </div>
        <?php
          $avatarSize = 'sm';
          $avatarTone = 'dark';
          $avatarInitials = $iniciales;
          include __DIR__ . '/../components/avatar.php';
        ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
        <li>
          <span class="dropdown-item-text small text-secondary d-md-none">
            <?php echo htmlspecialchars($nombreUsuario); ?><br>
            <?php echo htmlspecialchars($rolEtiqueta); ?>
          </span>
        </li>
        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2" aria-hidden="true"></i>Mi perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout">
            <i class="bi bi-power me-2" aria-hidden="true"></i>Cerrar sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</header>
