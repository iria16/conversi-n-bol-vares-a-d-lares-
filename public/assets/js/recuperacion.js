/* ==========================================================================
   SIGDE - recuperacion.js
   Comportamiento específico de la vista de Recuperación de Acceso:
   aprobar / rechazar solicitudes, modal Ver Detalle, restablecimiento
   manual de contraseña y copiado de credenciales.
   Depende de: Bootstrap 5 (modales), SweetAlert2 (opcional),
   window.BASE_URL (definido globalmente en layouts/app.php).
   Nota: el backend (RecuperacionAccesoController) responde siempre vía
   BaseController::jsonResponse(), forma {ok, mensaje, data} — nunca
   'datos'. No usar el fallback data.datos || data.data de otros
   módulos legados; aquí data.data es siempre la clave correcta.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initAprobarSolicitud();
  initRechazarSolicitud();
  initVerDetalleModal();
  initRestablecerClaveForm();
  initCopyToClipboardButtons();
});

var baseUrl = window.BASE_URL || '';
var rutaControlador = 'recuperacionAcceso';

/**
 * POST application/x-www-form-urlencoded. `data` es un objeto plano
 * { campo: valor }.
 */
function postForm(url, data) {
  var params = new URLSearchParams(data || {});

  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: params
  }).then(function (res) { return res.json(); });
}

function getJson(url) {
  return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (res) { return res.json(); });
}

/**
 * Refresca la tabla de solicitudes conservando los filtros/página
 * actuales, en vez de recargar toda la página (que los perdería, ya
 * que initAutoFilters() de main.js aplica los filtros vía AJAX sin
 * tocar la URL en cada cambio de <select>/búsqueda). Si por algún
 * motivo el formulario de filtros no está en el DOM o no expuso su
 * refreshDataPanel(), se recurre a location.reload() como respaldo.
 */
function refrescarListaSolicitudes() {
  var filterForm = document.querySelector('form.data-panel__filters, form.auto-filters');
  if (filterForm && typeof filterForm.refreshDataPanel === 'function') {
    filterForm.refreshDataPanel();
  } else {
    window.location.reload();
  }
}

/* ── Aprobar solicitud ──────────────────────────────────────────── */

// Delegado en document (no en cada botón): las filas se reemplazan por
// completo cuando refrescarListaSolicitudes()/refreshDataPanel() actualiza
// la tabla vía AJAX (filtro, búsqueda, paginación), así que los botones
// .aprobar-solicitud de las filas nuevas nunca existieron cuando
// DOMContentLoaded corrió initAprobarSolicitud() la primera vez. Atar el
// listener a document evita tener que "reinicializar" nada después de
// cada refresco del panel.
function initAprobarSolicitud() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.aprobar-solicitud');
    if (btn) aprobarSolicitud(btn);
  });
}

async function aprobarSolicitud(btn) {
  var id = btn.dataset.solicitudId;
  var nombre = btn.dataset.usuarioNombre || '';

  var confirmacion = false;
  if (window.Swal) {
    var result = await Swal.fire({
      title: '¿Aprobar solicitud?',
      text: '¿Aprobar la solicitud de ' + nombre + '? Se generará una contraseña provisional.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, aprobar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });
    confirmacion = result.isConfirmed;
  } else {
    confirmacion = confirm('¿Aprobar la solicitud de ' + nombre + '? Se generará una contraseña provisional.');
  }
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var data = await postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'aprobar'
    });

    if (!data.ok) {
      mostrarError(data.mensaje || 'No se pudo aprobar la solicitud.');
      return;
    }

    mostrarCredenciales(data.data);

    var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
    if (modalCredencialesEl) {
      modalCredencialesEl.addEventListener('hidden.bs.modal', function () {
        refrescarListaSolicitudes();
      }, { once: true });
    } else {
      refrescarListaSolicitudes();
    }
  } catch (err) {
    console.error('Error al aprobar la solicitud:', err);
    mostrarError('Error de conexión al aprobar la solicitud.');
  } finally {
    btn.disabled = false;
  }
}

/* ── Rechazar solicitud ─────────────────────────────────────────── */

// Mismo motivo que initAprobarSolicitud(): delegado en document para que
// siga funcionando después de que el panel se refresque vía AJAX.
function initRechazarSolicitud() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.rechazar-solicitud');
    if (btn) rechazarSolicitud(btn);
  });
}

