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
 * 'primer_apellido','tipo_documento','numero_documento',
 * 'cargo','turno','estado' => 'ACTIVO'|'INACTIVO','foto',
 * 'avatar_tipo','avatar_foto','iniciales','avatar_color']
 * (avatar_* los genera AvatarTrait en el modelo).
*/

foreach ($empleados as $e):
    // persona.estado se guarda en MAYÚSCULA; strtoupper() evita que esto se
    // rompa si algún día se guarda distinto.
    $esInactivo = strtoupper((string) $e['estado']) !== 'ACTIVO';
?>
  <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
    <td>
      <div class="d-flex align-items-center gap-3">
        <?php
          // Avatar centralizado en components/avatar.php: foto si persona.foto
          // tiene valor, iniciales+color en caso contrario (AvatarTrait).
          $avatarSize     = 'md';
          $avatarTone     = $e['avatar_color'] ?? 'primary';
          $avatarSrc      = ($e['avatar_tipo'] ?? 'iniciales') === 'foto'
              ? htmlspecialchars(BASE_URL . $e['avatar_foto'])
              : '';
          $avatarInitials = $e['iniciales'] ?? '';
          $avatarAlt      = htmlspecialchars((string) $e['nombre_completo']);
          include __DIR__ . '/../../components/avatar.php';
        ?>
        <span class="fw-semibold"><?= htmlspecialchars((string) $e['nombre_completo']) ?></span>
      </div>
    </td>
    <td><?= htmlspecialchars($e['tipo_documento'] . '-' . $e['numero_documento']) ?></td>
    <td>
      <?= htmlspecialchars((string) ($e['cargo'] ?? 'Sin cargo')) ?>
      <div class="text-support small"><?= htmlspecialchars((string) ($e['turno'] ?? 'Sin turno')) ?></div>
    </td>
    <?php /* La nacionalidad se imprime aunque esté vacía: la columna existe porque la
            secretaria la consulta, y una celda en blanco delata que falta el dato
             mientras un "—" la haría indistinguishable de las que sí lo tienen. */ ?>
    <td class="text-support"><?= htmlspecialchars((string) ($e['nacionalidad'] ?? '')) ?></td>
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
    <?php /* El colspan tiene que seguir la cuenta de columnas: al agregar
             Nacionalidad pasaron a ser 6 y con el 5 quedaba una celda sin cubrir. */ ?>
    <td colspan="6" class="text-center text-support py-4">
      No se encontraron empleados con los filtros seleccionados.
    </td>
  </tr>
<?php endif; ?>