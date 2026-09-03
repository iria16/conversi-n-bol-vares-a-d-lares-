<?php
/**
 * Partial: user/partials/table-footer.php
 * Footer del data-panel con contador y paginación.
 * Usado tanto por la vista completa (index.php) como por searchAjax.
 *
 * Variables esperadas:
 * - array $paginacion
 */
?>
<span>
  Mostrando <?= (int) $paginacion['desde'] ?> a <?= (int) $paginacion['hasta'] ?>
  de <?= (int) $paginacion['total'] ?> empleados
</span>

<?php
  $paginacionAriaLabel = 'Paginación de cuentas de usuario';
  require __DIR__ . '/../../partials/pagination.php';
?>
