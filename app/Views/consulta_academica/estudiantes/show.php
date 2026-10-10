<?php
/**
 * Ficha de consulta del estudiante con información personal y académica disponible.
*/
$pageTitle       = 'Expediente del Estudiante';
$pageDescription = 'Información personal y académica del estudiante.';
$currentNav      = 'estudiantes';
$puedeEditar      = $puedeEditar ?? false;
$estudiante      = $estudiante ?? [];
$historialAcademico = $historialAcademico ?? [];
$nombre          = trim((string) ($estudiante['nombre'] ?? 'Estudiante'));
// Estado del ciclo: activo, retirado o egresado. Se distingue porque no es lo mismo
// haber salido antes de culminar que haber culminado los seis grados.
$estadoClave      = (string) ($estudiante['estado'] ?? 'activo');
$estado           = ['activo' => 'Activo', 'retirado' => 'Retirado', 'egresado' => 'Egresado'][$estadoClave] ?? 'Activo';
$documento       = trim(($estudiante['tipo_documento'] ?? '') . '-' . ($estudiante['numero_documento'] ?? ''), '-');
$nombres          = trim(($estudiante['primer_nombre'] ?? '') . ' ' . ($estudiante['segundo_nombre'] ?? ''));
$apellidos        = trim(($estudiante['primer_apellido'] ?? '') . ' ' . ($estudiante['segundo_apellido'] ?? ''));
$direccion        = trim(($estudiante['calle_avenida'] ?? '') . (($estudiante['nro_casa_apto'] ?? '') !== '' ? ' ' . $estudiante['nro_casa_apto'] : ''));
$fechaNacimiento  = !empty($estudiante['fecha_nacimiento']) ? date('d/m/Y', strtotime($estudiante['fecha_nacimiento'])) : '—';
/* El lugar de nacimiento se arma con los mismos dos niveles que el PDF: del estado y la
   parroquia salen el nombre y el municipio, que no se guarda porque se deduce. */
$lugarNacimiento = '';
if ((int) ($estudiante['id_estado_nacimiento'] ?? 0) > 0) {
    $partes = [];
    if ((int) ($estudiante['id_parroquia_nacimiento'] ?? 0) > 0) {
        $partes[] = (string) ($estudiante['parroquia_nacimiento'] ?? '');
        if ((string) ($estudiante['municipio_nacimiento'] ?? '') !== '') {
            $partes[] = 'Municipio ' . (string) $estudiante['municipio_nacimiento'];
        }
    }
    if ((string) ($estudiante['estado_nacimiento'] ?? '') !== '') {
        $partes[] = (string) $estudiante['estado_nacimiento'];
    }
    $lugarNacimiento = trim(implode(', ', array_filter($partes)));
}
$nacionalidad = trim((string) ($estudiante['nacionalidad'] ?? ''));
$sexo = match ($estudiante['sexo'] ?? '') {
    'F' => 'Femenino',
    'M' => 'Masculino',
    default => '—',
};
$anioId = (int) ($anioSeleccionado ?? 0);

$breadcrumbs = [
    ['label' => 'Gestión de estudiantes', 'href' => BASE_URL . 'estudiantes/index?anio=' . $anioId],
    ['label' => 'Expediente', 'href' => null],
];

