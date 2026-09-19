<?php
/**
 * Vista: dashboard/admin/index.php
 * Placeholder de diseño hasta el módulo de administrador
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/admin'],
    ['label' => 'Dashboard'],
];

ob_start();
$pageHeading = 'Panel de Administrador';
$pageSubheading = 'Visión general del sistema, módulos activos, usuarios y configuración general.';
include __DIR__ . '/../../components/page-header.php';
?>
<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-diagram-3" aria-hidden="true"></i>
    <p>Una vez activado, este tablero mostrará métricas consolidadas de toda la institución.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';
