/* ==========================================================================
   SIGDE - cuentas.js
   Comportamiento específico de la vista de Cuentas de Usuario:
   toggle de estado, alta vía AJAX, modal Ver Detalle y modal Editar.
   Depende de: Bootstrap 5 (modales), SweetAlert2 (opcional),
   window.BASE_URL (definido globalmente en layouts/app.php).
   Nota: store/update envían su token vía <form> con
   components/csrf-field.php; toggleStatus no envía CSRF token.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initToggleEstadoUsuario();
  initCrearUsuarioForm();
  initVerUsuarioModal();
  initEditarUsuarioModal();
  initCopyToClipboardButtons();
});

var baseUrl = window.BASE_URL || '';
var rutaControlador = 'cuentaUsuario';

/**
 * Activa/desactiva una cuenta de usuario desde el switch de la tabla,
 * con confirmación previa (SweetAlert2 si está disponible, si no
 * confirm() nativo) y actualización visual inmediata de la fila.
 */
function initToggleEstadoUsuario() {
  document.addEventListener('change', async function (e) {
    var checkbox = e.target.closest('.toggle-estado-usuario');
    if (!checkbox) return;
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
      var res = await fetch(baseUrl + rutaControlador + '/toggleStatus', {
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
  var activasEl = document.querySelector('.row.g-3.mb-3 .col-sm-6:nth-child(2) .stat-card__value');
  var inactivasEl = document.querySelector('.row.g-3.mb-3 .col-sm-6:nth-child(3) .stat-card__value');
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

  actualizarPorcentajesStats(act, inact);
}

function actualizarPorcentajesStats(activas, inactivas) {
  var total = activas + inactivas;
  if (total <= 0) return;

  var trendActivasEl = document.querySelector('.row.g-3.mb-3 .col-sm-6:nth-child(2) .stat-card__trend');
  var trendInactivasEl = document.querySelector('.row.g-3.mb-3 .col-sm-6:nth-child(3) .stat-card__trend');

  if (trendActivasEl) {
    trendActivasEl.textContent = Math.round((activas / total) * 100) + '% del total';
  }
  if (trendInactivasEl) {
    trendInactivasEl.textContent = Math.round((inactivas / total) * 100) + '% del total';
  }
}

/* ── Validación en vivo (frontend) ──────────────────────────────────
   Las reglas (required, minlength, maxlength, pattern) ya están
   declaradas en el propio HTML de create-modal.php / edit-modal.php
   — el JS NO las reimplementa (evita tener la misma regla en dos
   lugares que puedan desincronizarse). Lo único que hace este bloque
   es leer el estado de validez nativo (input.validity) y ponerle un
   mensaje en español vía setCustomValidity(), coherente con los
   mensajes que devuelve CuentaUsuarioController::validarNombreUsuario(). */

function aplicarValidacionNombreUsuario(input) {
  if (!input) return;

  var actualizarMensaje = function () {
    if (input.validity.valueMissing) {
      input.setCustomValidity('El nombre de usuario es obligatorio.');
    } else if (input.validity.tooShort || input.validity.tooLong) {
      input.setCustomValidity('El nombre de usuario debe tener entre 3 y 50 caracteres.');
    } else if (input.validity.patternMismatch) {
      input.setCustomValidity('El nombre de usuario solo puede contener letras, números, puntos y guiones bajos..');
    } else {
      input.setCustomValidity('');
    }
  };

  input.addEventListener('input', actualizarMensaje);
  input.addEventListener('blur', actualizarMensaje);
  actualizarMensaje();
}

function aplicarValidacionSelectRequerido(select, mensaje) {
  if (!select) return;

  var actualizarMensaje = function () {
    select.setCustomValidity(select.validity.valueMissing ? mensaje : '');
  };

  select.addEventListener('change', actualizarMensaje);
  actualizarMensaje();
}

/**
 * Alta de usuario (modal Crear Usuario).
 *
 * OPCIÓN A: no se inserta la fila nueva a mano en el cliente. Al cerrar
 * el modal de credenciales (tras copiar la contraseña provisional) se
 * recarga la página completa, para que tabla, contadores y el <select>
 * de empleados disponibles los sirva siempre el servidor como única
 * fuente de verdad — sin duplicar el markup de la fila en JS.
 */
function initCrearUsuarioForm() {
  var formCrear = document.getElementById('formCrearUsuario');
  if (!formCrear) return;

  var modalCrearEl = document.getElementById('modalCrearUsuario');
  var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
  var btnGuardar = document.querySelector('button[form="formCrearUsuario"]');
  var credUsuarioNombreEl = document.getElementById('credUsuarioNombre');
  var credUsuarioEl = document.getElementById('credUsuario');
  var credPasswordEl = document.getElementById('credPassword');

  aplicarValidacionNombreUsuario(document.getElementById('nuUsuario'));
  aplicarValidacionSelectRequerido(document.getElementById('nuEmpleado'), 'Debe seleccionar un empleado.');
  aplicarValidacionSelectRequerido(document.getElementById('nuRol'), 'Debe seleccionar un rol.');

  if (modalCredencialesEl) {
    modalCredencialesEl.addEventListener('hidden.bs.modal', function () {
      window.location.reload();
    });
  }

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
      var res = await fetch(baseUrl + rutaControlador + '/store', { method: 'POST', body: new FormData(formCrear) });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        mostrarError(data.mensaje || 'No se pudo crear el usuario. Verifique los datos.', 'Error al crear usuario');
        return;
      }

      if (modalCrearEl) (bootstrap.Modal.getInstance(modalCrearEl) || new bootstrap.Modal(modalCrearEl)).hide();

      formCrear.reset();
      formCrear.classList.remove('was-validated');

      var nombreParaSaludo = (data.datos && data.datos.empleado) ? data.datos.empleado : data.usuario;
      if (credUsuarioNombreEl) credUsuarioNombreEl.textContent = nombreParaSaludo || '';
      if (credUsuarioEl) credUsuarioEl.textContent = data.usuario || '';
      if (credPasswordEl) credPasswordEl.textContent = data.password_provisional || '';

      if (modalCredencialesEl) new bootstrap.Modal(modalCredencialesEl).show();
    } catch (err) {
      console.error('Error al procesar petición AJAX:', err);
      mostrarError('Ocurrió un fallo de conexión al crear el usuario.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Crear usuario';
      }
    }
  });
}

