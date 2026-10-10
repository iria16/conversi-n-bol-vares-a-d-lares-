<?php
/**
 * Vista: gestion_estudiante/ratificacion/index.php
 * Ratificación de Matrícula — gestión de continuidad escolar y proyección
 * del próximo periodo académico.
 *
 * Armada con los mismos componentes que el resto de módulos:
 * components/page-header.php, components/stat-card.php,
 * components/avatar.php, components/status-badge.php y
 * components/data-panel.php. La ratificación es individual (el botón de
 * cada fila hace POST a ratificacion/ratificar), así que la vista no
 * necesita JS propio.
 *
 * Espera del controlador:
 * - array $stats ['porRatificar' => int, 'yaRatificados' => int, 'avance' => int]
 * - array $ratificaciones cada uno: ['id','nombre','cedula','iniciales','avatar_color',
 * 'avatar_img' (opcional),'grado_actual','seccion','turno',
 * 'proximo_grado','estado' => 'ratificado'|'pendiente']
 * - array $grados lista de grados para el filtro
 * - array $secciones lista de secciones para el filtro
 * - array $turnos lista de turnos para el filtro
 * - array $filters valores actuales de los filtros aplicados
 * - array $paginacion ['desde','hasta','total','pagina_actual','total_paginas']
 *
 * @var array $stats
 * @var array $ratificaciones
 * @var array $grados
 * @var array $secciones
 * @var array $turnos
 * @var array $filters
 * @var array $paginacion
*/

$pageTitle       = 'Ratificación de Matrícula';
/* La descripción dice cuándo se usa, porque de eso se trata. Antes decía "gestione
 la continuidad y proyecte el próximo periodo", que era cierto en cualquier
 momento del año y por eso la pantalla se llenaba de alumnos que seguían
 cursando: ratificar no es una gestión del día a día, es lo que se hace al cerrar
 el año para los que continúan.*/
$pageDescription = 'Al cierre del año escolar, coloque en el periodo en curso a los alumnos que continúan. '
    . 'Los que ya están matriculados en el año en curso no se listan: todavía están cursando.';
$currentNav      = 'ratificacion';

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Ratificación de Matrícula', 'href' => null],
];

