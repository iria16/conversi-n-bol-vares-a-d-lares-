<!-- Sub-modal: Agregar Familiar -->
<div class="modal fade app-modal" id="modalAgregarFamiliar" tabindex="-1"
     aria-labelledby="modalAgregarFamiliarLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalAgregarFamiliarLabel">
            <i class="bi bi-person-plus"></i> Agregar Familiar
          </h5>
          <p class="modal-header__subtitle">Complete los datos del familiar o representante del estudiante.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">

        <!-- Foto -->
        <div class="wizard-photo-upload mb-4">
          <div class="wizard-photo-upload__circle">
            <div class="wizard-photo-upload__preview" id="fotoFamiliarPreview">
              <i class="bi bi-person"></i>
            </div>
            <label class="wizard-photo-upload__edit" for="fotoFamiliarInput">
              <i class="bi bi-pencil-fill"></i>
            </label>
            <input type="file" id="fotoFamiliarInput" name="familiar_foto" accept="image/*" class="d-none">
          </div>
          <span class="wizard-photo-upload__hint">Foto del familiar (Opcional)</span>
        </div>

        <!-- Identificación -->
        <div class="form-section__label">Identificación Oficial</div>
        <p class="text-support mb-4">Ingrese los datos del documento de identidad del familiar.</p>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label" for="familiarTipoDocumento">Tipo de Documento *</label>
            <select class="form-select" id="familiarTipoDocumento" name="familiar_tipo_documento">
              <option value="V">Cédula de Identidad (V)</option>
              <option value="E">Extranjero (E)</option>
              <option value="P">Pasaporte</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label" for="familiarNumeroDocumento">N° Cédula / Pasaporte *</label>
            <div class="input-icon-group">
              <i class="bi bi-search"></i>
              <input type="text" class="form-control" id="familiarNumeroDocumento"
                     name="familiar_numero_documento" placeholder="00.000.000">
            </div>
          </div>
        </div>

        <!-- Nombres -->
        <div class="form-section__label">Nombres y Apellidos</div>
        <p class="text-support mb-4">Ingrese el nombre completo tal como aparece en el documento.</p>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label" for="familiarPrimerNombre">Primer Nombre *</label>
            <input type="text" class="form-control" id="familiarPrimerNombre"
                   name="familiar_primer_nombre" placeholder="Ej: Elena">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="familiarSegundoNombre">Segundo Nombre (Opcional)</label>
            <input type="text" class="form-control" id="familiarSegundoNombre"
                   name="familiar_segundo_nombre" placeholder="Ej: Sofía">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="familiarPrimerApellido">Primer Apellido *</label>
            <input type="text" class="form-control" id="familiarPrimerApellido"
                   name="familiar_primer_apellido" placeholder="Ej: Pérez">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="familiarSegundoApellido">Segundo Apellido (Opcional)</label>
            <input type="text" class="form-control" id="familiarSegundoApellido"
                   name="familiar_segundo_apellido" placeholder="Ej: Márquez">
          </div>
        </div>

        <!-- Sexo -->
        <div class="form-section__label">Sexo *</div>
        <p class="text-support mb-4">Seleccione el sexo del familiar.</p>

        <div class="selectable-card-group mb-4">
          <label class="selectable-card" data-familiar-sexo-card>
            <input type="radio" name="familiar_sexo" value="M">
            <i class="bi bi-gender-male"></i>
            <span>Masculino</span>
          </label>
          <label class="selectable-card" data-familiar-sexo-card>
            <input type="radio" name="familiar_sexo" value="F">
            <i class="bi bi-gender-female"></i>
            <span>Femenino</span>
          </label>
        </div>

        <!-- Contacto y parentesco -->
        <div class="form-section__label">Contacto y Parentesco</div>
        <p class="text-support mb-4">Datos de comunicación y relación con el estudiante.</p>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label" for="familiarParentesco">Parentesco *</label>
            <select class="form-select" id="familiarParentesco" name="familiar_parentesco">
              <option value="" selected disabled>Seleccione...</option>
              <option value="padre">Padre</option>
              <option value="madre">Madre</option>
              <option value="representante">Representante Legal</option>
              <option value="abuelo">Abuelo/a</option>
              <option value="tio">Tío/a</option>
              <option value="hermano">Hermano/a</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="familiarTelefono">Teléfono</label>
            <div class="input-group">
              <select class="form-select flex-grow-0" style="width:6.5rem;" id="familiarCodigoTelefono" name="familiar_codigo_telefono">
                <option value="0412">0412</option>
                <option value="0414">0414</option>
                <option value="0416">0416</option>
                <option value="0424">0424</option>
                <option value="0426">0426</option>
              </select>
              <input type="text" class="form-control" id="familiarTelefono"
                     name="familiar_telefono" placeholder="000 0000" maxlength="8">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label" for="familiarCorreo">Correo Electrónico (Opcional)</label>
            <div class="input-icon-group">
              <i class="bi bi-envelope"></i>
              <input type="email" class="form-control" id="familiarCorreo"
                     name="familiar_correo" placeholder="correo@ejemplo.com">
            </div>
          </div>
        </div>

        <!-- Toggles de vínculo -->
        <div class="form-section__label">Vínculo con el Estudiante</div>
        <p class="text-support mb-4">Defina el rol de este familiar dentro de la institución.</p>

        <div class="wizard-toggle-card mb-2">
          <div class="wizard-toggle-card__text">
            <strong>Representante Principal</strong>
            <span>Persona legalmente responsable ante la institución.</span>
          </div>
          <label class="wizard-switch">
            <input type="checkbox" id="familiarRepresentantePrincipal" name="familiar_representante_principal">
            <span class="wizard-switch__slider"></span>
          </label>
        </div>

        <div class="wizard-toggle-card">
          <div class="wizard-toggle-card__text">
            <strong>Autorizado para Retiros</strong>
            <span>Puede retirar al estudiante del plantel.</span>
          </div>
          <label class="wizard-switch">
            <input type="checkbox" id="familiarAutorizadoRetiros" name="familiar_autorizado_retiros">
            <span class="wizard-switch__slider"></span>
          </label>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnGuardarFamiliar">
          <i class="bi bi-check-lg me-1"></i> Guardar Familiar
        </button>
      </div>

    </div>
  </div>
</div>
