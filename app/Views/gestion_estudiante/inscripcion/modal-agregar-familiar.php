<?php
/**
 * app/Views/gestion_estudiante/inscripcion/modal-agregar-familiar.php
 * Sub-modal "Agregar Familiar" del wizard de inscripción.
 *
 * Se arma sobre components/modal.php, igual que los modales anidados del
 * wizard de empleados (empleados/modals/add-title-modal.php). Debe quedar
 * como hermano inmediato del wizard (ver "Modales anidados" en
 * _form-wizard.scss), por eso lo incluye enrollment-modal.php al final.
 *
 * Variables del controlador:
 * $catFamiliares['parentescos']       array [ ['id'=>..,'nombre'=>..] ]
 * $catFamiliares['ocupaciones']       array [ ['id'=>..,'nombre'=>..] ]
 * $catFamiliares['nivelInstruccion']  array [ ['id'=>..,'nombre'=>..] ]
 * $parroquias                         array [ ['id'=>..,'nombre'=>..] ]
 * $estados                            array [ ['id_estado'=>..,'nombre'=>..] ]  select de
 *                                      la cascada de dirección
 *
 * El estudiante es menor de edad: su contacto y su domicilio no se registran
 * en él, sino en el REPRESENTANTE LEGAL, que es obligatorio en toda
 * inscripción. Quién es ese representante lo elige quien registra con el
 * interruptor "Representante legal" (solo uno por estudiante), y el resto de
 * los familiares que se agreguen también pueden tener contacto propio: su
 * teléfono principal es obligatorio y su domicilio opcional.
 */
$parentescos  = $catFamiliares['parentescos'] ?? [];
$niveles      = $catFamiliares['nivelInstruccion'] ?? [];
$ocupaciones  = $catFamiliares['ocupaciones'] ?? [];
$parroquias   = $parroquias ?? [];
$estados      = $estados ?? [];

ob_start();
?>
<form id="formAgregarFamiliar" novalidate>

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
    <div class="text-center">
      <span class="wizard-photo-upload__hint">Foto del familiar (Opcional)</span>
      <div>
        <button type="button" class="btn btn-link btn-sm text-danger p-0" id="btnQuitarFotoFamiliar" hidden>
          <i class="bi bi-x-circle me-1"></i>Quitar foto
        </button>
      </div>
    </div>
  </div>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Identificación oficial</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <?php /* Igual que el formulario de empleados: solo V y E, sin cédula
                 escolar. La cédula escolar es del menor, y se arma con los
                 dígitos de la del representante (App\Support\CedulaEscolar); el
                 representante no necesita una. El select lo pinta el
                 componente compartido por todos los formularios de persona. */ ?>
