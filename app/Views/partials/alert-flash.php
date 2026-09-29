<?php
/**
 * Partial: alert-flash.php
 * Renderiza mensajes flash de sesión (flash_error / flash_success) como
 * alertas Bootstrap descartables. Se leen y se eliminan en una sola pasada.
 */
$flashError   = $_SESSION['flash_error']   ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

foreach ([['type' => 'success', 'texto' => $flashSuccess], ['type' => 'danger', 'texto' => $flashError]] as $alerta) {
    if ($alerta['texto'] === null) {
        continue;
    }
    ?>
    <div class="alert alert-<?= $alerta['type'] ?> alert-dismissible fade show mb-4" role="alert">
      <?= htmlspecialchars($alerta['texto']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php
}