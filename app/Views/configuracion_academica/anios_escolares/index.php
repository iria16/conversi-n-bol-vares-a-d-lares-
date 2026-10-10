<?php
$pageTitle = 'Gestión de Años Escolares';
$pageDescription = 'Administre los períodos académicos, fechas de inicio y cierres institucionales.';
$currentNav = 'anios-escolares';
$extraScripts = ['/js/academic-years.js'];

$breadcrumbs = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Años escolares', 'href' => null],
];

$stats = $stats ?? [
    'total' => 0,
    'activos' => 0,
    'planificados' => 0,
    'cerrados' => 0,
    'actual' => 'Sin período activo',
];
$anios = $anios ?? [];
$paginacion = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

  // ---------- Page header ----------
  ob_start();
  ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevoAnioModal">
    <i class="bi bi-plus-lg"></i> Nuevo Año Escolar
  </button>
  <?php
  $pageHeaderActions = ob_get_clean();
  $pageHeading       = 'Gestión de Años Escolares';
  $pageSubheading    = $pageDescription;
  include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Período activo';
      $statValue     = $stats['actual'];
      $statIcon      = 'bi-check-circle';
      $statTone      = 'success';
      $statTrend     = 'Activo';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Planificados';
      $statValue     = (int) $stats['planificados'];
      $statIcon      = 'bi-calendar2-week';
      $statTone      = 'warning';
      $statTrend     = 'Por iniciar';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Cerrados';
      $statValue     = (int) $stats['cerrados'];
      $statIcon      = 'bi-archive';
      $statTone      = 'light';
      $statTrend     = 'Históricos';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
</div>

<?php
  // ---------- Data panel: filtros ----------
  $panelFormAction = BASE_URL . 'academicYear/index';

  ob_start();
  ?>
  <label for="filtroEstado" class="visually-hidden">Estado</label>
  <select id="filtroEstado" name="estado" class="form-select" aria-label="Filtrar por estado">
    <option value="" <?= empty($_GET['estado']) ? 'selected' : '' ?>>Estado (Todos)</option>
    <option value="planificado" <?= ($_GET['estado'] ?? '') === 'planificado' ? 'selected' : '' ?>>Planificado</option>
    <option value="activo"      <?= ($_GET['estado'] ?? '') === 'activo'      ? 'selected' : '' ?>>Activo</option>
    <option value="cerrado"     <?= ($_GET['estado'] ?? '') === 'cerrado'     ? 'selected' : '' ?>>Cerrado</option>
  </select>
  <?php
  $panelFilters           = ob_get_clean();
  $panelSearchName        = 'q';
  $panelSearchValue       = $_GET['q'] ?? '';
  $panelSearchPlaceholder = 'Buscar por año o estado...';

  // ---------- Data panel: colgroup ----------
  // Anchos fijos por columna: Período / Inicio / Cierre / Estado / Acciones.
  ob_start();
  ?>
  <colgroup>
    <col style="width: 25%;">
    <col style="width: 20%;">
    <col style="width: 20%;">
    <col style="width: 17%;">
    <col style="width: 18%;">
  </colgroup>
  <?php
  $panelColgroup = ob_get_clean();

  // ---------- Data panel: thead ----------
  ob_start();
  ?>
  <tr>
    <th scope="col">Período escolar</th>
    <th scope="col">Fecha inicio</th>
    <th scope="col">Fecha cierre</th>
    <th scope="col">Estado</th>
    <th scope="col" class="text-center">Acciones</th>
  </tr>
  <?php
  $panelTableHead = ob_get_clean();

  // ---------- Data panel: tbody ----------
  ob_start();
  foreach ($anios as $anio):
    $estado = $anio['estado'];
    $activo = $estado === 'activo';
    $planificado = $estado === 'planificado';
    $cerrado = $estado === 'cerrado';
    $estadoLabel = $planificado ? 'Planificado' : ($activo ? 'Activo' : 'Cerrado');
    $badgeStatus = $planificado ? 'pending' : ($activo ? 'active' : 'inactive');
  ?>
    <tr class="<?= $cerrado ? 'is-muted' : '' ?>">
      <td><strong><?= htmlspecialchars($anio['nombre']) ?></strong></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_inicio']))) ?></td>
      <td><?= htmlspecialchars(date('d/m/Y', strtotime($anio['fecha_cierre']))) ?></td>
      <td>
        <?php
          $badgeLabel = $estadoLabel;
          include __DIR__ . '/../../components/status-badge.php';
        ?>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button" class="action-btn ver-anio"
            data-id="<?= (int) $anio['id'] ?>"
            data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
            data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>"
            data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>"
            data-estado="<?= htmlspecialchars($estadoLabel) ?>"
            data-iniciado-en="<?= htmlspecialchars((string) ($anio['iniciado_en'] ?? '')) ?>"
            data-iniciado-por="<?= htmlspecialchars((string) ($anio['iniciado_por'] ?? '')) ?>"
            data-cerrado-en="<?= htmlspecialchars((string) ($anio['cerrado_en'] ?? '')) ?>"
            data-cerrado-por="<?= htmlspecialchars((string) ($anio['cerrado_por'] ?? '')) ?>"
            data-reabierto-por="<?= htmlspecialchars((string) ($anio['reapierto_por'] ?? '')) ?>"
            data-reapertura="<?= htmlspecialchars((string) ($anio['motivo_reapertura'] ?? '')) ?>"
            title="Ver año escolar" aria-label="Ver <?= htmlspecialchars($anio['nombre']) ?>">
            <i class="bi bi-eye"></i>
          </button>
          <?php if (!$cerrado): ?>
            <button type="button" class="action-btn action-btn--edit editar-anio"
              data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              data-inicio="<?= htmlspecialchars($anio['fecha_inicio']) ?>"
              data-cierre="<?= htmlspecialchars($anio['fecha_cierre']) ?>"
              title="Editar año escolar" aria-label="Editar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-pencil"></i>
            </button>
          <?php endif; ?>
          <?php if ($planificado): ?>
            <button type="button" class="action-btn iniciar-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Iniciar año escolar" aria-label="Iniciar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-play-circle"></i>
            </button>
          <?php elseif ($activo): ?>
            <button type="button" class="action-btn action-btn--danger cerrar-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Cerrar año escolar" aria-label="Cerrar <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-lock"></i>
            </button>
          <?php elseif ($cerrado && \function_exists('App\Traits\sigde_rol_clave') && \App\Traits\sigde_rol_clave($_SESSION['rol'] ?? '') === 'directivo'): ?>
            <?php /* Botón de reapertura: solo la directiva, igual que la ruta. Se comprueba
 también en el servidor, esto solo evita mostrar una acción que va a fallar.*/ ?>
            <button type="button" class="action-btn reabrir-anio" data-id="<?= (int) $anio['id'] ?>"
              data-nombre="<?= htmlspecialchars($anio['nombre']) ?>"
              title="Reabrir año escolar (requiere motivo)" aria-label="Reabrir <?= htmlspecialchars($anio['nombre']) ?>">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$anios): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron años escolares.
      </td>
    </tr>
  <?php endif; ?>
  <?php
  $panelTableBody = ob_get_clean();

  $panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
                . ' de ' . (int) $paginacion['total'] . ' períodos registrados';
  $panelPagination = [
      'pagina_actual' => $paginacion['pagina_actual'],
      'total_paginas' => $paginacion['total_paginas'],
  ];

  include __DIR__ . '/../../components/data-panel.php';