async function rechazarSolicitud(btn) {
  var id = btn.dataset.solicitudId;

  var confirmacion = false;
  if (window.Swal) {
    var result = await Swal.fire({
      title: '¿Rechazar solicitud?',
      text: '¿Está seguro que desea rechazar esta solicitud de recuperación de acceso?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, rechazar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    });
    confirmacion = result.isConfirmed;
  } else {
    confirmacion = confirm('¿Rechazar esta solicitud de recuperación de acceso?');
  }
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var data = await postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'rechazar'
    });

    if (!data.ok) {
      mostrarError(data.mensaje || 'No se pudo rechazar la solicitud.');
      return;
    }

    if (window.Swal) {
      Swal.fire({
        icon: 'success',
        title: '¡Listo!',
        text: data.mensaje || 'Solicitud rechazada.',
        timer: 1500,
        showConfirmButton: false
      });
    }
    setTimeout(function () { refrescarListaSolicitudes(); }, 600);
  } catch (err) {
    console.error('Error al rechazar la solicitud:', err);
    mostrarError('Error de conexión al rechazar la solicitud.');
  } finally {
    btn.disabled = false;
  }
}

/* ── Modal "Ver Detalle" ────────────────────────────────────────── */

function initVerDetalleModal() {
  var modalDetalleEl = document.getElementById('modalDetalleSolicitud');
  if (!modalDetalleEl) return;

  // Se dispara al abrir el modal por atributo data-bs-toggle (ver botones .ver-solicitud)
  modalDetalleEl.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn) return;
    var id = btn.dataset.solicitudId || btn.dataset.id;
    if (id) cargarYMostrarDetalle(id, modalDetalleEl);
  });
}

function cargarYMostrarDetalle(id, modalDetalleEl) {
  var elNombre = modalDetalleEl.querySelector('#detalleNombre');
  if (elNombre) elNombre.textContent = 'Cargando...';

  getJson(baseUrl + rutaControlador + '/getDetalle?id=' + encodeURIComponent(id))
    .then(function (data) {
      if (!data.ok) {
        mostrarError(data.mensaje || 'No se pudo cargar el detalle.');
        return;
      }
      pintarDetalleSolicitud(modalDetalleEl, data.data);
    })
    .catch(function (err) {
      console.error('Error al cargar detalle:', err);
      mostrarError('Error de conexión al cargar el detalle.');
    });
}

function pintarDetalleSolicitud(modalDetalleEl, d) {
  if (!d) return;

  var estadoInfo = {
    aprobado: ['Aprobado', 'active'],
    rechazado: ['Rechazado', 'inactive'],
    pendiente: ['Pendiente', 'pending']
  };
  var estadoClave = (d.estado || 'pendiente').toLowerCase();
  var info = estadoInfo[estadoClave] || estadoInfo.pendiente;

  var set = function (id, val) {
    var el = modalDetalleEl.querySelector('#' + id);
    if (el) el.textContent = val || '—';
  };

  set('detalleNombre', d.nombre);
  set('detalleUsuario', d.usuario ? '@' + d.usuario : '—');
  set('detalleFecha', ((d.fecha || '') + ' ' + (d.hora || '')).trim() || '—');
  set('detalleFechaAtencion', d.fecha_atencion);
  set('detalleAdmin', d.admin_atendio);

  var elEstado = modalDetalleEl.querySelector('#detalleEstado');
  if (elEstado) {
    elEstado.textContent = info[0];
    elEstado.className = 'status-badge status-badge--' + info[1];
  }

  // 'origen' distingue una solicitud real del usuario ('solicitud')
  // de un restablecimiento directo del admin sin solicitud previa
  // ('admin') — ver RecuperacionAccesoModel::getDetalleSolicitud().
  // Se alterna el título/texto del bloque de atención para no mostrar
  // ambos casos como si fueran lo mismo.
  var elTitulo = modalDetalleEl.querySelector('#detalleAtencionTitulo');
  var elTexto = modalDetalleEl.querySelector('#detalleAtencionTexto');
  var fechaAtencion = d.fecha_atencion || '—';
  var admin = d.admin_atendio || '—';

  if (elTitulo && elTexto) {
    if (d.origen === 'admin') {
      elTitulo.textContent = 'Restablecimiento directo';
      elTexto.innerHTML = 'Restablecida el <strong>' + escapeHtml(fechaAtencion) + '</strong> por <strong>' + escapeHtml(admin) + '</strong>, sin solicitud previa del usuario.';
    } else {
      elTitulo.textContent = 'Atención de la solicitud';
      elTexto.innerHTML = 'Atendida el <strong>' + escapeHtml(fechaAtencion) + '</strong> por <strong>' + escapeHtml(admin) + '</strong>.';
    }
  }
}

