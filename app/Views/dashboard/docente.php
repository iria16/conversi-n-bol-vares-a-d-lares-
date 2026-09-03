<?php
/**
 * Vista: dashboard/docente.php
 * Placeholder de diseño hasta el módulo académico.
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/docente'],
    ['label' => 'Dashboard'],
];

ob_start();
$pageHeading = 'Panel docente';
$pageSubheading = 'Resumen de secciones y actividades. El contenido operativo se conectará más adelante.';
include __DIR__ . '/../components/page-header.php';
?>
<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-journal-text" aria-hidden="true"></i>
    <p>Este tablero estará disponible cuando se habilite el módulo académico.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
