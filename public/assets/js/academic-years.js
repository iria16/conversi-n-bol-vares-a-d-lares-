/* SIGDE - comportamiento de Años Escolares */
document.addEventListener('DOMContentLoaded', function () {
  var baseUrl = window.BASE_URL || '';
  var routeUrl = baseUrl + 'index.php/academicYear/';
  var form = document.getElementById('formAnioEscolar');
  var editForm = document.getElementById('formEditarAnio');

  function enviar(url, options) {
    return fetch(routeUrl + url, options).then(function (response) {
      return response.json().then(function (data) { return { response: response, data: data }; });
    });
  }

  document.querySelectorAll('.editar-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('editarAnioId').value = button.dataset.id;
      document.getElementById('editarAnioNombre').value = button.dataset.nombre;
      document.getElementById('editarAnioInicio').value = button.dataset.inicio;
      document.getElementById('editarAnioCierre').value = button.dataset.cierre;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('editarAnioModal')).show();
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
          Swal.fire('Error', result.data.mensaje || 'No se pudo guardar el período.', 'error');
          return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).hide();
        window.location.reload();
      })
      .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
  }

  if (form) form.addEventListener('submit', function (event) { event.preventDefault(); guardar(form, 'storeAjax', 'nuevoAnioModal'); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); guardar(editForm, 'updateAjax', 'editarAnioModal'); });

  document.querySelectorAll('.toggle-anio').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var checked = checkbox.checked;
      Swal.fire({
        icon: 'warning',
        title: checked ? '¿Activar este período?' : '¿Desactivar este período?',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (!result.isConfirmed) { checkbox.checked = !checked; return; }
        enviar('toggleEstadoAjax', { method: 'POST', body: new URLSearchParams({ id: checkbox.dataset.id }) })
          .then(function (answer) {
            if (!answer.response.ok || !answer.data.ok) {
              checkbox.checked = !checked;
              Swal.fire('Error', answer.data.mensaje || 'No se pudo actualizar el estado.', 'error');
              return;
            }
            window.location.reload();
          })
          .catch(function () {
            checkbox.checked = !checked;
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
          });
      });
    });
  });
});
