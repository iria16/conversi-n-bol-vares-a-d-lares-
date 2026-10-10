<?php
/**
 * Modal crear/editar de la Estructura Académica, compartido entre nuevaEstructuraModal y
 * editarEstructuraModal.
 *
 * Variables desde la vista index:
 * - $modalId, $modalTitle, $modalSubtitle, $formId, $editMode
 * - $aniosEditables, $grados, $secciones, $turnos (catálogos [id, nombre])
 *
 * El año se toma de $aniosEditables y no de $anios: el filtro necesita ofrecer también los años
 * cerrados, porque un período anterior se consulta aunque no se pueda escribir en él, pero en
 * el modal cualquier año distinto del ACTIVO lo va a rechazar el servidor con un 422.
*/
$editMode = $editMode ?? false;
$formId = $formId ?? 'formNuevaEstructura';
$aniosEditables = $aniosEditables ?? [];
$grados = $grados ?? [];
$secciones = $secciones ?? [];
$turnos = $turnos ?? [];
$prefix = $editMode ? 'editar' : 'nuevo';
ob_start();
?>
<form id="<?= htmlspecialchars($formId) ?>" novalidate>
  <?php if ($editMode): ?><input type="hidden" name="id" id="editarEstructuraId"><?php endif; ?>

  <section class="wizard-summary__section">
    <div class="wizard-summary__section-header">
      <span class="wizard-summary__icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
      <span class="wizard-summary__section-title">Configuración académica</span>
    </div>
    <div class="wizard-summary__card">
      <div class="row g-2">
        <?php /* Solo hay un año ACTIVO a la vez —lo garantiza una clave única en la base—,
 así que la lista de editables tiene exactamente uno y ese es el que va
 marcado. Preguntarlo en un desplegable que no tiene por dónde elegir es una
 decisión que la secretaria no necesita tomar.

 En el modal de EDICIÓN no se marca nada: el valor lo pone el JavaScript con
 el año de la fila que se está editando, y dejar una opción marcada en el
 HTML lo dejaría fuera de paso hasta que el script corrigiera.*/ ?>
        <?php $anioPorDefecto = ($editMode || $aniosEditables === []) ? 0 : (int) $aniosEditables[0]['id']; ?>
        <?php foreach ([['anio', 'Año escolar', $aniosEditables], ['grado', 'Grado', $grados], ['seccion', 'Sección',$secciones], ['turno', 'Turno', $turnos]] as $field): ?>
          <div class="col-6">
            <label class="label-sigde" for="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>"><?= $field[1] ?></label>
            <select
              class="form-select"
              name="id_<?= $field[0] === 'anio' ? 'anio_escolar' : $field[0] ?>"
              id="<?= $prefix ?>Estructura<?= ucfirst($field[0]) ?>"
              required
            >
              <option value="">Seleccione...</option>
              <?php foreach ($field[2] as $option): ?>
                <option value="<?= (int) $option['id'] ?>"
                        <?= (int) $option['id'] === $anioPorDefecto ? 'selected' : '' ?>>
                  <?= htmlspecialchars($option['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($field[0] === 'anio' && $anioPorDefecto > 0): ?>
              <div class="form-text">Por defecto, el año escolar activo. Solo ese admite cambios.</div>
            <?php endif; ?>
            <div class="invalid-feedback">Seleccione <?= strtolower($field[1]) ?>.</div>
          </div>
        <?php endforeach; ?>
        <div class="col-6">
          <label class="label-sigde" for="<?= $prefix ?>EstructuraCapacidad">Capacidad máxima</label>
          <input type="number" name="capacidad_maxima" id="<?= $prefix ?>EstructuraCapacidad" class="form-control" min="1" max="60" required>
          <div class="invalid-feedback">Indique la capacidad (1 a 60).</div>
        </div>
      </div>

      <?php if ($editMode): ?>
        <?php /* En un ALTA la capacidad no tiene mínimo que respetar: la sección está vacía por definición.
 En una EDICIÓN sí, y sin esto quien edita no lo sabe hasta que el servidor rechaza el
 guardado: una capacidad de 5 en una sección con 30 alumnos parece válida.*/
        ?>
        <div class="mt-3 pt-3 border-top" id="editarEstructuraCupoResumen" hidden>
          <div class="d-flex flex-wrap align-items-center gap-3">
            <div>
              <div class="text-muted small">Ocupados ahora</div>
              <div class="fw-semibold">
                <span id="editarEstructuraOcupados">0</span>
                <span class="text-muted small" id="editarEstructuraOcupadosDe">de</span>
                <span class="text-muted small" id="editarEstructuraCapacidadActual">cupos</span>
              </div>
            </div>
            <div>
              <div class="text-muted small">Capacidad mínima permitida</div>
              <div class="fw-semibold" id="editarEstructuraMinimo">0</div>
            </div>
            <div class="ms-auto" id="editarEstructuraCupoAviso"></div>
          </div>
          <div class="form-text mt-2">
            Cambiar el grado, la sección o el turno mueve a los alumnos que ya están en
            esta combinación. La capacidad nunca puede quedar por debajo de ellos.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</form>
<?php
$modalBody = ob_get_clean();
$modalFooter = '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" form="' . htmlspecialchars($formId) . '" class="btn btn-primary">Guardar</button>';
include __DIR__ . '/../../components/modal.php';
