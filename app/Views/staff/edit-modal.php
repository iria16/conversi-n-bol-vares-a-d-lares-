<?php
/**
 * Modal: Editar Empleado
 * Se puebla vía AJAX (getByIdAjax) antes de abrir el modal.
 * Envía los cambios vía AJAX a staff/updateAjax.
 * Usa components/modal.php + clases del sistema de diseño.
 *
 * Variables esperadas en scope (inyectadas por index()):
 * - array $cargos, $tiposDocumento, $gradosAcademicos, $titulos, $instituciones
 */
ob_start();
?>
<form id="formEditarEmpleado" novalidate>
  <!-- ID oculto del empleado que se está editando -->
  <input type="hidden" id="editEmpleado__id" name="id" value="">

  <div class="form-section__label">Identificación oficial</div>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="label-sigde" for="editEmpleado__tipoDocumento">Tipo de documento</label>
      <select class="form-select" id="editEmpleado__tipoDocumento" name="tipo_documento" required>
        <option value="V">Venezolano</option>
        <option value="E">Extranjero</option>
        <option value="CE">Cédula extranjera</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="label-sigde" for="editEmpleado__documento">N.º de documento</label>
      <input type="text" class="form-control" id="editEmpleado__documento" name="numero_documento" required>
      <div class="invalid-feedback">El número de documento es obligatorio.</div>
    </div>
    <div class="col-md-4">
      <label class="label-sigde" for="editEmpleado__nacimiento">Fecha de nacimiento</label>
      <input type="date" class="form-control" id="editEmpleado__nacimiento" name="fecha_nacimiento" required>
      <div class="invalid-feedback">La fecha de nacimiento es obligatoria.</div>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__primerNombre">Primer nombre</label>
      <input type="text" class="form-control" id="editEmpleado__primerNombre" name="primer_nombre" required>
      <div class="invalid-feedback">El primer nombre es obligatorio.</div>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__segundoNombre">Segundo nombre</label>
      <input type="text" class="form-control" id="editEmpleado__segundoNombre" name="segundo_nombre">
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__primerApellido">Primer apellido</label>
      <input type="text" class="form-control" id="editEmpleado__primerApellido" name="primer_apellido" required>
      <div class="invalid-feedback">El primer apellido es obligatorio.</div>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__segundoApellido">Segundo apellido</label>
      <input type="text" class="form-control" id="editEmpleado__segundoApellido" name="segundo_apellido">
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
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__nacionalidad">Nacionalidad</label>
      <input type="text" class="form-control" id="editEmpleado__nacionalidad" name="nacionalidad" required>
      <div class="invalid-feedback">La nacionalidad es obligatoria.</div>
    </div>
  </div>

  <div class="form-section__label mt-4">Información laboral</div>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__cargo">Cargo</label>
      <select class="form-select" id="editEmpleado__cargo" name="id_cargo">
        <option value="">Seleccione...</option>
        <?php foreach ($cargos as $option): ?>
          <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__fechaIngreso">Fecha de ingreso</label>
      <input type="date" class="form-control" id="editEmpleado__fechaIngreso" name="fecha_ingreso" required>
      <div class="invalid-feedback">La fecha de ingreso es obligatoria.</div>
    </div>
  </div>

  <div class="form-section__label mt-4">Información de contacto</div>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="label-sigde" for="editEmpleado__codigoPrincipal">Código</label>
      <select class="form-select" id="editEmpleado__codigoPrincipal" name="codigo_telefono_principal">
        <option value="0412">0412</option>
        <option value="0414">0414</option>
        <option value="0416">0416</option>
        <option value="0424">0424</option>
        <option value="0426">0426</option>
      </select>
    </div>
    <div class="col-md-8">
      <label class="label-sigde" for="editEmpleado__telefonoPrincipal">Teléfono principal</label>
      <input type="text" class="form-control" id="editEmpleado__telefonoPrincipal" name="numero_telefono_principal" required>
      <div class="invalid-feedback">El teléfono principal es obligatorio.</div>
    </div>
    <div class="col-12">
      <label class="label-sigde" for="editEmpleado__correo">Correo electrónico</label>
      <input type="email" class="form-control" id="editEmpleado__correo" name="correo_electronico">
    </div>
  </div>

  <div class="form-section__label mt-4">Formación académica (opcional)</div>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__titulo">Título</label>
      <select class="form-select" id="editEmpleado__titulo" name="id_titulo">
        <option value="">Seleccione...</option>
        <?php foreach ($titulos as $option): ?>
          <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="label-sigde" for="editEmpleado__gradoAcademico">Grado académico</label>
      <select class="form-select" id="editEmpleado__gradoAcademico" name="id_grado_academico">
        <option value="">Seleccione...</option>
        <?php foreach ($gradosAcademicos as $option): ?>
          <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-8">
      <label class="label-sigde" for="editEmpleado__institucion">Institución</label>
      <select class="form-select" id="editEmpleado__institucion" name="id_institucion">
        <option value="">Seleccione...</option>
        <?php foreach ($instituciones as $option): ?>
          <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="label-sigde" for="editEmpleado__fechaObtencion">Fecha de obtención</label>
      <input type="date" class="form-control" id="editEmpleado__fechaObtencion" name="fecha_obtencion">
    </div>
  </div>
</form>
<?php
$modalBody    = ob_get_clean();
$modalId      = 'editarEmpleadoModal';
$modalTitle   = 'Editar Empleado';
$modalSubtitle = 'Modifique la información institucional y personal del empleado.';
$modalSize    = 'lg';
$modalStatic  = true;
$modalFooter  = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                 <button type="submit" form="formEditarEmpleado" id="btnGuardarEdicionEmpleado" class="btn btn-primary">
                   <i class="bi bi-floppy me-1"></i> Guardar cambios
                 </button>';
include __DIR__ . '/../components/modal.php';
