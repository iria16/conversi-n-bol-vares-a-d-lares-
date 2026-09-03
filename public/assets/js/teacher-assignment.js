/* SIGDE - comportamiento de Asignación Docente */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'index.php/teacherAssignment/';
  var newForm = document.getElementById('formNuevaAsignacion');
  var editForm = document.getElementById('formEditarAsignacion');

  function submit(form) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    fetch(route + 'assignAjax', { method: 'POST', body: new FormData(form) })
      .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok || !result.data.ok) { Swal.fire('Error', result.data.mensaje || 'No se pudo guardar la asignación.', 'error'); return; }
        window.location.reload();
      })
      .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
  }

  if (newForm) newForm.addEventListener('submit', function (event) { event.preventDefault(); submit(newForm); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); submit(editForm); });

  document.querySelectorAll('.editar-asignacion').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById('editarAsignacionModal');
      document.getElementById('editarAsignacionEstructura').value = button.dataset.id;
      document.getElementById('editarAsignacionDocente').value = '';
      bootstrap.Modal.getOrCreateInstance(modal).show();
    });
  });

  document.querySelectorAll('.quitar-asignacion').forEach(function (button) {
    button.addEventListener('click', function () {
      Swal.fire({ icon: 'warning', title: '¿Retirar asignación?', showCancelButton: true, confirmButtonText: 'Sí, retirar', cancelButtonText: 'Cancelar' }).then(function (result) {
        if (!result.isConfirmed) return;
        fetch(route + 'removeAjax', { method: 'POST', body: new URLSearchParams({ id: button.dataset.id }) })
          .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
          .then(function (answer) { if (!answer.ok || !answer.data.ok) { Swal.fire('Error', answer.data.mensaje || 'No se pudo retirar.', 'error'); return; } window.location.reload(); });
      });
    });
  });
});
