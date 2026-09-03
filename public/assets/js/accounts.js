/* ==========================================================================
   SIGDE - cuentas.js
   Comportamiento específico de la vista de Cuentas de Usuario:
   toggle de estado, alta vía AJAX, modal Ver Detalle y modal Editar.
   Depende de: Bootstrap 5 (modales), SweetAlert2 (opcional),
   window.BASE_URL (definido globalmente en layouts/app.php).
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initToggleEstadoUsuario();
  initCrearUsuarioForm();
  initVerUsuarioModal();
  initEditarUsuarioModal();
});

var baseUrl = window.BASE_URL || '';

/**
 * Activa/desactiva una cuenta de usuario desde el switch de la tabla,
 * con confirmación previa (SweetAlert2 si está disponible, si no
 * confirm() nativo) y actualización visual inmediata de la fila.
 */
function initToggleEstadoUsuario() {
  document.querySelectorAll('.toggle-estado-usuario').forEach(asociarToggleEstado);
}

function asociarToggleEstado(input) {
  input.addEventListener('change', async function (e) {
    var checkbox = e.target;
    var id = checkbox.dataset.usuarioId;
    var nuevoEstadoActivo = checkbox.checked;
    var accion = nuevoEstadoActivo ? 'activar' : 'desactivar';
    var checkboxAnterior = !checkbox.checked;
    var tr = checkbox.closest('tr');
    var badge = tr ? tr.querySelector('.status-badge') : null;

    var confirmacion = false;
    if (window.Swal) {
      var result = await Swal.fire({
        title: accion.charAt(0).toUpperCase() + accion.slice(1) + ' usuario?',
        text: '¿Está seguro que desea ' + accion + ' esta cuenta de usuario?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, ' + accion,
        cancelButtonText: 'Cancelar',
        reverseButtons: true
      });
      confirmacion = result.isConfirmed;
    } else {
      confirmacion = confirm('¿Está seguro que desea ' + accion + ' esta cuenta de usuario?');
    }

    if (!confirmacion) {
      checkbox.checked = checkboxAnterior;
      return;
    }

    try {
      var res = await fetch(baseUrl + 'userAccount/toggleEstadoAjax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id)
      });
      var data = await res.json();

      if (!data.ok) {
        checkbox.checked = checkboxAnterior;
        mostrarError('No se pudo actualizar el estado del usuario.');
        return;
      }

      if (tr) tr.classList.toggle('is-muted', !nuevoEstadoActivo);
      if (badge) {
        badge.className = 'status-badge status-badge--' + (nuevoEstadoActivo ? 'active' : 'inactive');
        badge.textContent = nuevoEstadoActivo ? 'Activo' : 'Inactivo';
      }
      actualizarStatsEstado(nuevoEstadoActivo);

      if (window.Swal) {
        Swal.fire({
          title: '¡Listo!',
          text: 'Usuario ' + accion + 'do correctamente.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
      }
    } catch (err) {
      checkbox.checked = checkboxAnterior;
      mostrarError('Error de conexión al actualizar el estado.');
    }
  });
}

function actualizarStatsEstado(activo) {
  var activasEl = document.querySelector('.row.g-3.mb-4 .col-sm-6:nth-child(2) .stat-card__value');
  var inactivasEl = document.querySelector('.row.g-3.mb-4 .col-sm-6:nth-child(3) .stat-card__value');
  if (!activasEl || !inactivasEl) return;

  var act = parseInt(activasEl.textContent, 10) || 0;
  var inact = parseInt(inactivasEl.textContent, 10) || 0;
  if (activo) {
    act++;
    inact = Math.max(0, inact - 1);
  } else {
    act = Math.max(0, act - 1);
    inact++;
  }
  activasEl.textContent = act;
  inactivasEl.textContent = inact;
}

function initCrearUsuarioForm() {
  var formCrear = document.getElementById('formCrearUsuario');
  if (!formCrear) return;

  var modalCrearEl = document.getElementById('crearUsuarioModal');
  var modalCredencialesEl = document.getElementById('credencialesGeneradasModal');
  var btnGuardar = document.getElementById('btnGuardarUsuario');
  var credencialUsuarioSpan = document.getElementById('credencialUsuarioSpan');
  var passwordProvisionalValue = document.getElementById('passwordProvisionalValue');
  var btnCopiar = document.getElementById('btnCopiarPassword');

  formCrear.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!formCrear.checkValidity()) {
      e.stopPropagation();
      formCrear.classList.add('was-validated');
      return;
    }

    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var res = await fetch(baseUrl + 'userAccount/storeAjax', { method: 'POST', body: new FormData(formCrear) });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        mostrarError(data.mensaje || 'No se pudo crear el usuario. Verifique los datos.', 'Error al crear usuario');
        return;
      }

      if (modalCrearEl) (bootstrap.Modal.getInstance(modalCrearEl) || new bootstrap.Modal(modalCrearEl)).hide();

      var selectEmpleado = document.getElementById('crearUsuario__empleado');
      if (selectEmpleado && selectEmpleado.value) {
        var opt = selectEmpleado.querySelector('option[value="' + selectEmpleado.value + '"]');
        if (opt) opt.remove();
      }

      formCrear.reset();
      if (credencialUsuarioSpan) credencialUsuarioSpan.textContent = data.usuario;
      if (passwordProvisionalValue) passwordProvisionalValue.textContent = data.password_provisional;
      if (btnCopiar) btnCopiar.setAttribute('data-clipboard-text', data.password_provisional);
      if (modalCredencialesEl) new bootstrap.Modal(modalCredencialesEl).show();
      if (data.datos) insertarUsuarioEnTabla(data.datos);
    } catch (err) {
      console.error('Error al procesar petición AJAX:', err);
      mostrarError('Ocurrió un fallo de conexión al crear el usuario.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Crear Usuario';
      }
    }
  });
}