$stats          = $stats ?? ['porRatificar' => 0, 'yaRatificados' => 0, 'avance' => 0];
$ratificaciones = $ratificaciones ?? [];
$grados         = $grados ?? [];
$secciones      = $secciones ?? [];
$turnos         = $turnos ?? [];
$filters        = $filters ?? ['grado' => '', 'seccion' => '', 'turno' => '', 'q' => ''];
$paginacion     = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  // Sin acciones en el header: la ratificación es siempre individual, desde
  // el botón de cada fila.
  $pageHeading    = 'Ratificación de Matrícula';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    /* Esta pantalla es de ratificación, y el número que importa es cuántos de los
 que ya estaban en el plantel todavía no tienen matrícula para el año que
 viene. Los que hay que INSCRIBIR no se cuentan acá: no son un pendiente de
 esta pantalla, se gestionan en el módulo de Inscripción, y mezclarlos hacía
 que el número de "por ratificar" fuera más grande de lo que realmente era y
 que en el cierre de año no se supiera con qué lista trabajar.*/
    $statCards = [
        [
            'label'     => 'Por ratificar',
            'value'     => number_format((int) $stats['porRatificar'], 0, ',', '.'),
            'icon'      => 'bi-clipboard-check',
            'tone'      => 'warning',
            'trend'     => 'Ya inscritos',
            'trendType' => 'neutral',
        ],
        [
            'label'     => 'Ya ratificados',
            'value'     => (int) $stats['yaRatificados'],
            'icon'      => 'bi-check-circle-fill',
            'tone'      => 'success',
            'trend'     => 'Completados',
            'trendType' => 'up',
        ],
        [
            'label'     => 'Avance del proceso',
            'value'     => (int) $stats['avance'] . '%',
            'icon'      => 'bi-graph-up-arrow',
            'tone'      => 'primary',
            'trend'     => 'De los ratificables',
            'trendType' => 'neutral',
            'progress'  => (int) $stats['avance'],
        ],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel     = $card['label'];
        $statValue     = $card['value'];
        $statIcon      = $card['icon'];
        $statTone      = $card['tone'];
        $statTrend     = $card['trend'];
        $statTrendType = $card['trendType'];
        $statProgress  = $card['progress'] ?? null;
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  // El filtrado es automático (main.js, form.auto-filters): no hay botón
  // de buscar, los <select> y el input disparan el panel al cambiar.
  $panelFormAction = BASE_URL . 'ratificacion/index';

  ob_start();
  ?>
  <label for="filtroGrado" class="visually-hidden">Grado actual</label>
  <select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado actual">
    <option value="">Grado (Todos)</option>
    <?php foreach ($grados as $grado): ?>
      <option value="<?= htmlspecialchars($grado) ?>" <?= (string) ($filters['grado'] ?? '') === (string) $grado ? 'selected' : '' ?>>
        <?= htmlspecialchars($grado) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroSeccion" class="visually-hidden">Sección</label>
  <select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
    <option value="">Sección (Todas)</option>
    <?php foreach ($secciones as $seccion): ?>
      <option value="<?= htmlspecialchars($seccion) ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion ? 'selected' : '' ?>>
        <?= htmlspecialchars($seccion) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="filtroTurno" class="visually-hidden">Turno</label>
  <select id="filtroTurno" name="turno" class="form-select" aria-label="Filtrar por turno">
    <option value="">Turno (Todos)</option>
    <?php foreach ($turnos as $turno): ?>
      <option value="<?= htmlspecialchars($turno) ?>" <?= (string) ($filters['turno'] ?? '') === (string) $turno ? 'selected' : '' ?>>
        <?= htmlspecialchars($turno) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = (string) ($filters['q'] ?? '');
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Estudiante / Grado / Sección / Turno / Próximo / Estado / Acción.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 26%;">
    <col style="width: 12%;">
    <col style="width: 9%;">
    <col style="width: 10%;">
    <col style="width: 14%;">
    <col style="width: 13%;">
    <col style="width: 16%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Estudiante</th>
    <th scope="col">Grado actual</th>
    <th scope="col">Sección</th>
    <th scope="col">Turno</th>
    <th scope="col">Próximo grado</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acción</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($ratificaciones as $item):
    $esRatificado = ($item['estado'] ?? '') === 'ratificado';
    $estudianteId = (int) ($item['id'] ?? 0);

    /* El estado que se muestra no es solo "ratificado o pendiente". Son cinco
 situaciones, y dos de ellas seacharcan solo al mostrar bien la fila:

 - El estado dice qué pasó con la MATRÍCULA del año en curso: pendiente,
 ratificado, retirado o egresado.

 Antes esta fila tenía además un caso "por inscribir", para el alumno que no
 tenía inscripción previa. Se quitó porque con el criterio actual ya no puede
 aparecer: un alumno sin ninguna matrícula no está en el plantel, y el
 listado se arma sobre las matrículas, así que no llega a listarse. Y aunque
 llegara, el sistema ya lo impide: `RatificacionModel::ratificar()` lo
 rechaza y el trigger de la base también. Mostrar un estado que nadie va a
 ver es ruido.*/
    $estadoItem = (string) ($item['estado'] ?? 'pendiente');
    $cerrado    = in_array($estadoItem, ['retirado', 'egresado'], true);
?>

    <tr>
      <td>
        <div class="avatar-group">
          <?php
            $avatarSize     = 'md';
            $avatarTone     = $item['avatar_color'] ?? 'primary';
            $avatarSrc      = $item['avatar_img'] ?? '';
            $avatarAlt      = $item['nombre'] ?? '';
            $avatarInitials = $item['iniciales'] ?? 'ES';
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($item['nombre'] ?? '') ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($item['cedula'] ?? '') ?></div>
          </div>
        </div>
      </td>
      <td class="text-support"><?= htmlspecialchars($item['grado_actual'] ?? '') ?></td>
      <td class="text-support"><?= htmlspecialchars($item['seccion'] ?? '') ?></td>
      <?php /* El turno va a la vista y no solo al filtro. Con el filtro por turno
 pero sin la columna, elegir "Mañana" dividía la lista en dos grupos
 indistinguibles: no había forma de ver qué tenía cada alumno ni
 comprobar que el filtro hacía lo que decía.*/ ?>
      <td class="text-support"><?= htmlspecialchars($item['turno'] ?? '') ?></td>
      <td class="fw-semibold"><?= htmlspecialchars($item['proximo_grado'] ?? '—') ?></td>
      <td>
        <?php
          /* Se muestra el proceso que corresponde a ESTE estudiante, ya evaluado
 por el modelo con la misma pregunta que usa el guardado. La secretaria
 no tiene que deducirlo: si dice "Inscribir", el botón de ratificar no
 está porque ratificar no es su proceso.*/
          $etiquetas = [
              'ratificado'  => ['Ratificado', 'active',  'Ya tiene su continuación registrada'],
              'retirado'    => ['Retirado',   'inactive', 'Se retiró antes de culminar el año'],
              'egresado'    => ['Egresado',   'info',    'Culminó el ciclo completo: su año está cerrado'],
              'pendiente'   => ['Pendiente',  'pending', 'Ya estaba inscripto: le corresponde ratificación'],
          ];
          $etiqueta = $etiquetas[$estadoItem] ?? $etiquetas['pendiente'];
          $badgeLabel  = $etiqueta[0];
          $badgeStatus = $etiqueta[1];
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <?php if ($cerrado): ?>
            <button type="button" class="action-btn" disabled
                    title="<?= $estadoItem === 'egresado'
                        ? 'Ya egresó: su año está cerrado y no corresponde ratificarlo'
                        : 'Se retiró antes de culminar el año: su registro ya tiene ese estado' ?>">
              <i class="bi bi-<?= $estadoItem === 'egresado' ? 'award' : 'box-arrow-right' ?>"></i>
            </button>
          <?php elseif ($esRatificado): ?>
            <?php /* El botón de VER solo existe cuando ya se ratificó, porque antes
 no hay una ratificación que ver: se vería el detalle de una fila que
 todavía no existe. También cuando el año se cerró, porque entonces el
 botón sale deshabilitado y con la explicación en el title.*/ ?>
            <button type="button" class="action-btn action-btn--view btn-ver-ratificacion"
                    data-id="<?= $estudianteId ?>" title="Ver detalle de la ratificación">
              <i class="bi bi-eye"></i>
            </button>
          <?php else: ?>
            <button type="button"
                    class="btn btn-primary rounded-pill px-4 py-1 fw-semibold btn-ratificar-modal"
                    title="Ratificar estudiante"
                    data-id="<?= $estudianteId ?>"
                    data-anio-origen="<?= htmlspecialchars($item['anio_actual'] ?? '—') ?>"
                    data-grado-destino-id="<?= (int) ($item['proximo_grado_id'] ?? 0) ?>"
                    /* El nombre del grado predicho viaja además del id para poder nombrarlo
 en el aviso del modal cuando no exista en la estructura del período:
 "3er Grado no tiene secciones" es accionable; "no hay grados
 disponibles" no.*/
                    data-grado-destino="<?= htmlspecialchars($item['proximo_grado'] ?? '—') ?>"
                    data-seccion-destino-id="<?= (int) ($item['id_seccion'] ?? 0) ?>"
                    data-turno-destino-id="<?= (int) ($item['id_turno'] ?? 0) ?>">
              Ratificar
            </button>
          <?php endif; ?>

          <?php /* Solo queda la acción de esta pantalla. El enlace al expediente
 se quitó porque el botón de ver ya lleva al detalle del alumno, y dos
 botones con el mismo icono a un click de distancia hacen dudar de
 cuál ver. El expediente sigue estando, desde el listado de la sección
 Consulta académica → Estudiantes.*/ ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$ratificaciones): ?>
    <tr>
      <td colspan="7" class="text-center text-support py-4">
        No se encontraron estudiantes con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . number_format((int) $paginacion['total'], 0, ',', '.') . ' estudiantes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$pageContent = ob_get_clean();

