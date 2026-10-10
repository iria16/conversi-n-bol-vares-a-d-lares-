/* SIGDE - comportamiento de Asignación Docente*/
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'teacherAssignment/';
  var newForm = document.getElementById('formNuevaAsignacion');
  var editForm = document.getElementById('formEditarAsignacion');
  var newStructure = document.getElementById('nuevoAsignacionSeccion');
  var newTeacher = document.getElementById('nuevoAsignacionDocente');
  var editStructure = document.getElementById('editarAsignacionEstructura');
  var editAssignmentId = document.getElementById('editarAsignacionId');
  var editTeacher = document.getElementById('editarAsignacionDocente');
  var editType = document.getElementById('editarAsignacionTipo');
  var editDate = document.getElementById('editarAsignacionFecha');
  document.querySelectorAll('.data-panel__filters select').forEach(function (select) {
    select.addEventListener('change', function () {
      select.form.requestSubmit();
    });
  });

  function cargarDocentes(select, estructuraId) {
    if (!select) return;
    select.replaceChildren(new Option('Cargando docentes...', ''));
    select.disabled = true;
    if (!estructuraId) {
      select.replaceChildren(new Option('Seleccione primero una sección...', ''));
      select.disabled = false;
      return;
    }
    return SIGDEUI.enviar(route + 'getDocentesPorEstructura?id=' + encodeURIComponent(estructuraId))
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudieron cargar los docentes.');
          select.replaceChildren(new Option('No se pudieron cargar los docentes', ''));
          return;
        }
        select.replaceChildren(new Option('Seleccione un docente...', ''));
        result.data.data.forEach(function (docente) {
          var label = docente.nombre + (docente.cargo ? ' · ' + docente.cargo : '');
          select.add(new Option(label, docente.id));
        });
        if (result.data.data.length === 0) {
          select.replaceChildren(new Option('No hay docentes asignados a este turno', ''));
        }
      })
      .catch(function () {
        SIGDEUI.mostrarError('No se pudieron cargar los docentes de este turno.');
        select.replaceChildren(new Option('Error al cargar docentes', ''));
      })
      .finally(function () { select.disabled = false; });
  }

  if (newStructure) {
    newStructure.addEventListener('change', function () {
      cargarDocentes(newTeacher, newStructure.value);
    });
  }

  function submit(form, isEdit) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    var action = isEdit && editAssignmentId.value ? 'actualizar' : 'asignar';
    SIGDEUI.enviar(route + action, { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data.ok) { SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar la asignación.'); return; }
        window.location.reload();
      })
      .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
  }

  if (newForm) newForm.addEventListener('submit', function (event) { event.preventDefault(); submit(newForm, false); });
  if (editForm) editForm.addEventListener('submit', function (event) { event.preventDefault(); submit(editForm, true); });

  document.querySelectorAll('.editar-asignacion').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById('editarAsignacionModal');
      document.getElementById('editarAsignacionEstructura').value = button.dataset.id;
      editAssignmentId.value = button.dataset.asignacionId || '';
      editType.value = button.dataset.tipoId || '';
      editDate.value = button.dataset.fechaInicio || editDate.defaultValue;
      cargarDocentes(editTeacher, button.dataset.id).then(function () {
        editTeacher.value = button.dataset.docenteId || '';
      });
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
