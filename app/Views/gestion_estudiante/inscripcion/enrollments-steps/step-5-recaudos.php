<?php
/**
 * Paso 5 - Recaudos del estudiante.
 * La lista de recaudos es fija por ahora (ver $recaudos); el switch y la
 * observación los gestiona enrollments.js con data-recaudo-toggle.
 */
$recaudos = $recaudos ?? [
    ['id' => 'partida_nacimiento',    'nombre' => 'Partida de Nacimiento',             'detalle' => 'Original y copia',       'consignado' => false, 'observacion' => ''],
    ['id' => 'cedula_estudiante',     'nombre' => 'Cédula del estudiante',             'detalle' => 'Copia ampliada',         'consignado' => false, 'observacion' => ''],
    ['id' => 'fotos_carnet',          'nombre' => '2 Fotos tipo carnet',               'detalle' => 'Fondo blanco',           'consignado' => false, 'observacion' => ''],
    ['id' => 'constancia_estudio',    'nombre' => 'Constancia de estudio',             'detalle' => 'Año escolar anterior',   'consignado' => false, 'observacion' => ''],
    ['id' => 'tarjeta_vacunas',       'nombre' => 'Tarjeta de vacunas',                'detalle' => 'Esquema completo',       'consignado' => false, 'observacion' => ''],
    ['id' => 'cedula_representante',  'nombre' => 'Copia de cédula del representante', 'detalle' => 'Legible',                'consignado' => false, 'observacion' => ''],
];
$total       = count($recaudos);
$consignados = count(array_filter($recaudos, fn ($r) => $r['consignado']));
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

    <div class="table-responsive">
      <table class="wizard-titulos-table">
        <thead>
          <tr>
            <th>Documento</th>
            <th class="text-center" style="width:120px;">Consignado</th>
            <th>Observación</th>
          </tr>
        </thead>
        <tbody id="recaudosTbody">
          <?php foreach ($recaudos as $r): ?>
            <tr data-recaudo-row>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></div>
                <div class="text-support small"><?= htmlspecialchars($r['detalle']) ?></div>
              </td>
              <td class="text-center">
                <div class="form-check form-switch d-flex justify-content-center mb-0">
                  <input class="form-check-input" type="checkbox" role="switch"
                         name="recaudos[<?= htmlspecialchars($r['id']) ?>][consignado]"
                         data-recaudo-toggle
                         <?= $r['consignado'] ? 'checked' : '' ?>>
                </div>
              </td>
              <td>
                <input type="text"
                       class="form-control form-control-sm <?= $r['consignado'] ? '' : 'd-none' ?>"
                       name="recaudos[<?= htmlspecialchars($r['id']) ?>][observacion]"
                       placeholder="Ej: Entregó copia"
                       value="<?= htmlspecialchars($r['observacion']) ?>"
                       data-recaudo-observacion>
                <span class="text-support small <?= $r['consignado'] ? '' : 'd-none' ?>" data-recaudo-pendiente>
                  Pendiente
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<div class="info-alert info-alert--warning">
  <div class="info-alert__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
  <div>
    <p class="info-alert__title">Documentos pendientes</p>
    <p class="info-alert__text">Los documentos faltantes podrán consignarse durante el período de formalización de matrícula.</p>
  </div>
</div>
