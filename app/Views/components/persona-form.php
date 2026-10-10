<?php
/**
 * Componente reutilizable para los datos de una persona (Empleado, Estudiante, Familiar).
 *
 * @var string $prefixId Prefijo para los IDs de los campos (ej. 'familiar' -> 'familiarPrimerNombre')
 * @var string $prefixName Prefijo para los names de los campos (ej. 'familiar_' -> 'familiar_primer_nombre')
 * @var bool $showPhoto Mostrar o no la subida de foto.
 * @var string $minDate Mínima fecha de nacimiento.
 * @var string $maxDate Máxima fecha de nacimiento.
 * @var string $dateTitle Título emergente para la fecha de nacimiento.
 * @var string $docMinLength Mínima longitud de documento.
 * @var string $docMaxLength Máxima longitud de documento.
 * @var string $btnQuitarFotoId ID del botón para quitar foto.
 * @var array $tipoDocOpciones Tipos de documentos permitidos
 * @var string $tipoDocSeleccionado Tipo de documento seleccionado
 * @var bool $pideNacionalidad Mostrar el campo de nacionalidad. Va en true para toda
 * persona; se puede apagar en un formulario puntual.
 * @var array $nacionalidades Catálogo [id_nacionalidad, nombre] del paso 1 del estudiante.
 * @var int $nacionalidadSeleccionada Id ya guardado, para el modal de edición.
 * @var string $slotTrasIdentificacion HTML opcional que se pinta justo debajo de la sección
 * "Identificación oficial". Sirve para avisos que dependen de la cédula (por
 * ejemplo "Ya está registrada") y que deben verse junto al campo donde se
 * escribe, no al final del formulario. Se consume y se borra al terminar el
 * componente, para que no se arrastre a otro include de la misma página.
*/

$prefixId = $prefixId ?? '';
$prefixName = $prefixName ?? '';
$showPhoto = $showPhoto ?? true;
$minDate = $minDate ?? '';
$maxDate = $maxDate ?? '';
$dateTitle = $dateTitle ?? 'Indique la fecha de nacimiento.';
$docMinLength = $docMinLength ?? '7';
$docMaxLength = $docMaxLength ?? '20';
$btnQuitarFotoId = $btnQuitarFotoId ?? $prefixId . 'btnQuitarFoto';
$slotTrasIdentificacion = $slotTrasIdentificacion ?? '';

/* Lugar de nacimiento. La entidad federal se pregunta a toda persona; el municipio y la
   parroquia solo al estudiante, que es el único caso donde se guardan. No hay un campo
   "tipo de persona" en el sistema, así que quien incluye este componente decide con
   $pideMunicipioParroquiaNacimiento: la vista de inscripción lo pone en true y las de
   empleado y familiar lo dejan en false. */
$pideMunicipioParroquiaNacimiento = $pideMunicipioParroquiaNacimiento ?? false;
/* Varias de las vistas que incluyen este componente ya traen $estados para el
   formulario de dirección, así que se reutiliza esa lista en vez de obligar a
   pasarla otra vez por cada cadena de include. */
$estadosNacimiento = $estadosNacimiento ?? ($estados ?? []);

$idSeccionNacimiento = $idSeccionNacimiento ?? '';
$nacimientoEstadoSeleccionado = $nacimientoEstadoSeleccionado ?? 0;
$nacimientoParroquiaSeleccionada = $nacimientoParroquiaSeleccionada ?? 0;

/* Nacionalidad. Se la pide a TODA persona: estudiante, familiar y empleado. Empezó
   solo para el estudiante y terminó en tablas de familiares y empleados con la columna
   siempre vacía, que es ruido: una columna que dice "—" en todas las filas no informa
   nada y esconde las que sí valen. El flag se queda por si algún formulario concreto
   necesita ocultarla, pero ahora lo normal es que esté en true sin que nadie lo marque. */
