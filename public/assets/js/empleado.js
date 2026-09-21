/**
 * empleados.js
 * Lógica del módulo Personal > Empleados (Views/empleados/index.php).
 *
 * Lo que resuelve aquí:
 *  - Ver detalle: pide el empleado a empleados/show y lo pinta en el modal.
 *  - Editar: pide el empleado, rellena el formulario y lo envía a empleados/update.
 *  - Registrar: envía el wizard a empleados/store.
 *  - Activar/desactivar: el switch de la tabla llama a empleados/toggleStatus.
 *
 * Los filtros, la búsqueda y la paginación NO se manejan aquí: eso ya lo
 * resuelve main.js con el formulario del data-panel.
 *
 * Formato de respuesta del servidor (JsonResponse): { ok, mensaje, data }.
 * En un 422, `data` trae los errores por campo: { campo: 'mensaje' }.
 *
 * Contrato con el HTML de los modales (hay que respetarlo al armarlos):
 *  - #nuevoEmpleadoModal + #formNuevoEmpleado  → registro (wizard).
 *  - #verEmpleadoModal                          → detalle. Cada elemento con
 *      data-campo="nombre_completo" recibe el texto de ese campo. Campos
 *      disponibles: nombre_completo, cedula, cargo, estado, sexo,
 *      fecha_nacimiento, fecha_ingreso, fecha_egreso, nacionalidad.
 *      Una <img data-campo-foto> recibe la foto si existe.
 *  - #editarEmpleadoModal + #formEditarEmpleado → edición. Cada input se llena
 *      por su `name` (igual al campo de la BD: primer_nombre, id_cargo,
 *      fecha_ingreso, etc.) y debe existir un <input type="hidden" name="id">.
 *  - Los botones de enviar pueden estar dentro del <form> o fuera con el
 *    atributo form="idDelFormulario".
 *  - Para mostrar errores por campo uso .is-invalid y .invalid-feedback.
 *
 * @author Logística
 */
