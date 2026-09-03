<?php
/**
 * Componente: avatar.php
 *
 * - string $avatarInitials
 * - string $avatarSize   sm|md|lg  (default md)
 * - string $avatarTone   primary|dark|success  (default primary)
 * - string $avatarSrc    opcional, URL de imagen
 * - string $avatarAlt    opcional
 */
$avatarInitials = $avatarInitials ?? '';
$avatarSize     = $avatarSize ?? 'md';
$avatarTone     = $avatarTone ?? 'primary';
$avatarSrc      = $avatarSrc ?? '';
$avatarAlt      = $avatarAlt ?? '';
?>
<span class="avatar avatar--<?php echo htmlspecialchars($avatarSize); ?> avatar--<?php echo htmlspecialchars($avatarTone); ?>" aria-hidden="<?php echo $avatarSrc === '' ? 'true' : 'false'; ?>">
  <?php if ($avatarSrc !== ''): ?>
    <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="<?php echo htmlspecialchars($avatarAlt); ?>">
  <?php else: ?>
    <?php echo htmlspecialchars($avatarInitials); ?>
  <?php endif; ?>
</span>
<?php
unset($avatarInitials, $avatarSize, $avatarTone, $avatarSrc, $avatarAlt);
?>
