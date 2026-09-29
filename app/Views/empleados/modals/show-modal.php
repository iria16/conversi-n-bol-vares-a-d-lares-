<?php
/**
 * app/Views/empleados/modals/show-modal.php
 * Modal "Detalle del Empleado". Se puebla vía JS tras un GET a
 * empleados/show?id=...; el contrato con empleado.js son los atributos
 * [data-campo] (y [data-campo-foto], [data-campo-iniciales]).
 * Usa components/modal.php + user-detail + wizard-summary.
 *
 * IMPORTANTE: usa los MISMOS data-campo del archivo original.
 * No requiere cambios en empleado.js ni en el backend.
 */

// Helper local: imprime un par label/valor dentro de un wizard-summary__grid.
$campo = static function (string $etiqueta, string $clave): void {
    ?>
    <div class="wizard-summary__item">
      <span class="wizard-summary__label"><?= htmlspecialchars($etiqueta) ?></span>
      <span class="wizard-summary__value" data-campo="<?= htmlspecialchars($clave) ?>">—</span>
    </div>
    <?php
};

ob_start();
?>
<div class="user-detail__header">
  <span class="avatar avatar--xl avatar--primary">
    <img data-campo-foto alt="" hidden>
    <span data-campo-iniciales>EE</span>
  </span>

  <div class="d-flex align-items-center gap-2 mt-3">
    <span class="user-detail__name mt-0" data-campo="nombre_completo">—</span>
    <span class="status-badge status-badge--active" data-campo="estado">—</span>
  </div>

  <div class="user-detail__meta">
    <span data-campo="cargo">—</span>
    <span class="user-detail__meta-sep">•</span>
    <span data-campo="cedula">—</span>
  </div>
</div>

<div class="row g-2">
  <div class="col-md-6">
    <div class="wizard-summary__section">
      <div class="wizard-summary__section-header">
        <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
        <span class="wizard-summary__section-title">Información personal</span>
      </div>
      <div class="wizard-summary__card">
        <div class="wizard-summary__grid">
          <?php
            $campo('Documento de identidad', 'cedula');
            $campo('Fecha de nacimiento', 'fecha_nacimiento');
            $campo('Sexo', 'sexo');
          ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="wizard-summary__section">
      <div class="wizard-summary__section-header">
        <span class="wizard-summary__icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
        <span class="wizard-summary__section-title">Información laboral</span>
      </div>
      <div class="wizard-summary__card">
        <div class="wizard-summary__grid">
          <?php
            $campo('Cargo', 'cargo');
            $campo('Fecha de ingreso', 'fecha_ingreso');
            $campo('Fecha de egreso', 'fecha_egreso');
          ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información de contacto</span>
  </div>
  <div class="wizard-summary__card">
    <div class="wizard-summary__grid">
      <div class="contact-channel">
        <span class="contact-channel__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
        <div class="contact-channel__body">
          <span class="contact-channel__label">Teléfono principal</span>
          <span class="contact-channel__value" data-campo="telefono">—</span>
        </div>
      </div>
      <div class="contact-channel">
        <span class="contact-channel__icon"><i class="bi bi-phone" aria-hidden="true"></i></span>
        <div class="contact-channel__body">
          <span class="contact-channel__label">Teléfono alternativo</span>
          <span class="contact-channel__value" data-campo="telefono_alternativo">—</span>
        </div>
      </div>
      <div class="contact-channel">
        <span class="contact-channel__icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
        <div class="contact-channel__body">
          <span class="contact-channel__label">Correo electrónico</span>
          <span class="contact-channel__value" data-campo="correo">—</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Dirección de habitación</span>
  </div>
  <div class="wizard-summary__card">
    <div class="wizard-summary__value" data-campo="direccion_completa">—</div>
  </div>
</div>

<div class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Formación académica</span>
  </div>
  <div class="wizard-summary__card">
    <div class="wizard-summary__value" data-campo="formacion">—</div>
  </div>
</div>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'verEmpleadoModal';
$modalTitle    = 'Detalle del Empleado';
$modalSubtitle = 'Vista detallada del perfil institucional';
$modalSize     = 'lg';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';

include __DIR__ . '/../../components/modal.php';