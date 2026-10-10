<?php
$pageTitle = 'Retiro';
$pageDescription = 'Gestión y procesamiento de solicitudes de retiro escolar.';
$currentNav = 'retiro';
$extraScripts = ['/js/withdrawals.js'];
$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => null],
    ['label' => 'Retiro', 'href' => null],
];
$stats = $stats ?? ['pendientes' => 0, 'aprobados' => 0, 'rechazados' => 0];
$solicitudes = $solicitudes ?? [];
$aniosEscolares = $aniosEscolares ?? [];
$motivos = $motivos ?? [];
// El controlador decide qué puede hacer cada rol: la secretaria registra los
// retiros y el directivo los aprueba/rechaza.
$puedeRegistrar = $puedeRegistrar ?? false;
$puedeResolver = $puedeResolver ?? false;
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];
ob_start();

  // ---------- Page header ----------
  // El botón de "Registrar retiro" solo lo ve quien puede crearlo (secretaria);
  // el resto de la vista es de consulta para los dos roles.
  ob_start();
  if ($puedeRegistrar):
  ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registrarRetiroModal">
      <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar Retiro
    </button>
  <?php
  endif;
  $pageHeaderActions = ob_get_clean();
  $pageHeading    = 'Retiros Escolares';
  $pageSubheading = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <?php
    $statCards = [
        ['label' => 'Pendientes', 'value' => (int) $stats['pendientes'], 'icon' => 'bi-hourglass-split',    'tone' => 'warning'],
        ['label' => 'Aprobados',  'value' => (int) $stats['aprobados'],  'icon' => 'bi-check-circle-fill',  'tone' => 'success'],
        ['label' => 'Rechazados', 'value' => (int) $stats['rechazados'], 'icon' => 'bi-x-circle-fill',      'tone' => 'danger'],
    ];
    foreach ($statCards as $card):
  ?>
    <div class="col-sm-6 col-lg-4">
      <?php
        $statLabel = $card['label'];
        $statValue = $card['value'];
        $statIcon  = $card['icon'];
        $statTone  = $card['tone'];
        include __DIR__ . '/../../components/stat-card.php';
      ?>
    </div>
  <?php endforeach; ?>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'withdrawal/index';

  ob_start();
  ?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="">Estado (Todos)</option>
    <option value="pendiente" <?= ($_GET['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
    <option value="aprobado"  <?= ($_GET['estado'] ?? '') === 'aprobado'  ? 'selected' : '' ?>>Aprobado</option>
    <option value="rechazado" <?= ($_GET['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por nombre o cédula...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos: Estudiante / Grado-Sección / Fecha / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 28%;">
    <col style="width: 20%;">
    <col style="width: 18%;">
    <col style="width: 17%;">
    <col style="width: 17%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Estudiante</th>
    <th scope="col">Grado/Sección</th>
    <th scope="col">Fecha solicitud</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($solicitudes as $item):
    $estudiante     = $item['estudiante'] ?? '';
    $cedula         = $item['cedula'] ?? '';
    $grado          = $item['grado'] ?? '';
    $seccion        = $item['seccion'] ?? '';
    $fechaSolicitud = $item['fecha_solicitud'] ?? '';
    $observaciones  = $item['observaciones'] ?? '';
    $estado         = $item['estado'] ?? 'pendiente';
    $iniciales      = $item['iniciales'] ?? '';

    $estadoClase = $estado === 'aprobado'
        ? 'active'
        : ($estado === 'rechazado' ? 'inactive' : 'pending');
  ?>
    <tr>
      <td>
        <div class="avatar-group d-flex align-items-center gap-2">
          <?php
            $avatarInitials = $iniciales;
            $avatarSize     = 'sm';
            $avatarTone     = 'primary';
            $avatarSrc      = '';
            $avatarAlt      = $estudiante;
            include __DIR__ . '/../../components/avatar.php';
          ?>
          <div>
            <div class="avatar-group__name"><?= htmlspecialchars($estudiante) ?></div>
            <div class="avatar-group__meta"><?= htmlspecialchars($cedula) ?></div>
          </div>
        </div>
      </td>
      <td>
        <?= htmlspecialchars($grado) ?><br>
        <span class="text-support">Sección <?= htmlspecialchars($seccion) ?></span>
      </td>
      <td><?= htmlspecialchars($fechaSolicitud) ?></td>
      <td>
        <?php
          $badgeLabel  = ucfirst($estado);
          $badgeStatus = $estadoClase;
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
            class="action-btn action-btn--view ver-retiro"
            data-id="<?= (int) $item['id'] ?>"
            title="Ver solicitud">
            <i class="bi bi-eye"></i>
          </button>
          <?php if ($puedeResolver && $estado === 'pendiente'): ?>
            <button type="button" class="action-btn aprobar-retiro" data-id="<?= (int) $item['id'] ?>" title="Aprobar retiro">
              <i class="bi bi-check-circle"></i>
            </button>
            <button type="button" class="action-btn action-btn--danger rechazar-retiro" data-id="<?= (int) $item['id'] ?>" title="Rechazar retiro">
              <i class="bi bi-x-circle"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$solicitudes): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron solicitudes de retiro.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' solicitudes';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
  ?>

  <?php
  $pageContent = ob_get_clean();

// ---------- Modales ----------
// Se capturan aparte de $pageContent: layouts/app.php los imprime bajo <body>,
// fuera de .app-shell, para que no hereden overflow ni apilamiento del cascarón.
ob_start();

  // ---------- Modal: registrar retiro (solo secretaria) ----------
  if ($puedeRegistrar):
      $hayMotivos = !empty($motivos);
  ob_start();
  ?>
  <form id="formRegistrarRetiro" novalidate>
    <!-- La matrícula viaja oculta: la secretaria no escribe un id, elige de la lista. -->
    <input type="hidden" id="retiroIdMatricula" name="id_matricula" value="">

    <div class="mb-3">
      <label for="retiroBuscarEstudiante" class="label-sigde">Cédula del estudiante</label>
      <div class="input-icon-group">
        <i class="bi bi-person-vcard" aria-hidden="true"></i>
        <input type="search" class="form-control" id="retiroBuscarEstudiante"
               name="q" placeholder="Ej: 11625863"
               autocomplete="off">
      </div>
      <div class="invalid-feedback" id="retiroBuscarFeedback">Busque y seleccione un estudiante.</div>
    </div>

    <!-- Lista de coincidencias: la arma el JS con lo que devuelve buscarEstudiante. -->
    <div id="retiroResultados" class="mb-3 d-none"></div>

    <!-- Datos del estudiante ya localizado: son los que se revisan antes de confirmar. -->
    <div id="retiroEstudianteDatos" class="mb-3 d-none">
      <div class="detail-section__label"><i class="bi bi-person-vcard"></i>Estudiante seleccionado</div>
      <div class="detail-list__item">
        <div class="label-sigde">Nombre</div>
        <div class="detail-list__value" id="retiroDatosNombre">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Documento</div>
        <div class="detail-list__value" id="retiroDatosCedula">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Grado y sección</div>
        <div class="detail-list__value" id="retiroDatosGrado">—</div>
      </div>
      <div class="detail-list__item">
        <div class="label-sigde">Año escolar</div>
        <div class="detail-list__value" id="retiroDatosAnio">—</div>
      </div>
    </div>

    <div class="mb-3">
      <label for="retiroMotivo" class="label-sigde">Motivo del retiro</label>
      <div class="input-icon-group">
        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        <select class="form-select" id="retiroMotivo" name="id_motivo_retiro" required <?= $hayMotivos ? '' : 'disabled' ?>>
          <option value="" selected disabled>Seleccionar motivo</option>
          <?php foreach ($motivos as $motivo): ?>
            <option value="<?= (int) $motivo['id'] ?>">
              <?= htmlspecialchars($motivo['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!$hayMotivos): ?>
        <div class="form-text text-danger">No hay motivos de retiro configurados.</div>
      <?php else: ?>
        <div class="invalid-feedback">Seleccione el motivo.</div>
      <?php endif; ?>
    </div>

    <div class="mb-0">
      <label for="retiroObservaciones" class="label-sigde">Observaciones</label>
      <textarea class="form-control" id="retiroObservaciones" name="observaciones"
                rows="3" maxlength="500" placeholder="Detalle opcional de la solicitud"></textarea>
    </div>

    <div class="info-alert mt-3">
      <span class="info-alert__icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
      <div>
        <p class="info-alert__title">Queda pendiente de aprobación</p>
        <p class="info-alert__text">El retiro se registra como pendiente; el directivo lo aprueba o rechaza desde el listado.</p>
      </div>
    </div>
  </form>
  <?php
      $modalBody = ob_get_clean();

      ob_start();
  ?>
  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
  <button type="submit" form="formRegistrarRetiro" id="btnRegistrarRetiro" class="btn btn-primary"
          <?= $hayMotivos ? '' : 'disabled' ?>>
    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar retiro
  </button>
  <?php
      $modalFooter = ob_get_clean();

      $modalId       = 'registrarRetiroModal';
      $modalTitle    = 'Registrar Retiro';
      $modalSubtitle = 'Busque al estudiante, revise sus datos y luego indique el motivo.';
      include __DIR__ . '/../../components/modal.php';
      unset($modalBody, $modalFooter, $modalId, $modalTitle, $modalSubtitle, $hayMotivos);
  endif;

  // ---------- Modal: detalle de la solicitud ----------
  $modalId       = 'detalleRetiroModal';
  $modalTitle    = 'Detalle de Retiro';
  $modalSubtitle = 'Información de la solicitud seleccionada.';
  ob_start();
  ?>
<?php /* El cuerpo del modal lo arma el servidor (retiro/ver.php) y el JS lo vuelca
 dentro de #retiroDetalleCuerpo. Antes estos campos vivían en el markup con un
 id cada uno y los llenaba el navegador leyendo atributos data- del botón, que
 ya no lleva: quedaban todos en "—". Al pedirlo al servidor, el modal y la
 fila del listado no pueden mostrar dos versiones del mismo dato.*/ ?>
<div id="retiroDetalleCuerpo">
  <div class="text-center text-support py-4">
    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    Cargando...
  </div>
</div>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../../components/modal.php';

$pageModals = ob_get_clean();

require __DIR__ . '/../../layouts/app.php';