(() => {
  'use strict';

  const IDS = {
    modalNuevo:      'nuevoEmpleadoModal',
    formNuevo:       'formNuevoEmpleado',
    modalVer:        'verEmpleadoModal',
    modalEditar:     'editarEmpleadoModal',
    formEditar:      'formEditarEmpleado',
    formFiltros:     'filtrosEmpleados',
  };

  // ---------- URLs ----------

  // Saco la URL base del propio formulario de filtros (empleados/index), así
  // no dependo de cómo esté montado BASE_URL: 'show', 'update', etc. se
  // resuelven como hermanos de esa ruta.
  const formFiltros = document.getElementById(IDS.formFiltros);
  const urlBase = new URL(
    formFiltros ? formFiltros.getAttribute('action') : window.location.pathname,
    window.location.href
  );

  const urlAccion = (accion, params = {}) => {
    const url = new URL(accion, urlBase);
    Object.entries(params).forEach(([clave, valor]) => url.searchParams.set(clave, valor));
    return url;
  };

  /**
   * Llama a una acción del controlador y devuelve { status, ok, mensaje, data }.
   * Si el servidor responde algo que no es JSON (por ejemplo, la sesión venció
   * y el router devolvió HTML), lanzo un error legible en vez de romper.
   */
  async function llamar(accion, { method = 'GET', params = {}, body = null } = {}) {
    const respuesta = await fetch(urlAccion(accion, params), {
      method,
      body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    });

    let json;
    try {
      json = await respuesta.json();
    } catch {
      throw new Error('El servidor devolvió una respuesta inesperada. Recarga la página e intenta de nuevo.');
    }

    return { status: respuesta.status, ...json };
  }

  // ---------- Avisos (SweetAlert2) ----------

  const alerta = (icon, title, text = '') => {
    if (window.Swal) {
      return Swal.fire({ icon, title, text, confirmButtonText: 'Aceptar' });
    }
    window.alert(text ? `${title}\n${text}` : title);
    return Promise.resolve();
  };

  // Aviso corto que se cierra solo; devuelve una promesa que se resuelve al cerrarse.
  const avisoExito = (title) => {
    if (window.Swal) {
      return Swal.fire({
        toast: true, position: 'top-end', icon: 'success', title,
        showConfirmButton: false, timer: 1200, timerProgressBar: true,
      });
    }
    window.alert(title);
    return Promise.resolve();
  };

  // Después de guardar recargo la página: así se actualizan la tabla, la
  // paginación y las tarjetas de estadísticas, conservando filtros y página.
  const recargar = () => window.location.reload();

  // ---------- Modales ----------

  const instanciaModal = (id) => {
    const el = document.getElementById(id);
    return el && window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(el) : null;
  };

  // ---------- Errores por campo ----------

  function limpiarErrores(form) {
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback[data-generado]').forEach((el) => el.remove());
  }

  /**
   * Marca cada campo con error y muestra su mensaje debajo.
   * Devuelve los mensajes que no pudo asociar a ningún campo del formulario.
   */
  function mostrarErrores(form, errores) {
    const sueltos = [];

    Object.entries(errores).forEach(([campo, mensaje]) => {
      const input = form.elements[campo];
      if (!input || input instanceof RadioNodeList) {
        sueltos.push(mensaje);
        return;
      }

      input.classList.add('is-invalid');

      let feedback = input.parentElement.querySelector('.invalid-feedback');
      if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.dataset.generado = '1';
        input.insertAdjacentElement('afterend', feedback);
      }
      feedback.textContent = mensaje;
    });

    return sueltos;
  }

  // ---------- Envío de formularios ----------

  const botonesEnvio = (form) => [
    ...form.querySelectorAll('[type="submit"]'),
    ...(form.id ? document.querySelectorAll(`[type="submit"][form="${form.id}"]`) : []),
  ];

  function manejarEnvio(form, accion, modalId) {
    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      limpiarErrores(form);

      const botones = botonesEnvio(form);
      botones.forEach((b) => { b.disabled = true; });

      try {
        const r = await llamar(accion, { method: 'POST', body: new FormData(form) });

        if (r.ok) {
          const modal = instanciaModal(modalId);
          if (modal) modal.hide();
          await avisoExito(r.mensaje || 'Guardado correctamente.');
          recargar();
          return;
        }

        const errores = r.data && typeof r.data === 'object' && !Array.isArray(r.data) ? r.data : {};
        const sueltos = mostrarErrores(form, errores);
        alerta('error', r.mensaje || 'No se pudo guardar.', sueltos.join('\n'));
      } catch (error) {
        alerta('error', 'No se pudo completar la solicitud', error.message);
      } finally {
        botones.forEach((b) => { b.disabled = false; });
      }
    });
  }

  // ---------- Carga de un empleado ----------

  async function cargarEmpleado(id) {
    const r = await llamar('show', { params: { id } });
    if (!r.ok) {
      throw new Error(r.mensaje || 'No se pudo cargar el empleado.');
    }
    return r.data;
  }

  // "2024-03-15" -> "15/03/2024" (sin pasar por Date, para evitar líos de zona horaria)
  const formatearFecha = (iso) => {
    if (!iso) return '—';
    const [anio, mes, dia] = String(iso).split('-');
    return dia && mes && anio ? `${dia}/${mes}/${anio}` : String(iso);
  };

  // ---------- Ver detalle ----------

  function pintarDetalle(modalEl, d) {
    const valores = {
      nombre_completo:  d.nombre_completo,
      cedula:           `${d.tipo_documento}-${d.numero_documento}`,
      cargo:            d.cargo || 'Sin cargo',
      estado:           d.estado === 'ACTIVO' ? 'Activo' : 'Inactivo',
      sexo:             { F: 'Femenino', M: 'Masculino' }[d.sexo] || d.sexo,
      nacionalidad:     d.nacionalidad,
      fecha_nacimiento: formatearFecha(d.fecha_nacimiento),
      fecha_ingreso:    formatearFecha(d.fecha_ingreso),
      fecha_egreso:     formatearFecha(d.fecha_egreso),
    };

    modalEl.querySelectorAll('[data-campo]').forEach((el) => {
      el.textContent = valores[el.dataset.campo] ?? '—';
    });

    const foto = modalEl.querySelector('[data-campo-foto]');
    if (foto) {
      if (d.foto) {
        // La foto se guarda como ruta absoluta del sitio (/uploads/...).
        foto.src = new URL(d.foto.replace(/^\//, ''), new URL('../', urlBase)).href;
        foto.hidden = false;
      } else {
        foto.hidden = true;
      }
    }
  }

  async function verEmpleado(id) {
    const modalEl = document.getElementById(IDS.modalVer);
    if (!modalEl) return;

    try {
      pintarDetalle(modalEl, await cargarEmpleado(id));
      instanciaModal(IDS.modalVer).show();
    } catch (error) {
      alerta('error', 'No se pudo abrir el detalle', error.message);
    }
  }

  // ---------- Editar ----------

  async function editarEmpleado(id) {
    const form = document.getElementById(IDS.formEditar);
    if (!form) return;

    try {
      const d = await cargarEmpleado(id);

      form.reset();
      limpiarErrores(form);

      // Cada input se llena por su name; lo que la BD devuelve como null se deja vacío.
      Object.entries(d).forEach(([campo, valor]) => {
        const input = form.elements[campo];
        if (input && valor !== null) input.value = valor;
      });
      if (form.elements.id) form.elements.id.value = d.id_empleado;

      instanciaModal(IDS.modalEditar).show();
    } catch (error) {
      alerta('error', 'No se pudo abrir la edición', error.message);
    }
  }

  // ---------- Activar / desactivar ----------

  async function cambiarEstado(interruptor) {
    const activar = interruptor.checked;
    interruptor.disabled = true;

    try {
      const body = new FormData();
      body.append('id', interruptor.dataset.empleadoId);

      const r = await llamar('toggleStatus', { method: 'POST', body });
      if (!r.ok) {
        throw new Error(r.mensaje || 'No se pudo actualizar el estado.');
      }

      await avisoExito(activar ? 'Empleado activado.' : 'Empleado desactivado.');
      recargar();
    } catch (error) {
      // Si falló, devuelvo el switch a su posición anterior.
      interruptor.checked = !activar;
      interruptor.disabled = false;
      alerta('error', 'No se pudo cambiar el estado', error.message);
    }
  }

  // ---------- Eventos ----------

  // Delegación en document: los botones de la tabla pueden ser reemplazados
  // por main.js al filtrar o paginar, y así siguen funcionando.
  document.addEventListener('click', (evento) => {
    const ver = evento.target.closest('.ver-empleado');
    if (ver) {
      verEmpleado(ver.dataset.empleadoId);
      return;
    }

    const editar = evento.target.closest('.editar-empleado');
    if (editar) {
      editarEmpleado(editar.dataset.empleadoId);
    }
  });

  document.addEventListener('change', (evento) => {
    const interruptor = evento.target.closest('.toggle-estado');
    if (interruptor) {
      cambiarEstado(interruptor);
    }
  });

  const formNuevo = document.getElementById(IDS.formNuevo);
  if (formNuevo) manejarEnvio(formNuevo, 'store', IDS.modalNuevo);

  const formEditar = document.getElementById(IDS.formEditar);
  if (formEditar) manejarEnvio(formEditar, 'update', IDS.modalEditar);

  // Al cerrar el modal de registro dejo el formulario limpio. Filtro por
  // target porque los modales anidados (título, institución) también
  // disparan hidden.bs.modal y burbujean hasta aquí.
  const modalNuevo = document.getElementById(IDS.modalNuevo);
  if (modalNuevo && formNuevo) {
    modalNuevo.addEventListener('hidden.bs.modal', (evento) => {
      if (evento.target !== modalNuevo) return;
      formNuevo.reset();
      limpiarErrores(formNuevo);
    });
  }
})();