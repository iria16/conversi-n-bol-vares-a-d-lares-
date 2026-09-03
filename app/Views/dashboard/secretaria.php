<?php
/**
 * Vista: dashboard/docente.php
 * Placeholder de diseño hasta el módulo académico.
 */
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/secretaria'],
    ['label' => 'Dashboard'],
];

ob_start();
$pageHeading = 'Panel de Secretaría';
$pageSubheading = 'Visión general de estudiantes, trámites activos y reportes escolares.';
include __DIR__ . '/../components/page-header.php';
?>
<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-journal-text" aria-hidden="true"></i>
    <p>La información operativa se vinculará automáticamente al activar el módulo académico.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
