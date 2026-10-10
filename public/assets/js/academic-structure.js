/* SIGDE - comportamiento de Estructura Académica*/
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'academicStructure/';
  var newForm = document.getElementById('formNuevaEstructura');
  var editForm = document.getElementById('formEditarEstructura');

  /* ---------- Filtrado ----------
 No se engancha nada aquí a propósito. main.js (initAutoFilters) ya pone un
 listener de 'change' a cada <select> del form .data-panel__filters y refresca
 el panel por AJAX. Con un requestSubmit() propio, cambiar un filtro disparaba
 los dos: dos peticiones y una navegación de página por cada clic.*/

  /* Estado del cupo dentro del modal. Al abrir la edición se pregunta a
 academicStructure/impacto cuántos alumnos tiene la sección y cuál es la capacidad mínima.
 Antes el único dato era la fila del listado, y esa cifra envejece entre que se abre la
 pantalla y se edita: un "3 de 30" que en realidad son 5 es peor que no mostrar nada.*/
  var resumenCupo = document.getElementById('editarEstructuraCupoResumen');
  var inputCapacidad = document.getElementById('editarEstructuraCapacidad');

  function pintarEstadoCupo(ocupados, minimo, capacidadActual) {
    if (!resumenCupo) return;

    document.getElementById('editarEstructuraOcupados').textContent = ocupados;
    document.getElementById('editarEstructuraCapacidadActual').textContent = capacidadActual + ' cupos';
    document.getElementById('editarEstructuraMinimo').textContent = minimo;

    /* Se pone el suelo real en min. Con min=1 el campo acepta cualquier cosa y el
 error aparece recién al guardar, que es justo lo que se quería evitar.*/
    if (inputCapacidad) {
      inputCapacidad.min = Math.max(1, minimo);
      inputCapacidad.setCustomValidity(
        capacidadInvalida(inputCapacidad.value, minimo)
          ? 'La capacidad no puede quedar por debajo de los ' + minimo + ' alumno(s) ya matriculados.'
          : ''
      );
    }

    var aviso = document.getElementById('editarEstructuraCupoAviso');
    if (aviso) {
      if (minimo > 0) {
        aviso.className = 'small text-warning-emphasis';
        aviso.innerHTML = '<i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>'
          + 'Esta sección tiene ' + minimo + ' alumno(s) matriculado(s).';
      } else {
        aviso.className = 'small text-success';
        aviso.innerHTML = '<i class="bi bi-check-circle me-1" aria-hidden="true"></i>Sección vacía.';
      }
    }

    resumenCupo.hidden = false;
  }

  function capacidadInvalida(valor, minimo) {
    return parseInt(valor, 10) < minimo;
  }

  /* Confirmación de impacto antes de enviar. Editar una combinación (grado, sección o turno)
 no mueve a los alumnos: los mueve la fila, porque matricula.id_estructura_academica sigue
 apuntando al mismo id. Cambiar el turno de una sección lleva a todos sus alumnos al
 mismo lugar, así que se pregunta antes con el número real.*/

  /* Nombre visible de la opción elegida en un select del modal, para no mostrar ids
 crudos ("6/1/1") en un mensaje que va a leer una persona.*/
  function etiquetaDe(selectId) {
    var select = document.getElementById(selectId);
    if (!select) return '—';
    var opcion = select.options[select.selectedIndex];
    return opcion && opcion.textContent.trim() ? opcion.textContent.trim() : '—';
  }

  /* Consulta academicStructure/impacto para la fila que se está editando. La usan dos momentos
 distintos con los mismos parámetros: al abrir el modal, para pintar cuántos hay y cuál es
 el mínimo, y al enviar, para decidir si hay que pedir confirmación. Se centraliza para que
 las dos cifras siempre coincidan.*/
  function consultarImpacto(id) {
    var grado = document.getElementById('editarEstructuraGrado');
    var seccion = document.getElementById('editarEstructuraSeccion');
    var turno = document.getElementById('editarEstructuraTurno');

    if (!grado || !seccion || !turno || !id) return Promise.resolve(null);

    var url = route + 'impacto?id=' + encodeURIComponent(id)
      + '&id_grado=' + encodeURIComponent(grado.value)
      + '&id_seccion=' + encodeURIComponent(seccion.value)
      + '&id_turno=' + encodeURIComponent(turno.value);

    return SIGDEUI.enviar(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (respuesta) {
        return respuesta.ok ? (respuesta.data || null) : null;
      })
      .catch(function () { return null; });
  }

  function pedirConfirmacionSiAfecta(id) {
    return consultarImpacto(id).then(function (d) {
      /* Si no se pudo saber el impacto NO se bloquea el guardado: el servidor
 valida igual y, si algo no cuadra, lo dice con el mensaje real.*/
      if (!d) return true;

      /* Sin alumnos, o sin cambio de combinación, no hay nada que advertir: bajar la
 capacidad de una sección llena no mueve a nadie.*/
      if (!d.mueve || !d.ocupados) return true;

      var plural = d.ocupados === 1 ? 'alumno' : 'alumnos';
      var nueva = etiquetaDe('editarEstructuraGrado') + ' · '
        + etiquetaDe('editarEstructuraSeccion') + ' · '
        + etiquetaDe('editarEstructuraTurno');

      return SIGDEUI.confirmar({
        icono: 'warning',
        titulo: 'Esta acción mueve ' + d.ocupados + ' ' + plural,
        texto: 'Esta combinación tiene ' + d.ocupados + ' ' + plural
          + ' matriculado(s) y van a cambiar de grado, sección o turno: pasarán a '
          + nueva + '. La capacidad no puede quedar por debajo de ' + d.ocupados + '.',
        confirmar: 'Sí, mover ' + d.ocupados + ' ' + plural,
        cancelar: 'Cancelar',
      });
    });
  }

  function save(form, endpoint, modalId, id) {
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }

    /* El alta nunca mueve alumnos, así que no pasa por la confirmación.*/
    var preparar = id ? pedirConfirmacionSiAfecta(id) : Promise.resolve(true);

    preparar.then(function (confirmado) {
      if (!confirmado) return;

      SIGDEUI.enviar(route + endpoint, { metodo: 'POST', cuerpo: new FormData(form) })
        .then(function (result) {
          if (!result.ok || !result.data.ok) {
            SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo guardar el registro.');
            return;
          }
          SIGDEUI.ocultarModal(document.getElementById(modalId));
          /* Se recarga la página como antes. El refresco por AJAX del panel dejaría
 los valores del modal de edición pegados: el botón de pencil vuelve a
 ponerlos, pero un modal abierto con datos viejos se prestaría a
 confusión, y el formulario de alta sí necesita verse limpio.*/
          window.location.reload();
        })
        .catch(function () { SIGDEUI.mostrarError('No se pudo conectar con el servidor.'); });
    });
  }

  if (newForm) {
    newForm.addEventListener('submit', function (event) {
      event.preventDefault();
      save(newForm, 'store', 'nuevaEstructuraModal', null);
    });
  }

  if (editForm) {
    editForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('editarEstructuraId').value;
      save(editForm, 'update', 'editarEstructuraModal', id);
    });
  }

  /* Abrir el modal de edición por DELEGACIÓN en document, no con un listener por botón: main.js
 reemplaza el <tbody> por AJAX en cada filtro, búsqueda o cambio de página, y los botones
 que llegan son nodos distintos.*/
  document.addEventListener('click', function (e) {
    var button = e.target.closest('.editar-estructura');
    if (!button) return;

    document.getElementById('editarEstructuraId').value = button.dataset.id;
    document.getElementById('editarEstructuraAnio').value = button.dataset.anio;
    document.getElementById('editarEstructuraGrado').value = button.dataset.grado;
    document.getElementById('editarEstructuraSeccion').value = button.dataset.seccion;
    document.getElementById('editarEstructuraTurno').value = button.dataset.turno;
    document.getElementById('editarEstructuraCapacidad').value = button.dataset.capacidad;

    /* El resumen arranca oculto en cada apertura: si la consulta falla, no puede
 quedar el número del alumno anterior pegado ahí.*/
    if (resumenCupo) resumenCupo.hidden = true;

    SIGDEUI.mostrarModal(document.getElementById('editarEstructuraModal'));

    /* Se pinta después de mostrar el modal y no antes: la consulta va al servidor y
 esperar a que volviera dejaría el formulario sin abrir medio segundo, que es
 justo lo que hace que una pantalla se sienta lenta.*/
    consultarImpacto(button.dataset.id).then(function (d) {
      if (!d) return;
      pintarEstadoCupo(d.ocupados, d.capacidad_minima, d.actual.capacidad_maxima);
    });
  });

  /* ---------- El mínimo se avisa mientras se escribe ----------
 Con el min puesto en el input, el navegador ya impide enviar un valor bajo, pero
 no explica el porqué hasta que se intenta. Este aviso lo dice mientras se
 escribe, que es cuando se puede corregir sin perder nada.*/
  if (inputCapacidad) {
    inputCapacidad.addEventListener('input', function () {
      var minimo = parseInt(inputCapacidad.min, 10) || 1;
      inputCapacidad.setCustomValidity(
        capacidadInvalida(inputCapacidad.value, minimo)
          ? 'La capacidad no puede quedar por debajo de los ' + minimo + ' alumno(s) ya matriculados.'
          : ''
      );
    });
  }
});