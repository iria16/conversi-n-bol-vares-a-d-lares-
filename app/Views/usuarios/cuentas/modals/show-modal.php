<?php
/**
 * Modal: show-modal.php
 * Vista de solo lectura con el detalle de una cuenta de usuario.
 * cuentas.js abre este modal desde .btn-ver-usuario (data-id) y llena
 * todos los campos vía fetch al endpoint de detalle; no recibe
 * variables PHP en el render inicial (todo el contenido nace vacío
 * y se inyecta por JS).
 *
 * IDs que cuentas.js debe rellenar:
 * - #verUsuarioNombre           nombre completo del usuario
 * - #verUsuarioRolBadge         nombre del rol (reutiliza .status-badge, no representa un estado)
 * - #verUsuarioDocumentoTop     documento/cédula del empleado (confirmar que el endpoint lo devuelve)
 * - #verUsuarioEmpleado         nombre del empleado asociado
 * - #verUsuarioCargo            cargo del empleado
 * - #verUsuarioUsuario          nombre_usuario de la cuenta
 * - #verUsuarioUltimoAcceso     fecha/hora del último acceso
 * - #verUsuarioEstado           badge de estado (activo/inactivo)
 * - #verUsuarioMiembroDesde     fecha de creación de la cuenta
 *
 * TODO: agregar el avatar (clases .avatar, avatar--lg, avatar--primary)
 * que se usa en el resto de la app para representar al usuario,
 * consistente con la tabla de index.php.
 */
ob_start();
?>
<div class="text-center mb-4">
  <p class="fw-bold fs-4 mb-1" id="verUsuarioNombre" aria-live="polite"></p>
  <span class="status-badge" id="verUsuarioRolBadge" aria-live="polite"></span>
  <p class="text-support small mb-0 mt-2" id="verUsuarioDocumentoTop"></p>
</div>

<div class="wizard-summary__row mb-3">
  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Información personal</span>
    </div>
    <div class="wizard-summary__card">
      <div class="wizard-summary__item mb-3">
        <div class="wizard-summary__label">Empleado</div>
        <div class="wizard-summary__value" id="verUsuarioEmpleado"></div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Cargo</div>
        <div class="wizard-summary__value" id="verUsuarioCargo"></div>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Información de cuenta</span>
    </div>
    <div class="wizard-summary__card">
      <div class="wizard-summary__item mb-3">
        <div class="wizard-summary__label">Usuario</div>
        <div class="wizard-summary__value" id="verUsuarioUsuario"></div>
      </div>
      <div class="wizard-summary__item">
        <div class="wizard-summary__label">Último acceso</div>
        <div class="wizard-summary__value" id="verUsuarioUltimoAcceso"></div>
      </div>
    </div>
  </section>
</div>

<div class="wizard-summary__card d-flex justify-content-between align-items-center">
  <div>
    <div class="wizard-summary__label mb-1">Estado</div>
    <span class="status-badge" id="verUsuarioEstado" aria-live="polite"></span>
  </div>
  <div class="text-end">
    <div class="wizard-summary__label mb-1">Miembro desde</div>
    <div class="wizard-summary__value" id="verUsuarioMiembroDesde"></div>
  </div>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalVerUsuario';
$modalTitle    = 'Detalle del usuario';
$modalSubtitle = 'Vista detallada del perfil institucional';
$modalSize     = 'lg';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);