<?php
// Prefijos telefónicos venezolanos definidos como ENUM en la tabla `contacto`.
$codigosTelefono = ['0412', '0414', '0416', '0422', '0424', '0426', '0212', '0241', '0251', '0261', '0271', '0281', '0291', '0231', '0254', '0255', '0256', '0257', '0258', '0259'];
?>
<div class="form-section__label">Información de Contacto</div>
<p class="text-support mb-4">Complete los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.</p>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Teléfono Principal *</label>
    <div class="input-group">
      <select class="form-select flex-grow-0" style="width: 6.5rem;" name="codigo_telefono_principal" required>
        <?php foreach ($codigosTelefono as $codigo): ?>
          <option value="<?= $codigo ?>"><?= $codigo ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" class="form-control" name="numero_telefono_principal" placeholder="000 0000" maxlength="8" required>
    </div>
  </div>
  <div class="col-md-6">
    <label class="form-label">Teléfono Alternativo (Opcional)</label>
    <div class="input-group">
      <select class="form-select flex-grow-0" style="width: 6.5rem;" name="codigo_telefono_alternativo">
        <option value="">--</option>
        <?php foreach ($codigosTelefono as $codigo): ?>
          <option value="<?= $codigo ?>"><?= $codigo ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" class="form-control" name="numero_telefono_alternativo" placeholder="000 0000" maxlength="8">
    </div>
  </div>
  <div class="col-12">
    <label class="form-label" for="correoElectronico">Correo Electrónico *</label>
    <div class="input-icon-group">
      <i class="bi bi-envelope"></i>
      <input type="email" class="form-control" id="correoElectronico" name="correo_electronico" placeholder="nombre@ejemplo.com" required>
    </div>
  </div>
</div>