<?php
/**
 * Vista: Gestión de Grados y Secciones (estructura académica)
 * Usa los componentes: page-header, stat-card, data-panel, modal (structure-modal.php)
*/

$pageTitle       = 'Gestión de Grados y Secciones';
$pageDescription = 'Administre la distribución de grados, secciones y turnos académicos.';
$currentNav      = 'estructura-academica';
$extraScripts    = ['/js/academic-structure.js'];
$breadcrumbs     = [
    ['label' => 'Configuración académica', 'href' => null],
    ['label' => 'Estructura académica',     'href' => null],
];

$stats       = $stats ?? ['total' => 0, 'grados' => 0, 'secciones' => 0, 'capacidad' => 0];
$estructuras = $estructuras ?? [];
$anios       = $anios ?? [];
$anioActivo  = $anioActivo ?? 'No hay un año escolar activo';
/* El año que se está mirando y si admite escritura. $anioEsLectura sale del
 controlador con el mismo criterio que aplican store() y update() (solo el ACTIVO se
 toca), para que la vista no tenga que deducirlo comparando estados por su cuenta.*/
$anioEsLectura = $anioEsLectura ?? true;
$anioConsultado = isset($filters['anio']) ? (int) $filters['anio'] : 0;
$grados      = $grados ?? [];
$secciones   = $secciones ?? [];
$turnos      = $turnos ?? [];
$paginacion  = $paginacion ?? [
    'desde' => 0, 'hasta' => 0, 'total' => 0, 'pagina_actual' => 1, 'total_paginas' => 1,
];

ob_start();

// ---------- Page header ----------
ob_start();
?>
<?php /* Un año CERRADO es historial: se consulta, no se escribe. El botón se esconde en
 vez de deshabilitarse para que no quede a la vista una acción que nunca va a
 funcionar. La misma condición la repite el servidor en store().*/ ?>
<?php if (!$anioEsLectura): ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuevaEstructuraModal">
    <i class="bi bi-plus-lg"></i> Nuevo Registro
  </button>
<?php endif; ?>
<?php
$pageHeaderActions = ob_get_clean();
$pageHeading       = 'Gestión de Grados y Secciones';
$pageSubheading    = $pageDescription;
include __DIR__ . '/../../components/page-header.php';
?>

<!-- ---------- Stat cards ---------- -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Grados y secciones';
      $statValue     = (int) $stats['total'];
      $statIcon      = 'bi-diagram-3';
      $statTone      = 'primary';
      $statTrend     = 'Configurados';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Secciones disponibles';
      $statValue     = (int) $stats['secciones'];
      $statIcon      = 'bi-collection';
      $statTone      = 'success';
      $statTrend     = 'Activos';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
  <div class="col-sm-6 col-lg-4">
    <?php
      $statLabel     = 'Cupos configurados';
      $statValue     = (int) $stats['capacidad'];
      $statIcon      = 'bi-people';
      $statTone      = 'warning';
      $statTrend     = 'Capacidad';
      $statTrendType = 'neutral';
      include __DIR__ . '/../../components/stat-card.php';
    ?>
  </div>
</div>

<?php
// ---------- Data panel: filtros ----------
$panelFormAction = BASE_URL . 'academicStructure/index';

ob_start();
?>
<?php /* El año se consulta y se elige. Antes era un texto fijo y además el controlador ignoraba
 cualquier ?anio= de la URL, así que la estructura de un período cerrado no se podía ver sin
 entrar a la base de datos. El select va DENTRO del form del data-panel para que lo recoja.*/ ?>
<label for="filtroAnio" class="visually-hidden">Año escolar</label>
<select id="filtroAnio" name="anio" class="form-select" style="width:auto" data-carga-completa
        aria-label="Año escolar a consultar"
        title="<?= $anioEsLectura ? 'Año cerrado: solo consulta.' : 'Año escolar a configurar.' ?>"
        onchange="this.form.submit()">
  <?php foreach ($anios as $anio): ?>
    <option value="<?= (int) $anio['id'] ?>"
            <?= (int) $anio['id'] === $anioConsultado ? 'selected' : '' ?>>
      <?= htmlspecialchars($anio['nombre'] . $anio['sufijo_estado']) ?>
    </option>
  <?php endforeach; ?>
</select>

<?php if ($anioEsLectura): ?>
  <?php /* Distintivo de solo lectura. Sin él, alguien puede creer que el pencil de
 una fila le va a dejar guardar, y se encuentra con un 409 al intentarlo.*/ ?>
  <div class="data-panel__active-year" role="status"
       aria-label="Año en solo consulta: <?= htmlspecialchars($anioActivo) ?>">
    <i class="bi bi-lock-fill" aria-hidden="true"></i>
    <strong><?= htmlspecialchars($anioActivo) ?></strong>
  </div>
<?php endif; ?>

