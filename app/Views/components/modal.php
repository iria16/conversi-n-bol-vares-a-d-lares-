<?php
/**
 * Componente: modal.php
 * Modal genérico reutilizable (Bootstrap 5 + .app-modal de _modal.scss).
 *
 * - string $modalId        id del modal (requerido para el trigger data-bs-target)
 * - string $modalTitle     título (texto plano, se escapa)
 * - string $modalSubtitle  subtítulo opcional (texto plano, se escapa)
 * - string $modalBody      HTML del cuerpo (ya armado, NO se escapa)
 * - string $modalFooter    HTML del footer (botones, ya armado, NO se escapa)
 * - string $modalSize      sm|lg|xl (default '' = tamaño base ~500px)
 * - bool   $modalStatic    true = backdrop estático, no cierra al hacer click fuera
 * - bool   $modalCentered  true = centrado verticalmente (default true)
 */
$modalId       = $modalId ?? '';
$modalTitle    = $modalTitle ?? '';
$modalSubtitle = $modalSubtitle ?? '';
$modalBody     = $modalBody ?? '';
$modalFooter   = $modalFooter ?? '';
$modalSize     = $modalSize ?? '';
$modalStatic   = $modalStatic ?? false;
$modalCentered = $modalCentered ?? true;

$dialogClass = 'modal-dialog';
if (in_array($modalSize, ['sm', 'lg', 'xl'], true)) {
    $dialogClass .= ' modal-' . $modalSize;
}
if ($modalCentered) {
    $dialogClass .= ' modal-dialog-centered';
}

$backdropAttr = $modalStatic ? ' data-bs-backdrop="static" data-bs-keyboard="false"' : '';
?>
<div class="modal fade app-modal" id="<?php echo htmlspecialchars($modalId); ?>" tabindex="-1" aria-labelledby="<?php echo htmlspecialchars($modalId); ?>Label" aria-hidden="true"<?php echo $backdropAttr; ?>>
  <div class="<?php echo $dialogClass; ?>">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="<?php echo htmlspecialchars($modalId); ?>Label"><?php echo htmlspecialchars($modalTitle); ?></h5>
          <?php if ($modalSubtitle !== ''): ?>
            <p class="modal-header__subtitle"><?php echo htmlspecialchars($modalSubtitle); ?></p>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <?php echo $modalBody; ?>
      </div>
      <?php if ($modalFooter !== ''): ?>
        <div class="modal-footer">
          <?php echo $modalFooter; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
unset($modalId, $modalTitle, $modalSubtitle, $modalBody, $modalFooter, $modalSize, $modalStatic, $modalCentered, $dialogClass, $backdropAttr);
?>