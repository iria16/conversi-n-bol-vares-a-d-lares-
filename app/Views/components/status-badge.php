<?php
/**
 * Componente: status-badge.php
 *
 * - string $badgeLabel
 * - string $badgeStatus  active|inactive|pending|info
 */
$badgeLabel  = $badgeLabel ?? '';
$badgeStatus = $badgeStatus ?? 'info';
?>
<span class="status-badge status-badge--<?php echo htmlspecialchars($badgeStatus); ?>">
  <?php echo htmlspecialchars($badgeLabel); ?>
</span>
<?php
unset($badgeLabel, $badgeStatus);
?>
