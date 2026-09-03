<?php
$pageTitle = 'Error del sistema';
$pageDescription = 'Ocurrió un problema al procesar la solicitud.';
$assetsPath = BASE_URL . 'assets';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
<main class="container py-5 text-center">
    <h1 class="text-danger">No se pudo cargar <?= htmlspecialchars($seccion ?? 'la página') ?></h1>
    <p class="text-muted">Ocurrió un problema al procesar la solicitud. Intenta nuevamente en unos minutos.</p>
    <a href="<?= BASE_URL ?>" class="btn btn-primary mt-3">Volver al inicio</a>
</main>
</body>
</html>