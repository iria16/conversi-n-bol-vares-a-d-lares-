/* SIGDE - comportamiento de Asignación Docente */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'teacherAssignment/';
  var newForm = document.getElementById('formNuevaAsignacion');
  var editForm = document.getElementById('formEditarAsignacion');

  function submit(form) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    SIGDEUI.enviar(route + 'asignar', { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data.ok) { SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar la asignación.'); return; }
        window.location.reload();
      })
      .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
  }

  if (newForm) newForm.addEventListener('submit', function (event) { event.preventDefault(); submit(newForm); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); submit(editForm); });

  document.querySelectorAll('.editar-asignacion').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById('editarAsignacionModal');
      document.getElementById('editarAsignacionEstructura').value = button.dataset.id;
      document.getElementById('editarAsignacionDocente').value = '';
      SIGDEUI.mostrarModal(modal);
    });
  });

  document.querySelectorAll('.quitar-asignacion').forEach(function (button) {
    button.addEventListener('click', function () {
      SIGDEUI.confirmar({ icono: 'warning', titulo: '¿Retirar asignación?', confirmar: 'Sí, retirar' }).then(function (confirmado) {
        if (!confirmado) return;
        SIGDEUI.enviar(route + 'retirar', { metodo: 'POST', cuerpo: { id: button.dataset.id } })
          .then(function (answer) { if (!answer.ok || !answer.data.ok) { SIGDEUI.mostrarError(answer.data.mensaje || 'No se pudo retirar.'); return; } window.location.reload(); });
      });
    });
  });
});
