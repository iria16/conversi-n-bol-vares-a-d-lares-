/* ==========================================================================
   SIGDE - catalogo.js
   Comportamiento específico de la vista de Gestión de Catálogos:
   selector de tipo de catálogo, toggle de estado, alta y edición de
   elementos vía AJAX.
   Depende de: Bootstrap 5 (modales), SweetAlert2 (opcional),
   window.BASE_URL (definido globalmente en layouts/app.php).
   ========================================================================== */

// --- Inicialización Módulo ---
function initCatalogoModule() {
  initSelectorTipoCatalogo();
  initToggleEstadoElemento();
  initCrearElementoForm();
  initEditarElementoModal();
}

// Inicialización segura del DOM que evita problemas cuando los scripts
// se cargan con 'defer', 'async' o al final de la plantilla layout.
if (document.readyState === 'interactive' || document.readyState === 'complete') {
  initCatalogoModule();
} else {
  document.addEventListener('DOMContentLoaded', initCatalogoModule);
}

// --- Configuración Global de Rutas ---
var rawBaseUrl = window.BASE_URL || '';
var baseUrl = rawBaseUrl.endsWith('/') ? rawBaseUrl : rawBaseUrl + '/';
var rutaControlador = 'catalogo';

// --- Funciones Auxiliares ---
function getTipoCatalogoActivo() {
  var select = document.getElementById('tipoCatalogo');
  return select ? select.value : '';
}

function mostrarError(mensaje, titulo) {
  if (window.Swal) {
    Swal.fire({
      icon: 'error',
      title: titulo || 'Error',
      text: mensaje
    });
  } else {
    alert((titulo ? titulo + '\n' : '') + mensaje);
  }
}

function aplicarValidacionNombreElemento(input) {
  if (!input) return;

  var actualizarMensaje = function () {
    if (input.validity.valueMissing) {
      input.setCustomValidity('El nombre es obligatorio.');
    } else if (input.validity.tooLong) {
      input.setCustomValidity('El nombre no puede superar los 50 caracteres.');
    } else {
      input.setCustomValidity('');
    }
  };

  input.addEventListener('input', actualizarMensaje);
  input.addEventListener('blur', actualizarMensaje);
  actualizarMensaje();
}

// --- Selector de Catálogo (Filtro por Tipo) ---
function initSelectorTipoCatalogo() {
  var select = document.getElementById('tipoCatalogo');
  if (!select) return;

  select.addEventListener('change', function () {
    var url = new URL(window.location.href);
    url.searchParams.set('tipo', select.value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
  });
}

// --- Cambiar Estado (Activo / Inactivo) ---
function initToggleEstadoElemento() {
  document.querySelectorAll('.toggle-estado').forEach(asociarToggleEstado);
}

function asociarToggleEstado(input) {
  input.addEventListener('change', async function (e) {
    var checkbox = e.target;
    var id = checkbox.dataset.elementoId;
    var tipoActivo = getTipoCatalogoActivo();
    var nuevoEstadoActivo = checkbox.checked;
    var accion = nuevoEstadoActivo ? 'activar' : 'desactivar';
    var checkboxAnterior = !checkbox.checked;
    var tr = checkbox.closest('tr');
    var badge = tr ? tr.querySelector('.status-badge') : null;

    var confirmacion = false;
    if (window.Swal) {
      var result = await Swal.fire({
        title: '¿' + accion.charAt(0).toUpperCase() + accion.slice(1) + ' elemento?',
        text: '¿Está seguro que desea ' + accion + ' este elemento del catálogo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, ' + accion,
        cancelButtonText: 'Cancelar',
        reverseButtons: true
      });
      confirmacion = result.isConfirmed;
    } else {
      confirmacion = confirm('¿Está seguro que desea ' + accion + ' este elemento del catálogo?');
    }

    if (!confirmacion) {
      checkbox.checked = checkboxAnterior;
      return;
    }

    try {
      // 'toggleStatus' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await fetch(baseUrl + rutaControlador + '/toggleStatus', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&tipo=' + encodeURIComponent(tipoActivo)
      });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        checkbox.checked = checkboxAnterior;
        mostrarError(data.mensaje || 'No se pudo actualizar el estado del elemento.');
        return;
      }

      if (tr) tr.classList.toggle('is-muted', !nuevoEstadoActivo);
      if (badge) {
        badge.className = 'status-badge status-badge--' + (nuevoEstadoActivo ? 'active' : 'inactive');
        badge.textContent = nuevoEstadoActivo ? 'Activo' : 'Inactivo';
      }

      if (window.Swal) {
        Swal.fire({
          title: '¡Listo!',
          text: 'Elemento ' + accion + 'do correctamente.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        });
      }
    } catch (err) {
      checkbox.checked = checkboxAnterior;
      console.error('Error al cambiar estado:', err);
      mostrarError('Error de conexión al actualizar el estado.');
    }
  });
}