ob_start();

/* Las combinaciones del año activo van una sola vez en la página, en JSON, y el
 navegador arma los <select> del modal a partir de aquí. No se piden por
 alumno: la lista depende del año, que es el mismo para todos los de la
 pantalla, así que traerla en cada fila sería una petición por fila sin ganar
 nada. Se imprime como type="application/json" y no en un data- attribute
 porque el JSON lleva comillas y ahí hay que escaparlas a mano.*/
?>
<script type="application/json" id="ratificarCombinaciones"><?= json_encode(
    $combinaciones ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?></script>
<?php

require_once __DIR__ . '/modals/ratificar-modal.php';
require_once __DIR__ . '/modals/editar-estudiante-modal.php';

// Modal de detalle de la ratificación. El cuerpo se puebla por AJAX desde
// ratificacion.js contra ratificacion/ver, que devuelve ver.php (esa misma vista
// detecta la cabecera XHR y responde solo el cuerpo, sin el layout).
$modalBody     = '<div id="ratificacionVerBody"></div>';
$modalFooter   = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
$modalId       = 'modalVerRatificacion';
$modalTitle    = 'Detalle de Ratificación';
$modalSubtitle = 'De dónde venía el estudiante y a qué grado quedó ratificado.';
$modalSize     = 'lg';
include __DIR__ . '/../../components/modal.php';

$pageModals = ob_get_clean();

/* familiares.js va primero: ratificacion.js lo arranca con FamiliaresUI.init().
 Los dos archivos se cargan en las dos pantallas porque el sub-modal de
 familiares es el MISMO (inscripcion/modal-agregar-familiar.php) y sus reglas son
 las mismas; lo que cambia es la configuración que le pasa cada uno.*/
$extraScripts = ['/js/familiares.js', '/js/ratificacion.js', '/js/recaudos.js'];
require __DIR__ . '/../../layouts/app.php';
