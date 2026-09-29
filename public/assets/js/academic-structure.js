/* SIGDE - comportamiento de Estructura Académica */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'academicStructure/';
  var newForm = document.getElementById('formNuevaEstructura');
  var editForm = document.getElementById('formEditarEstructura');

  function save(form, endpoint, modalId) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    SIGDEUI.enviar(route + endpoint, { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data.ok) { SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar el registro.'); return; }
        SIGDEUI.ocultarModal(document.getElementById(modalId));
        window.location.reload();
      })
      .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
  }

  if (newForm) newForm.addEventListener('submit', function (event) { event.preventDefault(); save(newForm, 'store', 'nuevaEstructuraModal'); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); save(editForm, 'update', 'editarEstructuraModal'); });

  document.querySelectorAll('.editar-estructura').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('editarEstructuraId').value = button.dataset.id;
      document.getElementById('editarEstructuraAnio').value = button.dataset.anio;
      document.getElementById('editarEstructuraGrado').value = button.dataset.grado;
      document.getElementById('editarEstructuraSeccion').value = button.dataset.seccion;
      document.getElementById('editarEstructuraTurno').value = button.dataset.turno;
      document.getElementById('editarEstructuraCapacidad').value = button.dataset.capacidad;
      SIGDEUI.mostrarModal(document.getElementById('editarEstructuraModal'));
    });
  });
});
