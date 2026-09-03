<?php
/**
 * Vista: dashboard/docente.php
 * Placeholder de diseño hasta el módulo académico.
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/directivo'],
    ['label' => 'Dashboard'],
];

ob_start();
$pageHeading = 'Panel de Directivo';
$pageSubheading = 'Visión estratégica de la institución: indicadores clave, rendimiento académico y gestión escolar.';
include __DIR__ . '/../components/page-header.php';
?>
<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-journal-text" aria-hidden="true"></i>
    <p>Este tablero consolidará información de docentes, estudiantes, matrículas y resultados académicos para la toma de decisiones.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
