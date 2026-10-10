<?php
/**
 * Un par etiqueta/valor de los bloques del resumen del wizard y de las vistas de detalle.
 *
 * Sin variables obligatorias: solo imprime el par y borra las suyas.
*/
$wsField = $wsField ?? '';
$wsValue = $wsValue ?? '—';
$wsId    = $wsId ?? '';
$wsLabel = $wsLabel ?? '';
$wsClass = $wsClass ?? '';
$wsRaw   = $wsRaw ?? '';

$wsAtributos = '';
if ($wsId !== '') {
    $wsAtributos .= ' id="' . htmlspecialchars($wsId) . '"';
}
if ($wsField !== '') {
    $wsAtributos .= ' data-resumen="' . htmlspecialchars($wsField) . '"';
}
$wsContenido = $wsRaw !== '' ? $wsRaw : htmlspecialchars((string) $wsValue);
?>
<div class="wizard-summary__item<?php echo $wsClass !== '' ? ' ' . $wsClass : ''; ?>">
  <div class="wizard-summary__label"><?php echo htmlspecialchars($wsLabel); ?></div>
  <div class="wizard-summary__value"<?php echo $wsAtributos; ?>><?php echo $wsContenido; ?></div>
</div>
<?php
unset($wsField, $wsValue, $wsId, $wsLabel, $wsClass, $wsRaw, $wsAtributos, $wsContenido);
?>