?>

<?php
$modalId = 'nuevoAnioModal';
$modalTitle = 'Nuevo Año Escolar';
$modalSubtitle = 'Registre un nuevo período académico institucional.';
ob_start();
?>
<form id="formAnioEscolar" novalidate>
  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Información del período</span>
    </div>
    <div class="wizard-summary__card">
      <div class="mb-3">
        <label for="anioNombre" class="label-sigde">Nombre del período</label>
        <input type="text" id="anioNombre" name="nombre" class="form-control" placeholder="Ej. 2024-2025" maxlength="20" required>
        <div class="invalid-feedback">Indique el nombre del período.</div>
      </div>
      <div class="row g-2">
        <div class="col-md-6">
          <label for="anioInicio" class="label-sigde">Fecha de inicio</label>
          <input type="date" id="anioInicio" name="fecha_inicio" class="form-control" required>
          <div class="invalid-feedback">Indique la fecha de inicio.</div>
        </div>
        <div class="col-md-6">
          <label for="anioCierre" class="label-sigde">Fecha de cierre</label>
          <input type="date" id="anioCierre" name="fecha_cierre" class="form-control" required>
          <div class="invalid-feedback">Indique la fecha de cierre.</div>
        </div>
      </div>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formAnioEscolar" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar período</button>';
include __DIR__ . '/../../components/modal.php';

$modalId = 'editarAnioModal';
$modalTitle = 'Editar Año Escolar';
$modalSubtitle = 'Actualice la información del período seleccionado.';
ob_start();
?>
<form id="formEditarAnio" novalidate>
  <input type="hidden" name="id" id="editarAnioId">
  <div class="mb-3">
    <label for="editarAnioNombre" class="label-sigde">Nombre del período</label>
    <input type="text" name="nombre" id="editarAnioNombre" class="form-control" maxlength="20" required>
  </div>
  <div class="row g-3">
    <div class="col-md-6">
      <label for="editarAnioInicio" class="label-sigde">Fecha de inicio</label>
      <input type="date" name="fecha_inicio" id="editarAnioInicio" class="form-control" required>
    </div>
    <div class="col-md-6">
      <label for="editarAnioCierre" class="label-sigde">Fecha de cierre</label>
      <input type="date" name="fecha_cierre" id="editarAnioCierre" class="form-control" required>
    </div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formEditarAnio" class="btn btn-primary">Guardar cambios</button>';
