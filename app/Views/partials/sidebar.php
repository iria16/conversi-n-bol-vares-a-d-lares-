<?php
/**
 * Partial: sidebar.php
 * Navegación lateral del portal. El menú se filtra por rol de sesión.
 *
 * Variables esperadas (layout/app.php):
 * - string $assetsPath
 * - string $currentNav   id del ítem activo (ej. "dashboard")
 * - string $nombreUsuario
 * - string $rolSesion
 */
$currentNav = $currentNav ?? 'dashboard';
$rolSesion  = sigde_rol_clave($rolSesion ?? ($_SESSION['rol'] ?? ''));
$secciones  = sigde_nav_sections($rolSesion);
?>
<aside class="app-shell__sidebar sidebar" id="appSidebar" aria-label="Navegación principal">
  <div class="sidebar__brand">
    <img
      src="<?php echo htmlspecialchars($assetsPath); ?>/img/logo.png"
      alt="Escudo de la E.B. Isidro Ramírez"
      class="sidebar__logo"
      width="48"
      height="48"
    >
    <div class="sidebar__brand-text">
      <p class="sidebar__brand-name mb-0">E.B. “Isidro Ramírez”</p>
      <p class="sidebar__brand-sub mb-0">Portal administrativo</p>
    </div>
  </div>

  <nav class="sidebar__nav" aria-label="Menú del sistema">
    <?php foreach ($secciones as $seccion): ?>
      <div class="sidebar__section">
        <p class="sidebar__section-label"><?php echo htmlspecialchars($seccion['label']); ?></p>
        <?php foreach ($seccion['items'] as $item): ?>
          <?php
            $activo = ($item['id'] === $currentNav);
            $clases = 'sidebar__link' . ($activo ? ' is-active' : '');
          ?>
          <a
            href="<?php echo htmlspecialchars($item['href']); ?>"
            class="<?php echo $clases; ?>"
            <?php echo $activo ? 'aria-current="page"' : ''; ?>
          >
            <i class="bi <?php echo htmlspecialchars($item['icon']); ?>" aria-hidden="true"></i>
            <span><?php echo htmlspecialchars($item['label']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__footer">
    <a href="<?php echo BASE_URL; ?>auth/logout" class="sidebar__logout">
      <i class="bi bi-power" aria-hidden="true"></i>
      <span>Cerrar sesión</span>
    </a>
  </div>
</aside>
