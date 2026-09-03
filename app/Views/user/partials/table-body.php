<?php
/**
 * Partial: user/partials/table-body.php
 * Filas del tbody + footer de paginación.
 * Usado tanto por la vista completa (index.php) como por searchAjax.
 *
 * Variables esperadas:
 * - array $usuarios
 * - array $paginacion
 */
?>
<tbody>
  <?php foreach ($usuarios as $u): ?>
    <?php $esInactivo = $u['estado'] === 'inactivo'; ?>
    <tr class="<?= $esInactivo ? 'is-muted' : '' ?>">
      <td>
        <div class="avatar-group">
          <span class="avatar avatar--md avatar--<?= htmlspecialchars($u['avatar_color']) ?>">
            <?= htmlspecialchars($u['iniciales']) ?>
          </span>
          <span class="avatar-group__name"><?= htmlspecialchars($u['nombre']) ?></span>
        </div>
      </td>
      <td class="text-support">
        <?php if (empty($u['empleado']) || trim((string) $u['empleado']) === '' || $u['empleado'] === '—'): ?>
          <span class="text-body-tertiary">—</span>
        <?php else: ?>
          <?= htmlspecialchars($u['empleado']) ?>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars(ucwords(mb_strtolower($u['rol'], 'UTF-8'))) ?></td>
      <td class="text-center">
        <span class="status-badge status-badge--<?= $esInactivo ? 'inactive' : 'active' ?>">
          <?= $esInactivo ? 'Inactivo' : 'Activo' ?>
        </span>
      </td>
      <td class="text-center">
        <div class="data-panel__actions">
          <button type="button"
                  class="action-btn action-btn--view btn-ver-usuario"
                  title="Ver detalle"
                  data-id="<?= (int) $u['id'] ?>"
                  data-nombre="<?= htmlspecialchars($u['nombre']) ?>"
                  data-usuario="<?= htmlspecialchars($u['nombre_usuario']) ?>"
                  data-iniciales="<?= htmlspecialchars($u['iniciales']) ?>"
                  data-avatar-color="<?= htmlspecialchars($u['avatar_color']) ?>"
                  data-empleado="<?= htmlspecialchars($u['empleado']) ?>"
                  data-rol="<?= htmlspecialchars($u['rol']) ?>"
                  data-estado="<?= htmlspecialchars($u['estado']) ?>">
            <i class="bi bi-eye"></i>
          </button>
          <button type="button"
                  class="action-btn action-btn--edit btn-editar-usuario"
                  title="Editar"
                  data-id="<?= (int) $u['id'] ?>"
                  data-nombre-usuario="<?= htmlspecialchars($u['nombre_usuario']) ?>"
                  data-empleado="<?= htmlspecialchars($u['empleado']) ?>"
                  data-rol="<?= htmlspecialchars($u['rol']) ?>">
            <i class="bi bi-pencil"></i>
          </button>
          <div class="form-check form-switch table-switch mb-0" title="<?= $esInactivo ? 'Activar' : 'Desactivar' ?> cuenta">
            <input
              class="form-check-input toggle-estado-usuario"
              type="checkbox"
              role="switch"
              data-usuario-id="<?= (int) $u['id'] ?>"
              <?= $esInactivo ? '' : 'checked' ?>
            >
          </div>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>

  <?php if (empty($usuarios)): ?>
    <tr>
      <td colspan="5" class="text-center text-support py-4">
        No se encontraron usuarios con los filtros seleccionados.
      </td>
    </tr>
  <?php endif; ?>
</tbody>
