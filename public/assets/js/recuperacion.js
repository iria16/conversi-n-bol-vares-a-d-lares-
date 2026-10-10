/* SIGDE - recuperacion.js
 Vista de Recuperación de Acceso: aprobar/rechazar solicitudes, modal Ver Detalle,
 restablecimiento manual de contraseña y copiado de credenciales.*/

document.addEventListener('DOMContentLoaded', function () {
  initAprobarSolicitud();
  initRechazarSolicitud();
  initVerDetalleModal();
  initRestablecerClaveForm();
  SIGDEUI.copiarAlPortapapeles();
});

var baseUrl = window.BASE_URL || '';
var rutaControlador = 'recuperacionAcceso';

/**
 * Devuelve siempre el sobre del servidor {ok, mensaje, data}, tanto si SIGDEUI lo entrega
 * directo como si lo envuelve en otro objeto {ok, status, data}. Evita leer un nivel equivocado.
*/
function sobreDe(respuesta) {
  if (respuesta && respuesta.data && typeof respuesta.data === 'object' && 'ok' in respuesta.data) {
    return respuesta.data;
  }
  return respuesta || {};
}

/**
 * Refresca la tabla de solicitudes conservando filtros y página, en vez de recargar todo.
 * Si el formulario de filtros no expone refreshDataPanel(), recarga la página como respaldo.
*/
function refrescarListaSolicitudes() {
  var filterForm = document.querySelector('form.data-panel__filters, form.auto-filters');
  if (filterForm && typeof filterForm.refreshDataPanel === 'function') {
    filterForm.refreshDataPanel();
  } else {
    window.location.reload();
  }
}

/* Aprobar solicitud. Exige un motivo: una recuperación de acceso sin explicación no es
 * auditable, y el motivo queda en la bitácora.*/

// Delegado en document: las filas se reemplazan por AJAX al filtrar o paginar,
// y así los botones nuevos funcionan sin volver a inicializar nada.
function initAprobarSolicitud() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.aprobar-solicitud');
    if (btn) aprobarSolicitud(btn);
  });
}

async function aprobarSolicitud(btn) {
  var id = btn.dataset.solicitudId;
  var nombre = btn.dataset.usuarioNombre || '';

  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Aprobar solicitud?',
    texto: '¿Aprobar la solicitud de ' + nombre + '? Se generará una contraseña provisional.',
    confirmar: 'Sí, aprobar'
  });
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var sobre = sobreDe(await SIGDEUI.postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'aprobar'
    }));

    if (!sobre.ok) {
      SIGDEUI.mostrarError(sobre.mensaje || 'No se pudo aprobar la solicitud.');
      return;
    }

    if (!mostrarCredenciales(sobre.data)) {
      SIGDEUI.mostrarError('La solicitud se aprobó, pero la clave no llegó a la pantalla. '
        + 'Restablezca la contraseña de ese usuario para generar una nueva.');
      refrescarListaSolicitudes();
      return;
    }

    var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
    if (modalCredencialesEl) {
      SIGDEUI.alCerrarModal(modalCredencialesEl, function refrescarTrasCredenciales() {
        refrescarListaSolicitudes();
      });
    } else {
      refrescarListaSolicitudes();
    }
  } catch (err) {
    console.error('Error al aprobar la solicitud:', err);
    SIGDEUI.mostrarError('Error de conexión al aprobar la solicitud.');
  } finally {
    btn.disabled = false;
  }
}

/* ── Rechazar solicitud*/

// Delegado en document, por el mismo motivo que en aprobar.
function initRechazarSolicitud() {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.rechazar-solicitud');
    if (btn) rechazarSolicitud(btn);
  });
}

async function rechazarSolicitud(btn) {
  var id = btn.dataset.solicitudId;

  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Rechazar solicitud?',
    texto: '¿Está seguro que desea rechazar esta solicitud de recuperación de acceso?',
    confirmar: 'Sí, rechazar'
  });
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var sobre = sobreDe(await SIGDEUI.postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'rechazar'
    }));

    if (!sobre.ok) {
      SIGDEUI.mostrarError(sobre.mensaje || 'No se pudo rechazar la solicitud.');
      return;
    }

    SIGDEUI.exito(sobre.mensaje || 'Solicitud rechazada.', '¡Listo!');
    setTimeout(function () { refrescarListaSolicitudes(); }, 600);
  } catch (err) {
    console.error('Error al rechazar la solicitud:', err);
    SIGDEUI.mostrarError('Error de conexión al rechazar la solicitud.');
  } finally {
    btn.disabled = false;
  }
}

/* ── Modal "Ver Detalle"*/

