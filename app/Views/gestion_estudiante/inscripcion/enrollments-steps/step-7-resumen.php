<?php
/**
 * Paso 7 - Confirmación.
 *
 * Mismo armazón que el paso 6 del wizard de empleados (empleados/employee-steps/
 * step-6-confirmacion.php): la identidad del estudiante arriba con su botón
 * "Editar", y debajo las secciones declaradas en $filas. Cada fila se reparte
 * sola en columnas (.wizard-summary__row) y cada sección lleva el "Editar" que
 * la devuelve al paso donde se capturó.
 *
 * Las secciones van agrupadas por propósito, no por paso: "Procedencia" y
 * "Académico" conviven en la primera fila porque son los dos bloques que se
 * leen juntos, aunque las captura el paso 2 y el 3 respectivamente.
 *
 * Contrato con enrollments.js (llenarResumen):
 * - `[data-resumen="campo"]` se rellena con textContent, así que el elemento
 * no puede llevar markup alrededor (ver wizard-summary-field.php).
 * - `#resumenAvatarPreview` / `#resumenAvatarIcon` la foto que se subió en el paso 1.
 * - `#badgeFichaMedica` cambia entre registrada y sin registrada.
 * - `#resumenDocsProgressBar`, `#resumenDocsProgreso`, `#resumenListaDocumentos` recaudos.
 * - `#resumenFamiliares` lo inyecta el JS, una tarjeta por familiar.
*/

/* Cuerpos que no son un par etiqueta/valor. Se arman antes de declararlos en
 $filas porque ahí solo caben datos, no markup.*/

// Ficha médica: un estado, no un dato de texto. El id va en el badge y no en un
// contenedor porque el JS le cambia clase y texto a ese elemento.
ob_start();
?>
<div class="wizard-summary__item mt-3">
  <div class="wizard-summary__label">Ficha médica</div>
  <div class="wizard-summary__value">
    <span class="status-badge status-badge--inactive" id="badgeFichaMedica">Sin ficha registrada</span>
  </div>
</div>
<?php
$cuerpoFichaMedica = ob_get_clean();

// Recaudos: barra de avance + un <li> por fila del paso 5 (verde entregado,
// rojo pendiente). El texto sale de la tabla del paso 5, no de este archivo.
ob_start();
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <div class="progress flex-grow-1" style="height: 6px;">
    <div class="progress-bar bg-warning" role="progressbar" style="width: 0"
         aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="resumenDocsProgressBar"></div>
  </div>
  <span class="wizard-summary__label mb-0" id="resumenDocsProgreso">— de —</span>
</div>
<ul class="list-unstyled mb-0" id="resumenListaDocumentos"></ul>
<?php
$cuerpoRecaudos = ob_get_clean();

// Familiares: lo inyecta entero el JS, una tarjeta por familiar.
ob_start();
?>
<div id="resumenFamiliares">
  <p class="text-support mb-0">Sin familiares registrados.</p>
</div>
<?php
$cuerpoFamiliares = ob_get_clean();

/* Cada fila es una lista de secciones; cada sección, sus campos.
 'campos' => [['Etiqueta', 'clave'], ...] para lo que pinta el JS, y 'cuerpo'
 para lo que es markup. Las dos cosas pueden convivir en la misma sección.*/
$filas = [
    [
        [
            'titulo' => 'Ubicación académica',
            'icono'  => 'mortarboard',
            'paso'   => 3,
            'campos' => [
                ['Grado',         'grado'],
                ['Sección',       'seccion'],
                ['Turno',         'turno'],
                ['Año escolar',   'anio_escolar'],
            ],
        ],
        [
            'titulo' => 'Procedencia',
            'icono'  => 'buildings',
            'paso'   => 2,
            'campos' => [
                ['Tipo de ingreso',               'tipo_ingreso'],
                ['Institución anterior',          'institucion_anterior'],
                ['Último grado cursado',          'ult_grado_cursado'],
                ['Año escolar de procedencia',    'anio_escolar_procedencia'],
            ],
        ],
    ],
    [
        [
            'titulo' => 'Talla y salud',
            'icono'  => 'rulers',
            'paso'   => 4,
            'campos' => [
                ['Camisa',   'talla_camisa'],
                ['Pantalón', 'talla_pantalon'],
                ['Calzado',  'talla_zapato'],
            ],
            'cuerpo' => $cuerpoFichaMedica,
        ],
        [
            'titulo' => 'Recaudos',
            'icono'  => 'file-earmark-text',
            'paso'   => 5,
            'cuerpo' => $cuerpoRecaudos,
        ],
    ],
];
?>
<div class="wizard-summary__section-header mb-2">
  <span class="wizard-summary__icon"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
  <span class="wizard-summary__section-title">Confirmar inscripción</span>
</div>
<p class="text-support mb-3">Revisa que todo esté correcto antes de finalizar.</p>

<?php /* La identidad lleva su propio "Editar" al paso 1, igual que en empleados:
 los datos personales se leen primero y se corrigen primero. La foto es
 la del paso 1, que el JS copia al entrar al resumen.*/ ?>
<div class="wizard-summary__identity mb-3">
  <span class="avatar avatar--xl avatar--primary">
    <img id="resumenAvatarPreview" alt="" hidden>
    <i class="bi bi-person" id="resumenAvatarIcon" aria-hidden="true"></i>
  </span>
  <div class="wizard-summary__identity-info">
    <div class="wizard-summary__identity-name" data-resumen="nombre_completo">—</div>
    <div class="wizard-summary__identity-doc" data-resumen="documento">—</div>
    <div class="wizard-summary__grid mt-3">
      <?php
      $wsLabel = 'Fecha de nacimiento';
      $wsField = 'fecha_nacimiento';
      include __DIR__ . '/../../../components/wizard-summary-field.php';

      $wsLabel = 'Sexo';
      $wsField = 'sexo';
      include __DIR__ . '/../../../components/wizard-summary-field.php';
      ?>
    </div>
  </div>
  <button type="button" class="wizard-summary__edit-btn" data-editar-paso="1">
    <i class="bi bi-pencil" aria-hidden="true"></i> Editar
  </button>
</div>

<div class="wizard-summary">
  <?php foreach ($filas as $fila): ?>
    <div class="wizard-summary__row">
      <?php foreach ($fila as $seccion): ?>
        <?php
        $wsCampos     = $seccion['campos'] ?? null;
        $wsBody       = $seccion['cuerpo'] ?? '';
        $wsTitulo     = $seccion['titulo'];
        $wsIcono      = $seccion['icono'];
        $wsEditarPaso = $seccion['paso'];
        include __DIR__ . '/../../../components/wizard-summary-section.php';
        ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php /* Familiares fuera de las filas: el grid reparte el ancho y las
 tarjetas de familiar necesitan la fila entera.*/ ?>
  <?php
  $wsCampos     = null;
  $wsBody       = $cuerpoFamiliares;
  $wsTitulo     = 'Familiares y representación';
  $wsIcono      = 'people';
  $wsEditarPaso = 6;
  include __DIR__ . '/../../../components/wizard-summary-section.php';
  ?>

  <div class="info-alert info-alert--warning">
    <div class="info-alert__icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>
    <div>
      <div class="info-alert__title">Verificación requerida</div>
      <p class="info-alert__text">
        Confirme que los datos están correctos antes de finalizar. Una vez registrada,
        la inscripción no se edita desde aquí: para corregir algo hay que pedir la
        anulación del registro.
      </p>
    </div>
  </div>
</div>