$pideNacionalidad = $pideNacionalidad ?? true;
$nacionalidadSeleccionada = $nacionalidadSeleccionada ?? 0;
/* Lo pone en true la vista que EDITA un alumno, no la que lo da de alta. Con la
   nacionalizacion en NULL, el alta propone Venezolano y la edicion deja el campo vacío
   con aviso: son dos situaciones distintas y tratarlas igual fabricaría datos. */
$nacionalidadEsRegistroExistente = $nacionalidadEsRegistroExistente ?? false;


$ucPrefixId = $prefixId ? ucfirst($prefixId) : '';

$idFotoPreview = $prefixId ? $prefixId . 'FotoPreview' : 'fotoPreview';
$idFotoInput = $prefixId ? $prefixId . 'FotoInput' : 'fotoInput';
$idNumeroDocumento = $prefixId ? $prefixId . 'NumeroDocumento' : 'numeroDocumento';
$idFechaNacimiento = $prefixId ? $prefixId . 'FechaNacimiento' : 'fechaNacimiento';
$idPrimerNombre = $prefixId ? $prefixId . 'PrimerNombre' : 'primerNombre';
$idSegundoNombre = $prefixId ? $prefixId . 'SegundoNombre' : 'segundoNombre';
$idPrimerApellido = $prefixId ? $prefixId . 'PrimerApellido' : 'primerApellido';
$idSegundoApellido = $prefixId ? $prefixId . 'SegundoApellido' : 'segundoApellido';

$nameFoto = $prefixName . 'foto';
$nameNumeroDocumento = $prefixName . 'numero_documento';
$nameFechaNacimiento = $prefixName . 'fecha_nacimiento';
$namePrimerNombre = $prefixName . 'primer_nombre';
$nameSegundoNombre = $prefixName . 'segundo_nombre';
$namePrimerApellido = $prefixName . 'primer_apellido';
$nameSegundoApellido = $prefixName . 'segundo_apellido';
$nameSexo = $prefixName . 'sexo';

?>

<?php if ($showPhoto): ?>
<div class="wizard-photo-upload mb-3">
  <div class="wizard-photo-upload__circle">
    <div class="wizard-photo-upload__preview" id="<?= $idFotoPreview ?>">
      <i class="bi bi-person"></i>
    </div>
    <label class="wizard-photo-upload__edit" for="<?= $idFotoInput ?>">
      <i class="bi bi-pencil-fill"></i>
    </label>
    <input type="file" id="<?= $idFotoInput ?>" name="<?= $nameFoto ?>" accept="image/*" class="d-none">
  </div>
  <div class="text-center">
    <span class="wizard-photo-upload__hint">Foto de perfil (Opcional)</span>
    <div>
      <button type="button" class="btn btn-link btn-sm text-danger p-0" id="<?= $btnQuitarFotoId ?>" hidden>
        <i class="bi bi-x-circle me-1"></i>Quitar foto
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Identificación oficial</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2">
      <?php
      $tipoDocId    = $prefixId ? $prefixId . 'TipoDocumento' : 'tipoDocumento';
      $tipoDocName  = $prefixName ? $prefixName . 'tipo_documento' : 'tipo_documento';
      $tipoDocCol   = 'col-3';
      // Las variables de opciones las provee el formulario padre o se dejan las default del componente hijo
      include __DIR__ . '/tipo-documento-select.php';
      ?>
      <div class="col-5">
        <label class="label-sigde" for="<?= $idNumeroDocumento ?>">Nro documento *</label>
        <div class="input-icon-group">
          <i class="bi bi-person-vcard" aria-hidden="true"></i>
          <input
            type="text"
            class="form-control"
            id="<?= $idNumeroDocumento ?>"
            name="<?= $nameNumeroDocumento ?>"
            placeholder="<?= htmlspecialchars(\App\Support\Documento::PLACEHOLDER_HTML) ?>"
            inputmode="numeric"
            pattern="<?= htmlspecialchars(\App\Support\Documento::PATRON_HTML) ?>"
            minlength="<?= $docMinLength ?>"
            maxlength="<?= $docMaxLength ?>"
            title="<?= htmlspecialchars(\App\Support\Documento::TITULO_HTML) ?>"
            required>
        </div>
      </div>
      <div class="col-4">
        <label class="label-sigde" for="<?= $idFechaNacimiento ?>">Fecha de nacimiento *</label>
        <input
          type="date"
          class="form-control"
          id="<?= $idFechaNacimiento ?>"
          name="<?= $nameFechaNacimiento ?>"
          <?php if ($minDate): ?>min="<?= $minDate ?>"<?php endif; ?>
          <?php if ($maxDate): ?>max="<?= $maxDate ?>"<?php endif; ?>
          title="<?= htmlspecialchars($dateTitle) ?>"
          required>
      </div>
    </div>
  </div>
