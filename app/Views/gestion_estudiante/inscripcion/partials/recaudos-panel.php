<?php
/**
 * Panel de recaudos reutilizable.
 *
 * Aparece en dos sitios y es el mismo dato en ambos: la ficha del estudiante y el modal de
 * ratificar. Se hizo parcial y no dos copias porque los recaudos se piden en la inscripción
 * y en la ratificación, y dos pantallas para lo mismo se terminan contradiciendo.
 *
 * Variables:
 * - array  $recaudos           Filas del catálogo con el estado de cada estudiante.
 * - int    $recaudosEstudiante  id del estudiante; 0 mientras el modal aún no lo sabe.
 * - bool   $recaudosEditable    true = switches y campo de observación editables.
 *                               false = solo lectura (ficha).
 * - bool   $recaudosBotonPropio true = botón "Guardar recaudos" propio. false cuando el
 *                               panel va dentro de un formulario que ya tiene su botón
 *                               (el modal "Actualizar expediente"), donde un segundo
 *                               botón para lo mismo confunde más que ayuda.
 * - string $recaudosContenedor  id del <div>, para poder rellenarlo por AJAX.
 *
 * El guardado siempre va por estudiantes/guardarRecaudos: no importa desde dónde se llame,
 * así que el control de turno y la bitácora están en un solo sitio.
 */
$recaudos            = $recaudos ?? [];
$recaudosEstudiante  = (int) ($recaudosEstudiante ?? 0);
$recaudosEditable    = (bool) ($recaudosEditable ?? false);
$recaudosBotonPropio = (bool) ($recaudosBotonPropio ?? true);
$recaudosContenedor  = $recaudosContenedor ?? 'recaudosPanel';
/* id del <tbody>. Solo el paso 5 del asistente lo necesita: su JavaScript (enrollments.js)
   engancha el cambio del switch a ese id. En la ficha y en el modal no lleva id porque
   allí los switches se atienden por delegación desde [data-recaudos-panel]. */
$recaudosCuerpoId    = $recaudosCuerpoId ?? '';

$entregados = 0;
$pendientes = [];
foreach ($recaudos as $r) {
    if (!empty($r['consignado'])) {
        $entregados++;
    } else {
        $pendientes[] = $r['nombre'];
    }
}
?>
<?php /* data-recaudos-compacto le dice al JS qué layout tiene que rehacer cuando el panel
       se rellena por AJAX (el modal de edición), para que no construya una tabla donde va
       una lista de tarjetas. */ ?>
