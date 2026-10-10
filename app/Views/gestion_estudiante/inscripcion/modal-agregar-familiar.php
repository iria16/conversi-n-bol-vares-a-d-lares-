<?php
/**
 * Modal: Agregar o editar familiar.
 * @var array $estados
 * @var array $catFamiliares {parentescos, niveles_instruccion, ocupaciones}
*/

ob_start();
?>
<form id="formAgregarFamiliar" novalidate>
  <input type="hidden" id="familiarIndiceEdicion" value="">
  <input type="hidden" id="familiarIdRelacion" value="">
  <input type="hidden" id="familiarEstadoRelacion" value="">

  <div class="alert alert-info py-2" id="familiarInfoBloqueo" hidden>
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    <span class="small">Solo el contacto es editable (la persona existe en el sistema).</span>
  </div>

  <?php
  // ---- 1. DATOS PERSONALES ----
  $prefixId = 'familiar';
  $prefixName = 'familiar_';
  $showPhoto = true;
  $btnQuitarFotoId = 'btnQuitarFotoFamiliar';
  $minDate = '';
  $maxDate = date('Y-m-d');
  $dateTitle = 'Indique la fecha de nacimiento.';
  $docMinLength = '7';
  $docMaxLength = '20';
  // Un familiar solo tiene cédula V o E: la cédula escolar (CE) es únicamente del estudiante.
  $tipoDocOpciones = ['V', 'E'];
  $tipoDocSeleccionado = 'V';

  /* El aviso "Ya está registrada" lo escribe familiares.js al buscar la cédula. Va justo
 debajo de la sección "Identificación oficial", donde se escribe la cédula, y no al
 final de los datos personales: con el formulario largo, quien escribe la cédula
 arriba no veía el aviso, que quedaba varias pantallas más abajo. Los ids no cambian,
 así que el JS sigue funcionando igual.*/
  ob_start();
  ?>
  <div class="alert alert-info py-2 mt-2 d-none" id="personaEncontrada" role="status" aria-live="polite">
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    <span id="personaEncontradaTexto"></span>
  </div>
  <?php
  $slotTrasIdentificacion = ob_get_clean();

  /* El lugar de nacimiento se imprime dentro de los datos de la persona, junto a la
     fecha, porque es una columna de `persona` y sale en "Datos personales" del
     expediente. Lo que lo revela está más abajo —el parentesco y el interruptor de
     representante—, y por eso familiares.js lleva la vista hasta el campo cuando
     aparece, en vez de dejar el campo viajando por el formulario. */
  $idSeccionNacimiento = 'bloqueLugarNacimientoFamiliar';
  /* El sub-modal declara SOLO la entidad federal de nacimiento, nunca municipio y
  parroquia. Y aquí hay que decirlo aunque el valor por defecto ya sea false: los
  `include` de PHP comparten el scope, y en esta misma pantalla el paso 1 del
  estudiante se incluye ANTES y pone la marca en true. Sin esta línea el familiar se
  terminaba印发 los tres niveles, que es lo que hacía. */
  $pideMunicipioParroquiaNacimiento = false;

  include __DIR__ . '/../../components/persona-form.php';
  ?>

  <section class="wizard-summary__section mt-3 border-top pt-3">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Relación con el estudiante</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <label class="label-sigde" for="familiarParentesco">Parentesco *</label>
          <select class="form-select" id="familiarParentesco" name="familiar_parentesco" required>
            <option value="">Seleccione el parentesco</option>
            <?php foreach ($catFamiliares['parentescos'] ?? [] as $par): ?>
              <option value="<?= htmlspecialchars($par) ?>"><?= htmlspecialchars($par) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6" id="bloqueRepresentanteLegal">
          <div class="wizard-toggle-card wizard-toggle-card--compact">
            <div class="wizard-toggle-card__text">
              <strong>Representante Legal</strong>
              <span>Es el responsable ante la institución.</span>
            </div>
            <label class="wizard-switch">
              <input type="checkbox" id="familiarRepresentantePrincipal" name="familiar_es_representante_legal">
              <span class="wizard-switch__slider"></span>
            </label>
          </div>
        </div>
      </div>

      <div class="row g-2" id="bloqueDatosRepresentante">
        <div class="col-md-6">
          <label class="label-sigde" for="familiarNivelInstruccion">Nivel de instrucción <span data-requerido-instruccion class="d-none">*</span></label>
          <select class="form-select" id="familiarNivelInstruccion" name="familiar_nivel_instruccion" data-requerido-instruccion>
            <option value="">Seleccione el nivel</option>
            <?php foreach ($catFamiliares['niveles_instruccion'] ?? [] as $niv): ?>
              <option value="<?= htmlspecialchars($niv) ?>"><?= htmlspecialchars($niv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="label-sigde" for="familiarOcupacion">Ocupación <span data-requerido-instruccion class="d-none">*</span></label>
          <select class="form-select" id="familiarOcupacion" name="familiar_ocupacion" data-requerido-instruccion>
            <option value="">Seleccione la ocupación</option>
            <?php foreach ($catFamiliares['ocupaciones'] ?? [] as $ocu): ?>
              <option value="<?= htmlspecialchars($ocu) ?>"><?= htmlspecialchars($ocu) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="wizard-toggle-card wizard-toggle-card--compact mt-2">
        <div class="wizard-toggle-card__text">
          <strong>Autorizado para retirar</strong>
          <span>Puede retirar al estudiante de la institución.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarAutorizadoRetiros" name="familiar_autorizado_retiros">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <div class="wizard-toggle-card wizard-toggle-card--compact mt-2">
        <div class="wizard-toggle-card__text">
          <strong>Vive con el estudiante</strong>
          <span>Comparte la casa con el menor. Se desmarca si vive en otro sitio.</span>
        </div>
        <label class="wizard-switch">
          <input type="checkbox" id="familiarViveConEstudiante" name="familiar_vive_con_estudiante">
          <span class="wizard-switch__slider"></span>
        </label>
      </div>

      <div id="bloqueContactoRepresentante" class="mt-3">
        <?php
        // ---- 2. CONTACTO ----
        $prefixId = 'familiar';
        $prefixName = 'familiar_';
        $supportText = '';
        $phoneRequired = true;
        include __DIR__ . '/../../components/contacto-form.php';
        ?>

        <div id="bloqueDireccionFamiliar" class="mt-2">
          <?php
          // ---- 3. DIRECCIÓN ----
          $prefixId = 'familiar';
          $prefixName = 'familiar_';
          $direccionRequired = true;
          include __DIR__ . '/../../components/direccion-form.php';
          ?>
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