/**
 * Modal "Ver Detalle": carga los datos vía AJAX a
 * cuentaUsuario/getDetalle en lugar de leerlos de data-* de la fila,
 * ya que el detalle incluye campos (documento, último acceso, miembro
 * desde) que la tabla no expone.
 */
function initVerUsuarioModal() {
  var modalVerEl = document.getElementById('modalVerUsuario');
  if (!modalVerEl) return;

  document.addEventListener('click', function (e) {
    var btnVer = e.target.closest('.btn-ver-usuario');
    if (!btnVer) return;

    var id = btnVer.dataset.id;

    fetch(baseUrl + rutaControlador + '/getDetalle?id=' + encodeURIComponent(id))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) {
          mostrarError(data.mensaje || 'No se pudo cargar el detalle del usuario.');
          return;
        }
        pintarDetalleUsuario(modalVerEl, data.data);
        bootstrap.Modal.getOrCreateInstance(modalVerEl).show();
      })
      .catch(function () {
        mostrarError('Fallo de conexión al cargar el detalle.');
      });
  });
}

function pintarDetalleUsuario(modalVerEl, u) {
  var set = function (id, val) {
    var el = modalVerEl.querySelector('#' + id);
    if (el) el.textContent = val || '—';
  };

  var avatarEl = modalVerEl.querySelector('#verUsuarioAvatar');
  if (avatarEl) {
    avatarEl.className = 'avatar avatar--lg avatar--' + (u.avatar_color || 'primary');
    avatarEl.textContent = u.iniciales || 'U';
  }

  set('verUsuarioNombre', u.nombre_completo || u.empleado || u.nombre);
  set('verUsuarioDocumentoTop', u.documento ? u.documento : '—');

  var elRolBadge = modalVerEl.querySelector('#verUsuarioRolBadge');
  if (elRolBadge) {
    elRolBadge.className = 'status-badge status-badge--' + colorBadgePorRol(u.rol);
    elRolBadge.textContent = capitalizarPalabras(u.rol || '').toUpperCase();
  }

  set('verUsuarioEmpleado', u.empleado);
  set('verUsuarioCargo', u.cargo);
  set('verUsuarioUsuario', u.nombre_usuario ? u.nombre_usuario : '—');
  set('verUsuarioUltimoAcceso', u.ultimo_acceso ? formatearFecha(u.ultimo_acceso) : 'Sin registros');
  set('verUsuarioMiembroDesde', u.miembro_desde ? formatearFecha(u.miembro_desde, true) : '—');

  var estadoActivo = (u.estado || 'activo') === 'activo';
  var elEstado = modalVerEl.querySelector('#verUsuarioEstado');
  if (elEstado) {
    elEstado.className = 'status-badge status-badge--' + (estadoActivo ? 'active' : 'inactive');
    elEstado.textContent = estadoActivo ? 'Activo' : 'Inactivo';
  }
}

