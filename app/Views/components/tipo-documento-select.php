<?php
/**
 * Componente: tipo-documento-select.php
 * <select> del tipo de documento de una persona, compartido por TODOS los
 * formularios de persona del sistema: empleados (alta y edición), estudiantes
 * e inscripción y el sub-modal de familiares.
 *
 * Antes cada formulario tenía su copia y se fueron desincronizando: el de
 * empleados y el de inscripción usaban "V - Venezolano" y el de familiares
 * "V - Venezolano / E - Extranjero / CE - Especial", y solo algunos
 * offerían la cédula escolar. Acá la lista de tipos y sus textos viven en un
 * solo lugar, así que un formulario nuevo no puede inventarse su propia
 * variante.
 *
 * Las etiquetas son "LETRA - texto" a propósito: el select muestra solo la
 * letra mientras está cerrado (ver
 * SIGDEWizardUI.initTipoDocumentoLetra() en public/assets/js/wizard-ui.js) y
 * el texto completo al desplegarlo.
 *
 * Variables:
 * - string $tipoDocId id del <select> (obligatorio)
 * - string $tipoDocName name del <select> (default: 'tipo_documento')
 * - array $tipoDocOpciones tipos permitidos, en orden (default: V, E)
 * - string $tipoDocSeleccionado tipo marcado; default: el primero de la lista
 * - string $tipoDocLabel texto de la etiqueta (default: 'Tipo de documento *')
 * - bool $tipoDocRequired default true
 * - string $tipoDocCol clase de columna del Bootstrap (default: 'col-4')
 *
 * Tipos y lo que significan:
 * V → cédula de identidad venezolana (la cédula escolar empieza por V)
 * E → cédula de extranjería (la cédula escolar empieza por E)
 * CE → cédula escolar: la asigna la institución a un menor que todavía no
 * tiene cédula propia, y la genera el sistema (App\Support\CedulaEscolar).
 * Solo el estudiante la usa: ni empleados ni familiares.
*/
$tipoDocId          = $tipoDocId ?? '';
$tipoDocName        = $tipoDocName ?? 'tipo_documento';
$tipoDocOpciones    = $tipoDocOpciones ?? ['V', 'E'];
$tipoDocLabel       = $tipoDocLabel ?? 'Tipo de documento *';
$tipoDocRequired    = $tipoDocRequired ?? true;
$tipoDocCol         = $tipoDocCol ?? 'col-4';

// Textos canónicos. El ENUM de persona.tipo_documento es ('V','E','CE','P'),
// pero 'P' (pasaporte) no se ofrece a propósito: la institución no lo necesita
// y su formato es alfanumérico, que no encaja con el limpiado de las cédulas
// (App\Support\Documento::normalizar deja solo dígitos). Si algún día hace
// falta, no alcanza con agregar la opción acá: hay que darle su propio patrón y
// su propia limpieza, como se hizo y se revirtió.
$tipoDocTextos = [
    'V'  => 'V - Venezolano',
    'E'  => 'E - Extranjero',
    'CE' => 'CE - Cédula escolar',
];

$tipoDocSeleccionado = $tipoDocSeleccionado ?? reset($tipoDocOpciones);
?>
<div class="<?= htmlspecialchars($tipoDocCol) ?>">
  <label class="label-sigde" for="<?= htmlspecialchars($tipoDocId) ?>"><?= htmlspecialchars($tipoDocLabel) ?></label>
  <select class="form-select" id="<?= htmlspecialchars($tipoDocId) ?>" name="<?= htmlspecialchars($tipoDocName) ?>"<?= $tipoDocRequired ? ' required' : '' ?>>
<?php foreach ($tipoDocOpciones as $tipoDocValor): ?>
    <option value="<?= htmlspecialchars($tipoDocValor) ?>"<?= $tipoDocValor === $tipoDocSeleccionado ? ' selected' : '' ?>><?= htmlspecialchars($tipoDocTextos[$tipoDocValor] ?? $tipoDocValor) ?></option>
<?php endforeach; ?>
  </select>
</div>
<?php
// Se limpian para que la vista que incluye no los herede por accidente.
unset($tipoDocId, $tipoDocName, $tipoDocOpciones, $tipoDocSeleccionado, $tipoDocLabel, $tipoDocRequired, $tipoDocCol, $tipoDocTextos);
?>
