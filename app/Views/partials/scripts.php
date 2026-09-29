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

/* Versionado de los estáticos con el mtime del archivo; el comentario de por
   qué está en partials/head.php. */
$versionDe = static function (string $relativo): string {
    /* Desde app/Views/partials hay que subir tres niveles para llegar a la
       raíz del proyecto: partials -> Views -> app -> raíz. */
    $archivo = dirname(__DIR__, 3) . '/public/assets/' . ltrim($relativo, '/');
    $mtime   = is_file($archivo) ? filemtime($archivo) : false;

    return $mtime === false ? '0' : (string) $mtime;
};

$extraScripts = $extraScripts ?? [];
?>
<script>
  window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
</script>

<script src="<?php echo $assetsPath, '/js/vendor/bootstrap.bundle.min.js?v=', $versionDe('/js/vendor/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo $assetsPath, '/js/vendor/sweetalert2.all.min.js?v=', $versionDe('/js/vendor/sweetalert2.all.min.js'); ?>"></script>
<?php /* Módulos compartidos. Deben cargarse ANTES que los scripts de
         módulo, que se enganchan a window.SIGDEUI y window.SIGDEWizardUI:
           - sigde-ui.js : avisos, red, modales, portapapeles, <select>
           - wizard-ui.js: foto, tarjetas de sexo y filtros de escritura
                            de los formularios tipo wizard */ ?>
<script src="<?php echo $assetsPath, '/js/sigde-ui.js?v=', $versionDe('/js/sigde-ui.js'); ?>"></script>
<script src="<?php echo $assetsPath, '/js/wizard-ui.js?v=', $versionDe('/js/wizard-ui.js'); ?>"></script>
<script src="<?php echo $assetsPath, '/js/main.js?v=', $versionDe('/js/main.js'); ?>"></script>

<?php foreach ($extraScripts as $script): ?>
  <script src="<?php echo $assetsPath, $script, '?v=', $versionDe($script); ?>"></script>
<?php endforeach; ?>