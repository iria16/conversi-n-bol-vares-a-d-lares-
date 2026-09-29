/* SIGDE - comportamiento del módulo de Retiros.
   Registrar retiro: solo secretaria. Aprobar/rechazar: solo directivo. */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'withdrawal/';
  var detailModal = document.getElementById('detalleRetiroModal');

  document.querySelectorAll('.ver-retiro').forEach(function (button) {
    button.addEventListener('click', function () {
      var set = function (id, value) { var element = document.getElementById(id); if (element) element.textContent = value || '—'; };
      set('retiroDetalleNombre', button.dataset.estudiante);
      set('retiroDetalleCedula', button.dataset.cedula);
      set('retiroDetalleEstado', button.dataset.estado);
      set('retiroDetalleGrado', button.dataset.grado);
      set('retiroDetalleFecha', button.dataset.fecha);
      set('retiroDetalleMotivo', button.dataset.motivo);
      set('retiroDetalleObservaciones', button.dataset.observaciones);
      SIGDEUI.mostrarModal(detailModal);
    });
  });

  document.querySelectorAll('.aprobar-retiro, .rechazar-retiro').forEach(function (button) {
    button.addEventListener('click', function () {
      var aprobado = button.classList.contains('aprobar-retiro');
      SIGDEUI.confirmar({ titulo: aprobado ? '¿Aprobar retiro?' : '¿Rechazar retiro?' }).then(function (confirmado) {
        if (!confirmado) return;
        var body = new URLSearchParams({ id: button.dataset.id, estado: aprobado ? 'APROBADO' : 'RECHAZADO' });
        SIGDEUI.enviar(route + 'updateEstado', { metodo: 'POST', cuerpo: body })
          .then(function (answer) { if (!answer.ok || !answer.data.ok) { SIGDEUI.mostrarError(answer.data.mensaje || 'No se pudo actualizar el retiro.'); return; } window.location.reload(); })
          .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
      });
    });
  });

  var formRegistrar = document.getElementById('formRegistrarRetiro');

  if (formRegistrar) {
    var selEstudiante = document.getElementById('retiroEstudiante');
    var selMotivo     = document.getElementById('retiroMotivo');
    var btnRegistrar  = document.getElementById('btnRegistrarRetiro');

    formRegistrar.addEventListener('submit', function (event) {
      event.preventDefault();

      [selEstudiante, selMotivo].forEach(function (select) {
        if (select) select.classList.toggle('is-invalid', !select.value);
      });
      if (!selEstudiante.value || !selMotivo.value) {
        SIGDEUI.mostrarAviso('warning', 'Campos incompletos', 'Selecciona el estudiante y el motivo del retiro.');
        return;
      }

      btnRegistrar.disabled = true;
      btnRegistrar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Registrando...';

      SIGDEUI.enviar(route + 'store', { metodo: 'POST', cuerpo: new FormData(formRegistrar) })
        .then(function (result) {
          if (!result.ok || !result.data.ok) {
            // store() responde los errores por campo en `data`; se muestra el
            // primero para que el mensaje sea útil y no el genérico.
            var porCampo = result.data.data;
            var mensaje = (porCampo && typeof porCampo === 'object' && Object.keys(porCampo).length)
              ? porCampo[Object.keys(porCampo)[0]]
              : (result.data.mensaje || 'No se pudo registrar el retiro.');
            SIGDEUI.mostrarAviso('error', 'No se pudo registrar', mensaje);
            return;
          }

          var modalEl = document.getElementById('registrarRetiroModal');
          if (modalEl) {
            SIGDEUI.alCerrarModal(modalEl, function recargarTrasRegistro() { window.location.reload(); });
            SIGDEUI.ocultarModal(modalEl);
            return;
          }
          window.location.reload();
        })
        .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); })
        .finally(function () {
          btnRegistrar.disabled = false;
          btnRegistrar.innerHTML = '<i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar retiro';
        });
    });
  }
});
