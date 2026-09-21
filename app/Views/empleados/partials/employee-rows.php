<?php
/**
 * Fragmento: empleados/partials/employee-rows.php
 * Filas <tr> del listado de empleados (Personal > Empleados).
 *
 * Lo incluye empleados/index.php dentro de un ob_start() y el resultado va a
 * $panelTableBody (components/data-panel.php).
 *
 * Espera del controlador (EmpleadoController, vía EmpleadoModel::getAll):
 * @var array $empleados cada uno: ['id_empleado','nombre_completo','primer_nombre',
 *                       'primer_apellido','tipo_documento','numero_documento',
 *                       'cargo','estado' => 'ACTIVO'|'INACTIVO','foto']
 */

foreach ($empleados as $e):
    // persona.estado se guarda en MAYÚSCULA; strtoupper() evita que esto se
    // rompa si algún día se guarda distinto.
    $esInactivo = strtoupper((string) $e['estado']) !== 'ACTIVO';
    $iniciales  = mb_strtoupper(
        mb_substr((string) $e['primer_nombre'], 0, 1) . mb_substr((string) $e['primer_apellido'], 0, 1),
        'UTF-8'
    );
?>
  <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
    <td>
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($e['foto'])): ?>
          <img class="avatar" src="<?= htmlspecialchars(BASE_URL . ltrim((string) $e['foto'], '/')) ?>"
               alt="" width="40" height="40">
        <?php else: ?>
          <span class="avatar" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></span>
        <?php endif; ?>
        <span class="fw-semibold"><?= htmlspecialchars((string) $e['nombre_completo']) ?></span>
      </div>
    </td>
    <td><?= htmlspecialchars($e['tipo_documento'] . '-' . $e['numero_documento']) ?></td>
    <td><?= htmlspecialchars((string) ($e['cargo'] ?? 'Sin cargo')) ?></td>
    <td>
      <?php
        $badgeLabel  = $esInactivo ? 'Inactivo' : 'Activo';
        $badgeStatus = $esInactivo ? 'inactive' : 'active';
        include __DIR__ . '/../../components/status-badge.php';
      ?>
    </td>
    <td class="text-center">
      <div class="data-panel__actions">
        <button type="button"
                class="action-btn action-btn--view ver-empleado"
                data-empleado-id="<?= (int) $e['id_empleado'] ?>"
                title="Ver detalle">
          <i class="bi bi-eye" aria-hidden="true"></i>
        </button>
        <button type="button"
                class="action-btn action-btn--edit editar-empleado"
                data-empleado-id="<?= (int) $e['id_empleado'] ?>"
                title="Editar empleado">
          <i class="bi bi-pencil" aria-hidden="true"></i>
        </button>
        <div class="form-check form-switch table-switch mb-0" title="<?= $esInactivo ? 'Activar' : 'Desactivar' ?> empleado">
          <input class="form-check-input toggle-estado"
                 type="checkbox"
                 role="switch"
                 data-empleado-id="<?= (int) $e['id_empleado'] ?>"
                 <?= $esInactivo ? '' : 'checked' ?>
                 aria-label="Activar o desactivar a <?= htmlspecialchars((string) $e['nombre_completo']) ?>">
        </div>
      </div>
    </td>
  </tr>
<?php endforeach; ?>

<?php if (empty($empleados)): ?>
  <tr>
    <td colspan="5" class="text-center text-support py-4">
      No se encontraron empleados con los filtros seleccionados.
    </td>
  </tr>
<?php endif; ?>