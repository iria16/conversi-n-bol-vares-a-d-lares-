<?php
/**
 * Espera $resumen con los datos ya cargados del estudiante en proceso de registro.
 * Estructura de ejemplo abajo (reemplazar por los datos reales del wizard/sesión).
 */
$resumen = $resumen ?? [
    'personales' => [
        'foto' => null,
        'nombre_completo' => 'Samuel David Rodríguez Pérez',
        'documento' => 'V-34.892.101',
        'fecha_nacimiento' => '12 de Octubre, 2017',
        'edad' => 6,
        'genero' => 'Masculino',
        'nacionalidad' => 'Venezolano',
    ],
    'academico' => [
        'grado_seccion' => '1ero "B"',
        'turno' => 'Mañana',
        'estatus' => 'NUEVO',
    ],
    'procedencia' => [
        'institucion_anterior' => null, // null => "Ingreso por primera vez"
        'ultimo_grado' => 'Educación Inicial (Kinder)',
    ],
    'tallas' => [
        'camisa' => '8',
        'pantalon' => '6',
        'calzado' => '28',
        'ficha_medica_registrada' => false,
    ],
    'documentos' => [
        'consignados' => 5,
        'total' => 6,
        'lista' => [
            ['nombre' => 'Copia de Partida de Nacimiento', 'ok' => false],
            ['nombre' => '4 Fotos Tipo Carnet', 'ok' => true],
            ['nombre' => 'Copia C.I. de Representantes', 'ok' => true],
        ],
    ],
    'familiares' => [
        ['nombre' => 'Elena Sofía Pérez Márquez', 'parentesco' => 'Madre', 'badges' => ['Representante', 'Autorizado']],
        ['nombre' => 'Ricardo Antonio Rodríguez', 'parentesco' => 'Padre', 'badges' => ['Autorizado']],
    ],
];

$p = $resumen['personales'];
$ac = $resumen['academico'];
$pr = $resumen['procedencia'];
$ta = $resumen['tallas'];
$doc = $resumen['documentos'];
$famil = $resumen['familiares'];
$docPct = $doc['total'] > 0 ? round($doc['consignados'] / $doc['total'] * 100) : 0;
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h5 class="mb-0">Registrar Nuevo Estudiante</h5>
    <small class="text-success fw-semibold">Paso 7 de 7: Resumen Final</small>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

