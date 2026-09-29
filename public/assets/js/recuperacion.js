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
  SIGDEUI.copiarAlPortapapeles();
});

var baseUrl = window.BASE_URL || '';
var rutaControlador = 'recuperacionAcceso';

/* Los helpers de red (postForm/getJson) viven en SIGDEUI (sigde-ui.js):
   los usaban este módulo y academic-years.js con el mismo cuerpo. */

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
  confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Aprobar solicitud?',
    texto: '¿Aprobar la solicitud de ' + nombre + '? Se generará una contraseña provisional.',
    confirmar: 'Sí, aprobar'
  });
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var data = await SIGDEUI.postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'aprobar'
    });

    if (!data.ok) {
      SIGDEUI.mostrarError(data.mensaje || 'No se pudo aprobar la solicitud.');
      return;
    }

    mostrarCredenciales(data.data);

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

  var confirmacion = await SIGDEUI.confirmar({
    titulo: '¿Rechazar solicitud?',
    texto: '¿Está seguro que desea rechazar esta solicitud de recuperación de acceso?',
    confirmar: 'Sí, rechazar'
  });
  if (!confirmacion) return;

  btn.disabled = true;
  try {
    var data = await SIGDEUI.postForm(baseUrl + rutaControlador + '/updateEstado', {
      id: id,
      accion: 'rechazar'
    });

    if (!data.ok) {
      SIGDEUI.mostrarError(data.mensaje || 'No se pudo rechazar la solicitud.');
      return;
    }

    SIGDEUI.exito(data.mensaje || 'Solicitud rechazada.', '¡Listo!');
    setTimeout(function () { refrescarListaSolicitudes(); }, 600);
  } catch (err) {
    console.error('Error al rechazar la solicitud:', err);
    SIGDEUI.mostrarError('Error de conexión al rechazar la solicitud.');
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

    SIGDEUI.getJson(baseUrl + rutaControlador + '/getDetalle?id=' + encodeURIComponent(id))
      .then(function (data) {
        if (!data.ok) {
          SIGDEUI.mostrarError(data.mensaje || 'No se pudo cargar el detalle.');
          return;
        }
        /* Doble .data, a propósito, y es lo más fácil de romper de este archivo.
           SIGDEUI.getJson() resuelve {ok, status, data} donde ese `data` es el
           sobre que devolvió el servidor, {ok, mensaje, data:{...}}. La carga
           útil está un nivel más adentro: data.data.data. Por eso acá van dos.

           Antes se pasaba solo data.data, o sea el sobre, y pintarDetalleDetalle
           leía d.nombre, d.estado, d.fecha... sobre un objeto que no tiene nada
           de eso. No daba error: los `set()` caen a '—' y el estado se iba a
           'pendiente', así que el modal abría LIMPIO y en silencio, con el
           nombre, la fecha y el administrador en guiones. Parecía un modal vacío,
           y en realidad era un modal con el sobre equivocado. */
        pintarDetalleSolicitud(modalDetalleEl, data.data && data.data.data);
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
    // El documento se arma aquí y no en el modelo porque el modelo manda el tipo
    // y el número por separado, tal como se guardan. Lo que la persona ve, en
    // cambio, es el documento junto: "V-22509285", como se cita en un expediente.
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
      /* Estos dos ids (el bloque y su ícono) existían en el HTML desde antes y
         ningún script los tocaba: eran un cableado a medio hacer de un diseño
         anterior. Ahora sirven para lo que parecían haber nacido para, que es
         distinguir el estado a simple vista y no solo con el texto.

         Sin atender es una advertencia: hay algo esperando y el ícono de
         información ("aquí no hay nada que saber") engaña. Con el reloj de
         arena se lee de un vistazo que la solicitud está en cola. */
      var elBloque = modalDetalleEl.querySelector('#detalleAtencionBloque');
      var elIcono  = modalDetalleEl.querySelector('#detalleAtencionIcono');
      var pendiente = !d.fecha_atencion || !d.admin_atendio;

      if (elBloque) {
        elBloque.classList.toggle('info-alert--warning', pendiente);
      }
      if (elIcono) {
        elIcono.classList.toggle('bi-hourglass-split', pendiente);
        elIcono.classList.toggle('bi-info-circle', !pendiente);
      }

      /* El texto se arma según los tres casos que existen de verdad: atendida
         por alguien, restablecida de una, o sin atender todavía.

         Antes ponía "Atendida el <fecha> por <nombre>" siempre, y para una
         solicitud pendiente salía "Atendida el — por —": afirmaba algo que
         no había pasado y con dos huecos. Hoy no hay solicitudes pendientes en
         la base, pero el código las admite y el día que haya una, el modal iba
         a mentir con seguridad en vez de a lo sumo. */
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
      var data = await SIGDEUI.postForm(baseUrl + rutaControlador + '/resetPassword', {
        usuario_id: usuarioId
      });

      if (!data.ok) {
        SIGDEUI.mostrarError(data.mensaje || 'No se pudo restablecer la contraseña.');
        return;
      }

      var restablecerModalEl = document.getElementById('restablecerClaveModal');
      if (restablecerModalEl) {
        SIGDEUI.ocultarModal(restablecerModalEl);
      }

      mostrarCredenciales(data.data);

      var modalCredencialesEl = document.getElementById('modalCredencialesUsuario');
      if (modalCredencialesEl) {
        modalCredencialesEl.addEventListener('hidden.bs.modal', function () {
          formRestablecerClave.reset();
          refrescarListaSolicitudes();
        });
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

/* ── Modal de credenciales (compartido por aprobar y restablecer) ── */

function mostrarCredenciales(data) {
  var nombre = data ? data.nombre || '' : '';
  var clave = data ? data.clave || '' : '';

  var elNombre = document.getElementById('credUsuarioNombre');
  var elClave = document.getElementById('credPassword');
  if (elNombre) elNombre.textContent = nombre;
  if (elClave) elClave.textContent = clave;

  var modalEl = document.getElementById('modalCredencialesUsuario');
  if (modalEl) SIGDEUI.mostrarModal(modalEl);
}

