<?php
/**
 * Componente: page-header.php
 *
 * - string $pageHeading
 * - string $pageSubheading     opcional
 * - string $pageHeaderActions  HTML opcional (botones, period-chip, etc.)
 */
$pageHeading       = $pageHeading ?? '';
$pageSubheading    = $pageSubheading ?? '';
$pageHeaderActions = $pageHeaderActions ?? '';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?php echo htmlspecialchars($pageHeading); ?></h1>
    <?php if ($pageSubheading !== ''): ?>
      <p class="page-header__subtitle"><?php echo htmlspecialchars($pageSubheading); ?></p>
    <?php endif; ?>
  </div>
  <?php if ($pageHeaderActions !== ''): ?>
    <div class="page-header__actions">
      <?php echo $pageHeaderActions; ?>
    </div>
  <?php endif; ?>
</div>
<?php
unset($pageHeading, $pageSubheading, $pageHeaderActions);
?>
