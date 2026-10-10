<?php
/**
 * Componente reutilizable para el contacto de una persona.
 *
 * @var string $prefixId Prefijo para los IDs de los campos
 * @var string $prefixName Prefijo para los names de los campos
 * @var string $supportText Texto de apoyo opcional.
 * @var bool $phoneRequired Si el teléfono principal es requerido (default: true).
*/

$prefixId = $prefixId ?? '';
$prefixName = $prefixName ?? '';
$supportText = $supportText ?? 'Complete los datos de comunicación.';
$phoneRequired = $phoneRequired ?? true;

$idCorreo = $prefixId ? $prefixId . 'CorreoElectronico' : 'correoElectronico';

$nameCodigoP = $prefixName ? $prefixName . 'codigo' : 'codigo_telefono_principal';
$nameNumeroP = $prefixName ? $prefixName . 'numero' : 'numero_telefono_principal';
$nameCodigoA = $prefixName ? $prefixName . 'codigo_alt' : 'codigo_telefono_alternativo';
$nameNumeroA = $prefixName ? $prefixName . 'numero_alt' : 'numero_telefono_alternativo';
$nameCorreo = $prefixName ? $prefixName . 'correo' : 'correo_electronico';
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información de contacto</span>
  </div>
  <div class="wizard-summary__card">
    <?php if ($supportText): ?>
      <p class="text-support mb-3"><?= htmlspecialchars($supportText) ?></p>
    <?php endif; ?>
    <div class="row g-2">
      <div class="col-md-6">
        <?php
        $phoneLabel      = 'Teléfono Principal' . ($phoneRequired ? ' *' : '');
        $phoneCodeName   = $nameCodigoP;
        $phoneNumberName = $nameNumeroP;
        $phoneRequiredInput = $phoneRequired;
        include __DIR__ . '/phone-input.php';
        ?>
      </div>
      <div class="col-md-6">
        <?php
        $phoneLabel      = 'Teléfono Alternativo (Opcional)';
        $phoneCodeName   = $nameCodigoA;
        $phoneNumberName = $nameNumeroA;
        $phoneRequiredInput = false;
        include __DIR__ . '/phone-input.php';
        ?>
      </div>
      <div class="col-12">
        <label class="label-sigde" for="<?= $idCorreo ?>">Correo Electrónico (Opcional)</label>
        <div class="input-icon-group">
          <i class="bi bi-envelope"></i>
          <input
            type="email"
            class="form-control"
            id="<?= $idCorreo ?>"
            name="<?= $nameCorreo ?>"
            placeholder="maria@gmail.com"
            maxlength="100"
            pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}"
            title="Debe ser un correo con un dominio válido (ej. maria@gmail.com)."
          >
        </div>
      </div>
    </div>
  </div>
</section>