// --- Formulario Crear Elemento ---
function initCrearElementoForm() {
  var formCrear = document.getElementById('formNuevoElemento');
  if (!formCrear) return;

  var modalCrearEl = document.getElementById('modalNuevoElemento');
  var btnGuardar = document.querySelector('button[form="formNuevoElemento"]');

  aplicarValidacionNombreElemento(document.getElementById('nuevoElementoNombre'));

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
      var formData = new FormData(formCrear);
      // 'store' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await fetch(baseUrl + rutaControlador + '/store', {
        method: 'POST',
        body: formData
      });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        var msgError = data.mensaje || 'No se pudo crear el elemento. Verifique los datos.';
        if (data.data && typeof data.data === 'object') {
          var detalles = Object.values(data.data).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        mostrarError(msgError, 'Error al crear elemento');
        return;
      }

      // Esperamos a que el modal termine de cerrarse (hidden.bs.modal, no
      // solo el hide() disparado) antes de mostrar el Swal de éxito. Si el
      // Swal se dispara mientras Bootstrap todavía está procesando el
      // cierre, el foco puede quedar en un descendiente del modal justo
      // cuando Bootstrap le pone aria-hidden, lo que generaba el warning
      // "Blocked aria-hidden on an element because its descendant
      // retained focus" en consola.
      if (modalCrearEl) {
        var bsModal = bootstrap.Modal.getInstance(modalCrearEl) || new bootstrap.Modal(modalCrearEl);
        modalCrearEl.addEventListener('hidden.bs.modal', function mostrarSwalCreado() {
          modalCrearEl.removeEventListener('hidden.bs.modal', mostrarSwalCreado);

          formCrear.reset();
          formCrear.classList.remove('was-validated');

          if (window.Swal) {
            Swal.fire({
              icon: 'success',
              title: 'Creado',
              text: data.mensaje || 'Elemento creado correctamente.',
              timer: 1500,
              showConfirmButton: false
            }).then(function () {
              window.location.reload();
            });
          } else {
            window.location.reload();
          }
        }, { once: true });
        bsModal.hide();
      } else {
        formCrear.reset();
        formCrear.classList.remove('was-validated');
        window.location.reload();
      }

    } catch (err) {
      console.error('Error al procesar petición AJAX (store):', err);
      mostrarError('Ocurrió un fallo de conexión al crear el elemento.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Crear elemento';
      }
    }
  });
}

