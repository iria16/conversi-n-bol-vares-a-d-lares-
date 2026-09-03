<?php
/**
 * Componente: period-chip.php
 *
 * - string $periodLabel
 */
$periodLabel = $periodLabel ?? 'Año lectivo en curso';
?>
<span class="period-chip">
  <i class="bi bi-calendar3" aria-hidden="true"></i>
  <?php echo htmlspecialchars($periodLabel); ?>
</span>
<?php unset($periodLabel); ?>
