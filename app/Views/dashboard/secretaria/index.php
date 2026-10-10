<?php
/**
 * Vista: dashboard/secretaria/index.php
 * Panel de secretaría.
 *
 * Por ahora solo lleva el aviso de configuración académica. Es lo que más falta le
 * hace a este rol: no puede crear estructura (esa pantalla es del directivo), así que
 * si le falta una combinación se entera al final del formulario de inscripción, con un
 * mensaje que no deja claro que lo que falta es una tarea pendiente y no un dato
 * mal escrito.
 *
 * Variables desde DashboardController::secretaria():
 * - $estructura: AcademicStructureModel::pendientesDeConfiguracion() o null si falló
 * la consulta. Con null NO se muestra el aviso: un panel sin el cartel es mejor que
 * un dashboard entero en blanco por un error de conexión.
*/
$pageTitle       = 'Dashboard';
$currentNav      = 'dashboard';
$breadcrumbs     = [
    ['label' => 'Panel', 'href' => BASE_URL . 'dashboard/secretaria'],
    ['label' => 'Dashboard'],
];

$estructura = $estructura ?? null;

/* Solo hay algo que avisar si el cálculo corrió Y hay algo pendiente. Un panel verde
 con "todo en orden" cada día sería ruido: lo que importa es el problema.*/
$hayAnioAbierto = $estructura !== null && $estructura['hay_anio_activo'] === true;
$pendientes     = $hayAnioAbierto ? (int) $estructura['pendientes'] : 0;
$mostrarAviso   = $estructura !== null && (!$hayAnioAbierto || $pendientes > 0);

ob_start();
$pageHeading    = 'Panel de Secretaría';
$pageSubheading = 'Visión general de estudiantes, trámites activos y reportes escolares.';
include __DIR__ . '/../../components/page-header.php';
?>

<?php if ($mostrarAviso): ?>
  <?php /* Alerta de configuración. El tono depende de la gravedad: si no hay año
 abierto es un bloqueante total (no se puede matricular nada), y si lo hay pero
 faltan combinaciones es una advertencia.*/ ?>
  <?php $esBloqueante = !$hayAnioAbierto; ?>
  <div class="alert alert-<?= $esBloqueante ? 'danger' : 'warning' ?> d-flex align-items-start gap-3"
       role="alert">
    <i class="bi bi-<?= $esBloqueante ? 'x-octagon' : 'exclamation-triangle' ?> fs-5 flex-shrink-0"
       aria-hidden="true"></i>
    <div class="flex-grow-1">
      <?php if (!$hayAnioAbierto): ?>
        <div class="fw-semibold mb-1">No hay un año escolar abierto</div>
        <p class="mb-1">
          Mientras ningún período esté en curso no se pueden registrar inscripciones ni
          ratificaciones. Avise al directivo para que abra el período en
          Configuración → Años escolares.
        </p>
      <?php else: ?>
        <?php /* "combinación" + "es" daría "combinaciónes"; el plural correcto lleva la
 s al lado de la n. Se calcula en variables para no repetir la regla en
 el título y en el texto.*/ ?>
        <?php $s = $pendientes === 1 ? '' : 's'; ?>
        <div class="fw-semibold mb-1">
          Falta configurar la estructura académica: <?= $pendientes ?>
          combinación<?= $s ?> pendiente<?= $s ?> en <?= htmlspecialchars($estructura['anio']) ?>
        </div>
        <p class="mb-1">
          <?php /* Se muestra la aritmética y no solo el total. "9 pendientes" suena a
 nine tareas concretas; en realidad son 6 grados por 2 turnos menos las
 3 combinaciones ya registradas, y quien lo lee puede comprobarlo y
 discutirlo si el supuesto no aplica en su colegio.*/ ?>
          Están registradas <?= (int) $estructura['registradas'] ?> de las
          <?= (int) $estructura['esperadas'] ?> que se esperarían
          (<?= (int) $estructura['grados'] ?> grados × <?= (int) $estructura['turnos'] ?> turnos).
          La estructura la configura el directivo; usted no puede crearla ni editarla.
        </p>
        <?php if (!empty($estructura['grados_sin_seccion'])): ?>
          <p class="mb-1">
            <strong>Sin ninguna sección en <?= htmlspecialchars($estructura['anio']) ?>:</strong>
            <?= htmlspecialchars(implode(', ', $estructura['grados_sin_seccion'])) ?>.
            Un alumno que venga de un grado anterior no podrá ratificarse a uno de esos,
            porque la combinación no existe.
          </p>
        <?php endif; ?>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>inscripciones/index" class="btn btn-sm btn-outline-light mt-1">
        <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Ir a Inscripción
      </a>
    </div>
  </div>
<?php endif; ?>

<section class="content-card">
  <div class="content-card__empty">
    <i class="bi bi-journal-text" aria-hidden="true"></i>
    <p>La información operativa se vinculará automáticamente al activar el módulo académico.</p>
  </div>
</section>
<?php
$pageContent = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';