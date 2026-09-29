<?php
/**
 * Componente: phone-input.php
 * Teléfono venezolano = <select> de prefijo + <input> del número dentro de un .input-group.
 * Los prefijos se leen de App\Support\Telefonos, que replica el ENUM de
 * la tabla `contacto`: la lista no se duplica en cada formulario.
 *
 * - string $phoneLabel        texto de la etiqueta (el " *" se agrega solo si es obligatorio)
 * - string $phoneCodeName     name del <select> de prefijo
 * - string $phoneNumberName   name del <input> del número
 * - string $phoneCodeId       opcional. id del <select>  (default: $phoneCodeName)
 * - string $phoneNumberId     opcional. id del <input>   (default: $phoneNumberName)
 * - string $phoneCodeValue    opcional. valor seleccionado del prefijo
 * - string $phoneNumberValue  opcional. valor ingresado en el número
 * - bool   $phoneRequired     true = obligatorio. false = el select incluye la opción vacía "--"
 * - string $phoneFeedback     opcional. Mensaje .invalid-feedback bajo el campo
 *
 * El número solo acepta dígitos, hasta 7 (mismo formato que valida
 * EmpleadoController::validate() en el servidor: `^[0-9]{7}$`):
 *  - pattern="[0-9]{7}" + inputmode="numeric": hacen que checkValidity()
 *    (usado por el wizard en panelCompleto()) marque el campo como inválido
 *    si trae letras o menos de 7 dígitos, y muestran teclado numérico en
 *    móvil. Por sí solos NO impiden teclear letras, solo invalidan al
 *    validar.
 *  - El listener inline de más abajo sí impide teclear letras: descarta en
 *    tiempo real cualquier carácter que no sea dígito.
 */
// La lista vive en App\Support\Telefonos (el ENUM de la tabla contacto), no
// aquí: el mismo criterio debe pintar las opciones y validar en el servidor.
$phoneCodes = \App\Support\Telefonos::CODIGOS;

$phoneLabel        =$phoneLabel ?? '';
$phoneCodeName     =$phoneCodeName ?? '';
$phoneNumberName   =$phoneNumberName ?? '';
$phoneCodeId       = $phoneCodeId ?? $phoneCodeName;
$phoneNumberId     = $phoneNumberId ?? $phoneNumberName;
$phoneCodeValue    =$phoneCodeValue ?? '';
$phoneNumberValue  =$phoneNumberValue ?? '';
$phoneRequired     =$phoneRequired ?? false;
$phoneFeedback     =$phoneFeedback ?? '';
?>
<label class="label-sigde" for="<?= htmlspecialchars($phoneNumberId) ?>">
  <?= htmlspecialchars($phoneLabel) ?><?=$phoneRequired ? ' *' : '' ?>
</label>
<div class="input-group<?= $phoneFeedback !== '' ? ' has-validation' : '' ?>">
  <select class="form-select flex-grow-0 w-auto<?= $phoneFeedback !== '' ? ' is-invalid' : '' ?>" id="<?= htmlspecialchars($phoneCodeId) ?>" name="<?= htmlspecialchars($phoneCodeName) ?>" aria-label="Prefijo telefónico" <?= $phoneRequired ? 'required' : '' ?>>
    <?php if (!$phoneRequired): ?>
      <option value="" <?= $phoneCodeValue === '' ? 'selected' : '' ?>>--</option>
    <?php endif; ?>
    <?php foreach ($phoneCodes as$code): ?>
      <option value="<?= $code ?>" <?= $phoneCodeValue === $code ? 'selected' : '' ?>><?= $code ?></option>
    <?php endforeach; ?>
  </select>
  <input
    type="text"
    class="form-control<?= $phoneFeedback !== '' ? ' is-invalid' : '' ?>"
    id="<?= htmlspecialchars($phoneNumberId) ?>"
    name="<?= htmlspecialchars($phoneNumberName) ?>"
    placeholder="4169641"
    maxlength="7"
    inputmode="numeric"
    pattern="[0-9]{7}"
    title="Debe contener exactamente 7 dígitos numéricos."
    value="<?= htmlspecialchars($phoneNumberValue) ?>"
    <?= $phoneRequired ? 'required' : '' ?>
  >
  <?php if ($phoneFeedback !== ''): ?>
    <div class="invalid-feedback"><?= htmlspecialchars($phoneFeedback) ?></div>
  <?php endif; ?>
</div>
<?php
// El filtro de "solo dígitos" en tiempo real vive en empleado.js
// (initSoloDigitosTelefono), como listener único delegado sobre
// input[pattern="[0-9]{7}"]. No se repite un <script> por cada inclusión
// de este componente.
?>
<?php
unset($phoneLabel, $phoneCodeName,$phoneNumberName, $phoneCodeId,$phoneNumberId, $phoneCodeValue,$phoneNumberValue, $phoneRequired,$phoneFeedback);
?>