</section>

<?php /* El lugar de nacimiento se imprime aquí, con los datos de la persona: es una
   columna de `persona` y sale en "Datos personales" del expediente. El bloque vive en
   su propio componente para que quien lo quiera en otro sitio lo incluya por su cuenta.

   Los tres niveles (estado, municipio y parroquia) los declara SOLO el estudiante, y
   eso lo decide quien incluye este componente con $pideMunicipioParroquiaNacimiento.
   Ojo con el scope: los `include` de PHP comparten variables, así que si una pantalla
   incluye el paso 1 del estudiante y después otro formulario, esa marca en true se
   cuela. Por eso el sub-modal de familiares y el paso 1 del empleado la ponen en false
   explícitamente, aunque false ya sea el valor por defecto. */
include __DIR__ . '/lugar-nacimiento-form.php';

/* Nacionalidad. Se le pregunta a toda persona, como la entidad federal de nacimiento.
   Va detrás de $pideNacionalidad solo para que un formulario puntual pueda ocultarla;
   los tres niveles del lugar de nacimiento sí dependen de su marca, porque de verdad
   solo el estudiante los registra.

   Es un catálogo, no texto libre: Configuración → Catálogos → Nacionalidad. Se guarda
   el id y no el nombre, para que renombrar "Venezolano" no obligue a editar cada alumno.

   EL VALOR POR DEFECTO SOLO APLICA A LO NUEVO. Venezolano viene marcado cuando la
   secretaria está dando de alta un alumno, porque es el caso normal y porque una
   persona capturando un dato nuevo espera que le venga proposing lo de siempre.

   En un alumno YA REGISTRADO que no tiene nacionalidad guardada NO se marca. Marcarlo
   escribiría "Venezolano" como si alguien lo hubiera verificado, y no lo verificó
   nadie: el dato nunca existió. Un default que rellena huecos de datos históricos
   convierte una ausencia de información en una afirmación, y esa afirmación después
   sale en el expediente y en el PDF como si fuera cierta. Es peor que dejar el campo
   vacío, porque un vacío se ve y una inventada no.

   Por eso el campo sale sin marcar y con un aviso de que falta el dato. El `required`
   hace que no se pueda guardar sin elegir, que es justo lo que hay que hacer. */
