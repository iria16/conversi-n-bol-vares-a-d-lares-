/* SIGDE - Recaudos del estudiante. El mismo control aparece en tres sitios: la ficha, el modal de
 * actualización de esa ficha y el modal de ratificar. Los tres usan el mismo partial, así que un
 * cambio de columna se hace una vez y llega a las tres pantallas.*/
document.addEventListener('DOMContentLoaded', function () {
  const paneles = document.querySelectorAll('[data-recaudos-panel]');
  if (!paneles.length) return;

  /* Mostrar la observación solo cuando el documento está marcado: una caja de texto
 escondida junto a un "No" invita a escribir en el lugar equivocado.*/
  function sincronizarFila(fila) {
    const toggle = fila.querySelector('[data-recaudo-toggle]');
    const observacion = fila.querySelector('[data-recaudo-observacion]');
    if (!toggle || !observacion) return;
    observacion.classList.toggle('d-none', !toggle.checked);

    /* Misma regla del asistente (enrollments.js): al desmarcar, la observación se borra.
 Si se dejara, "copia ilegible" se quedaría guardada en un documento marcado como NO
 entregado, y eso se lee como un documento entregado con nota.*/
    const pendiente = fila.querySelector('[data-recaudo-pendiente]');
    if (pendiente) {
      pendiente.classList.toggle('d-none', toggle.checked);
      if (!toggle.checked && observacion.tagName === 'INPUT') {
        observacion.value = '';
      }
    }
  }

  function contar(panel) {
    const filas = panel.querySelectorAll('[data-recaudo-row]');
    let marcados = 0;
    filas.forEach(function (fila) {
      const toggle = fila.querySelector('[data-recaudo-toggle]');
      if (!toggle) return;
      sincronizarFila(fila);
      if (toggle.checked) marcados++;
    });
    const resumen = panel.querySelector('[data-recaudos-resumen]');
    if (resumen) {
      resumen.textContent = marcados + ' de ' + filas.length + ' recaudos entregados.';
    }
  }

  /* Una fila por documento, siempre con la misma forma. Antes esta función rehacía el layout en
 JavaScript y elegía entre tarjeta o tabla de tres columnas según lo que hubiera; ahora el
 marcado es del partial y lo único que cambia es la clase.*/
  function fila(r) {
    const nombre = document.createElement('div');
    nombre.className = 'fw-semibold';
    /* textContent y no interpolado en el HTML: el nombre viene de la base y podría
 llevar una comilla o un "<".*/
    nombre.textContent = r.nombre || '';

    const celdaNombre = document.createElement('td');
    celdaNombre.appendChild(nombre);

    /* El oculto no es opcional: un checkbox sin marcar no viaja en el POST, y sin él
 el servidor no distinguiría "no entregado" de "este documento no estaba en la
 pantalla".*/
    const oculto = document.createElement('input');
    oculto.type = 'hidden';
    oculto.name = 'recaudos[' + r.id + '][consignado]';
    oculto.value = '0';

    const casilla = document.createElement('input');
    casilla.className = 'form-check-input';
    casilla.type = 'checkbox';
    casilla.setAttribute('role', 'switch');
    casilla.name = 'recaudos[' + r.id + '][consignado]';
    casilla.value = '1';
    casilla.setAttribute('data-recaudo-toggle', r.id);
    casilla.checked = !!r.consignado;

    const celdaConsignado = document.createElement('td');
    celdaConsignado.className = 'text-center';
    const marco = document.createElement('div');
    marco.className = 'form-check form-switch d-flex justify-content-center mb-0';
    marco.appendChild(oculto);
    marco.appendChild(casilla);
    celdaConsignado.appendChild(marco);

    const observacion = document.createElement('input');
    observacion.type = 'text';
    observacion.className = 'form-control form-control-sm';
    observacion.name = 'recaudos[' + r.id + '][observacion]';
    observacion.setAttribute('data-recaudo-observacion', r.id);
    observacion.maxLength = 255;
    observacion.placeholder = 'Ej.: copia ilegible, se pidió el original';
    observacion.value = r.observacion || '';
    observacion.classList.toggle('d-none', !r.consignado);

    const celdaObservacion = document.createElement('td');
    celdaObservacion.appendChild(observacion);

    const pendiente = document.createElement('span');
    pendiente.className = 'text-support small';
    pendiente.textContent = 'Pendiente';
    pendiente.setAttribute('data-recaudo-pendiente', '');
    if (r.consignado) pendiente.classList.add('d-none');
    celdaObservacion.appendChild(pendiente);

    const tr = document.createElement('tr');
    tr.setAttribute('data-recaudo-row', '');
    tr.appendChild(celdaNombre);
    tr.appendChild(celdaConsignado);
    tr.appendChild(celdaObservacion);
    return tr;
  }

  function rellenar(panel, recaudos, idEstudiante) {
    const cuerpo = panel.querySelector('[data-recaudos-form] tbody');
    if (!cuerpo) return;

    /* El id va primero: los botones se habilitan según el id, y con el id en 0 el botón
 queda deshabilitado para que no se pueda enviar una carga sin estudiante.*/
    const oculto = panel.querySelector('input[name="id_estudiante"]');
    if (oculto) oculto.value = idEstudiante;

    const btn = panel.querySelector('[data-guardar-recaudos]');
    if (btn) btn.disabled = !idEstudiante || idEstudiante <= 0;

    if (!recaudos || !recaudos.length) {
      cuerpo.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-support">'
        + 'No hay recaudos registrados en el catálogo.</td></tr>';
      const resumen = panel.querySelector('[data-recaudos-resumen]');
      if (resumen) resumen.textContent = '';
      return;
    }

    cuerpo.innerHTML = '';
    recaudos.forEach(function (r) { cuerpo.appendChild(fila(r)); });

    contar(panel);
  }

  paneles.forEach(function (panel) {
    const form = panel.querySelector('[data-recaudos-form]');
    if (!form) return;

    form.addEventListener('change', function (evento) {
      if (evento.target.matches('[data-recaudo-toggle]')) contar(panel);
    });

    /* El panel no es un <form> (ver el partial): va dentro del formulario del modal
 "Actualizar expediente" y dos formularios anidados no son válidos. El guardado se
 dispara con el botón, y el cuerpo se arma a mano en el delegado de abajo.*/
    contar(panel);
  });

  /* Arma el cuerpo del POST con los campos del panel. Se hace a mano y no con FormData
 porque el panel no es un formulario: sus inputs cuelgan de un <div> y FormData solo
 recoge lo que está dentro de un <form>.*/
  function armarCuerpo(panel) {
    const cuerpo = new URLSearchParams();
    panel.querySelectorAll('[name]').forEach(function (campo) {
      if (campo.name === 'id_estudiante') {
        cuerpo.append('id_estudiante', campo.value);
        return;
      }
      /* Solo los campos del panel: el modal que lo contiene tiene hundreds de inputs
 propios y meterlos todos aquí sería mandar el formulario entero dos veces.*/
      if (campo.name.indexOf('recaudos[') === 0) {
        cuerpo.append(campo.name, campo.value);
      }
    });
    return cuerpo;
  }

  /* Un solo delegado en document para el botón: los paneles que llegan por AJAX no tienen
 listener propio, igual que pasó con los botones del listado de ratificación.*/
  document.addEventListener('click', async function (evento) {
    const btn = evento.target.closest('[data-guardar-recaudos]');
    if (!btn) return;

    const panel = btn.closest('[data-recaudos-panel]');
    if (!panel || !panel.querySelector('[data-recaudos-form]')) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando...';

    try {
      const cuerpo = armarCuerpo(panel);
      const sobre = await SIGDEUI.enviar(BASE_URL + 'estudiantes/guardarRecaudos', {
        metodo: 'POST',
        cuerpo: cuerpo
      });

      if (!sobre.ok || !sobre.data || sobre.data.ok === false) {
        SIGDEUI.mostrarAviso('error', 'No se pudieron guardar los recaudos',
          (sobre.data && sobre.data.mensaje) || 'Intente nuevamente.');
        return;
      }

      SIGDEUI.exito(sobre.data.mensaje || 'Recaudos actualizados.', '¡Listo!');

      /* Se recarga la página y no solo la tabla: la columna "última actualización" muestra
 usuario y fecha, que se resuelven en el servidor, y refrescar la tabla sola dejaría
 esos valores viejos junto a un estado nuevo. En la ficha se recarga entera; dentro
 de un modal que se cerrará y se recargará, no tiene sentido pintar y tirar.*/
      const enModal = !!panel.closest('[data-recaudos-modal]');
      if (!enModal) {
        window.location.reload();
        return;
      }
      const modal = panel.closest('.modal');
      if (modal) {
        SIGDEUI.alCerrarModal(modal, function recargar() { window.location.reload(); });
        bootstrap.Modal.getOrCreateInstance(modal).hide();
      } else {
        window.location.reload();
      }
    } catch (err) {
      SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  /* Rellenado por AJAX: lo usa el modal de ratificar, donde el estudiante se elige fila a
 fila y no hay un id en el HTML. La ficha no lo necesita, porque se renderiza con el
 estudiante ya conocido.*/
  window.SIGDERecaudos = { rellenar: rellenar };
});