<div class="row g-3">

  <!-- Datos Personales -->
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="text-primary mb-0"><i class="bi bi-person"></i> Datos Personales</h6>
          <a href="#" class="small" data-wizard-editar="1">Editar</a>
        </div>
        <div class="d-flex gap-3">
          <?php if (!empty($p['foto'])): ?>
            <img src="<?= htmlspecialchars($p['foto']) ?>" alt="Foto del estudiante" class="rounded-3" style="width:64px;height:64px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-3 bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:64px;height:64px;">
              <i class="bi bi-person fs-3 text-secondary"></i>
            </div>
          <?php endif; ?>
          <div class="row g-2 flex-grow-1">
            <div class="col-6">
              <div class="text-muted small">Nombres y Apellidos</div>
              <div class="fw-semibold"><?= htmlspecialchars($p['nombre_completo']) ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Documento de Identidad</div>
              <div class="fw-semibold"><?= htmlspecialchars($p['documento']) ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Fecha de Nacimiento</div>
              <div class="fw-semibold"><?= htmlspecialchars($p['fecha_nacimiento']) ?> (<?= (int) $p['edad'] ?> años)</div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Género / Nacionalidad</div>
              <div class="fw-semibold"><?= htmlspecialchars($p['genero']) ?> / <?= htmlspecialchars($p['nacionalidad']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Académico -->
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="text-primary mb-0"><i class="bi bi-mortarboard"></i> Académico</h6>
          <a href="#" class="small" data-wizard-editar="3">Editar</a>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span class="text-muted small">Grado / Sección</span>
          <span class="fw-semibold"><?= htmlspecialchars($ac['grado_seccion']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span class="text-muted small">Turno</span>
          <span class="fw-semibold"><?= htmlspecialchars($ac['turno']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center py-1">
          <span class="text-muted small">Estatus</span>
          <span class="badge text-bg-success"><?= htmlspecialchars($ac['estatus']) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Procedencia -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="text-primary mb-0"><i class="bi bi-signpost-split"></i> Procedencia</h6>
          <a href="#" class="small" data-wizard-editar="2">Editar</a>
        </div>
        <div class="text-muted small">Institución Anterior</div>
        <div class="fw-semibold mb-2">
          <?= !empty($pr['institucion_anterior'])
                ? htmlspecialchars($pr['institucion_anterior'])
                : '<span class="fst-italic text-muted">Ingreso por primera vez</span>' ?>
        </div>
        <div class="text-muted small">Último Grado</div>
        <div class="fw-semibold"><?= htmlspecialchars($pr['ultimo_grado']) ?></div>
      </div>
    </div>
  </div>

  <!-- Tallas y Salud -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="text-primary mb-0"><i class="bi bi-person-standing"></i> Tallas y Salud</h6>
          <a href="#" class="small" data-wizard-editar="4">Editar</a>
        </div>
        <div class="row text-center mb-3">
          <div class="col-4">
            <div class="text-muted small">Camisa</div>
            <div class="fw-semibold"><?= htmlspecialchars($ta['camisa']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted small">Pantalón</div>
            <div class="fw-semibold"><?= htmlspecialchars($ta['pantalon']) ?></div>
          </div>
          <div class="col-4">
            <div class="text-muted small">Calzado</div>
            <div class="fw-semibold"><?= htmlspecialchars($ta['calzado']) ?></div>
          </div>
        </div>
        <div class="text-muted small mb-1"><i class="bi bi-briefcase"></i> Ficha Médica</div>
        <?php if ($ta['ficha_medica_registrada']): ?>
          <span class="badge text-bg-success">REGISTRADA</span>
        <?php else: ?>
          <span class="badge text-bg-danger-subtle text-danger">SIN FICHA REGISTRADA</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Documentos -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="text-primary mb-0"><i class="bi bi-file-earmark-text"></i> Documentos</h6>
          <a href="#" class="small" data-wizard-editar="5">Editar</a>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="progress flex-grow-1" style="height:6px;">
            <div class="progress-bar bg-warning" style="width: <?= $docPct ?>%;"></div>
          </div>
          <small class="text-muted"><?= (int) $doc['consignados'] ?> de <?= (int) $doc['total'] ?></small>
        </div>
        <ul class="list-unstyled mb-0 small">
          <?php foreach ($doc['lista'] as $d): ?>
            <li class="d-flex align-items-center gap-2 py-1">
              <?php if ($d['ok']): ?>
                <i class="bi bi-check-circle-fill text-success"></i>
              <?php else: ?>
                <i class="bi bi-x-circle-fill text-danger"></i>
              <?php endif; ?>
              <span class="<?= $d['ok'] ? '' : 'text-danger' ?>"><?= htmlspecialchars($d['nombre']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Grupo Familiar y Representación -->
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="text-primary mb-0"><i class="bi bi-people"></i> Grupo Familiar y Representación</h6>
          <a href="#" class="small" data-wizard-editar="6">Editar</a>
        </div>
        <div class="row g-3">
          <?php foreach ($famil as $f): ?>
            <div class="col-md-6">
              <div class="d-flex align-items-center gap-3 border rounded-3 p-2">
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;">
                  <i class="bi bi-person text-secondary"></i>
                </div>
                <div>
                  <div class="text-uppercase text-muted small fw-semibold"><?= htmlspecialchars($f['parentesco']) ?></div>
                  <div class="fw-semibold"><?= htmlspecialchars($f['nombre']) ?></div>
                  <div class="d-flex gap-1 mt-1">
                    <?php foreach ($f['badges'] as $b): ?>
                      <span class="badge text-bg-success"><?= htmlspecialchars($b) ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>