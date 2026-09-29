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
 *   $cargos, $titulos, $gradosAcademicos, $instituciones
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
$tipoDocId  = 'editEmpleado__tipoDocumento';
$tipoDocCol = 'col-md-4';
include __DIR__ . '/../../components/tipo-documento-select.php';
?>
        <div class="col-md-4">
          <label class="label-sigde" for="editEmpleado__documento">N.º de documento</label>
          <input
            type="text"
            class="form-control"
            id="editEmpleado__documento"
            name="numero_documento"
            placeholder="<?= htmlspecialchars(\App\Support\Documento::PLACEHOLDER_HTML) ?>"
            inputmode="numeric"
            pattern="<?= htmlspecialchars(\App\Support\Documento::PATRON_HTML) ?>"
            minlength="7"
            maxlength="20"
            title="<?= htmlspecialchars(\App\Support\Documento::TITULO_HTML) ?>"
            required>
          <div class="invalid-feedback">El número de documento es obligatorio.</div>
        </div>
        <div class="col-md-4">
          <label class="label-sigde" for="editEmpleado__nacimiento">Fecha de nacimiento</label>
          <input
            type="date"
            class="form-control"
            id="editEmpleado__nacimiento"
            name="fecha_nacimiento"
            min="<?= date('Y-m-d', strtotime('-75 years')) ?>"
            max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
            title="El empleado debe tener entre 18 y 75 años."
            required>
          <div class="invalid-feedback">La fecha de nacimiento es obligatoria.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__primerNombre">Primer nombre</label>
          <input
            type="text"
            class="form-control"
            id="editEmpleado__primerNombre"
            name="primer_nombre"
            placeholder="Ej: María"
            minlength="2"
            maxlength="50"
            pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
            title="Solo se permiten letras y espacios"
            required>
          <div class="invalid-feedback">El primer nombre es obligatorio.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__segundoNombre">Segundo nombre</label>
          <input
            type="text"
            class="form-control"
            id="editEmpleado__segundoNombre"
            name="segundo_nombre"
            placeholder="Ej: Alejandra"
            minlength="2"
            maxlength="50"
            pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
            title="Solo se permiten letras y espacios">
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__primerApellido">Primer apellido</label>
          <input
            type="text"
            class="form-control"
            id="editEmpleado__primerApellido"
            name="primer_apellido"
            placeholder="Ej: García"
            minlength="2"
            maxlength="50"
            pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
            title="Solo se permiten letras y espacios"
            required>
          <div class="invalid-feedback">El primer apellido es obligatorio.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__segundoApellido">Segundo apellido</label>
          <input
            type="text"
            class="form-control"
            id="editEmpleado__segundoApellido"
            name="segundo_apellido"
            placeholder="Ej: López"
            minlength="2"
            maxlength="50"
            pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
            title="Solo se permiten letras y espacios">
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editEmpleado__sexo">Sexo</label>
          <select class="form-select" id="editEmpleado__sexo" name="sexo" required>
            <option value="">Seleccione...</option>
            <option value="F">Femenino</option>
            <option value="M">Masculino</option>
          </select>
          <div class="invalid-feedback">El sexo es obligatorio.</div>
        </div>
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
        <div class="col-md-6">
          <?php
          $phoneLabel      = 'Teléfono principal';
          $phoneCodeName   = 'codigo_telefono_principal';
          $phoneCodeId     = 'editEmpleado__codigoPrincipal';
          $phoneNumberName = 'numero_telefono_principal';
          $phoneNumberId   = 'editEmpleado__telefonoPrincipal';
          $phoneRequired   = true;
          include __DIR__ . '/../../components/phone-input.php';
          ?>
        </div>
        <div class="col-md-6">
          <?php
          $phoneLabel      = 'Teléfono alternativo (Opcional)';
          $phoneCodeName   = 'codigo_telefono_alternativo';
          $phoneCodeId     = 'editEmpleado__codigoAlternativo';
          $phoneNumberName = 'numero_telefono_alternativo';
          $phoneNumberId   = 'editEmpleado__telefonoAlternativo';
          include __DIR__ . '/../../components/phone-input.php';
          ?>
        </div>
        <div class="col-md-12">
          <label class="label-sigde" for="editEmpleado__correo">Correo electrónico (Opcional)</label>
          <input
            type="email"
            class="form-control"
            id="editEmpleado__correo"
            name="correo_electronico"
            placeholder="maria@gmail.com"
            maxlength="100"
            pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}"
            title="Debe ser un correo con un dominio válido (ej. maria@gmail.com)."
          >
        </div>
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
        <div class="col-md-4">
          <label class="label-sigde" for="editDireccionEstado">Estado</label>
          <select class="form-select" id="editDireccionEstado" name="id_estado_ui" required>
            <option value="">Seleccione el estado</option>
            <?php foreach ($estados as $estado): ?>
              <option value="<?= (int) $estado['id_estado'] ?>"><?= htmlspecialchars($estado['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="label-sigde" for="editDireccionMunicipio">Municipio</label>
          <select class="form-select" id="editDireccionMunicipio" name="id_municipio_ui"
                  data-depende-de="editDireccionEstado" required>
            <option value="">Seleccione el municipio</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="label-sigde" for="editDireccionParroquia">Parroquia *</label>
          <select class="form-select" id="editDireccionParroquia" name="id_parroquia"
                  data-depende-de="editDireccionMunicipio" required>
            <option value="">Seleccione la parroquia</option>
          </select>
          <div class="invalid-feedback">Debes seleccionar la parroquia de residencia.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editSectorUrbanizacion">Sector / Urbanización *</label>
          <input type="text" class="form-control" id="editSectorUrbanizacion" name="sector_urbanizacion" placeholder="Ej: Los Dos Caminos" minlength="3" maxlength="100" required>
          <div class="invalid-feedback">El sector / urbanización es obligatorio.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editCalleAvenida">Calle / Avenida *</label>
          <input type="text" class="form-control" id="editCalleAvenida" name="calle_avenida" placeholder="Ej: Av. Francisco de Miranda" minlength="2" maxlength="100" required>
          <div class="invalid-feedback">La calle / avenida es obligatoria.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editNroCasaApto">N° Casa / Apto *</label>
          <input type="text" class="form-control" id="editNroCasaApto" name="nro_casa_apto" placeholder="Ej: Edif. Sigde, Apto 48" minlength="1" maxlength="20" required>
          <div class="invalid-feedback">El número de casa / apto es obligatorio.</div>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="editPuntoReferencia">Punto de referencia (Opcional)</label>
          <input type="text" class="form-control" id="editPuntoReferencia" name="punto_referencia" maxlength="150" placeholder="Ej. Frente a la cancha deportiva">
        </div>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section mt-2">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Formación académica (opcional)</span>
    </div>
    <div class="wizard-summary__card">
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
          <input type="date" class="form-control" id="editEmpleado__fechaObtencion" name="fecha_obtencion">
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