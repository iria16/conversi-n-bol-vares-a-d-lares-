<?php
/**
 * Componente: phone-input.php
 * Teléfono venezolano = <select> de prefijo + <input> del número dentro de un .input-group.
 * Los prefijos son los del ENUM de la tabla `contacto` y viven SOLO aquí:
 * si el ENUM cambia, se cambia en este archivo y se actualizan todas las vistas.
 *
 * - string $phoneLabel        texto de la etiqueta (el " *" se agrega solo si es obligatorio)
 * - string $phoneCodeName     name del <select> de prefijo
 * - string $phoneNumberName   name del <input> del número
 * - string $phoneCodeId       opcional. id del <select>  (default: $phoneCodeName)
 * - string $phoneNumberId     opcional. id del <input>   (default: $phoneNumberName)
 * - bool   $phoneRequired     true = obligatorio. false = el select incluye la opción vacía "--"
 * - string $phoneFeedback     opcional. Mensaje .invalid-feedback bajo el campo
 */
$phoneCodes = ['0412', '0414', '0416', '0422', '0424', '0426', '0212', '0241', '0251', '0261', '0271', '0281', '0291', '0231', '0254', '0255', '0256', '0257', '0258', '0259'];

$phoneLabel      = $phoneLabel ?? '';
$phoneCodeName   = $phoneCodeName ?? '';
$phoneNumberName = $phoneNumberName ?? '';
$phoneCodeId     = $phoneCodeId ?? $phoneCodeName;
$phoneNumberId   = $phoneNumberId ?? $phoneNumberName;
$phoneRequired   = $phoneRequired ?? false;
$phoneFeedback   = $phoneFeedback ?? '';
?>
<label class="label-sigde" for="<?= htmlspecialchars($phoneNumberId) ?>">
  <?= htmlspecialchars($phoneLabel) ?><?= $phoneRequired ? ' *' : '' ?>
</label>
<div class="input-group<?= $phoneFeedback !== '' ? ' has-validation' : '' ?>">
  <select class="form-select flex-grow-0 w-auto" id="<?= htmlspecialchars($phoneCodeId) ?>" name="<?= htmlspecialchars($phoneCodeName) ?>" aria-label="Prefijo telefónico" <?= $phoneRequired ? 'required' : '' ?>>
    <?php if (!$phoneRequired): ?>
      <option value="">--</option>
    <?php endif; ?>
    <?php foreach ($phoneCodes as $code): ?>
      <option value="<?= $code ?>"><?= $code ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" class="form-control" id="<?= htmlspecialchars($phoneNumberId) ?>" name="<?= htmlspecialchars($phoneNumberName) ?>" placeholder="000 0000" maxlength="8" <?= $phoneRequired ? 'required' : '' ?>>
  <?php if ($phoneFeedback !== ''): ?>
    <div class="invalid-feedback"><?= htmlspecialchars($phoneFeedback) ?></div>
  <?php endif; ?>
</div>
<?php
unset($phoneCodes, $phoneLabel, $phoneCodeName, $phoneNumberName, $phoneCodeId, $phoneNumberId, $phoneRequired, $phoneFeedback);
?>