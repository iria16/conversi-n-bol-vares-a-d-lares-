<?php
/**
 * Bloque del resumen del wizard: cabecera con icono y título, botón "Editar" que salta al paso
 * dueño de esos datos, y el cuerpo dentro de la tarjeta.
 *
 * Sin variables obligatorias: solo imprime el bloque y borra las suyas.
*/
$wsBody       = $wsBody ?? '';
$wsCampos     = $wsCampos ?? null;
$wsEditarPaso = $wsEditarPaso ?? null;
$wsIcono      = $wsIcono ?? 'dot';
$wsTitulo     = $wsTitulo ?? '';
$wsClass      = $wsClass ?? '';
?>
<section class="wizard-summary__section<?php echo $wsClass !== '' ? ' ' . htmlspecialchars($wsClass) : ''; ?>">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-<?php echo htmlspecialchars($wsIcono); ?>" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title"><?php echo htmlspecialchars($wsTitulo); ?></span>
    <?php if ($wsEditarPaso !== null): ?>
      <button type="button" class="wizard-summary__edit-btn" data-editar-paso="<?php echo (int) $wsEditarPaso; ?>">
        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
      </button>
    <?php endif; ?>
  </div>
  <div class="wizard-summary__card">
    <?php if ($wsCampos !== null): ?>
      <div class="wizard-summary__grid">
        <?php foreach ($wsCampos as $wsCampo): ?>
          <?php
          /* El elemento que lleva data-resumen debe contener SOLO el valor: el JS
 lo sobrescribe con textContent y se comería cualquier markup.*/
          $wsLabel = $wsCampo[0];
          $wsField = $wsCampo[1] ?? '';
          $wsValue = $wsCampo[2] ?? '—';
          $wsRaw   = $wsCampo[3] ?? '';
          /* $wsClass es de la sección (ya se imprimió arriba). Se vacía para que
 el componente de campo no la herede en cada .wizard-summary__item.*/
          $wsClass = '';
          include __DIR__ . '/wizard-summary-field.php';
          ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php echo $wsBody; ?>
  </div>
</section>
<?php
unset($wsBody, $wsCampos, $wsEditarPaso, $wsIcono, $wsTitulo, $wsClass, $wsCampo, $wsLabel, $wsField, $wsValue, $wsRaw);
?>