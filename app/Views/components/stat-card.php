<?php
/**
 * Componente: stat-card.php
 *
 * - string $statLabel
 * - string $statValue
 * - string $statIcon      clase Bootstrap Icons (ej. bi-people)
 * - string $statTone      primary|success|danger|info|warning
 * - string $statVariant   default|alert|highlighted
 * - string $statTrend     texto del badge (ej. +12%)
 * - string $statTrendType up|down|neutral
 * - string $statMeta      texto inferior
 * - int    $statProgress  0-100 (null = no mostrar barra)
 * - string $statHref      si se define, la tarjeta es un enlace
 */
$statLabel     = $statLabel ?? '';
$statValue     = $statValue ?? '';
$statIcon      = $statIcon ?? 'bi-graph-up';
$statTone      = $statTone ?? 'primary';
$statVariant   = $statVariant ?? '';
$statTrend     = $statTrend ?? '';
$statTrendType = $statTrendType ?? 'neutral';
$statMeta      = $statMeta ?? '';
$statProgress  = $statProgress ?? null;
$statHref      = $statHref ?? '';

$cardClass = 'stat-card';
if ($statVariant === 'alert') {
    $cardClass .= ' stat-card--alert';
} elseif ($statVariant === 'highlighted') {
    $cardClass .= ' stat-card--highlighted';
}

$tag = $statHref !== '' ? 'a' : 'article';
$hrefAttr = $statHref !== '' ? ' href="' . htmlspecialchars($statHref) . '"' : '';
?>
<<?php echo $tag; ?> class="<?php echo $cardClass; ?>"<?php echo $hrefAttr; ?>>
  <div class="stat-card__top">
    <span class="stat-card__icon stat-card__icon--<?php echo htmlspecialchars($statTone); ?>">
      <i class="bi <?php echo htmlspecialchars($statIcon); ?>" aria-hidden="true"></i>
    </span>
    <?php if ($statTrend !== ''): ?>
      <span class="stat-card__trend stat-card__trend--<?php echo htmlspecialchars($statTrendType); ?>">
        <?php echo htmlspecialchars($statTrend); ?>
      </span>
    <?php endif; ?>
  </div>
  <p class="stat-card__label"><?php echo htmlspecialchars($statLabel); ?></p>
  <p class="stat-card__value mb-0"><?php echo htmlspecialchars($statValue); ?></p>
  <?php if ($statProgress !== null): ?>
    <div class="stat-card__bar" role="progressbar" aria-valuenow="<?php echo (int) $statProgress; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo htmlspecialchars($statLabel); ?>">
      <span style="width: <?php echo (int) $statProgress; ?>%"></span>
    </div>
  <?php endif; ?>
  <?php if ($statMeta !== ''): ?>
    <p class="stat-card__meta mb-0"><?php echo htmlspecialchars($statMeta); ?></p>
  <?php endif; ?>
</<?php echo $tag; ?>>
<?php
unset($statLabel, $statValue, $statIcon, $statTone, $statVariant, $statTrend, $statTrendType, $statMeta, $statProgress, $statHref);
?>