<?php
        $tipoDocId    = 'familiarTipoDocumento';
        $tipoDocName  = 'familiar_tipo_documento';
        $tipoDocLabel = 'Tipo de documento';
        $tipoDocCol   = 'col-3';
        include __DIR__ . '/../../components/tipo-documento-select.php';
        ?>
        <div class="col-5">
          <label class="label-sigde" for="familiarNumeroDocumento">Nro documento *</label>
          <div class="input-icon-group">
            <i class="bi bi-person-vcard" aria-hidden="true"></i>
            <input
              type="text"
              class="form-control"
              id="familiarNumeroDocumento"
              name="familiar_numero_documento"
              placeholder="<?= htmlspecialchars(\App\Support\Documento::PLACEHOLDER_HTML) ?>"
              inputmode="numeric"
              pattern="<?= htmlspecialchars(\App\Support\Documento::PATRON_HTML) ?>"
              minlength="7"
              maxlength="20"
              title="<?= htmlspecialchars(\App\Support\Documento::TITULO_HTML) ?>"
            >
          </div>
        </div>
        <?php /* Mismo patrón, title y placeholder que el paso 1 de empleados y
                 que el paso 1 del estudiante: el prefijo V/E y los separadores
                 se aceptan porque es lo que hace el servidor
                 (App\Support\Documento), que guarda solo los dígitos. */ ?>
        <div class="col-4">
          <label class="label-sigde" for="familiarFechaNacimiento">Fecha de nacimiento *</label>
          <input
            type="date"
            class="form-control"
            id="familiarFechaNacimiento"
            name="familiar_fecha_nacimiento"
            min="<?= date('Y-m-d', strtotime('-100 years')) ?>"
            max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
            title="El representante legal debe ser mayor de 18 años."
            required
          >
          <?php /* El representante legal es una persona real y mayor de edad,
                   así que su fecha de nacimiento se pide como en los demás
                   formularios de persona (empleado, estudiante) y se guarda en
                   persona.fecha_nacimiento. Antes quedaba una fecha fija de
                   relleno en la base. */ ?>
        </div>
        <?php /* Al escribir una cédula que ya está registrada, el JS busca a la
                 persona (inscripciones/buscarPersona), llena sus datos y
                 BLOQUEA los que la identifican (nombres, sexo y fecha de
                 nacimiento): ya están en el sistema y no se cambian desde acá.
                 El teléfono, el correo y el domicilio quedan editables, que es
                 lo que sí cambia. Este bloque muestra qué encontró y en qué
                 difiere el contacto y el domicilio de lo escrito. */ ?>
        <div class="col-12">
          <div class="info-alert d-none" id="personaEncontrada">
            <div class="info-alert__icon"><i class="bi bi-person-check"></i></div>
            <div class="info-alert__text" id="personaEncontradaTexto"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Nombres y apellidos</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <div class="col-6">
          <label class="label-sigde" for="familiarPrimerNombre">Primer nombre *</label>
          <input type="text" class="form-control" id="familiarPrimerNombre"
                 name="familiar_primer_nombre" placeholder="Ej: Elena">
        </div>
        <div class="col-6">
          <label class="label-sigde" for="familiarSegundoNombre">Segundo nombre (Opcional)</label>
          <input type="text" class="form-control" id="familiarSegundoNombre"
                 name="familiar_segundo_nombre" placeholder="Ej: Sofía">
        </div>
        <div class="col-6">
          <label class="label-sigde" for="familiarPrimerApellido">Primer apellido *</label>
          <input type="text" class="form-control" id="familiarPrimerApellido"
                 name="familiar_primer_apellido" placeholder="Ej: Pérez">
        </div>
        <div class="col-6">
          <label class="label-sigde" for="familiarSegundoApellido">Segundo apellido (Opcional)</label>
          <input type="text" class="form-control" id="familiarSegundoApellido"
                 name="familiar_segundo_apellido" placeholder="Ej: Márquez">
        </div>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-gender-ambiguous" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Sexo *</span>
    </div>
    <div class="wizard-summary__card">
      <div class="selectable-card-group">
        <label class="selectable-card" data-familiar-sexo-card>
          <input type="radio" name="familiar_sexo" value="M">
          <i class="bi bi-gender-male" aria-hidden="true"></i>
          <span>Masculino</span>
        </label>
        <label class="selectable-card" data-familiar-sexo-card>
          <input type="radio" name="familiar_sexo" value="F">
          <i class="bi bi-gender-female" aria-hidden="true"></i>
          <span>Femenino</span>
        </label>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Parentesco</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <div class="col-12">
          <?php /* El parentesco es OPCIONAL (migración 0002): quien no es familiar
                     se registra sin vínculo y solo autorizado para retirar. El
                     asterisco lo pone o lo quita el interruptor "No es familiar"
                     (ver sincronizarNoFamiliar en enrollments.js). */ ?>
          <label class="label-sigde" for="familiarParentesco">Parentesco <span data-requerido-parentesco class="d-none">*</span></label>
          <select class="form-select" id="familiarParentesco" name="familiar_parentesco">
            <option value="" selected disabled>Seleccione...</option>
            <?php if ($parentescos): ?>
              <?php foreach ($parentescos as $parentesco): ?>
                <option value="<?= htmlspecialchars($parentesco['nombre']) ?>"><?= htmlspecialchars($parentesco['nombre']) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <option value="Padre">Padre</option>
              <option value="Madre">Madre</option>
              <option value="Representante Legal">Representante Legal</option>
              <option value="Abuelo/a">Abuelo/a</option>
              <option value="Tío/a">Tío/a</option>
              <option value="Hermano/a">Hermano/a</option>
              <option value="Otro">Otro</option>
            <?php endif; ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <?php /* Ocupación y nivel de instrucción: para el REPRESENTANTE LEGAL y para
         el PADRE o la MADRE.

         No es un dato decorativo. La institución los necesita de quien es
         responsable del menor —es la primera persona a la que se llama cuando
         hay que resolver algo— y son la base de los estudios del menor: a
         partir de ellos se puede saber a qué nivel educativo está orientado el
         hogar.

         Antes no se pedían y el modelo escribía la primera fila activa de cada
         tabla, así que todos los familiares del sistema tenían la misma
         ocupación y el mismo nivel de instrucción sin que nadie lo hubiera
         dicho. Para los demás familiares siguen siendo opcionales: no son
         responsables de nada ante la institución. */ ?>
  <section class="wizard-summary__section" id="bloqueDatosRepresentante">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title" id="tituloDatosRepresentante">Datos del representante</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="label-sigde" for="familiarNivelInstruccion">
            Nivel de instrucción <span data-requerido-datos-representante class="d-none">*</span>
          </label>
          <select class="form-select" id="familiarNivelInstruccion" name="familiar_nivel_instruccion">
            <option value="">Seleccione...</option>
            <?php foreach ($niveles as $nivel): ?>
              <option value="<?= htmlspecialchars($nivel['nombre']) ?>"><?= htmlspecialchars($nivel['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="familiarOcupacion">
            Ocupación <span data-requerido-datos-representante class="d-none">*</span>
          </label>
          <select class="form-select" id="familiarOcupacion" name="familiar_ocupacion">
            <option value="">Seleccione...</option>
            <?php foreach ($ocupaciones as $ocupacion): ?>
              <option value="<?= htmlspecialchars($ocupacion['nombre']) ?>"><?= htmlspecialchars($ocupacion['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </section>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Vínculo con el estudiante</span>
    </div>
    <div class="wizard-summary__card">
      <?php /* Los cuatro interruptores del vínculo, en el orden en que se
               decides: primero QUÉ PAPEL tiene (representante legal), después
               si es o no de la familia, después qué puede hacer (retiros) y por
               último dónde vive. El de "no es familiar" va después del
               representante a propósito, porque las dos cosas son
               incompatibles: al marcar el representante, ese interruptor se
               deshabilita solo, y así se ve por qué.

               Todo eso lo ajusta enrollments.js; el servidor lo revisa igual,
               porque el payload es un JSON que se puede armar a mano. */ ?>
      <div class="wizard-toggle-card">
        <div class="wizard-toggle-card__text">
          <strong>Representante legal</strong>
          <span>Responsable legal ante la institución. Solo uno por estudiante.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarRepresentantePrincipal" name="familiar_representante_principal">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <?php /* Al marcar el representante, este interruptor se deshabilita y se
               desmarca solo: una persona sin parentesco no puede tener la
               representación legal de un menor. Por eso el parentesco es
               obligatorio cuando es el representante. Y al revés, "no es
               familiar" fuerza el permiso de retiros, porque sin vínculo no hay
               otro motivo para estar en la lista. */ ?>
      <div class="wizard-toggle-card">
        <div class="wizard-toggle-card__text">
          <strong>No es familiar</strong>
          <span>Solo está autorizado para retirar: no tiene parentesco con el
                estudiante. Déjalo apagado si es un familiar.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarSoloAutorizado" name="familiar_solo_autorizado">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <div class="wizard-toggle-card">
        <div class="wizard-toggle-card__text">
          <strong>Autorizado para retiros</strong>
          <span>Puede retirar al estudiante del plantel.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarAutorizadoRetiros" name="familiar_autorizado_retiros">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <?php /* Vive con el estudiante. Es un dato de la RELACIÓN, no de la
               persona: la misma tía puede vivir con un sobrino y no con otro,
               así que se guarda en persona_estudiante (migración 0003) y no en
               persona.

               Por qué importa aunque no se pida el domicilio: la dirección que
               se registra es la del representante legal (ver más abajo), así que
               este interruptor es lo que dice si además convive con el menor
               algún otro familiar.

               Nace APAGADO (migración 0004): convivir con el menor es un dato
               que se registra, no algo que se pueda dar por hecho. Para el
               representante legal se marca solo, porque el menor vive con su
               representante. */ ?>
      <div class="wizard-toggle-card">
        <div class="wizard-toggle-card__text">
          <strong>Vive con el estudiante</strong>
          <span>Comparte la casa con el menor. Se desmarca si vive en otro sitio.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarViveConEstudiante" name="familiar_vive_con_estudiante">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <?php /* Contacto del familiar. El teléfono principal se pide siempre, para
                 cualquiera: es con el que se localiza a esa persona. El
                 DOMICILIO va aparte, más abajo, y solo se muestra cuando el
                 familiar es el representante legal, porque el estudiante es
                 menor y no tiene domicilio propio: el único con el que se puede
                 llegar a su casa es el del representante.

                 No hay un texto que lo explique arriba: los asteriscos del
                 domicilio y el hecho de que el bloque aparezca o no ya lo
                 dicen, y los mensajes de reglas que no se pueden cambiar
                 terminan leyéndose menos. */ ?>
        <div class="border-top mt-3 pt-3" id="bloqueContactoRepresentante">
        <div class="row g-2">
          <?php /* Mismos campos y mismo componente (phone-input.php) que el
                   formulario de empleados. */ ?>
          <div class="col-md-6">
            <?php
              $phoneLabel      = 'Teléfono Principal *';
              $phoneCodeName   = 'familiar_codigo_telefono_principal';
              $phoneNumberName = 'familiar_telefono_principal';
              $phoneCodeId     = 'familiarCodigoTelefonoPrincipal';
              $phoneNumberId   = 'familiarTelefonoPrincipal';
              // Obligatorio para cualquier familiar, no solo para el
              // representante: es el teléfono con el que se localiza a la
              // persona, y sin él el registro no sirve para nada.
              $phoneRequired   = true;
              $phoneFeedback   = '';
              include __DIR__ . '/../../components/phone-input.php';
            ?>
          </div>
          <div class="col-md-6">
            <?php
              $phoneLabel      = 'Teléfono Alternativo (Opcional)';
              $phoneCodeName   = 'familiar_codigo_telefono_alternativo';
              $phoneNumberName = 'familiar_telefono_alternativo';
              $phoneCodeId     = 'familiarCodigoTelefonoAlternativo';
              $phoneNumberId   = 'familiarTelefonoAlternativo';
              $phoneRequired   = false;
              include __DIR__ . '/../../components/phone-input.php';
            ?>
          </div>
          <div class="col-12">
            <label class="label-sigde" for="familiarCorreo">Correo Electrónico (Opcional)</label>
            <div class="input-icon-group">
              <i class="bi bi-envelope" aria-hidden="true"></i>
              <input
                type="email"
                class="form-control"
                id="familiarCorreo"
                name="familiar_correo"
                placeholder="maria@gmail.com"
                maxlength="100"
                pattern="[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}"
                title="Debe ser un correo con un dominio válido (ej. maria@gmail.com)."
              >
            </div>
          </div>
          <?php /* La dirección es la del formulario de empleados: la cascada
             estado -> municipio -> parroquia, con los mismos ids, la misma
             mismo endpoint
             (SIGDEWizardUI.initCascadaUbicacion). El municipio y la parroquia
             llegan por AJAX porque son catálogos grandes.

             Solo la parroquia se guarda: `direccion` tiene una única FK
             (id_parroquia) y el estado y el municipio se deducen de ella, igual
             que en el empleado. Por eso los names llevan el sufijo _ui. */ ?>
          <div class="col-12" id="bloqueDireccionFamiliar">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="label-sigde" for="familiarDireccionEstado">Estado <span data-requerido-direccion class="d-none">*</span></label>
                <select class="form-select" id="familiarDireccionEstado" name="id_estado_ui" required>
                  <option value="">Seleccione el estado</option>
                  <?php foreach ($estados as $estado): ?>
                    <option value="<?= (int) $estado['id_estado'] ?>"><?= htmlspecialchars($estado['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="label-sigde" for="familiarDireccionMunicipio">Municipio <span data-requerido-direccion class="d-none">*</span></label>
                <select class="form-select" id="familiarDireccionMunicipio" name="id_municipio_ui"
                        data-depende-de="familiarDireccionEstado" data-requerido-direccion required>
                  <option value="">Seleccione el municipio</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="label-sigde" for="familiarDireccionParroquia">Parroquia <span data-requerido-direccion class="d-none">*</span></label>
                <select class="form-select" id="familiarDireccionParroquia" name="id_parroquia"
                        data-depende-de="familiarDireccionMunicipio" data-requerido-direccion required>
                  <option value="">Seleccione la parroquia</option>
                </select>
              </div>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="label-sigde" for="familiarDireccionSector"><span data-requerido-direccion class="d-none">*</span> Sector / Urbanización</label>
                <input type="text" class="form-control" id="familiarDireccionSector"
                       name="familiar_direccion_sector" placeholder="Ej: Centro, El Campito...">
              </div>
              <div class="col-md-6">
                <label class="label-sigde" for="familiarDireccionCalle"><span data-requerido-direccion class="d-none">*</span> Calle / Avenida</label>
                <input type="text" class="form-control" id="familiarDireccionCalle"
                       name="familiar_direccion_calle" placeholder="Ej: Av. Libertad">
              </div>
              <div class="col-md-4">
                <label class="label-sigde" for="familiarDireccionCasa"><span data-requerido-direccion class="d-none">*</span> N° Casa / Apto</label>
                <input type="text" class="form-control" id="familiarDireccionCasa"
                       name="familiar_direccion_casa" placeholder="Ej: 15-B">
              </div>
              <div class="col-md-4">
                <label class="label-sigde" for="familiarDireccionReferencia">Punto de Referencia</label>
                <input type="text" class="form-control" id="familiarDireccionReferencia"
                       name="familiar_direccion_referencia" placeholder="Ej: Enfrente de la farmacia">
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>
  </section>

</form>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
<button type="button" class="btn btn-primary" id="btnGuardarFamiliar">
  <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Guardar familiar
</button>
<?php
$modalFooter = ob_get_clean();

$modalId       = 'modalAgregarFamiliar';
$modalTitle    = 'Agregar familiar';
$modalSubtitle = 'Complete los datos del familiar o representante del estudiante.';
$modalSize     = 'lg';
include __DIR__ . '/../../components/modal.php';
