/* SIGDE - comportamiento del módulo de Retiros.
 Registrar retiro: solo secretaria. Aprobar/rechazar: solo directivo.*/
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'withdrawal/';
  var detailModal = document.getElementById('detalleRetiroModal');

  var detalleCuerpo = document.getElementById('retiroDetalleCuerpo');

  /* El detalle lo arma el servidor (retiro/ver.php) y aquí solo se vuelca en el
 modal: así la vista del modal y la de página completa no se pueden desfasar.*/
  document.querySelectorAll('.ver-retiro').forEach(function (button) {
    button.addEventListener('click', function () {
      detalleCuerpo.innerHTML = '<div class="text-center text-support py-4">'
        + '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Cargando...</div>';
      SIGDEUI.mostrarModal(detailModal);

      fetch(route + 'ver/' + encodeURIComponent(button.dataset.id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (respuesta) {
          if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
          return respuesta.text();
        })
        .then(function (html) { detalleCuerpo.innerHTML = html; })
        .catch(function () {
          detalleCuerpo.innerHTML = '<div class="info-alert"><span class="info-alert__icon">'
            + '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>'
            + '<div><p class="info-alert__title">No se pudo cargar el detalle</p>'
            + '<p class="info-alert__text">Intente nuevamente.</p></div></div>';
        });
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
    var selMotivo      = document.getElementById('retiroMotivo');
    var btnRegistrar   = document.getElementById('btnRegistrarRetiro');
    var inputBuscar    = document.getElementById('retiroBuscarEstudiante');
    var inputMatricula = document.getElementById('retiroIdMatricula');
    var feedbackBuscar = document.getElementById('retiroBuscarFeedback');
    var cajaResultados = document.getElementById('retiroResultados');
    var panelDatos     = document.getElementById('retiroEstudianteDatos');

    /* Los resultados de la última búsqueda. Cada fila de la lista guarda solo el
 índice dentro de este arreglo, nunca los datos del alumno: `escapeHtml()`
 escapa & < > pero NO las comillas, así que un nombre con comilla rompería
 el atributo data- y, en el peor caso, inyectaría marcado.*/
    var resultados = [];

    /* El alumno elegido, tal como lo devolvió el servidor. Es la referencia para
 el resumen de la confirmación: leerlo del panel ya escrito sería depender
 de lo que se ve en pantalla en vez de de lo que se va a guardar.*/
    var elegido = null;

    var ponerTexto = function (id, valor) {
      var element = document.getElementById(id);
      if (element) element.textContent = valor || '—';
    };

    /* El borde lo pone `.input-icon-group:has(.form-control.is-invalid)` y el
 texto se muestra a la fuerza con `d-block`: el mensaje es hermano del
 grupo, no del input, así que la regla de Bootstrap
 `.is-invalid ~ .invalid-feedback` nunca llega a aplicarse.*/
    var marcarFaltaEstudiante = function (falta) {
      inputBuscar.classList.toggle('is-invalid', falta);
      feedbackBuscar.classList.toggle('d-block', falta);
    };

    /* Volver a escribir descarta lo elegido: el campo vuelve a ser la fuente de
 la verdad. Si no, se podría registrar al alumno que quedó escrito en el
 buscador mientras el panel mostraba otro distinto.*/
    var limpiarSeleccion = function () {
      resultados = [];
      elegido = null;
      inputMatricula.value = '';
      panelDatos.classList.add('d-none');
      marcarFaltaEstudiante(false);
    };

    var avisar = function (html) {
      cajaResultados.innerHTML = html;
      cajaResultados.classList.remove('d-none');
    };

    var avisoError = function (titulo, texto) {
      return '<div class="info-alert"><span class="info-alert__icon">'
        + '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>'
        + '<div><p class="info-alert__title">' + titulo + '</p>'
        + '<p class="info-alert__text">' + SIGDEUI.escapeHtml(texto) + '</p></div></div>';
    };

    /* Un resultado: botón si se puede retirar, texto apagado si no. El motivo
 viene del servidor, y sin él la secretaria no distinguiría "no existe"
 de "ya egresó".*/
    var filaResultado = function (item, indice) {
      var grado = item.grado + ' "' + item.seccion + '"';
      if (item.elegible) {
        return '<button type="button" class="list-group-item list-group-item-action retiro-resultado" data-indice="' + indice + '">'
          + '<span class="retiro-resultado__principal">' + SIGDEUI.escapeHtml(item.nombre) + '</span>'
          + '<span class="retiro-resultado__secundario">' + SIGDEUI.escapeHtml(item.cedula) + ' · '
          + SIGDEUI.escapeHtml(grado) + ' · ' + SIGDEUI.escapeHtml(item.anio) + '</span>'
          + '</button>';
      }
      return '<div class="list-group-item retiro-resultado retiro-resultado--bloqueado">'
        + '<span class="retiro-resultado__principal">' + SIGDEUI.escapeHtml(item.nombre) + '</span>'
        + '<span class="retiro-resultado__secundario">' + SIGDEUI.escapeHtml(item.cedula) + ' · '
        + SIGDEUI.escapeHtml(grado) + '</span>'
        + '<span class="retiro-resultado__motivo">' + SIGDEUI.escapeHtml(item.motivo) + '</span>'
        + '</div>';
    };

    var mostrarSeleccion = function (item) {
      elegido = item;
      inputMatricula.value = item.id_matricula;
      marcarFaltaEstudiante(false);
      ponerTexto('retiroDatosNombre', item.nombre);
      ponerTexto('retiroDatosCedula', item.cedula);
      ponerTexto('retiroDatosGrado', item.grado + ' — Sección ' + item.seccion);
      ponerTexto('retiroDatosAnio', item.anio);
      panelDatos.classList.remove('d-none');
    };

var buscar = SIGDEUI.esDebounced(400, function () {
      var texto = inputBuscar.value.trim();

      if (texto.length < 3) {
        cajaResultados.classList.add('d-none');
        cajaResultados.innerHTML = '';
        return;
      }

      avisar('<div class="text-center text-support py-2"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Buscando...</div>');

      SIGDEUI.getJson(route + 'buscarEstudiante?q=' + encodeURIComponent(texto))
        .then(function (respuesta) {
          // Si mientras se consultaba se corrigió el texto, el resultado ya no
          // corresponde a lo que hay en el campo: se descarta.
          if (inputBuscar.value.trim() !== texto) return;

          if (!respuesta.ok || !respuesta.data) {
            avisar(avisoError('No se pudo buscar',
              (respuesta.data && respuesta.data.mensaje) || 'Intente nuevamente.'));
            return;
          }

          var datos = respuesta.data.data;

          if (datos.esperando) {
            cajaResultados.classList.add('d-none');
            cajaResultados.innerHTML = '';
            return;
          }

          resultados = datos.resultados || [];

          if (!resultados.length) {
            // El servidor puede mandar un aviso: sirve para lo que se escribió a
            // medias, como un prefijo sin número. Si no, la cédula no existe.
            avisar(datos.aviso
              ? '<div class="info-alert info-alert--warning"><span class="info-alert__icon">'
                + '<i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>'
                + '<div><p class="info-alert__title">Falta completar</p>'
                + '<p class="info-alert__text">' + SIGDEUI.escapeHtml(datos.aviso) + '</p></div></div>'
              : '<div class="info-alert"><span class="info-alert__icon">'
                + '<i class="bi bi-person-x" aria-hidden="true"></i></span>'
                + '<div><p class="info-alert__title">Cédula no encontrada</p>'
                + '<p class="info-alert__text">Ningún estudiante tiene una cédula que empiece por "'
                + SIGDEUI.escapeHtml(texto) + '".</p></div></div>');
            return;
          }

          avisar('<div class="detail-section__label">'
            + (resultados.length === 1 ? '1 coincidencia' : resultados.length + ' coincidencias')
            + '</div><div class="list-group list-group-flush">'
            + resultados.map(filaResultado).join('') + '</div>');

          /* Con una sola coincidencia no hay nada que decidir: se elige sola.
 El documento no se repite, así que dos resultados nunca son la misma
 persona. Con varias es por un prefijo corto: se deja que la
 secretaria complete la cédula en vez de elegir entre alumnos.*/
          if (resultados.length === 1 && resultados[0].elegible) {
            mostrarSeleccion(resultados[0]);
            cajaResultados.classList.add('d-none');
          }
        })
        .catch(function () {
          if (inputBuscar.value.trim() !== texto) return;
          avisar(avisoError('No se pudo buscar', 'No hay conexión con el servidor.'));
        });
    });

    /* Borrar la cédula borra al estudiante, en el acto.

 El borrado va FUERA del debounce a propósito. Si se limpiara junto con la
 búsqueda, el formulario seguiría teniendo la matrícula del alumno durante
 los 400 ms de espera, y en esa ventana se podía registrar al estudiante
 cuya cédula ya se había borrado. La consulta sí espera; la limpieza no.*/
    inputBuscar.addEventListener('input', function () {
      limpiarSeleccion();
      buscar();
    });

    /* Al elegir de la lista se muestran los datos completos del alumno: es lo
 que la secretaria revisa antes de confirmar.*/
    cajaResultados.addEventListener('click', function (evento) {
      var fila = evento.target.closest('.retiro-resultado');
      if (!fila || fila.tagName !== 'BUTTON') return;

      /* El alumno que quedó elegido. En la lista se guarda en la variable de más
 arriba; esta es la copia local de ese click.*/
      var seleccionado = resultados[Number(fila.dataset.indice)];
      if (!seleccionado) return;

      mostrarSeleccion(seleccionado);
      cajaResultados.classList.add('d-none');
      selMotivo.focus();
    });

    /* El resumen que se lee al confirmar. Va en una sola línea porque SweetAlert2
 escribe `text` con textContent: un `\n` ahí no produce un salto.*/
    var resumenSolicitud = function (observaciones) {
      var motivo = selMotivo.options[selMotivo.selectedIndex].text;
      var partes = [
        elegido.nombre + ' (' + elegido.cedula + ')',
        elegido.grado + ' " ' + elegido.seccion + '"',
        'Motivo: ' + motivo,
      ];
      if (observaciones) partes.push('Observaciones: ' + observaciones);
      return partes.join(' · ');
    };

    formRegistrar.addEventListener('submit', function (event) {
      event.preventDefault();

      var sinEstudiante = !elegido;
      marcarFaltaEstudiante(sinEstudiante);
      selMotivo.classList.toggle('is-invalid', !selMotivo.value);

      if (sinEstudiante || !selMotivo.value) {
        SIGDEUI.mostrarAviso('warning', 'Campos incompletos',
          sinEstudiante ? 'Busque y seleccione el estudiante.' : 'Seleccione el motivo del retiro.');
        return;
      }

      var observaciones = (document.getElementById('retiroObservaciones') || {}).value.trim() || '';

      /* Nada se manda todavía: primero se pregunta. El retiro queda pendiente
 de aprobación del directivo, así que conviene ver qué se está
 registrando antes de que exista el registro.*/
      SIGDEUI.confirmar({
        icono: 'warning',
        titulo: '¿Registrar el retiro?',
        texto: resumenSolicitud(observaciones) + '. Quedará pendiente de aprobación del directivo.',
        confirmar: 'Sí, registrar'
      }).then(function (confirmado) {
        if (!confirmado) return;

        btnRegistrar.disabled = true;
        btnRegistrar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Registrando...';

        /* Se manda solo lo que va al servidor: `q` es del buscador y no lo usa
 store(), así que se arma el cuerpo a mano en vez de usar el FormData.*/
        var cuerpo = new URLSearchParams({
          id_matricula: inputMatricula.value,
          id_motivo_retiro: selMotivo.value,
          observaciones: observaciones,
        });

        SIGDEUI.enviar(route + 'store', { metodo: 'POST', cuerpo: cuerpo })
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
    });
  }
});