function insertarUsuarioEnTabla(usuario) {
  var tbody = document.querySelector('.data-panel__table tbody');
  if (!tbody) return;

  var filaVacia = tbody.querySelector('tr td[colspan]');
  if (filaVacia) filaVacia.closest('tr').remove();

  var tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <div class="avatar-group">
        <span class="avatar avatar--md avatar--${escapeHtml(usuario.avatar_color || 'primary')}">
          ${escapeHtml(usuario.iniciales || 'U')}
        </span>
        <span class="avatar-group__name">${escapeHtml(usuario.nombre || usuario.usuario)}</span>
      </div>
    </td>
    <td class="text-support">
      ${usuario.empleado && usuario.empleado !== '—' ? escapeHtml(usuario.empleado) : '<span class="text-body-tertiary">—</span>'}
    </td>
    <td>${escapeHtml(capitalizar(usuario.rol || ''))}</td>
    <td class="text-center">
      <span class="status-badge status-badge--active">Activo</span>
    </td>
    <td class="text-center">
      <div class="data-panel__actions">
        <button type="button" class="action-btn action-btn--view btn-ver-usuario" title="Ver detalle"
                data-id="${usuario.id}">
          <i class="bi bi-eye"></i>
        </button>
        <button type="button" class="action-btn action-btn--edit btn-editar-usuario" title="Editar"
                data-id="${usuario.id}"
                data-nombre-usuario="${escapeHtml(usuario.nombre_usuario || usuario.usuario || '')}"
                data-empleado="${escapeHtml(usuario.empleado || '—')}"
                data-rol="${escapeHtml(usuario.rol || '—')}">
          <i class="bi bi-pencil"></i>
        </button>
        <div class="form-check form-switch table-switch mb-0" title="Desactivar cuenta">
          <input class="form-check-input toggle-estado-usuario" type="checkbox" role="switch"
                 data-usuario-id="${usuario.id}" checked>
        </div>
      </div>
    </td>
  `;

  tbody.insertBefore(tr, tbody.firstChild);

  var nuevoSwitch = tr.querySelector('.toggle-estado-usuario');
  if (nuevoSwitch) asociarToggleEstado(nuevoSwitch);

  var cardTotal = document.querySelector('.row.g-3.mb-4 .col-sm-6:nth-child(1) .stat-card__value');
  var cardActivas = document.querySelector('.row.g-3.mb-4 .col-sm-6:nth-child(2) .stat-card__value');
  if (cardTotal) cardTotal.textContent = (parseInt(cardTotal.textContent, 10) || 0) + 1;
  if (cardActivas) cardActivas.textContent = (parseInt(cardActivas.textContent, 10) || 0) + 1;
}

/**
 * Modal "Ver Detalle": ahora carga los datos vía AJAX a
 * userAccount/getDetalleAjax en lugar de leerlos de data-* de la fila,
 * ya que el detalle incluye campos (documento, último acceso, miembro
 * desde) que la tabla no expone.
 */
function initVerUsuarioModal() {
  var modalVerEl = document.getElementById('verUsuarioModal');
  var modalEditarEl = document.getElementById('editarUsuarioModal');
  if (!modalVerEl) return;

  document.addEventListener('click', function (e) {
    var btnVer = e.target.closest('.btn-ver-usuario');
    if (!btnVer) return;

    var id = btnVer.dataset.id;
    usuarioActivoId = id;

    fetch(baseUrl + 'userAccount/getDetalleAjax?id=' + encodeURIComponent(id))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) {
          mostrarError(data.mensaje || 'No se pudo cargar el detalle del usuario.');
          return;
        }
        pintarDetalleUsuario(modalVerEl, data.datos);
        bootstrap.Modal.getOrCreateInstance(modalVerEl).show();
      })
      .catch(function () {
        mostrarError('Fallo de conexión al cargar el detalle.');
      });
  });
}

function pintarDetalleUsuario(modalVerEl, u) {
  var set = function (id, val) {
    var el = modalVerEl.querySelector(id);
    if (el) el.textContent = val || '—';
  };

  var avatarEl = modalVerEl.querySelector('#verUsuario__avatar');
  if (avatarEl) {
    avatarEl.className = 'avatar avatar--xl avatar--' + (u.avatar_color || 'primary');
    var inicialesEl = avatarEl.querySelector('#verUsuario__iniciales');
    if (inicialesEl) inicialesEl.textContent = u.iniciales || 'U';
  }
  set('#verUsuario__nombre', u.empleado || u.nombre);

  var rolBadge = modalVerEl.querySelector('#verUsuario__rolBadge');
  if (rolBadge) {
    rolBadge.className = 'status-badge status-badge--role status-badge--' + colorBadgePorRol(u.rol);
    rolBadge.textContent = capitalizar(u.rol || '—');
  }
  set('#verUsuario__documento', u.documento || '—');

  set('#verUsuario__nombreCompleto', u.nombre_completo || u.empleado || '—');
  set('#verUsuario__cedula', u.documento || '—');
  set('#verUsuario__nombreUsuario', u.nombre_usuario || '—');
  set('#verUsuario__ultimoAcceso', u.ultimo_acceso ? formatearFecha(u.ultimo_acceso) : 'Sin registros');

  var estadoValorEl = modalVerEl.querySelector('#verUsuario__estadoValor');
  if (estadoValorEl) {
    var esActivo = (u.estado || 'activo') === 'activo';
    estadoValorEl.textContent = esActivo ? 'Cuenta Activa y Verificada' : 'Cuenta Inactiva';
  }
  set('#verUsuario__miembroDesde', u.miembro_desde ? formatearFecha(u.miembro_desde, true) : '—');
}

function colorBadgePorRol(rol) {
  var r = (rol || '').trim().toLowerCase();
  if (r === 'admin' || r === 'administrador') return 'active';
  if (r === 'docente') return 'info';
  return 'pending';
}

function formatearFecha(valor, soloFecha) {
  var fecha = new Date(valor.replace(' ', 'T'));
  if (isNaN(fecha)) return valor;
  if (soloFecha) {
    return fecha.toLocaleDateString('es-VE', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  return fecha.toLocaleString('es-VE', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true
  });
}

/**
 * Modal "Editar Usuario": rellena el formulario desde los data-* del
 * botón de la fila y envía los cambios vía AJAX a userAccount/updateAjax.
 */
function initEditarUsuarioModal() {
  var modalEditarEl = document.getElementById('editarUsuarioModal');
  if (!modalEditarEl) return;

  document.addEventListener('click', function (e) {
    var btnEditar = e.target.closest('.btn-editar-usuario');
    if (!btnEditar) return;

    var d = btnEditar.dataset;
    var inputId = modalEditarEl.querySelector('#editUsuario__id');
    var inputNombreUs = modalEditarEl.querySelector('#editUsuario__nombreUsuario');
    var inputEmpleado = modalEditarEl.querySelector('#editUsuario__empleado');
    var selectRol = modalEditarEl.querySelector('#editUsuario__rol');

    if (inputId)       inputId.value       = d.id           || '';
    if (inputNombreUs) inputNombreUs.value = d.nombreUsuario || '';
    if (inputEmpleado) inputEmpleado.value = d.empleado      || '';

    if (selectRol && d.rol) {
      var rolNorm = (d.rol || '').trim().toLowerCase();
      Array.from(selectRol.options).forEach(function (opt) {
        opt.selected = opt.textContent.trim().toLowerCase() === rolNorm;
      });
    }

    modalEditarEl.querySelector('form') && modalEditarEl.querySelector('form').classList.remove('was-validated');
    bootstrap.Modal.getOrCreateInstance(modalEditarEl).show();
  });

  var formEditar = modalEditarEl.querySelector('#formEditarUsuario');
  if (!formEditar) return;

  formEditar.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formEditar.checkValidity()) {
      e.stopPropagation();
      formEditar.classList.add('was-validated');
      return;
    }
    formEditar.classList.remove('was-validated');

    var btnGuardar = document.getElementById('btnGuardarEdicion');
    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formEditar);
      var res = await fetch(baseUrl + 'userAccount/updateAjax', { method: 'POST', body: formData });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        mostrarError(data.mensaje || 'No se pudo actualizar el usuario.', 'Error al editar usuario');
        return;
      }

      bootstrap.Modal.getOrCreateInstance(modalEditarEl).hide();

      if (data.datos) {
        var id = data.datos.id;
        var tr = document.querySelector('.btn-editar-usuario[data-id="' + id + '"]');
        if (tr) {
          tr = tr.closest('tr');
        }
        if (tr && data.datos) {
          var u = data.datos;
          var btnEditarEnFila = tr.querySelector('.btn-editar-usuario');
          if (btnEditarEnFila) {
            btnEditarEnFila.dataset.nombreUsuario = u.nombre_usuario || u.usuario || '';
            btnEditarEnFila.dataset.rol           = u.rol || '';
          }
          var rolCell = tr.querySelectorAll('td')[2];
          if (rolCell) rolCell.textContent = capitalizar(u.rol || '');

          var avatarNameEl = tr.querySelector('.avatar-group__name');
          if (avatarNameEl && u.nombre) avatarNameEl.textContent = u.nombre;
        }
      }

      if (window.Swal) {
        Swal.fire({ icon: 'success', title: 'Actualizado', text: data.mensaje || 'Usuario actualizado correctamente.', timer: 2000, showConfirmButton: false });
      }
    } catch (err) {
      console.error('Error al procesar petición AJAX:', err);
      mostrarError('Ocurrió un fallo de conexión al editar el usuario.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';
      }
    }
  });
}

/* ── Utilidades ─────────────────────────────────────────────────── */

function mostrarError(mensaje, titulo) {
  if (window.Swal) {
    Swal.fire({ icon: 'error', title: titulo || 'Error', text: mensaje });
  } else {
    alert((titulo ? titulo + '\n' : '') + mensaje);
  }
}

function capitalizar(str) {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

function escapeHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