include __DIR__ . '/../../components/modal.php';

$modalId = 'verAnioModal';
$modalTitle = 'Información del Año Escolar';
$modalSubtitle = 'Consulta del período seleccionado.';
ob_start();
?>
<dl class="row mb-0">
  <dt class="col-sm-5">Período</dt><dd class="col-sm-7" id="verAnioNombre">—</dd>
  <dt class="col-sm-5">Estado</dt><dd class="col-sm-7" id="verAnioEstado">—</dd>
  <dt class="col-sm-5">Fecha de inicio</dt><dd class="col-sm-7" id="verAnioInicio">—</dd>
  <dt class="col-sm-5">Fecha de cierre institucional</dt><dd class="col-sm-7" id="verAnioCierre">—</dd>
  <?php /* Los tres bloques de abajo se OCULTAN si el dato no existe, en vez de mostrar un
 guion permanente. Un período activo no tiene "cerrado por" porque todavía no se ha
 cerrado, y un período abierto antes de que existiera el registro tampoco tiene
 "iniciado por": pintar cuatro filas con guion permanente es ruido, y además hace
 creer que la información existe y se perdió. El guion queda solo para los datos
 que el propio formulario de alta siempre tiene (nombre, fechas).*/ ?>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioIniciadoPorEtiqueta">Iniciado por</dt>
  <dd class="col-sm-7" id="verAnioIniciadoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioIniciadoEnEtiqueta">Iniciado el</dt>
  <dd class="col-sm-7" id="verAnioIniciadoEn" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioReabiertoPorEtiqueta">Reabierto por</dt>
  <dd class="col-sm-7" id="verAnioReabiertoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioReaperturaEtiqueta">Motivo de la reapertura</dt>
  <dd class="col-sm-7" id="verAnioReapertura" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioCerradoPorEtiqueta">Cerrado por</dt>
  <dd class="col-sm-7" id="verAnioCerradoPor" hidden></dd>
  <dt class="col-sm-5 anio-dato-etiqueta" id="verAnioCerradoEnEtiqueta">Cerrado el</dt>
  <dd class="col-sm-7" id="verAnioCerradoEn" hidden></dd>
</dl>
<?php
/* Si el período no tiene registro de inicio ni de cierre, se avisa una sola vez abajo en
 lugar de dejar cuatro filas vacías: así queda claro que el dato no se registró y no
 que la pantalla falló.*/ ?>
<p class="text-support small mb-0 mt-2" id="verAnioSinRegistro" hidden>
  <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
  Este período no tiene registrado quién lo inició ni quién lo cerró: se activó fuera del
  sistema, antes de que existiera ese registro.
</p>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>';
include __DIR__ . '/../../components/modal.php';

// ---------- Modal: reabrir un año escolar cerrado ----------
// El motivo es obligatorio y se muestra antes de enviar nada, porque la reapertura queda
// registrada con él: es la única explicación que quedará de por qué se deshizo un cierre.
if (\function_exists('App\Traits\sigde_rol_clave') && \App\Traits\sigde_rol_clave($_SESSION['rol'] ?? '') === 'directivo'):
$modalId = 'reabrirAnioModal';
$modalTitle = 'Reabrir Año Escolar';
$modalSubtitle = 'Solo es posible deshacer un cierre por error. La reapertura queda registrada con su nombre, la fecha y este motivo.';
ob_start();
?>
<form id="formReabrirAnio" class="reabrir-anio-form" novalidate>
  <input type="hidden" name="id" id="reabrirAnioId" value="">
  <div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
    Al reabrir, el período vuelve a ser el vigente y se podrán registrar inscripciones, ratificaciones,
    retiros y egresos. Las operaciones que se hicieron con el año cerrado no se deshacen.
  </div>
  <div class="mb-3">
    <label for="reabrirAnioNombre" class="form-label">Año escolar</label>
    <input type="text" id="reabrirAnioNombre" class="form-control" disabled value="">
  </div>
  <div class="mb-0">
    <label for="reabrirAnioMotivo" class="form-label">Motivo de la reapertura <span class="text-danger">*</span></label>
    <textarea class="form-control" id="reabrirAnioMotivo" name="motivo" rows="4" required
              maxlength="200" placeholder="Ej.: Se cerró por error antes de resolver las solicitudes de retiro del período."></textarea>
    <div class="form-text">Mínimo 10 caracteres. Quedará en la bitácora de auditoría.</div>
  </div>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="formReabrirAnio" class="btn btn-warning">Reabrir año escolar</button>';
include __DIR__ . '/../../components/modal.php';
endif;

$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';