ob_start();
?>
<section class="student-record__hero" aria-label="Resumen del estudiante">
  <?php
    $avatarSize     = 'xl';
    $avatarTone     = 'primary';
    $avatarSrc      = $estudiante['avatar_img'] ?? '';
    $avatarAlt      = $nombre;
    $avatarInitials = $estudiante['iniciales'] ?? 'ES';
    include __DIR__ . '/../../components/avatar.php';
  ?>
  <div class="student-record__identity">
    <h1 class="student-record__name"><?= htmlspecialchars($nombre) ?></h1>
    <div class="student-record__meta">
      <span><i class="bi bi-card-text" aria-hidden="true"></i><?= htmlspecialchars($documento ?: 'Documento no disponible') ?></span>
      <?php if (!empty($estudiante['anio_escolar'])): ?>
        <span><i class="bi bi-calendar3" aria-hidden="true"></i><?= htmlspecialchars($estudiante['anio_escolar']) ?></span>
      <?php endif; ?>
      <?php if (!empty($estudiante['grado']) || !empty($estudiante['seccion'])): ?>
        <span><i class="bi bi-book" aria-hidden="true"></i><?= htmlspecialchars(trim(($estudiante['grado'] ?? '') . ' · ' . ($estudiante['seccion'] ?? ''), ' ·')) ?></span>
      <?php endif; ?>
      <?php if (!empty($estudiante['turno'])): ?>
        <span><i class="bi bi-clock" aria-hidden="true"></i>Turno <?= htmlspecialchars($estudiante['turno']) ?></span>
      <?php endif; ?>
    </div>
  </div>
  <div class="student-record__hero-actions">
    <?php
      $badgeLabel  = $estado;
      $badgeStatus = ['activo' => 'active', 'retirado' => 'inactive', 'egresado' => 'info'][$estadoClave] ?? 'active';
      include __DIR__ . '/../../components/status-badge.php';
    ?>
      <button type="button" class="btn btn-sm btn-light"
              data-bs-toggle="modal" data-bs-target="#modalVistaPreviaExpediente">
      <i class="bi bi-printer me-1" aria-hidden="true"></i>Exportar ficha
    </button>
    <a href="<?= BASE_URL ?>estudiantes/index?anio=<?= $anioId ?>" class="btn btn-sm btn-light">
      <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver
    </a>
      </div>
    </section>

<?php /* Los recaudos se cuentan aquí y no en su sección porque la etiqueta de la pestaña
 necesita el número para mostrarlo. La sección está en "Información Adicional", que es
 la segunda pestaña y no la que se abre al entrar: sin este contador, quien busca un
 documento faltante tiene que recorrer las pestañas para descubrir que existe.*/ ?>
<?php
  $recaudos = $recaudos ?? [];
  $recaudosEntregados = 0;
  $recaudosPendientes  = [];
  foreach ($recaudos as $r) {
      if (!empty($r['consignado'])) {
          $recaudosEntregados++;
      } else {
          $recaudosPendientes[] = $r['nombre'];
      }
  }
  $totalRecaudos = count($recaudos);
?>
<ul class="nav nav-tabs student-record__tabs" role="tablist" aria-label="Secciones del expediente">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="student-tab-personales" data-bs-toggle="tab"
            data-bs-target="#student-pane-personales" type="button" role="tab"
            aria-controls="student-pane-personales" aria-selected="true">
      Datos Personales
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="student-tab-adicional" data-bs-toggle="tab"
            data-bs-target="#student-pane-adicional" type="button" role="tab"
            aria-controls="student-pane-adicional" aria-selected="false">
      Información Adicional
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="student-tab-familiares" data-bs-toggle="tab"
            data-bs-target="#student-pane-familiares" type="button" role="tab"
            aria-controls="student-pane-familiares" aria-selected="false">
      Familiares
    </button>
  </li>
</ul>

