<?php
/**
 * Paso 1 - Datos personales del estudiante.
 * Sin variables externas. El id/name de cada campo lo lee enrollments.js
 * (validación del paso y resumen).
 */
?>

<div class="wizard-photo-upload mb-3">
  <div class="wizard-photo-upload__circle">
    <div class="wizard-photo-upload__preview" id="fotoPreview">
      <i class="bi bi-person"></i>
    </div>
    <label class="wizard-photo-upload__edit" for="fotoInput">
      <i class="bi bi-pencil-fill"></i>
    </label>
    <input type="file" id="fotoInput" name="foto" accept="image/*" class="d-none">
  </div>
  <div class="text-center">
    <span class="wizard-photo-upload__hint">Foto de perfil (Opcional)</span>
    <div>
      <button type="button" class="btn btn-link btn-sm text-danger p-0" id="btnQuitarFotoEstudiante" hidden>
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
      <?php /* La cédula escolar (CE) es solo del estudiante y viene POR DEFECTO,
               porque el que se inscribe es menor de edad y casi nunca tiene
               cédula propia: el número lo genera el sistema. Si ya la tiene,
               basta elegir V o E en este mismo select y el campo de número se
               habilita solo (lo hace enrollments.js). El tipo ES la decisión,
               no hace falta un interruptor aparte.

               El select lo pinta el componente compartido por todos los
               formularios de persona; muestra solo la letra (V, E, CE) mientras
               está cerrado y el texto completo al desplegarlo
               (SIGDEWizardUI.initTipoDocumentoLetra()). */ ?>
<?php
      $tipoDocId           = 'tipoDocumento';
      $tipoDocOpciones     = ['CE', 'V', 'E'];
      $tipoDocSeleccionado = 'CE';
      include __DIR__ . '/../../../components/tipo-documento-select.php';
      ?>
      <div class="col-4" id="bloqueNumeroDocumento">
        <label class="label-sigde" for="numeroDocumento">Nro documento *</label>
        <div class="input-icon-group">
          <i class="bi bi-person-vcard" aria-hidden="true"></i>
          <input
            type="text"
            class="form-control"
            id="numeroDocumento"
            name="numero_documento"
            placeholder="<?= htmlspecialchars(\App\Support\Documento::PLACEHOLDER_HTML) ?>"
            inputmode="numeric"
            pattern="<?= htmlspecialchars(\App\Support\Documento::PATRON_HTML) ?>"
            minlength="7"
            maxlength="20"
            title="<?= htmlspecialchars(\App\Support\Documento::TITULO_HTML) ?>"
            required
          >
        </div>
        <?php /* Mismo patrón, title y placeholder que el paso 1 del formulario
                 de empleados: acepta el prefijo V/E y los separadores porque
                 es lo que acepta el servidor (App\Support\Documento), que
                 además se queda solo con los dígitos al guardar. Con la cédula
                 escolar (CE) el campo se deshabilita y el número lo genera el
                 sistema, por eso enrollments.js cambia el placeholder según el
                 tipo elegido. */ ?>
      </div>
      <div class="col-4">
        <label class="label-sigde" for="fechaNacimiento">Fecha de nacimiento *</label>
        <input
          type="date"
          class="form-control"
          id="fechaNacimiento"
          name="fecha_nacimiento"
          required
        >
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
        <label class="label-sigde" for="primerNombre">Primer nombre *</label>
        <input type="text" class="form-control" id="primerNombre" name="primer_nombre"
               placeholder="Ej: María" required>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="segundoNombre">Segundo nombre (Opcional)</label>
        <input type="text" class="form-control" id="segundoNombre" name="segundo_nombre"
               placeholder="Ej: Alejandra">
      </div>
      <div class="col-6">
        <label class="label-sigde" for="primerApellido">Primer apellido *</label>
        <input type="text" class="form-control" id="primerApellido" name="primer_apellido"
               placeholder="Ej: García" required>
      </div>
      <div class="col-6">
        <label class="label-sigde" for="segundoApellido">Segundo apellido (Opcional)</label>
        <input type="text" class="form-control" id="segundoApellido" name="segundo_apellido"
               placeholder="Ej: López">
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
      <label class="selectable-card" data-sexo-card>
        <input type="radio" name="sexo" value="M" required>
        <i class="bi bi-gender-male" aria-hidden="true"></i>
        <span>Masculino</span>
      </label>
      <label class="selectable-card" data-sexo-card>
        <input type="radio" name="sexo" value="F" required>
        <i class="bi bi-gender-female" aria-hidden="true"></i>
        <span>Femenino</span>
      </label>
    </div>
  </div>
</section>
