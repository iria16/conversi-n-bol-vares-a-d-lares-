/* SIGDE - comportamiento de Años Escolares */
document.addEventListener('DOMContentLoaded', function () {
  var baseUrl = window.BASE_URL || '';
  var routeUrl = baseUrl + 'academicYear/';
  var form = document.getElementById('formAnioEscolar');
  var editForm = document.getElementById('formEditarAnio');

  // La red la resuelve SIGDEUI.enviar() (sigde-ui.js); antes cada módulo
  // tenía su propia copia de este helper.
  function enviar(url, options) {
    return SIGDEUI.enviar(routeUrl + url, {
      metodo: (options && options.method) || 'GET',
      cuerpo: options && options.body
    }).then(function (r) {
      return { response: { ok: r.ok, status: r.status }, data: r.data };
    });
  }

  document.querySelectorAll('.editar-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('editarAnioId').value = button.dataset.id;
      document.getElementById('editarAnioNombre').value = button.dataset.nombre;
      document.getElementById('editarAnioInicio').value = button.dataset.inicio;
      document.getElementById('editarAnioCierre').value = button.dataset.cierre;
      SIGDEUI.mostrarModal(document.getElementById('editarAnioModal'));
    });
  });

  function guardar(formulario, endpoint, modalId) {
    if (!formulario.checkValidity()) {
      formulario.classList.add('was-validated');
      return;
    }
    enviar(endpoint, { method: 'POST', body: new FormData(formulario) })
      .then(function (result) {
        if (!result.response.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar el período.');
          return;
        }
        SIGDEUI.ocultarModal(document.getElementById(modalId));
        window.location.reload();
      })
      .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
  }

  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); guardar(form, 'store', 'nuevoAnioModal'); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); guardar(editForm, 'update', 'editarAnioModal'); });

  document.querySelectorAll('.toggle-anio').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var checked = checkbox.checked;
      SIGDEUI.confirmar({
        icono: 'warning',
        titulo: checked ? '¿Activar este período?' : '¿Desactivar este período?'
      }).then(function (confirmado) {
        if (!confirmado) { checkbox.checked = !checked; return; }
        enviar('toggleStatus', { method: 'POST', body: new URLSearchParams({ id: checkbox.dataset.id }) })
          .then(function (answer) {
            if (!answer.response.ok || !answer.data.ok) {
              checkbox.checked = !checked;
              SIGDEUI.mostrarError(answer.data.mensaje || 'No se pudo actualizar el estado.');
              return;
            }
            window.location.reload();
          })
          .catch(function () {
            checkbox.checked = !checked;
            SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
          });
      });
    });
  });
});
