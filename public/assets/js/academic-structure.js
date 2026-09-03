/* SIGDE - comportamiento de Estructura Académica */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'index.php/academicStructure/';
  var newForm = document.getElementById('formNuevaEstructura');
  var editForm = document.getElementById('formEditarEstructura');

  function save(form, endpoint, modalId) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    fetch(route + endpoint, { method: 'POST', body: new FormData(form) })
      .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok || !result.data.ok) { Swal.fire('Error', result.data.mensaje || 'No se pudo guardar el registro.', 'error'); return; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).hide();
        window.location.reload();
      })
      .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
  }

  if (newForm) newForm.addEventListener('submit', function (event) { event.preventDefault(); save(newForm, 'storeAjax', 'nuevaEstructuraModal'); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); save(editForm, 'updateAjax', 'editarEstructuraModal'); });

  document.querySelectorAll('.editar-estructura').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('editarEstructuraId').value = button.dataset.id;
      document.getElementById('editarEstructuraAnio').value = button.dataset.anio;
      document.getElementById('editarEstructuraGrado').value = button.dataset.grado;
      document.getElementById('editarEstructuraSeccion').value = button.dataset.seccion;
      document.getElementById('editarEstructuraTurno').value = button.dataset.turno;
      document.getElementById('editarEstructuraCapacidad').value = button.dataset.capacidad;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('editarEstructuraModal')).show();
    });
  });
});