<div class="row g-4 align-items-start student-record__layout">
  <div class="col-lg-8">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="student-pane-personales" role="tabpanel"
           aria-labelledby="student-tab-personales" tabindex="0">
    <section class="content-card student-record__section" id="datosPersonales" aria-labelledby="datosPersonalesHeading">
      <div class="content-card__header">
        <h2 class="content-card__title" id="datosPersonalesHeading">
          <i class="bi bi-person-vcard me-2" aria-hidden="true"></i>Información básica
        </h2>
        <?php if ($puedeEditar): ?>
          <button type="button" class="btn btn-sm btn-outline-primary student-record__edit"
                  data-id="<?= (int) ($estudiante['id'] ?? 0) ?>" data-tab="pane-personales">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar expediente
          </button>
        <?php endif; ?>
      </div>
      <div class="student-record__fields">
        <div class="student-record__field">
          <span class="label-sigde">Nombres</span>
          <strong><?= htmlspecialchars($nombres ?: '—') ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Apellidos</span>
          <strong><?= htmlspecialchars($apellidos ?: '—') ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Cédula de identidad</span>
          <strong><?= htmlspecialchars($documento ?: '—') ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Fecha de nacimiento</span>
          <strong><?= htmlspecialchars($fechaNacimiento) ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Lugar de nacimiento</span>
          <strong><?= htmlspecialchars($lugarNacimiento ?: '—') ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Nacionalidad</span>
          <strong><?= htmlspecialchars($nacionalidad ?: '—') ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Sexo</span>
          <strong><?= htmlspecialchars($sexo) ?></strong>
        </div>
        <div class="student-record__field">
          <span class="label-sigde">Estado</span>
          <strong><?= htmlspecialchars($estado) ?></strong>
        </div>
      </div>
    </section>
    <button type="button"
            class="student-record__contact-shortcut<?= $puedeEditar ? ' student-record__edit' : '' ?>"
            data-id="<?= (int) ($estudiante['id'] ?? 0) ?>"
            data-tab="pane-familiares"
            <?= $puedeEditar ? 'data-edit-representante="1"' : 'data-bs-toggle="tab" data-bs-target="#student-pane-familiares" role="tab"' ?>>
      <span class="student-record__contact-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
      <span class="student-record__contact-label">
        <i class="bi bi-telephone me-1" aria-hidden="true"></i>Dirección y teléfono
      </span>
      <span class="student-record__contact-action">
        <?= $puedeEditar ? 'Ver/Editar en Familiar' : 'Ver en Familiares' ?>
        <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </span>
    </button>
      </div>

      <div class="tab-pane fade" id="student-pane-adicional" role="tabpanel"
           aria-labelledby="student-tab-adicional" tabindex="0">
    <section class="content-card student-record__section mt-4" id="informacionAdicional" aria-labelledby="informacionAdicionalHeading">
      <div class="content-card__header">
        <h2 class="content-card__title" id="informacionAdicionalHeading">
          <i class="bi bi-rulers me-2" aria-hidden="true"></i>Talla y salud
        </h2>
      </div>
      <div class="student-record__subsection">
        <h3><i class="bi bi-person-standing me-2" aria-hidden="true"></i>Talla uniforme</h3>
        <div class="student-record__fields">
          <div class="student-record__field">
            <span class="label-sigde">Camisa</span>
            <strong><?= htmlspecialchars($datosEditables['talla_camisa'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Pantalón</span>
            <strong><?= htmlspecialchars($datosEditables['talla_pantalon'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Calzado</span>
            <strong><?= htmlspecialchars($datosEditables['talla_zapato'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field student-record__field--wide">
            <span class="label-sigde">Observaciones de talla</span>
            <strong><?= htmlspecialchars($datosEditables['talla_observaciones'] ?: '—') ?></strong>
          </div>
        </div>
      </div>
      <div class="student-record__subsection">
        <h3><i class="bi bi-heart-pulse me-2" aria-hidden="true"></i>Ficha médica</h3>
        <div class="student-record__fields">
          <div class="student-record__field">
            <span class="label-sigde">Tipo de sangre</span>
            <strong><?= htmlspecialchars($datosEditables['tipo_sangre'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Alergias</span>
            <strong><?= htmlspecialchars($datosEditables['alergias'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Medicamentos</span>
            <strong><?= htmlspecialchars($datosEditables['medicamentos'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field">
            <span class="label-sigde">Enfermedades</span>
            <strong><?= htmlspecialchars($datosEditables['enfermedades'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field student-record__field--wide">
            <span class="label-sigde">Condiciones especiales</span>
            <strong><?= htmlspecialchars($datosEditables['condiciones_especiales'] ?: '—') ?></strong>
          </div>
          <div class="student-record__field student-record__field--wide">
            <span class="label-sigde">Observaciones médicas</span>
            <strong><?= htmlspecialchars($datosEditables['observaciones_medicas'] ?: '—') ?></strong>
          </div>
        </div>
      </div>

      <?php /* Solo lectura. La EDICIÓN de los recaudos es una pestaña del modal "Actualizar
 expediente" (el mismo que usa la ratificación); aquí solo se informa, y sin
 botón, porque quien llega hasta aquí puede ser un docente o un coordinador que
 consulta y no tiene por qué poder alterar documentos de matrícula.*/ ?>
      <div class="student-record__subsection">
        <h3><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Recaudos</h3>
        <?php
          $recaudosEstudiante = (int) ($estudiante['id'] ?? 0);
          $recaudosEditable   = false;
          $recaudosContenedor = 'recaudosFicha';
          require __DIR__ . '/../../gestion_estudiante/inscripcion/partials/recaudos-panel.php';
        ?>
      </div>
    </section>
      </div>

      <div class="tab-pane fade" id="student-pane-familiares" role="tabpanel"
           aria-labelledby="student-tab-familiares" tabindex="0">
<section class="content-card student-record__section mt-4" id="familiares"
             aria-labelledby="familiaresHeading">
      <div class="content-card__header">
        <h2 class="content-card__title" id="familiaresHeading">
          <i class="bi bi-people me-2" aria-hidden="true"></i>Familiares
        </h2>
        <?php if ($familiaresNucleo): ?>
          <span class="status-badge status-badge--active">
            <?= count($familiaresNucleo) ?> en el núcleo familiar
          </span>
        <?php endif; ?>
      </div>

      <?php /* ── Bloque A · NÚCLEO FAMILIAR Solo quien tiene parentesco de sangre o civil, más el representante
 legal aunque su vínculo esté registrado como "Otro" (es representante
 por ley, no por sangre). Quien solo tiene un vínculo logístico —el
 transporte, el vecino de confianza— queda en el Bloque B: mezclarlo acá
 lo haría parecer pariente, y es la confusión que esta separación
 evita.*/ ?>
      <?php if ($familiaresNucleo): ?>
        <div class="student-record__family-list">
          <?php foreach ($familiaresNucleo as $familiar): ?>
            <?php
              $nombreFamiliar = trim(implode(' ', array_filter([
                  $familiar['primer_nombre'] ?? '',
                  $familiar['segundo_nombre'] ?? '',
                  $familiar['primer_apellido'] ?? '',
                  $familiar['segundo_apellido'] ?? '',
              ])));
              $esRepresentante = !empty($familiar['representante_principal']);
            ?>
            <article class="student-record__family">
              <span class="student-record__family-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
              <div class="student-record__family-details">
                <strong><?= htmlspecialchars($nombreFamiliar ?: 'Familiar') ?></strong>
                <span><?= htmlspecialchars($familiar['parentesco'] ?: 'Parentesco no indicado') ?></span>
                <?php /* La nacionalidad se escribe aunque venga vacía, sin texto de
                       relleno: una celda en blanco dice "falta el dato" y un "—"
                       sería indistinguible de las que sí lo tienen. */ ?>
                <?php if (($familiar['nacionalidad'] ?? '') !== ''): ?>
                  <span><?= htmlspecialchars((string) $familiar['nacionalidad']) ?></span>
                <?php endif; ?>
              </div>
              <?php if ($esRepresentante): ?>
                <span class="badge text-bg-info">
                  <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Representante legal
                </span>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="content-card__empty">
          <i class="bi bi-people" aria-hidden="true"></i>
          <p>No hay familiares registrados en el expediente.</p>
        </div>
      <?php endif; ?>
    </section>

<?php /* Bloque B · AUTORIZADOS PARA RETIRAR. Mismo componente y mismo aspecto que el Bloque A: misma
 tarjeta content-card, misma lista y mismas filas, mismo círculo de avatar y mismo badge.
 Antes esta tarjeta se distinguía con un borde azul grueso y fondos celestes, y eso rompía
 la lectura: el mismo componente se veía de dos maneras distintas. La separación la da el
 título y el contenido, no el color. Lo único que cambia es la columna de condición: si esa
 persona es también familiar o solo está autorizada por confianza.*/ ?>
    <section class="content-card student-record__section mt-4" id="autorizadosRetiro"
             aria-labelledby="autorizadosRetiroHeading">
      <div class="content-card__header">
        <h2 class="content-card__title" id="autorizadosRetiroHeading">
          <i class="bi bi-people me-2" aria-hidden="true"></i>Autorizados para retirar al alumno
        </h2>
        <?php if ($autorizadosParaRetiro): ?>
          <span class="status-badge status-badge--active">
            <?= count($autorizadosParaRetiro) ?> autorizado<?= count($autorizadosParaRetiro) === 1 ? '' : 's' ?>
          </span>
        <?php endif; ?>
      </div>

      <?php if ($autorizadosParaRetiro): ?>
        <p class="text-support small mb-3">
          <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
          Estas personas pueden retirar al alumno del plantel. No todas son familiares:
          aquí puede haber quien esté autorizado solo por confianza, como el transporte escolar.
        </p>

        <div class="student-record__family-list">
          <?php foreach ($autorizadosParaRetiro as $autorizado): ?>
            <?php
              $nombreAutorizado = trim(implode(' ', array_filter([
                  $autorizado['primer_nombre'] ?? '',
                  $autorizado['segundo_nombre'] ?? '',
                  $autorizado['primer_apellido'] ?? '',
                  $autorizado['segundo_apellido'] ?? '',
              ])));
              $cedula = trim(($autorizado['tipo_documento'] ?? '') . '-' . ($autorizado['numero_documento'] ?? ''), '-');
              $telefono = trim(
                  ($autorizado['contacto']['codigo'] ?? '') . ' ' . ($autorizado['contacto']['numero'] ?? '')
              );
              /* Sin parentesco no es un error: es justo el caso de quien está
 autorizado y no es familia (transporte, vecino de confianza).*/
              $vinculo = trim((string) ($autorizado['parentesco'] ?? ''));
              $vinculo = $vinculo !== '' ? $vinculo : 'Autorizado (sin parentesco)';
              $esTambienFamiliar = !empty($autorizado['representante_principal'])
                  || (($autorizado['parentesco'] ?? '') !== '' && ($autorizado['parentesco'] ?? '') !== 'Otro');
            ?>
            <article class="student-record__family">
              <span class="student-record__family-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
              <div class="student-record__family-details">
                <strong><?= htmlspecialchars($nombreAutorizado ?: 'Autorizado') ?></strong>
                <span><?= htmlspecialchars($vinculo) ?></span>
                <?php if ($cedula !== ''): ?>
                  <span><?= htmlspecialchars($cedula) ?></span>
                <?php endif; ?>
                <?php if ($telefono !== ''): ?>
                  <span><?= htmlspecialchars($telefono) ?></span>
                <?php endif; ?>
                <?php /* Va después del teléfono: es el dato menos consultado de los
                       cuatro, y el orden mantiene arriba lo que se lee primero. */ ?>
                <?php if (($autorizado['nacionalidad'] ?? '') !== ''): ?>
                  <span><?= htmlspecialchars((string) $autorizado['nacionalidad']) ?></span>
                <?php endif; ?>
              </div>
              <span class="badge text-bg-info">
                <?php if ($esTambienFamiliar): ?>
                  También familiar
                <?php else: ?>
                  Solo autorizado
                <?php endif; ?>
              </span>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="content-card__empty">
          <i class="bi bi-people" aria-hidden="true"></i>
          <p>Nadie está autorizado a retirar a este alumno.</p>
        </div>
      <?php endif; ?>
    </section>
      </div>

    </div>
  </div>

  <aside class="col-lg-4">
    <section class="content-card student-record__history" aria-labelledby="historialHeading">
      <div class="content-card__header">
        <h2 class="content-card__title" id="historialHeading">
          <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historial académico
        </h2>
      </div>
      <?php if ($historialAcademico): ?>
        <ol class="student-record__timeline">
          <?php foreach ($historialAcademico as $registro): ?>
            <?php $esActual = ($registro['estado_anio'] ?? '') === 'ACTIVO'; ?>
            <li class="student-record__timeline-item<?= $esActual ? ' is-current' : '' ?>">
              <span class="student-record__timeline-dot" aria-hidden="true"></span>
              <div class="student-record__timeline-card">
                <span class="student-record__timeline-state"><?= $esActual ? 'Actual' : htmlspecialchars(ucfirst(strtolower($registro['estado_anio'] ?? 'Histórico'))) ?></span>
                <strong><?= htmlspecialchars($registro['anio_escolar'] ?? 'Año no disponible') ?></strong>
                <span><?= htmlspecialchars(trim(($registro['grado'] ?? '') . ' · ' . ($registro['seccion'] ?? ''), ' ·') ?: 'Sección no disponible') ?></span>
                <?php if (!empty($registro['turno'])): ?>
                  <span>Turno <?= htmlspecialchars($registro['turno']) ?></span>
                <?php endif; ?>
                <small><?= htmlspecialchars(ucfirst(strtolower($registro['tipo_matricula'] ?? 'Matrícula'))) ?></small>
                <?php /* El motivo del salto de grado viaja con la fila de la matrícula. Se
 muestra en el historial para que un salto de 1er a 5to no aparezca
 como un hecho sin explicación.*/ ?>
                <?php if (!empty($registro['motivo_anticipo'])): ?>
                  <span class="text-support small">Justificación del salto: <?= htmlspecialchars($registro['motivo_anticipo']) ?></span>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="content-card__empty">
          <i class="bi bi-journal-x" aria-hidden="true"></i>
          <p>No hay historial académico disponible para este turno.</p>
        </div>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php
$pageContent = ob_get_clean();

$extraScripts = $extraScripts ?? [];
if ($puedeEditar) {
    $extraScripts = ['/js/familiares.js', '/js/ratificacion.js'];
    ob_start();
    require __DIR__ . '/../../gestion_estudiante/ratificacion/modals/editar-estudiante-modal.php';
    $pageModals = ob_get_clean();

    /* La edición de recaudos es una pestaña del modal "Actualizar expediente", el mismo que
 usa la ratificación. Aquí solo se necesita el script para pintar los pendientes que se
 leen al abrir el expediente.*/
    $extraScripts[] = '/js/recaudos.js';
}
$extraScripts[] = '/js/student-record.js';

$urlPdf = BASE_URL . 'estudiantes/exportar/' . (int) ($estudiante['id'] ?? 0) . '?anio=' . $anioId;
ob_start();
?>
<div class="student-record__pdf-preview">
  <iframe data-src="<?= htmlspecialchars($urlPdf) ?>" title="Vista previa del expediente estudiantil"></iframe>
</div>
<?php
$modalBody = ob_get_clean();

ob_start();
?>
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar vista previa</button>
<a href="<?= htmlspecialchars($urlPdf . '&descargar=1') ?>"
   class="btn btn-primary" target="_blank" rel="noopener">
  <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar PDF
</a>
<?php
$modalFooter = ob_get_clean();
$modalId = 'modalVistaPreviaExpediente';
$modalTitle = 'Vista previa del expediente';
$modalSubtitle = 'Revise el documento antes de descargarlo.';
$modalSize = 'xl';
$modalCentered = false;
ob_start();
require __DIR__ . '/../../components/modal.php';
$pageModals = ($pageModals ?? '') . ob_get_clean();

require __DIR__ . '/../../layouts/app.php';