// --- Modal y Formulario Editar Elemento ---
function initEditarElementoModal() {
  var modalEditarEl = document.getElementById('modalEditarElemento');
  if (!modalEditarEl) return;

  aplicarValidacionNombreElemento(modalEditarEl.querySelector('#editarElementoNombre'));

  // Handler delegatorio para abrir el modal con datos de la fila
  document.addEventListener('click', function (e) {
    var btnEditar = e.target.closest('.editar-elemento');
    if (!btnEditar) return;
    e.preventDefault();

    var d = btnEditar.dataset;
    var inputId = modalEditarEl.querySelector('#editarElementoId');
    var inputNombre = modalEditarEl.querySelector('#editarElementoNombre');

    if (inputId) inputId.value = d.elementoId || '';
    if (inputNombre) inputNombre.value = d.elementoNombre || '';

    if (inputNombre) inputNombre.dispatchEvent(new Event('input'));

    var formEditInner = modalEditarEl.querySelector('form');
    if (formEditInner) formEditInner.classList.remove('was-validated');

    bootstrap.Modal.getOrCreateInstance(modalEditarEl).show();
  });

  var formEditar = modalEditarEl.querySelector('#formEditarElemento');
  if (!formEditar) return;

  formEditar.addEventListener('submit', async function (e) {
    e.preventDefault();

    if (!formEditar.checkValidity()) {
      e.stopPropagation();
      formEditar.classList.add('was-validated');
      return;
    }
    formEditar.classList.remove('was-validated');

    var btnGuardar = document.querySelector('button[form="formEditarElemento"]');
    if (btnGuardar) {
      btnGuardar.disabled = true;
      btnGuardar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';
    }

    try {
      var formData = new FormData(formEditar);
      // 'update' es el nombre real en la whitelist de router.php para 'catalogo'
      var res = await fetch(baseUrl + rutaControlador + '/update', {
        method: 'POST',
        body: formData
      });
      var data = await res.json();

      if (!res.ok || !data.ok) {
        var msgError = data.mensaje || 'No se pudo actualizar el elemento.';
        if (data.data && typeof data.data === 'object') {
          var detalles = Object.values(data.data).join('\n');
          if (detalles) msgError += '\n\n' + detalles;
        }
        mostrarError(msgError, 'Error al editar elemento');
        return;
      }

      // Mismo motivo que en initCrearElementoForm(): esperamos a
      // hidden.bs.modal antes de disparar el Swal, para evitar el
      // conflicto de foco/aria-hidden entre Bootstrap y SweetAlert2.
      modalEditarEl.addEventListener('hidden.bs.modal', function mostrarSwalActualizado() {
        modalEditarEl.removeEventListener('hidden.bs.modal', mostrarSwalActualizado);

        var id = formData.get('id');
        // 'nuevoNombre' se toma de data.data.nombre (lo que el backend
        // realmente guardó, ya pasado por capitalizarTitulo()) y no de
        // formData.get('nombre') (el texto crudo tipeado por el
        // usuario). Si se usara el valor crudo, la fila mostraría
        // "licenciado en informatica" en pantalla mientras la BD
        // tiene "Licenciado en Informática", hasta el próximo reload.
        var nuevoNombre = (data.data && data.data.nombre) || formData.get('nombre');
        var btnEditarEnFila = document.querySelector('.editar-elemento[data-elemento-id="' + id + '"]');
        var tr = btnEditarEnFila ? btnEditarEnFila.closest('tr') : null;

        if (btnEditarEnFila) {
          btnEditarEnFila.dataset.elementoNombre = nuevoNombre || '';
        }

        if (tr) {
          var nombreCell = tr.querySelector('td');
          if (nombreCell) nombreCell.textContent = nuevoNombre || '';

          var toggleInput = tr.querySelector('.toggle-estado');
          if (toggleInput) toggleInput.setAttribute('aria-label', 'Activar o desactivar ' + (nuevoNombre || ''));
        }

        if (window.Swal) {
          Swal.fire({
            icon: 'success',
            title: 'Actualizado',
            text: data.mensaje || 'Elemento actualizado correctamente.',
            timer: 1500,
            showConfirmButton: false
          });
        }
      }, { once: true });

      bootstrap.Modal.getOrCreateInstance(modalEditarEl).hide();

    } catch (err) {
      console.error('Error al procesar petición AJAX (update):', err);
      mostrarError('Ocurrió un fallo de conexión al editar el elemento.');
    } finally {
      if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-floppy me-1"></i>Guardar cambios';
      }
    }
  });
}