<?php
/**
 * Partial: scripts.php
 * Scripts base al final de <body>, reutilizables en cualquier vista.
 *
 * Variables que la vista debe definir ANTES del include:
 * - string $assetsPath    Ruta relativa hacia public/assets desde la vista actual. Requerido.
 * - array  $extraScripts  Opcional. Rutas adicionales (relativas a $assetsPath) que
 *                         una vista específica necesite, ej. ['/js/usuarios.js'].
 */

$extraScripts = $extraScripts ?? [];
?>
<script>
  window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
</script>

<script src="<?php echo $assetsPath; ?>/js/vendor/bootstrap.bundle.min.js"></script>
<script src="<?php echo $assetsPath; ?>/js/vendor/sweetalert2.all.min.js"></script>
<script src="<?php echo $assetsPath; ?>/js/main.js"></script>

<?php foreach ($extraScripts as $script): ?>
  <script src="<?php echo $assetsPath . $script; ?>"></script>
<?php endforeach; ?>