if ($pideNacionalidad) {
  /* Se recibe el sobre tal como lo arma getNacionalidades() —
     ['nacionalidades' => [...], 'id_por_defecto' => int] — y se desarma acá. Antes cada
     vista lo desarmaba por su cuenta con un nombre distinto, y añadir un formulario
     obligaba a acordarse de cuál era cuál. Con una sola forma, incluir el componente
     y ya está. */
  $catNacionalidades = $nacionalidades ?? [];
  $listaNacionalidades = $catNacionalidades['nacionalidades'] ?? [];
  $idNacionalidadPorDefecto = (int) ($catNacionalidades['id_por_defecto']
                                     ?? \App\Models\NacionalidadModel::ID_POR_DEFECTO);
  $idNacionalidad = $prefixId ? $prefixId . 'Nacionalidad' : 'nacionalidad';
  $nameNacionalidad = $prefixName ? $prefixName . 'id_nacionalidad' : 'id_nacionalidad';

  /* Lo que viene de la base gana siempre: si el alumno ya tiene nacionalidad, esa es la
     que se muestra, aunque el catálogo la haya movido de lugar. */
  $nacionalidadGuardada = (int) ($nacionalidadSeleccionada ?? 0);
  $nacionalidadSeleccionada = $nacionalidadGuardada;

  /* Solo si no hay nada guardado se decide entre proposing el default y dejarlo vacío. */
  if ($nacionalidadGuardada === 0 && !$nacionalidadEsRegistroExistente) {
    $nacionalidadSeleccionada = $idNacionalidadPorDefecto;
  }
  /* El aviso solo aparece cuando el alumno existe y el dato no: en el alta no faltaría
     nada, el default ya respondió la pregunta. */
  $nacionalidadFalta = $nacionalidadGuardada === 0 && $nacionalidadEsRegistroExistente;
  ?>
  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-globe-americas" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Nacionalidad</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="label-sigde" for="<?= $idNacionalidad ?>">Nacionalidad *</label>
          <select class="form-select<?= $nacionalidadFalta ? ' is-invalid' : '' ?>"
                  id="<?= $idNacionalidad ?>" name="<?= $nameNacionalidad ?>" required>
            <option value="">Seleccione la nacionalidad</option>
            <?php foreach ($listaNacionalidades as $nac): ?>
              <?php $nacId = (int) ($nac['id_nacionalidad'] ?? 0); ?>
              <option value="<?= $nacId ?>"<?= $nacId === $nacionalidadSeleccionada ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) ($nac['nombre'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">
            <?= $nacionalidadFalta
                ? 'Este alumno no tiene nacionalidad registrada. Selecciónela para poder guardar.'
                : 'Seleccione la nacionalidad del alumno.' ?>
          </div>
          <p class="form-text mb-0">Se administra en Configuración &rsaquo; Cat&aacute;logos.</p>
        </div>
      </div>
    </div>
  </section>
<?php } ?>


<?php if ($slotTrasIdentificacion !== ''): ?>
  <?= $slotTrasIdentificacion ?>
<?php endif; ?>

<section class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-person" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Nombres y apellidos</span>
  </div>
  <div class="wizard-summary__card">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idPrimerNombre ?>">Primer nombre *</label>
        <input
          type="text"
          class="form-control"
          id="<?= $idPrimerNombre ?>"
          name="<?= $namePrimerNombre ?>"
          placeholder="Ej: María"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idSegundoNombre ?>">Segundo nombre (opcional)</label>
        <input
          type="text"
          class="form-control"
          id="<?= $idSegundoNombre ?>"
          name="<?= $nameSegundoNombre ?>"
          placeholder="Ej: Alejandra"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios">
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idPrimerApellido ?>">Primer apellido *</label>
        <input
          type="text"
          class="form-control"
          id="<?= $idPrimerApellido ?>"
          name="<?= $namePrimerApellido ?>"
          placeholder="Ej: García"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios"
          required>
      </div>
      <div class="col-md-6">
        <label class="label-sigde" for="<?= $idSegundoApellido ?>">Segundo apellido (opcional)</label>
        <input
          type="text"
          class="form-control"
          id="<?= $idSegundoApellido ?>"
          name="<?= $nameSegundoApellido ?>"
          placeholder="Ej: López"
          minlength="2"
          maxlength="50"
          pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+"
          title="Solo se permiten letras y espacios">
      </div>
    </div>
  </div>
</section>

<section class="wizard-summary__section mt-2">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-gender-ambiguous" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Sexo</span>
  </div>
  <div class="wizard-summary__card">
    <div class="selectable-card-group">
      <label class="selectable-card" data-sexo-card>
        <input
          type="radio"
          name="<?= $nameSexo ?>"
          value="M"
          required>
        <i class="bi bi-gender-male"></i>
        <span>Masculino</span>
      </label>
      <label class="selectable-card" data-sexo-card>
        <input type="radio" name="<?= $nameSexo ?>" value="F" required>
        <i class="bi bi-gender-female"></i>
        <span>Femenino</span>
      </label>
    </div>
  </div>
</section>
<?php
// Se consume aquí para que el aviso de un formulario no aparezca en el siguiente include.
unset($slotTrasIdentificacion);