function initVerDetalleModal() {
  var modalDetalleEl = document.getElementById('modalDetalleSolicitud');
  if (!modalDetalleEl) return;

  // Se dispara al abrir el modal desde un botón con data-bs-toggle (.ver-solicitud).
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

  SIGDEUI.getJson(baseUrl + rutaControlador + '/getDetalle?id=' + encodeURIComponent(id))
    .then(function (respuesta) {
      var sobre = sobreDe(respuesta);
      if (!sobre.ok) {
        SIGDEUI.mostrarError(sobre.mensaje || 'No se pudo cargar el detalle.');
        return;
      }
      // Los datos del detalle están en sobre.data (un nivel dentro del sobre del servidor).
      pintarDetalleSolicitud(modalDetalleEl, sobre.data);
    })
    .catch(function (err) {
      console.error('Error al cargar detalle:', err);
      SIGDEUI.mostrarError('Error de conexión al cargar el detalle.');
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
  set('detalleUsuario', d.usuario ? d.usuario : '—');
  set('detalleFecha', ((d.fecha || '') + ' ' + (d.hora || '')).trim() || '—');
  set('detalleCargo', d.cargo);
  // El modelo envía tipo y número por separado; aquí se unen para mostrar "V-22509285".
  set('detalleDocumento', d.tipo_documento && d.numero_documento
    ? d.tipo_documento + '-' + d.numero_documento
    : '—');
  set('detalleFechaAtencion', d.fecha_atencion);
  set('detalleAdmin', d.admin_atendio);

  var elEstado = modalDetalleEl.querySelector('#detalleEstado');
  if (elEstado) {
    elEstado.textContent = info[0];
    elEstado.className = 'status-badge status-badge--' + info[1];
  }

  // 'origen' distingue una solicitud del usuario ('solicitud') de un restablecimiento
  // directo del admin ('admin'), y cambia el título y el texto del bloque de atención.
  var elTitulo = modalDetalleEl.querySelector('#detalleAtencionTitulo');
  var elTexto = modalDetalleEl.querySelector('#detalleAtencionTexto');
  var fechaAtencion = d.fecha_atencion || '—';
  var admin = d.admin_atendio || '—';

  if (elTitulo && elTexto) {
    var elBloque = modalDetalleEl.querySelector('#detalleAtencionBloque');
    var elIcono  = modalDetalleEl.querySelector('#detalleAtencionIcono');
    var pendiente = !d.fecha_atencion || !d.admin_atendio;

    // Sin atender se marca como advertencia y con reloj de arena, para verlo de un vistazo.
    if (elBloque) {
      elBloque.classList.toggle('info-alert--warning', pendiente);
    }
    if (elIcono) {
      elIcono.classList.toggle('bi-hourglass-split', pendiente);
      elIcono.classList.toggle('bi-info-circle', !pendiente);
    }

    // Tres casos reales: sin atender, restablecida directo por el admin o atendida.
    // Así no se afirma "Atendida el — por —" cuando la solicitud sigue pendiente.
    if (pendiente) {
      elTitulo.textContent = 'Sin atender todavía';
      elTexto.innerHTML = 'Esta solicitud está pendiente de que un administrador la revise. '
        + 'Todavía no hay fecha de atención ni responsable.';
    } else if (d.origen === 'admin') {
      elTitulo.textContent = 'Restablecimiento directo';
      elTexto.innerHTML = 'Restablecida el <strong>' + SIGDEUI.escapeHtml(fechaAtencion) + '</strong> por <strong>' + SIGDEUI.escapeHtml(admin) + '</strong>, sin solicitud previa del usuario.';
    } else {
      elTitulo.textContent = 'Atención de la solicitud';
      elTexto.innerHTML = 'Atendida el <strong>' + SIGDEUI.escapeHtml(fechaAtencion) + '</strong> por <strong>' + SIGDEUI.escapeHtml(admin) + '</strong>.';
    }
  }
}

/* ── Restablecer contraseña manualmente*/

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
      var sobre = sobreDe(await SIGDEUI.postForm(baseUrl + rutaControlador + '/resetPassword', {
        usuario_id: usuarioId
      }));

      if (!sobre.ok) {
        SIGDEUI.mostrarError(sobre.mensaje || 'No se pudo restablecer la contraseña.');
        return;
      }

      var restablecerModalEl = document.getElementById('restablecerClaveModal');
      if (restablecerModalEl) {
        SIGDEUI.ocultarModal(restablecerModalEl);
      }

      // Si la clave no llegó, se avisa en vez de abrir una modal vacía.
      if (!mostrarCredenciales(sobre.data)) {
        SIGDEUI.mostrarError('La contraseña se restableció, pero no llegó a la pantalla. '
          + 'Repita el restablecimiento para generar una nueva.');
        formRestablecerClave.reset();
        refrescarListaSolicitudes();
        return;
      }

      var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
      if (modalCredencialesEl) {
        // { once: true }: el listener se quita solo; sin él se acumulaba en cada restablecimiento.
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
      SIGDEUI.mostrarError('Error de conexión al restablecer la contraseña.');
    } finally {
      if (btnRestablecerClave) {
        btnRestablecerClave.disabled = false;
        btnRestablecerClave.innerHTML = '<i class="bi bi-key-fill me-1" aria-hidden="true"></i>Restablecer clave';
      }
    }
  });
}

/* ── Modal de credenciales (compartido por aprobar y restablecer) ──*/

/**
 * Muestra la clave provisional en la modal de credenciales.
 * Devuelve false si la carga no trae clave, para que quien llama avise en vez de mostrar una modal vacía.
*/
function mostrarCredenciales(carga) {
  if (!carga || !carga.clave) return false;

  var elNombre = document.getElementById('credUsuarioNombre');
  var elClave = document.getElementById('credPassword');
  if (elNombre) elNombre.textContent = carga.nombre || carga.usuario || '';
  if (elClave) elClave.textContent = carga.clave;

  var modalEl = document.getElementById('modalCredencialesUsuario');
  if (modalEl) SIGDEUI.mostrarModal(modalEl);
  return true;
}