/* SIGDE - comportamiento de Años Escolares*/
document.addEventListener('DOMContentLoaded', function () {
  var baseUrl = window.BASE_URL || '';
  var routeUrl = baseUrl + 'academicYear/';
  var form = document.getElementById('formAnioEscolar');
  var editForm = document.getElementById('formEditarAnio');

  document.querySelectorAll('.data-panel__filters select').forEach(function (select) {
    select.addEventListener('change', function () {
      select.form.requestSubmit();
    });
  });

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

  function fechaVisible(value) {
    if (!value) return '—';
    var partes = value.slice(0, 10).split('-');
    return partes.length === 3 ? partes[2] + '/' + partes[1] + '/' + partes[0] : value;
  }

  function texto(id, value) {
    var element = document.getElementById(id);
    if (element) element.textContent = value || '—';
  }

  /* Escribe un valor y, si no hay nada que mostrar, esconde la etiqueta y el valor.
 Un período activo no tiene "cerrado por" todavía, y una fila con guion hace creer que
 el dato se perdió; es preferible que ese par no exista en pantalla.*/
  function dato(id, valor) {
    var destino = document.getElementById(id);
    var etiqueta = document.getElementById(id + 'Etiqueta');
    if (!destino) return;
    var texto = (valor || '').trim();
    destino.textContent = texto === '' ? '—' : texto;
    if (etiqueta) etiqueta.hidden = texto === '';
    destino.hidden = texto === '';
  }

  document.querySelectorAll('.ver-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      texto('verAnioNombre', button.dataset.nombre);
      texto('verAnioEstado', button.dataset.estado);
      texto('verAnioInicio', fechaVisible(button.dataset.inicio));
      texto('verAnioCierre', fechaVisible(button.dataset.cierre));
      dato('verAnioIniciadoPor', button.dataset.iniciadoPor);
      dato('verAnioIniciadoEn', button.dataset.iniciadoEn ? button.dataset.iniciadoEn.replace('T', ' ') : '');
      dato('verAnioReabiertoPor', button.dataset.reabiertoPor);
      dato('verAnioReapertura', button.dataset.reapertura);
      dato('verAnioCerradoPor', button.dataset.cerradoPor);
      dato('verAnioCerradoEn', button.dataset.cerradoEn ? button.dataset.cerradoEn.replace('T', ' ') : '');

      /* Un aviso cuando el período no tiene ni inicio ni cierre registrados: es la forma de
 decir "esto no se registró" sin dejar cuatro filas vacías en la pantalla.*/
      var sinRegistro = !button.dataset.iniciadoPor && !button.dataset.cerradoPor;
      var aviso = document.getElementById('verAnioSinRegistro');
      if (aviso) aviso.hidden = !sinRegistro;

      SIGDEUI.mostrarModal(document.getElementById('verAnioModal'));
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

  function transicionar(button, endpoint, confirmation) {
    SIGDEUI.confirmar(confirmation).then(function (confirmed) {
      if (!confirmed) return;
      enviar(endpoint, {
        method: 'POST',
        body: new URLSearchParams({ id: button.dataset.id })
      }).then(function (result) {
        if (!result.response.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo cambiar el estado del año escolar.');
          return;
        }
        window.location.reload();
      }).catch(function () {
        SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
      });
    });
  }

  document.querySelectorAll('.iniciar-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      transicionar(button, 'iniciar', {
        icono: 'question',
        titulo: '¿Iniciar año escolar?',
        texto: 'Se iniciará el año escolar ' + button.dataset.nombre + ' como período vigente.',
        confirmar: 'Iniciar año'
      });
    });
  });

  document.querySelectorAll('.cerrar-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      transicionar(button, 'cerrar', {
        icono: 'warning',
        titulo: '¿Cerrar año escolar?',
        texto: 'Está a punto de cerrar el año escolar ' + button.dataset.nombre + '.\nAl cerrarlo, dejará de ser el período escolar vigente y las operaciones académicas normales deberán realizarse en el nuevo año escolar.\n¿Desea continuar?',
        confirmar: 'Sí, cerrar año escolar'
      });
    });
  });

  /* Reapertura: a diferencia de iniciar y cerrar, aquí no basta un sí o un no. El motivo se
 pide en un modal porque queda registrado con la reapertura y es lo único que explica
 por qué se deshizo un cierre.*/
  document.querySelectorAll('.reabrir-anio').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById('reabrirAnioModal');
      var idAnio = document.getElementById('reabrirAnioId');
      var nombre = document.getElementById('reabrirAnioNombre');
      var motivo = document.getElementById('reabrirAnioMotivo');
      if (!modal || !idAnio || !nombre || !motivo) return;
      idAnio.value = button.dataset.id || '';
      nombre.value = button.dataset.nombre || '';
      motivo.value = '';
      SIGDEUI.mostrarModal(modal);
    });
  });

  var formularioReabrir = document.getElementById('formReabrirAnio');
  if (formularioReabrir) {
    formularioReabrir.addEventListener('submit', function (evento) {
      evento.preventDefault();
      var motivo = document.getElementById('reabrirAnioMotivo');
      if (!motivo || motivo.value.trim().length < 10) {
        SIGDEUI.mostrarError('Explique el motivo con al menos 10 caracteres: quedará registrado.');
        if (motivo) motivo.focus();
        return;
      }
      var cuerpo = new URLSearchParams({
        id: document.getElementById('reabrirAnioId').value,
        motivo: motivo.value.trim()
      });
      enviar('reabrir', { method: 'POST', body: cuerpo })
        .then(function (answer) {
          if (!answer.ok || !answer.data.ok) {
            SIGDEUI.mostrarError(answer.data.mensaje || 'No se pudo reabrir el año escolar.');
            return;
          }
          SIGDEUI.exito(answer.data.mensaje || "Año escolar reabierto correctamente.");
          SIGDEUI.ocultarModal(document.getElementById('reabrirAnioModal'));
          window.location.reload();
        })
        .catch(function () {
          SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
        });
    });
  }
});
