<?php
/**
 * Paso 2 - Contacto.
 * Los teléfonos usan components/phone-input.php (allí viven los prefijos del ENUM de `contacto`).
 *
 * El correo es opcional: coincide con EmpleadoController::validate(), que
 * solo exige formato válido si viene algo (correo_electronico está en las
 * columnas que aceptan NULL de App\Traits\ContactoDireccionTrait, así que la
 * BD lo admite). Por eso el <input> no lleva `required` ni el label el
 * asterisco de obligatorio.
 */
?>
<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información de contacto</span>
  </div>
  <div class="wizard-summary__card">
    <p class="text-support mb-3">Complete los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.</p>
    <div class="row g-2">
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
        <label class="label-sigde" for="correoElectronico">Correo Electrónico (Opcional)</label>
        <div class="input-icon-group">
          <i class="bi bi-envelope"></i>
          <input
            type="email"
            class="form-control"
            id="correoElectronico"
            name="correo_electronico"
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