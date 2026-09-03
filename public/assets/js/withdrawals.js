/* SIGDE - comportamiento de Retiros Pendientes */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'index.php/withdrawal/';
  var detailModal = document.getElementById('detalleRetiroModal');

  document.querySelectorAll('.ver-retiro').forEach(function (button) {
    button.addEventListener('click', function () {
      var set = function (id, value) { var element = document.getElementById(id); if (element) element.textContent = value || '—'; };
      set('retiroDetalleNombre', button.dataset.estudiante);
      set('retiroDetalleAvatar', button.dataset.estudiante ? button.dataset.estudiante.charAt(0).toUpperCase() : 'E');
      set('retiroDetalleCedula', button.dataset.cedula);
      set('retiroDetalleEstado', button.dataset.estado);
      set('retiroDetalleGrado', button.dataset.grado);
      set('retiroDetalleFecha', button.dataset.fecha);
      set('retiroDetalleMotivo', button.dataset.motivo);
      set('retiroDetalleObservaciones', button.dataset.observaciones);
      bootstrap.Modal.getOrCreateInstance(detailModal).show();
    });
  });

  document.querySelectorAll('.aprobar-retiro, .rechazar-retiro').forEach(function (button) {
    button.addEventListener('click', function () {
      var aprobado = button.classList.contains('aprobar-retiro');
      Swal.fire({ icon: 'question', title: aprobado ? '¿Aprobar retiro?' : '¿Rechazar retiro?', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' }).then(function (result) {
        if (!result.isConfirmed) return;
        var body = new URLSearchParams({ id: button.dataset.id, estado: aprobado ? 'APROBADO' : 'RECHAZADO' });
        fetch(route + 'updateStatusAjax', { method: 'POST', body: body })
          .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
          .then(function (answer) { if (!answer.ok || !answer.data.ok) { Swal.fire('Error', answer.data.mensaje || 'No se pudo actualizar el retiro.', 'error'); return; } window.location.reload(); })
          .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
      });
    });
  });
});
