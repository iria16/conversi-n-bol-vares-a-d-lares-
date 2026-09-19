<?php
/**
 * Vista: dashboard/coordinador/index.php
 * Placeholder de diseño hasta el módulo de coordinación.
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/coordinador'],
    ['label' => 'Dashboard'],
];

ob_start();
$pageHeading = 'Panel de coordinación';
$pageSubheading = 'Seguimiento académico por grado. El contenido operativo se conectará más adelante.';
include __DIR__ . '/../../components/page-header.php';
?>
<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-diagram-3" aria-hidden="true"></i>
    <p>Este tablero estará disponible cuando se habilite el módulo de coordinación.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';
