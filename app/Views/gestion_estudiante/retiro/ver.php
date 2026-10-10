<?php
/**
 * Vista: gestion_estudiante/retiro/ver.php
 * Detalle (solo lectura) de una solicitud de retiro.
 *
 * Sirve para dos cosas con el mismo archivo, igual que ratificación e inscripción:
 * - abierta en el navegador, es una página completa con encabezado y acciones;
 * - con la cabecera X-Requested-With, devuelve SOLO el cuerpo del modal.
 *
 * El cuerpo del modal es el que usa el botón "ver" del listado. Escribirlo dos veces
 * haría que el modal y la página mostraran datos distintos en cuanto uno se tocara,
 * que es justo lo que pasaba antes: el modal se armaba en el navegador leyendo
 * atributos data- del botón, que ya no los lleva, y quedaba en blanco.
 *
 * LAS DOS FORMAS TIENEN DISEÑO DISTINTO, y es a propósito (el mismo criterio que
 * ratificacion/ver.php):
 * - el MODAL usa cabecera `user-detail` + bloques `wizard-summary` armados con los
 * componentes, que es el lenguaje de todos los modales de detalle;
 * - la PÁGINA usa el layout de dos columnas con `detail-list`, que aprovecha el ancho.
 * Usar el de página dentro del modal es lo que hace que el modal se vea "distinto".
 *
 * Espera del controlador:
 * - array $item con las mismas claves que devuelve WithdrawalModel::getDetail()
 * (que a su vez reutiliza mapRow del listado): id, estudiante, cedula, iniciales,
 * avatar_img, avatar_color, grado, seccion, fecha_solicitud, motivo, observaciones,
 * registrado_por, resuelto_por, fecha_resolucion, estado.
 *
 * Todos los valores se escapan al imprimir, y los valores fijos de los campos del
 * resumen también escapan su etiqueta. El JS vuelca este HTML con innerHTML y no
 * necesita escapar de nuevo: lo que sale de aquí ya está escapado.
 *
 * @var array $item
*/

$item    = $item ?? [];
$esModal = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

$componentes = __DIR__ . '/../../components/';

$nombre = (string) ($item['estudiante'] ?? 'Estudiante');
$cedula = (string) ($item['cedula'] ?? '—');

/* La etiqueta del estado sale del mismo valor en minúsculas que usa la fila del
 listado, para que la fila y el modal digan exactamente lo mismo.*/
$etiquetasEstado = [
    'pendiente' => ['Pendiente', 'warning', 'hourglass-split'],
    'aprobado'  => ['Aprobado', 'active', 'check-circle'],
    'rechazado' => ['Rechazado', 'inactive', 'x-circle'],
];
$claveEstado    = strtolower((string) ($item['estado'] ?? ''));
[$etiquetaEstado, $tonoEstado, $iconoEstado] = $etiquetasEstado[$claveEstado]
    ?? [($claveEstado !== '' ? ucfirst($claveEstado) : '—'), 'inactive', 'question-circle'];

/* Un guion es más honesto que un campo vacío: los datos ausentes son datos que nadie
 registró, y esta vista es de solo lectura, así que no hay nada que rellenar.*/
$dato = static fn ($valor): string => (isset($valor) && trim((string) $valor) !== '') ? (string) $valor : '—';

/* Grado y sección se muestran juntos, como en la fila del listado.*/
$gradoSeccion = trim(($item['grado'] ?? '') . ' - Sección ' . ($item['seccion'] ?? ''), ' -');
if (trim((string) ($item['grado'] ?? '') . (string) ($item['seccion'] ?? '')) === '') {
    $gradoSeccion = '—';
}

/* La resolución solo existe en las solicitudes ya resueltas. Antes de ese dato, el
 formulario de registro exigía motivo y para qué se retiraba, y no guardaba quién
 lo pidió: conviene decirlo en vez de mostrar dos huecos.*/
$resuelto = !empty($item['fecha_resolucion']) || !empty($item['resuelto_por']);
$registroObs = trim((string) ($item['observaciones'] ?? ''));

ob_start();
?>

