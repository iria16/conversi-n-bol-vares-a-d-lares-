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

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <p class="form-section__label">Información personal</p>

    <div class="mb-3">
      <span class="label-sigde">Empleado</span>
      <p class="mb-0" id="verUsuarioEmpleado"></p>
    </div>
    <div>
      <span class="label-sigde">Cargo</span>
      <p class="mb-0" id="verUsuarioCargo"></p>
    </div>
  </div>

  <div class="col-md-6">
    <p class="form-section__label">Información de cuenta</p>

    <div class="mb-3">
      <span class="label-sigde">Usuario</span>
      <p class="mb-0" id="verUsuarioUsuario"></p>
    </div>
    <div>
      <span class="label-sigde">Último acceso</span>
      <p class="mb-0" id="verUsuarioUltimoAcceso"></p>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-3">
  <div>
    <span class="label-sigde d-block mb-1">Estado</span>
    <span class="status-badge" id="verUsuarioEstado" aria-live="polite"></span>
  </div>
  <div class="text-end">
    <span class="label-sigde d-block mb-1">Miembro desde</span>
    <span class="fw-semibold small" id="verUsuarioMiembroDesde"></span>
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
$modalSubtitle = 'Vista detallada del perfil institucional.';
include __DIR__ . '/../../../components/modal.php';

unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle);