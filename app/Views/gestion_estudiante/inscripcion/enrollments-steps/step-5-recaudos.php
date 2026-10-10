<?php
/**
 * Paso 5 - Recaudos del estudiante.
 *
 * La tabla la pinta el partial de recaudos, el MISMO que usan la ficha del expediente y el
 * modal "Actualizar expediente" (y este último, la ratificación). Estaba escrita aquí a mano
 * y terminó viéndose distinta en cada pantalla: mismo dato, tres aspectos. Un solo partial
 * hace que se vean iguales por construcción y no por memoria.
 *
 * Variables:
 * - array $recaudos Cada fila: 'id', 'nombre', 'detalle', 'consignado', 'observacion'.
*/
$recaudos = $recaudos ?? [];
$total    = count($recaudos);
$consignados = count(array_filter($recaudos, fn ($r) => !empty($r['consignado'])));
?>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Recaudos del estudiante</span>
    <span class="status-badge status-badge--active" id="recaudosBadge">
      <span id="recaudosConsignadosCount"><?= $consignados ?></span> de
      <span id="recaudosTotalCount"><?= $total ?></span> consignados
    </span>
  </div>
  <div class="wizard-summary__card">
    <p class="text-support">
      Marque los documentos que el representante entrega en este momento.
    </p>

    <?php
      /* Editable y sin botón propio: el guardado de este paso es el botón de abajo del
 asistente, no uno que diga "Guardar recaudos". El id del tbody lo necesita
 enrollments.js, que engancha ahí el cambio del switch.*/
      $recaudosEditable   = true;
      $recaudosBotonPropio = false;
      $recaudosContenedor = 'recaudosPaso5';
      $recaudosCuerpoId   = 'recaudosTbody';
      require __DIR__ . '/../partials/recaudos-panel.php';
    ?>
  </div>
</section>