<div id="<?= htmlspecialchars($recaudosContenedor) ?>" data-recaudos-panel>
  <?php if (!$recaudosEditable && $pendientes): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 mb-2" role="status">
      <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
      <div class="mb-0">
        <strong>Faltan <?= count($pendientes) ?>
          <?= count($pendientes) === 1 ? 'recaudo' : 'recaudos' ?> por entregar.</strong>
        <?php /* Los nombres no se repiten aquí: la tabla de abajo ya dice cuál falta y cuál
               no. Anunciarlos dos veces era leer lo mismo dos veces. */ ?>
      </div>
    </div>
  <?php endif; ?>

  <?php /* El formulario se imprime siempre en modo editable, aunque no haya filas todavía:
           el modal de ratificar se renderiza antes de saber de qué estudiante se trata y lo
           rellena por AJAX. Si aquí se Cayera el formulario por lista vacía, en ratificación
           no habría ni tabla donde pintar ni botón, y tampoco habría forma de avisarle al
           JS que el panel existe. */ ?>
  <?php if (!$recaudos && !$recaudosEditable): ?>
    <p class="text-support mb-0">
      No hay recaudos registrados en el catálogo. Agrégalos en
      Configuración → Tipo de Documento.
    </p>
  <?php else: ?>
    <?php /* El envoltorio de formulario y el id oculto solo existen en modo editable. En la
             ficha, que es de consulta, no hay nada que enviar: un <input type="hidden"> con
             un id de estudiante dentro de una sección que no se envía es ruido, y además
             hace que el JavaScript de recaudos la trate como un panel editable. El modal
             tampoco lo usa como <form> real, porque "Actualizar expediente" ya es un
             formulario y dos anidados son HTML inválido (el navegador descarta el
             interior): allí el POST lo arma el JS recorriendo los campos del panel. */ ?>
    <?php if ($recaudosEditable): ?>
      <div data-recaudos-form>
      <input type="hidden" name="id_estudiante" value="<?= $recaudosEstudiante ?>">
    <?php else: ?>
      <div data-recaudos-lectura>
    <?php endif; ?>
        <?php /* Se usan las MISMAS clases que la tabla del paso 5 del asistente
               (.wizard-titulos-table): mismo tamaño de letra, mismo encabezado en versalitas
               y mismos anchos de columna. Con `table table-sm` de Bootstrap el panel se veía
               como otra cosa distinta aunque fuera el mismo componente.

               `table-layout: fixed` con anchos en porcentaje es lo que quita el scroll: sin él
               el navegador calcula el ancho mínimo según el contenido, y un nombre largo como
               "Constancia de niño sano" (que no se puede partir) empujaba la tabla más allá
               del contenedor y el envoltorio sacaba la barra horizontal. Con ancho fijo las
               columnas respetan el espacio y el texto largo baja de línea.

               El <div> de aquí no es decorativo: es el que cierra la línea 157 y el que
               contenía a la tabla cuando este panel también tenía un layout de tarjetas.
               Si se quita, queda un </div> de más, el parser cierra el .modal-content antes
               de tiempo y el pie con los botones se renderiza como hermano del modal, es
               decir, al lado. */
        ?>
      <div>
        <table class="wizard-titulos-table" style="table-layout: fixed;">
          <colgroup>
            <col style="width: 40%">
            <col style="width: 22%">
            <col style="width: 38%">
          </colgroup>
          <thead>
            <tr>
              <th>Documento</th>
              <th class="text-center">Consignado</th>
              <th>Observación</th>
            </tr>
          </thead>
          <tbody<?= $recaudosCuerpoId !== '' ? ' id="' . htmlspecialchars($recaudosCuerpoId) . '"' : '' ?>>
            <?php if (!$recaudos): ?>
              <tr><td colspan="3" class="text-center py-3 text-support">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                Consultando recaudos...
              </td></tr>
            <?php endif; ?>
            <?php foreach ($recaudos as $r): ?>
              <?php
                $idRecaudo = (int) ($r['id'] ?? 0);
                $entregado = !empty($r['consignado']);
                $obs       = (string) ($r['observacion'] ?? '');
              ?>
              <tr data-recaudo-row>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars((string) $r['nombre']) ?></div>
                </td>
                <td class="text-center">
                  <?php if ($recaudosEditable): ?>
                    <?php /* El oculto no es opcional: un checkbox sin marcar no viaja en el
                         POST, y sin él el servidor no distinguiría "no entregado" de
                         "este documento no estaba en la pantalla". */ ?>
                    <input type="hidden" name="recaudos[<?= $idRecaudo ?>][consignado]" value="0">
                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                      <input class="form-check-input" type="checkbox" role="switch"
                             name="recaudos[<?= $idRecaudo ?>][consignado]"
                             value="1" data-recaudo-toggle="<?= $idRecaudo ?>"
                             <?= $entregado ? 'checked' : '' ?>>
                    </div>
                  <?php else: ?>
                    <?php if ($entregado): ?>
                      <span class="status-badge status-badge--active">
                        <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>Sí
                      </span>
                    <?php else: ?>
                      <span class="status-badge status-badge--inactive">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>No
                      </span>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($recaudosEditable): ?>
                    <input type="text" class="form-control form-control-sm <?= $entregado ? '' : 'd-none' ?>"
                           name="recaudos[<?= $idRecaudo ?>][observacion]"
                           data-recaudo-observacion="<?= $idRecaudo ?>"
                           value="<?= htmlspecialchars($obs) ?>"
                           maxlength="255"
                           placeholder="Ej.: copia ilegible, se pidió el original">
                    <span class="text-support small <?= $entregado ? 'd-none' : '' ?>" data-recaudo-pendiente>
                      Pendiente
                    </span>
                  <?php else: ?>
                    <?= $obs !== '' ? htmlspecialchars($obs) : '<span class="text-support">—</span>' ?>
                  <?php endif; ?>
                </td>

              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <p class="text-support small mb-0 me-auto" data-recaudos-resumen>
          <?= $entregados ?> de <?= count($recaudos) ?> recaudos entregados.
        </p>
        <?php if ($recaudosEditable && $recaudosBotonPropio): ?>
          <button type="button" class="btn btn-sm btn-primary" data-guardar-recaudos
                  <?= $recaudosEstudiante > 0 ? '' : 'disabled' ?>>
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar recaudos
          </button>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