<label for="filtroGrado" class="visually-hidden">Grado</label>
<select id="filtroGrado" name="grado" class="form-select" aria-label="Filtrar por grado">
  <option value="">Grado (Todos)</option>
  <?php foreach ($grados as $grado): ?>
    <option value="<?= (int) $grado['id'] ?>" <?= (string) ($filters['grado'] ?? '') === (string) $grado['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($grado['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroSeccion" class="visually-hidden">Sección</label>
<select id="filtroSeccion" name="seccion" class="form-select" aria-label="Filtrar por sección">
  <option value="">Sección (Todas)</option>
  <?php foreach ($secciones as $seccion): ?>
    <option value="<?= (int) $seccion['id'] ?>" <?= (string) ($filters['seccion'] ?? '') === (string) $seccion['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($seccion['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>

<label for="filtroTurno" class="visually-hidden">Turno</label>
<select id="filtroTurno" name="turno" class="form-select" aria-label="Filtrar por turno">
  <option value="">Turno (Todos)</option>
  <?php foreach ($turnos as $turno): ?>
    <option value="<?= (int) $turno['id'] ?>" <?= (string) ($filters['turno'] ?? '') === (string) $turno['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($turno['nombre']) ?>
    </option>
  <?php endforeach; ?>
</select>
<?php
$panelFilters           = ob_get_clean();
$panelSearchName        = 'q';
$panelSearchValue       = $filters['q'] ?? '';
$panelSearchPlaceholder = 'Buscar por grado o sección...';

// ---------- Data panel: colgroup ----------
// Anchos fijos: Grado / Sección / Turno / Año escolar / Matriculados / Capacidad / Acciones.
ob_start();
?>
<colgroup>
  <col style="width: 16%;">
  <col style="width: 14%;">
  <col style="width: 14%;">
  <col style="width: 18%;">
  <col style="width: 14%;">
  <col style="width: 10%;">
  <col style="width: 14%;">
</colgroup>
<?php
$panelColgroup = ob_get_clean();

// ---------- Data panel: thead ----------
ob_start();
?>
<tr>
  <th scope="col">Grado</th>
  <th scope="col">Sección</th>
  <th scope="col">Turno</th>
  <th scope="col">Año escolar</th>
  <th scope="col" class="text-center">Matriculados</th>
  <th scope="col" class="text-center">Capacidad</th>
  <th scope="col" class="text-center">Acciones</th>
</tr>
<?php
$panelTableHead = ob_get_clean();

// ---------- Data panel: tbody ----------
ob_start();
foreach ($estructuras as $estructura):
?>
  <tr>
    <td class="fw-semibold"><?= htmlspecialchars($estructura['grado']) ?></td>
    <td><?= htmlspecialchars($estructura['seccion']) ?></td>
    <td><?= htmlspecialchars($estructura['turno']) ?></td>
    <td><?= htmlspecialchars($estructura['anio']) ?></td>
    <?php
    /* Matriculados y capacidad van juntos porque la comparación es el dato: una sección
 con más alumnos que cupos es el error que hay que ver, y antes solo se mostraba la
 capacidad, así que el desborde no se veía en ningún lado.*/
    $matriculados = (int) $estructura['estudiantes'];
    $capacidad    = (int) $estructura['capacidad_maxima'];
    $sobrecupo    = $matriculados > $capacidad;
    ?>
    <td class="text-center fw-semibold <?= $sobrecupo ? 'text-danger' : '' ?>"
        title="<?= $sobrecupo ? 'La sección tiene más estudiantes que cupos' : 'Estudiantes matriculados' ?>">
      <?= $matriculados ?>
    </td>
    <td class="text-center"><?= $capacidad ?></td>
    <td class="text-center">
      <div class="data-panel__actions">
        <?php /* En un año cerrado no hay pencil. Se oculta y no se deshabilita: un
 botón atenuado invita a pulsarlo, y el servidor lo rechaza con 409.
 Antes de este filtro, además, ni siquiera había forma de abrir la
 edición, porque el listado siempre enseñaba el año activo.*/ ?>
        <?php if ($anioEsLectura): ?>
          <span class="action-btn" aria-hidden="true" title="Año cerrado: solo consulta">
            <i class="bi bi-lock-fill"></i>
          </span>
        <?php else: ?>
        <button type="button"
          class="action-btn action-btn--edit editar-estructura"
          data-id="<?= (int) $estructura['id'] ?>"
          data-anio="<?= (int) $estructura['id_anio_escolar'] ?>"
          data-grado="<?= (int) $estructura['id_grado'] ?>"
          data-seccion="<?= (int) $estructura['id_seccion'] ?>"
          data-turno="<?= (int) $estructura['id_turno'] ?>"
          data-capacidad="<?= (int) $estructura['capacidad_maxima'] ?>"
          /* El número de ocupados también viaja en el botón: es el dato que el modal
 necesita para poder avisar "esta acción afectará a N alumnos" sin tener
 que pedirlo por AJAX en el momento en que se abre. El backend lo vuelve a
 calcular al confirmar, porque entre abrir y guardar alguien puede
 haberse matriculado.*/
          data-ocupados="<?= (int) $estructura['estudiantes'] ?>"
          title="Editar estructura">
          <i class="bi bi-pencil"></i>
        </button>
        <?php endif; ?>
      </div>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$estructuras): ?>
  <tr>
    <td colspan="7" class="text-center text-support py-4">
      No se encontraron estructuras académicas.
    </td>
  </tr>
<?php endif; ?>
<?php
$panelTableBody = ob_get_clean();

$panelSummary = 'Mostrando ' . (int) $paginacion['desde'] . ' a ' . (int) $paginacion['hasta']
              . ' de ' . (int) $paginacion['total'] . ' registros';
$panelPagination = [
    'pagina_actual' => $paginacion['pagina_actual'],
    'total_paginas' => $paginacion['total_paginas'],
];

include __DIR__ . '/../../components/data-panel.php';
?>

<?php
// ---------- Modales (crear / editar) ----------
$modalId       = 'nuevaEstructuraModal';
$modalTitle    = 'Nuevo Registro';
$modalSubtitle = 'Configure una nueva sección académica.';
$formId        = 'formNuevaEstructura';
$editMode      = false;
include __DIR__ . '/structure-modal.php';

$modalId       = 'editarEstructuraModal';
$modalTitle    = 'Editar Registro';
$modalSubtitle = 'Actualice la configuración académica.';
$formId        = 'formEditarEstructura';
$editMode      = true;
include __DIR__ . '/structure-modal.php';

$pageContent = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';