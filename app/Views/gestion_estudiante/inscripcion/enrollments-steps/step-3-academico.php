<?php
/**
 * Paso 3 - Información académica.
 *
 * Variables del controlador: $academico, que trae
 *   ['anio' => ['id'=>..,'nombre'=>..],
 *    'grados'    => [ ['id','nombre','libres','total'] ],
 *    'secciones' => [ ['id','nombre','libres','total'] ],
 *    'turnos'    => [ ['id','nombre'] ],
 *    'combinaciones' => [ ['id_grado','id_seccion','id_turno','ocupados',
 *                          'capacidad_maxima','disponibles','lleno', …] ]]
 *
 * ── Por qué NO salen de los catálogos sueltos ──────────────────────────────
 * Las opciones vienen de lo registrado en Estructura Académica para el año
 * escolar activo. Si una combinación no está registrada, esa sección no existe
 * para ese periodo, y ofrecerla hace que la secretaria descubra el problema con
 * un error al final del wizard, después de haber escrito todo lo demás.
 *
 * ── Las claves de los <option> ─────────────────────────────────────────────
 * Sección y turno se repiten entre grados ("A" existe en todos), así que no
 * alcanza con filtrar por su id: cada opción lleva la combinación completa como
 * clave compuesta, "grado-seccion" y "grado-seccion-turno", y el navegador
 * filtra comparando ese prefijo (SIGDEUI.filtrarOpciones con
 * data-id-combinacion). Con eso, elegir "2do Grado" deja solo las secciones y
 * turnos que de verdad están registrados para ese grado.
 *
 * El cupo va en el TURNO porque es el último nivel: ahí la combinación ya está
 * completa. El que está lleno se ve deshabilitado y con el número, para que se
 * vea que la sección existe pero no hay puesto.
 */
$academico = $academico ?? [
    'anio'          => ['id' => '', 'nombre' => ''],
    'grados'        => [],
    'secciones'     => [],
    'turnos'        => [],
    'combinaciones' => [],
];
$anioActivo    = $academico['anio'];
$grados        = $academico['grados'];
$secciones     = $academico['secciones'];
$turnos        = $academico['turnos'];
$combinaciones = $academico['combinaciones'];

/* Para el JavaScript: un índice "grado|seccion" -> lista de combinaciones, que
   es lo que permite reescribir la etiqueta del turno con el cupo de la sección
   que se está eligiendo. */
$indiceCupos = [];
$opcionesSeccion = [];
$opcionesTurno   = [];
foreach ($combinaciones as $combo) {
    $g = (int) $combo['id_grado'];
    $s = (int) $combo['id_seccion'];
    $t = (int) $combo['id_turno'];

    $indiceCupos[$g . '|' . $s][] = [
        'id_turno'    => $t,
        'ocupados'    => (int) $combo['ocupados'],
        'capacidad'   => (int) $combo['capacidad_maxima'],
        'disponibles' => (int) $combo['disponibles'],
    ];

    /* Un <option> por combinación, no por sección o turno: la clave compuesta
       es lo que permite que el navegador sepa, al elegir el grado, qué secciones
       son de ese grado y no de otro. El value sigue siendo el id del nivel, que
       es lo que se manda al servidor. */
    $opcionesSeccion[$g . '|' . $s] = [
        'clave' => $g . '|' . $s,
        'id'    => $s,
        'nombre' => (string) $combo['seccion'],
    ];
    $opcionesTurno[$g . '|' . $s . '|' . $t] = [
        'clave'  => $g . '|' . $s . '|' . $t,
        'id'     => $t,
        'nombre' => (string) $combo['turno'],
    ];
}
?>

<section class="wizard-summary__section">
  <div class="wizard-summary__section-header">
    <span class="wizard-summary__icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
    <span class="wizard-summary__section-title">Información académica</span>
  </div>
  <div class="wizard-summary__card">
    <?php if (!$combinaciones): ?>
      <?php /* Sin combinaciones registradas no hay nada que ofrecer, y decirlo es
             mejor que mostrar tres selects que se van a rechazar al guardar: el
             problema está en Estructura Académica, no en el formulario. */ ?>
      <div class="alert alert-warning mb-0" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        No hay combinaciones de grado, sección y turno registradas para
        <?= htmlspecialchars((string) ($anioActivo['nombre'] ?: 'el año escolar activo')) ?>.
        Regístrelas en Configuración → Estructura Académica para poder inscribir.
      </div>
    <?php else: ?>
      <div class="row g-2" data-academico-cascada
           data-cupos="<?= htmlspecialchars((string) json_encode($indiceCupos, JSON_UNESCAPED_UNICODE)) ?>">
        <div class="col-6">
          <label class="label-sigde" for="gradoCursar">Grado a cursar *</label>
          <select class="form-select" id="gradoCursar" name="id_grado" required>
            <option value="" selected disabled>Seleccione el grado...</option>
            <?php foreach ($grados as $grado): ?>
              <?php /* Un grado sin ningún puesto libre no se ofrece: se podría
                     elegir y no se podría terminar. Se marca igual, para que se
                     vea por qué. */ ?>
              <?php $sinCupo = (int) $grado['libres'] <= 0; ?>
              <option value="<?= (int) $grado['id'] ?>"<?= $sinCupo ? ' disabled' : '' ?>>
                <?= htmlspecialchars($grado['nombre']) ?><?= $sinCupo ? ' (sin cupo)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Seleccione el grado.</div>
        </div>

        <div class="col-6">
          <label class="label-sigde" for="seccion">Sección *</label>
          <select class="form-select" id="seccion" name="id_seccion" required>
            <option value="" selected disabled>Seleccione la sección...</option>
            <?php foreach ($opcionesSeccion as $opcion): ?>
              <option value="<?= (int) $opcion['id'] ?>"
                      data-combinacion="<?= htmlspecialchars($opcion['clave']) ?>" hidden>
                <?= htmlspecialchars($opcion['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Seleccione la sección.</div>
        </div>

        <div class="col-6">
          <label class="label-sigde" for="turno">Turno *</label>
          <select class="form-select" id="turno" name="id_turno" required>
            <option value="" selected disabled>Seleccione el turno...</option>
            <?php foreach ($opcionesTurno as $opcion): ?>
              <?php /* El nombre pelado va en data-nombre porque la etiqueta se
                     reescribe con el cupo de la sección elegida: el mismo turno
                     puede estar lleno en una sección y libre en otra. */ ?>
              <option value="<?= (int) $opcion['id'] ?>"
                      data-nombre="<?= htmlspecialchars($opcion['nombre']) ?>"
                      data-combinacion="<?= htmlspecialchars($opcion['clave']) ?>" hidden>
                <?= htmlspecialchars($opcion['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Seleccione el turno.</div>
        </div>

        <div class="col-6">
          <label class="label-sigde" for="anioEscolarAcademico">Año escolar</label>
          <input type="text" class="form-control" id="anioEscolarAcademico" name="anio_escolar"
                 value="<?= htmlspecialchars((string) $anioActivo['nombre']) ?>" readonly>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