<?php if (!$esModal): ?>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">Detalle de Retiro</h1>
      <p class="page-header__subtitle">
        Solicitud de retiro de <?= htmlspecialchars($nombre) ?>.
      </p>
    </div>

    <div class="page-header__actions">
      <a href="<?= BASE_URL ?>withdrawal/index" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left"></i>
        Volver al listado
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($esModal): ?>
  <?php /* Cabecera + bloques de resumen: el mismo lenguaje que el modal de
 inscripción y el de ratificación.*/ ?>
  <div class="user-detail__header">
    <span class="avatar avatar--xl avatar--<?= htmlspecialchars($item['avatar_color'] ?? 'primary') ?>">
      <?php if (!empty($item['avatar_img'])): ?>
        <img src="<?= htmlspecialchars($item['avatar_img']) ?>" alt="">
      <?php else: ?>
        <?= htmlspecialchars($item['iniciales'] ?? 'ES') ?>
      <?php endif; ?>
    </span>
    <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
    <div class="user-detail__meta">
      <span><?= htmlspecialchars($cedula) ?></span>
      <span class="user-detail__meta-sep">&bull;</span>
      <span>
        <span class="status-badge status-badge--<?= htmlspecialchars($tonoEstado) ?>">
          <i class="bi bi-<?= htmlspecialchars($iconoEstado) ?>"></i>
          <?= htmlspecialchars($etiquetaEstado) ?>
        </span>
      </span>
    </div>
  </div>

  <div class="wizard-summary__row mb-3">
    <?php
    $wsIcono  = 'person';
    $wsTitulo = 'Estudiante';
    $wsCampos = [
        ['Grado y sección', null, $gradoSeccion],
        ['Fecha de la solicitud', null, $dato($item['fecha_solicitud'] ?? '')],
        ['Registrada por', null, $dato($item['registrado_por'] ?? '')],
    ];
    include $componentes . 'wizard-summary-section.php';
    ?>
  </div>

  <div class="wizard-summary__row mb-3">
    <?php
    $wsIcono  = 'file-earmark-text';
    $wsTitulo = 'Motivo de la solicitud';
    $wsCampos = [
        ['Motivo', null, $dato($item['motivo'] ?? '')],
        ['Observaciones', null, $dato($registroObs)],
    ];
    include $componentes . 'wizard-summary-section.php';
    ?>
  </div>

  <div class="wizard-summary__row mb-3">
    <?php /* La resolución solo tiene sentido si hubo resolución: se dice en una línea
 en vez de dejar dos campos vacíos que parecen un dato que se perdió.

 Este bloque de PHP no se cierra aquí a propósito. PHP termina el modo PHP al
 ver el cierre de etiqueta, incluso dentro de un comentario de bloque, así que
 ponerlo al final de este comentario haría que todo el código que viene a
 continuación se imprimiera como texto dentro del modal.*/
    $wsIcono  = $resuelto ? 'check-circle' : 'hourglass';
    $wsTitulo = 'Resolución';
    if ($resuelto) {
        $wsCampos = [
            ['Resuelta por', null, $dato($item['resuelto_por'] ?? '')],
            ['Fecha de resolución', null, $dato($item['fecha_resolucion'] ?? '')],
        ];
    } else {
        $wsCampos = [];
    }
    $wsBody = $resuelto ? '' :
        '<p class="text-support mb-0">Esta solicitud todavía no ha sido resuelta.</p>';
    include $componentes . 'wizard-summary-section.php';
    ?>
  </div>

<?php else: ?>
  <?php /* Página completa: aquí sí el layout de dos columnas, que aprovecha el ancho.*/ ?>
  <div class="user-detail__header">
    <?php
      $avatarInitials = (string) ($item['iniciales'] ?? 'E');
      $avatarSize     = 'xl';
      $avatarTone     = (string) ($item['avatar_color'] ?? 'primary');
      $avatarSrc      = (string) ($item['avatar_img'] ?? '');
      $avatarAlt      = $nombre;
      include $componentes . 'avatar.php';
    ?>
    <div class="user-detail__name"><?= htmlspecialchars($nombre) ?></div>
    <div class="user-detail__meta">
      <span><?= htmlspecialchars($cedula) ?></span>
      <span class="user-detail__meta-sep">&bull;</span>
      <span><?= htmlspecialchars($etiquetaEstado) ?></span>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="detail-section__label"><i class="bi bi-person"></i>Estudiante</div>
      <div class="detail-list__item">
        <div class="label-sigde">Grado y sección</div>
        <div class="detail-list__value"><?= htmlspecialchars($gradoSeccion) ?></div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Fecha de solicitud</div>
        <div class="detail-list__value"><?= htmlspecialchars($dato($item['fecha_solicitud'] ?? '')) ?></div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Registrada por</div>
        <div class="detail-list__value"><?= htmlspecialchars($dato($item['registrado_por'] ?? '')) ?></div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="detail-section__label"><i class="bi bi-file-text"></i>Motivo</div>
      <div class="detail-list__item">
        <div class="detail-list__value"><?= htmlspecialchars($dato($item['motivo'] ?? '')) ?></div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Observaciones</div>
        <div class="detail-list__value"><?= htmlspecialchars($dato($registroObs)) ?></div>
      </div>

      <?php /* Quién resolvió y cuándo: sin estos datos una aprobación no deja rastro de
 su autor. Solo las solicitudes ya resueltas los tienen.*/ ?>
      <div class="detail-list__item">
        <div class="label-sigde">Resuelta por</div>
        <div class="detail-list__value"><?= htmlspecialchars($dato($item['resuelto_por'] ?? '')) ?></div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Fecha de resolución</div>
        <div class="detail-list__value"><?= htmlspecialchars($dato($item['fecha_resolucion'] ?? '')) ?></div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-between mt-4">
    <a href="<?= BASE_URL ?>withdrawal/index" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left"></i> Volver al listado
    </a>
  </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();

if ($esModal) {
    echo $pageContent;
} else {
    require __DIR__ . '/../../layouts/app.php';
}
