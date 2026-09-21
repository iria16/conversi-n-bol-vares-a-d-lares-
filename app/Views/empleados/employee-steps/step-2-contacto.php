<?php
/**
 * Paso 2 - Contacto.
 * Los teléfonos usan components/phone-input.php (allí viven los prefijos del ENUM de `contacto`).
 */
?>
<div class="form-section__label">Información de Contacto</div>
<p class="text-support mb-4">Complete los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.</p>

<div class="row g-3">
  <div class="col-md-6">
    <?php
    $phoneLabel      = 'Teléfono Principal';
    $phoneCodeName   = 'codigo_telefono_principal';
    $phoneNumberName = 'numero_telefono_principal';
    $phoneRequired   = true;
    include __DIR__ . '/../../components/phone-input.php';
    ?>
  </div>
  <div class="col-md-6">
    <?php
    $phoneLabel      = 'Teléfono Alternativo (Opcional)';
    $phoneCodeName   = 'codigo_telefono_alternativo';
    $phoneNumberName = 'numero_telefono_alternativo';
    include __DIR__ . '/../../components/phone-input.php';
    ?>
  </div>
  <div class="col-12">
    <label class="label-sigde" for="correoElectronico">Correo Electrónico *</label>
    <div class="input-icon-group">
      <i class="bi bi-envelope"></i>
      <input type="email" class="form-control" id="correoElectronico" name="correo_electronico" placeholder="nombre@ejemplo.com" required>
    </div>
  </div>
</div>