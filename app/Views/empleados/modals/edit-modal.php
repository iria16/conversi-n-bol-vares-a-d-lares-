<?php
/**
 * app/Views/empleados/modals/edit-modal.php
 * Modal "Editar Empleado". Se puebla vía AJAX (empleados/show) antes de abrirse y
 * envía los cambios a empleados/update. Usa components/modal.php.
 *
 * Mismo lenguaje visual que el detalle (verEmpleadoModal) y el registro:
 * encabezado de identidad (user-detail__header) + secciones con
 * "icono + título" (wizard-summary__section-header) sobre tarjetas
 * wizard-summary__card.
 *
 * Variables en scope (definidas en index.php):
 * $cargos, $titulos, $gradosAcademicos, $instituciones
*/
ob_start();
?>
<form id="formEditarEmpleado" novalidate>
  <!-- ID oculto del empleado que se está editando -->
  <input type="hidden" id="editEmpleado__id" name="id" value="">

  <!-- Encabezado de identidad: mismo patrón que el modal de detalle
       (user-detail__header). Foto + nombre + documento + acciones.
       Rellenado por empleado.js (#editEncabezadoNombre/#editEncabezadoDocumento). -->
  <div class="user-detail__header">
    <div class="wizard-photo-upload__circle">
      <div class="wizard-photo-upload__preview" id="editFotoPreview">
        <i class="bi bi-person"></i>
      </div>
      <label class="wizard-photo-upload__edit" for="editFotoInput" title="Cambiar foto">
        <i class="bi bi-pencil-fill"></i>
      </label>
      <input type="file" id="editFotoInput" name="foto" accept="image/*" class="d-none">
 </div>
 <div class="user-detail__name mt-0" id="editEncabezadoNombre">—</div>
 <div class="user-detail__meta" id="editEncabezadoDocumento">—</div>
 <button type="button" class="btn btn-link btn-sm p-0 text-danger mt-1" id="btnQuitarFotoEdicion" hidden>
 <i class="bi bi-x-circle me-1"></i>Quitar foto
 </button>
 </div>
 <input type="hidden" name="eliminar_foto" id="editEliminarFoto" value="">

 <section class="wizard-summary__section">
 <div class="wizard-summary__section-header">
 <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
 <span class="wizard-summary__section-title">Identificación oficial</span>
 </div>
 <div class="wizard-summary__card">
 <div class="row g-2">
<?php
$prefixId = 'editEmpleado__';
$prefixName = '';
$showPhoto = false; // We already have a custom photo header in this modal
$minDate = date('Y-m-d', strtotime('-75 years'));
$maxDate = date('Y-m-d', strtotime('-18 years'));
$dateTitle = 'El empleado debe tener entre 18 y 75 años.';
$docMinLength = '7';
$docMaxLength = '20';
$tipoDocOpciones = ['V', 'E'];
/* El empleado declara SOLO la entidad federal de nacimiento. Explícito aunque false ya
   sea el valor por defecto, por la razón del scope compartido que se explica en
   persona-form.php: si esta pantalla viviera junto al paso 1 del estudiante, esa marca
   en true se colaría hasta acá. */
$pideMunicipioParroquiaNacimiento = false;

include __DIR__ . '/../../components/persona-form.php';
?>
 </div>
 </div>
 </section>

 <section class="wizard-summary__section mt-2">
 <div class="wizard-summary__section-header">
 <span class="wizard-summary__icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
 <span class="wizard-summary__section-title">Información laboral</span>
 </div>
 <div class="wizard-summary__card">
 <div class="row g-2">
 <div class="col-md-6">
 <label class="label-sigde" for="editEmpleado__cargo">Cargo</label>
 <select class="form-select" id="editEmpleado__cargo" name="id_cargo" required>
 <option value="">Seleccione...</option>
 <?php foreach ($cargos as $option): ?>
 <option value="<?= (int) $option['id_cargo'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
 <?php endforeach; ?>
 </select>
 <div class="invalid-feedback">El cargo es obligatorio.</div>
 </div>
 <div class="col-md-6">
 <label class="label-sigde" for="editEmpleado__fechaIngreso">Fecha de ingreso</label>
 <input
 type="date"
 class="form-control"
 id="editEmpleado__fechaIngreso"
 name="fecha_ingreso"
 min="1980-01-01"
 max="<?= date('Y-m-d') ?>"
 required>
 <div class="invalid-feedback">La fecha de ingreso es obligatoria.</div>
 </div>
 <div class="col-md-6">
 <label class="label-sigde" for="editEmpleado__turno">Turno de trabajo</label>
 <select class="form-select" id="editEmpleado__turno" name="id_turno">
 <option value="">Sin turno asignado</option>
 <?php foreach (($turnos ?? []) as $turno): ?>
 <option value="<?= (int) $turno['id_turno'] ?>"><?= htmlspecialchars($turno['nombre']) ?></option>
 <?php endforeach; ?>
 </select>
 <div class="form-text">Déjelo vacío para cargos generales que no dependen de un turno.</div>
 </div>
 </div>
 </div>
 </section>

 <section class="wizard-summary__section mt-2">
 <div class="wizard-summary__section-header">
 <span class="wizard-summary__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
 <span class="wizard-summary__section-title">Información de contacto</span>
 </div>
 <div class="wizard-summary__card">
 <div class="row g-2">
<?php
$prefixId = 'editEmpleado__';
$prefixName = '';
$supportText = 'Actualice los datos de comunicación del trabajador para asegurar una vía de contacto efectiva.';
$phoneRequired = true;
include __DIR__ . '/../../components/contacto-form.php';
?>
 </div>
 </div>
 </section>

 <section class="wizard-summary__section mt-2">
 <div class="wizard-summary__section-header">
 <span class="wizard-summary__icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
 <span class="wizard-summary__section-title">Dirección de habitación</span>
 </div>
 <div class="wizard-summary__card">
 <div class="row g-2">
<?php
$prefixId = 'editEmpleado__';
$prefixName = '';
$direccionRequired = true;
include __DIR__ . '/../../components/direccion-form.php';
?>
 </div>
 </div>
 </section>

 <section class="wizard-summary__section mt-2">
 <div class="wizard-summary__section-header">
 <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
 <span class="wizard-summary__section-title">Formación académica (opcional)</span>
 </div>
 <div class="wizard-summary__card">
 <?php /* Qué fila de formación se está editando y si se pidió quitarla. El botón de
 borrar no limpia los <select>: si los vaciara por JS, un guardado que fallara
 más abajo los dejaría en blanco y el siguiente intento borraría la fila de
 verdad. Se marca con un campo aparte, igual que el de la foto.*/ ?>
      <input type="hidden" name="id_formacion_academica" id="editFormacionId" value="">
      <input type="hidden" name="eliminar_formacion" id="editFormacionEliminar" value="">

      <div id="editFormacionActual" class="d-none mb-3">
        <div class="d-flex align-items-center gap-2 border rounded px-3 py-2"
             style="border-color:#cbd5e1 !important;">
          <i class="bi bi-mortarboard text-primary" aria-hidden="true"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold" id="editFormacionTitulo">—</div>
            <div class="text-support small" id="editFormacionDetalle">—</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger" id="editFormacionQuitar">
            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>Quitar
          </button>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__gradoAcademico">Grado académico</label>
          <select class="form-select" id="editEmpleado__gradoAcademico" name="id_grado_academico">
            <option value="">Seleccione...</option>
            <?php foreach ($gradosAcademicos as $option): ?>
              <option value="<?= (int) $option['id_grado_academico'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__titulo">Título</label>
          <select class="form-select" id="editEmpleado__titulo" name="id_titulo">
            <option value="">Seleccione...</option>
            <?php foreach ($titulos as $titulo): ?>
              <option value="<?= (int) $titulo['id_titulo'] ?>" data-id-grado="<?= (int) ($titulo['id_grado_academico'] ?? 0) ?>"><?= htmlspecialchars($titulo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="label-sigde" for="editEmpleado__institucion">Institución</label>
          <select class="form-select" id="editEmpleado__institucion" name="id_institucion">
            <option value="">Seleccione...</option>
            <?php foreach ($instituciones as $option): ?>
              <option value="<?= (int) $option['id_institucion'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="label-sigde" for="editEmpleado__fechaObtencion">Fecha de obtención</label>
          <?php /* Sin "required": la formación es opcional y este modal también sirve para
 cambiar un teléfono o un correo. Ponerlo aquí haría que checkValidity()
 bloquease el guardado entero con la fecha en blanco, que es el estado
 normal de un empleado sin formación. Si se elige título, el servidor
 responde con "Indica la fecha de obtención del título" sobre este campo.*/ ?>
          <input type="date" class="form-control" id="editEmpleado__fechaObtencion"
                 name="fecha_obtencion">
        </div>
      </div>
    </div>
  </section>
</form>
<?php
$modalBody     = ob_get_clean();
$modalId       = 'editarEmpleadoModal';
$modalTitle    = 'Editar Empleado';
$modalSubtitle = 'Modifique la información institucional y personal del empleado.';
$modalSize     = 'lg';
$modalStatic   = true;

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="submit" form="formEditarEmpleado" id="btnGuardarEdicionEmpleado" class="btn btn-primary">
  <i class="bi bi-floppy me-1"></i> Guardar cambios
</button>
<?php
$modalFooter = ob_get_clean();

include __DIR__ . '/../../components/modal.php';