function colorBadgePorRol(rol) {
  var r = (rol || '').trim().toLowerCase();
  if (r === 'admin' || r === 'administrador') return 'active';
  if (r === 'docente') return 'info';
  return 'pending';
}

function formatearFecha(valor, soloFecha) {
  var fecha = new Date(String(valor).replace(' ', 'T'));
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
 * botón de la fila y envía los cambios vía AJAX a cuentaUsuario/update.
 */
function initEditarUsuarioModal() {
  var modalEditarEl = document.getElementById('modalEditarUsuario');
  if (!modalEditarEl) return;

  aplicarValidacionNombreUsuario(modalEditarEl.querySelector('#euNombreUsuario'));
  aplicarValidacionSelectRequerido(modalEditarEl.querySelector('#euRol'), 'Debe seleccionar un rol.');

  document.addEventListener('click', function (e) {
    var btnEditar = e.target.closest('.btn-editar-usuario');
    if (!btnEditar) return;

    var d = btnEditar.dataset;
    var inputId = modalEditarEl.querySelector('#euId');
    var inputNombreUs = modalEditarEl.querySelector('#euNombreUsuario');
    var inputEmpleado = modalEditarEl.querySelector('#euEmpleado');
    var selectRol = modalEditarEl.querySelector('#euRol');

    if (inputId)       inputId.value       = d.id           || '';
    if (inputNombreUs) inputNombreUs.value = d.nombreUsuario || '';
    if (inputEmpleado) inputEmpleado.value = d.empleado      || '';
    if (selectRol)     selectRol.value     = d.idRol         || '';

    if (inputNombreUs) inputNombreUs.dispatchEvent(new Event('input'));
    if (selectRol) selectRol.dispatchEvent(new Event('change'));

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

    var btnGuardar = document.querySelector('button[form="formEditarUsuario"]');
    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formEditar);
      var res = await fetch(baseUrl + rutaControlador + '/update', { method: 'POST', body: formData });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        mostrarError(data.mensaje || 'No se pudo actualizar el usuario.', 'Error al editar usuario');
        return;
      }

      bootstrap.Modal.getOrCreateInstance(modalEditarEl).hide();

      if (data.data) {
        var u = data.data;
        var btnEditarEnFila = document.querySelector('.btn-editar-usuario[data-id="' + u.id + '"]');
        var tr = btnEditarEnFila ? btnEditarEnFila.closest('tr') : null;

        if (btnEditarEnFila) {
          btnEditarEnFila.dataset.nombreUsuario = u.nombre_usuario || u.usuario || '';
          btnEditarEnFila.dataset.idRol = u.id_rol || '';
        }

        if (tr) {
          var rolCell = tr.querySelectorAll('td')[2];
          if (rolCell) rolCell.textContent = capitalizarPalabras(u.rol || '');

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

function initCopyToClipboardButtons() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.credentials-display__copy');
    if (!btn) return;

    var targetId = btn.dataset.copyTarget;
    var targetEl = targetId ? document.getElementById(targetId) : null;
    if (!targetEl) return;

    var texto = targetEl.textContent || '';
    if (!navigator.clipboard) return;

    navigator.clipboard.writeText(texto).then(function () {
      var icono = btn.querySelector('i');
      if (!icono) return;
      var claseOriginal = icono.className;
      icono.className = 'bi bi-check-lg';
      setTimeout(function () { icono.className = claseOriginal; }, 1200);
    }).catch(function () {
      mostrarError('No se pudo copiar al portapapeles.');
    });
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

/**
 * Replica ucwords(mb_strtolower($str, 'UTF-8')) de PHP, usado en
 * index.php para el rol: pone en minúscula todo y luego mayúscula la
 * primera letra de cada palabra (ej. "SUPER ADMINISTRADOR" -> "Super Administrador").
 */
function capitalizarPalabras(str) {
  if (!str) return '';
  return str
    .toLowerCase()
    .split(' ')
    .map(function (palabra) {
      return palabra.charAt(0).toUpperCase() + palabra.slice(1);
    })
    .join(' ');
}