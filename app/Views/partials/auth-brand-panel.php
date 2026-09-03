<?php
/**
 * Partial: auth-brand-panel.php
 * Panel izquierdo de marca (fondo azul), usado en login.php y
 * recuperar-acceso.php. Se oculta en pantallas móviles (< lg) para
 * dejar todo el ancho a la tarjeta de formulario.
 *
 * Variables que la vista debe definir ANTES del include:
 * - string $assetsPath   Ruta relativa hacia public/assets desde la vista actual. Requerido.
 * - string $authTagline  Texto bajo el nombre del sistema. Requerido.
 * - array  $authFeatures Opcional. Lista de ['icon' => 'bi-people', 'label' => 'Personal'].
 */
 
$authFeatures = $authFeatures ?? [];
?>
<aside class="col-lg-5 auth-split__brand d-none d-lg-flex">
  <img
    src="<?php echo htmlspecialchars($assetsPath); ?>/img/logo.png"
    alt="Escudo de la Escuela Básica Isidro Ramírez"
    class="auth-split__logo"
    width="160"
    height="160"
  >
  <p class="auth-split__brand-name mb-0">SIGDE</p>
  <p class="auth-split__brand-sub">E.B. Isidro Ramírez</p>
  <p class="auth-split__tagline"><?php echo htmlspecialchars($authTagline); ?></p>
 
  <?php if (!empty($authFeatures)): ?>
    <ul class="list-unstyled d-flex gap-4 mt-5 mb-0">
      <?php foreach ($authFeatures as $feature): ?>
        <li class="auth-split__feature">
          <span class="auth-split__feature-icon" aria-hidden="true">
            <i class="bi <?php echo htmlspecialchars($feature['icon']); ?>"></i>
          </span>
          <?php echo htmlspecialchars($feature['label']); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</aside>