/* ── Restablecer contraseña manualmente ─────────────────────────── */

function initRestablecerClaveForm() {
  var formRestablecerClave = document.getElementById('formRestablecerClave');
  if (!formRestablecerClave) return;

  var selectUsuario = document.getElementById('restablecerClave__usuario');
  var btnRestablecerClave = document.getElementById('btnRestablecerClave');

  formRestablecerClave.addEventListener('submit', async function (e) {
    e.preventDefault();

    var usuarioId = selectUsuario ? selectUsuario.value : '';
    if (!usuarioId) {
      if (selectUsuario) selectUsuario.classList.add('is-invalid');
      return;
    }
    if (selectUsuario) selectUsuario.classList.remove('is-invalid');

    if (btnRestablecerClave) {
      btnRestablecerClave.disabled = true;
      btnRestablecerClave.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Restableciendo...';
    }

    try {
      var data = await postForm(baseUrl + rutaControlador + '/resetPassword', {
        usuario_id: usuarioId
      });

      if (!data.ok) {
        mostrarError(data.mensaje || 'No se pudo restablecer la contraseña.');
        return;
      }

      var restablecerModalEl = document.getElementById('restablecerClaveModal');
      if (restablecerModalEl) {
        bootstrap.Modal.getOrCreateInstance(restablecerModalEl).hide();
      }

      mostrarCredenciales(data.data);

      var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
      if (modalCredencialesEl) {
        modalCredencialesEl.addEventListener('hidden.bs.modal', function () {
          formRestablecerClave.reset();
          refrescarListaSolicitudes();
        }, { once: true });
      } else {
        formRestablecerClave.reset();
        refrescarListaSolicitudes();
      }
    } catch (err) {
      console.error('Error al restablecer la contraseña:', err);
      mostrarError('Error de conexión al restablecer la contraseña.');
    } finally {
      if (btnRestablecerClave) {
        btnRestablecerClave.disabled = false;
        btnRestablecerClave.innerHTML = '<i class="bi bi-key-fill me-1" aria-hidden="true"></i>Restablecer clave';
      }
    }
  });
}

/* ── Modal de credenciales (compartido por aprobar y restablecer) ── */

function mostrarCredenciales(data) {
  var nombre = data ? data.nombre || '' : '';
  var clave = data ? data.clave || '' : '';

  var elNombre = document.getElementById('credUsuarioNombre');
  var elClave = document.getElementById('credPassword');
  if (elNombre) elNombre.textContent = nombre;
  if (elClave) elClave.textContent = clave;

  var modalEl = document.getElementById('modalCredencialesUsuario');
  if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

/* ── Copiar al portapapeles (usuario / contraseña provisional) ──── */

function initCopyToClipboardButtons() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy-target]');
    if (!btn) return;

    var targetId = btn.dataset.copyTarget;
    var targetEl = targetId ? document.getElementById(targetId) : null;
    if (!targetEl || !navigator.clipboard) return;

    var texto = targetEl.textContent || '';

    navigator.clipboard.writeText(texto).then(function () {
      var icono = btn.querySelector('i');
      if (!icono) return;
      var claseOriginal = icono.className;
      icono.className = 'bi bi-check-lg';
      setTimeout(function () { icono.className = claseOriginal; }, 1500);
    }).catch(function () {
      mostrarError('No se pudo copiar al portapapeles.');
    });
  });
}

/* ── Utilidades ─────────────────────────────────────────────────── */

function escapeHtml(texto) {
  var div = document.createElement('div');
  div.textContent = texto == null ? '' : String(texto);
  return div.innerHTML;
}

function mostrarError(mensaje, titulo) {
  if (window.Swal) {
    Swal.fire({ icon: 'error', title: titulo || 'Error', text: mensaje });
  } else {
    alert((titulo ? titulo + '\n' : '') + mensaje);
  }
}