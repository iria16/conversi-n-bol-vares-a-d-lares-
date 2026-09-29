/* ==========================================================================
   SIGDE - sigde-ui.js
   Comportamientos generales de la interfaz, compartidos por todos los
   módulos. Se carga antes que los scripts de módulo (partials/scripts.php)
   y publica window.SIGDEUI.

  _existsían_ por módulo: los avisos SweetAlert, los helpers de red
   (postForm/getJson), el acceso a modales de Bootstrap y el copiador al
   portapapeles estaban copiados en casi todos los scripts (mostrarError()
   estaba 4 veces idéntico). Acá hay una sola implementación.

   API:
     SIGDEUI.mostrarError(mensaje, titulo?)
     SIGDEUI.mostrarAviso(icono, titulo, mensaje)
     SIGDEUI.exito(mensaje, titulo?)
     SIGDEUI.confirmar({titulo, texto, confirmar, cancelar, icono}) -> Promise<bool>
     SIGDEUI.enviar(url, {metodo, cuerpo, json}) -> Promise<{ok, status, data}>
     SIGDEUI.instanciaModal(id) -> bootstrap.Modal|null
     SIGDEUI.alCerrarModal(id, callback)   // se ejecuta una vez, al cerrar
     SIGDEUI.copiarAlPortapapeles(selector?)
     SIGDEUI.rellenarSelect(select, opciones, marcador?)
     SIGDEUI.filtrarOpciones(select, valor, atributo)
     SIGDEUI.esDebounced(ms, fn) -> fn   // antirrebote reutilizable
   ========================================================================== */

window.SIGDEUI = (function () {
  'use strict';

  /* ── Avisos ────────────────────────────────────────────────────────
     Todos toleran que SweetAlert2 no esté cargado (por ejemplo en una
     página de solo lectura) y caen a alert(). */

  function mostrarError(mensaje, titulo) {
    if (window.Swal) {
      Swal.fire({ icon: 'error', title: titulo || 'Error', text: mensaje });
    } else {
      alert((titulo ? titulo + '\n' : '') + mensaje);
    }
  }

  function mostrarAviso(icono, titulo, mensaje) {
    if (window.Swal) {
      Swal.fire({ icon: icono, title: titulo, text: mensaje, confirmButtonColor: '#0a1440' });
    } else {
      alert((titulo ? titulo + '\n' : '') + mensaje);
    }
  }

  /**
   * Aviso de éxito: se cierra solo y devuelve la promesa de SweetAlert2,
   * para encadenar lo que siga (recargar, cerrar otro modal, etc.). Sin
   * SweetAlert2 devuelve una promesa resuelta, así que el .then() igual se
   * ejecuta y no hace falta el if (window.Swal) en el llamador.
   *
   * @param {string} mensaje
   * @param {string} [titulo]
   * @param {number} [ms=1500] tiempo en pantalla antes de cerrarse
   */
  function exito(mensaje, titulo, ms) {
    if (!window.Swal) return Promise.resolve();
    return Swal.fire({
      icon: 'success',
      title: titulo || 'Listo',
      text: mensaje,
      timer: ms || 1500,
      showConfirmButton: false
    });
  }

  /**
   * Confirmación. Devuelve Promise<boolean> en vez de obligar a cada
   * módulo a repetir el .then(result => result.isConfirmed) y el bloque
   * else con confirm().
   *
   * @param {Object} [opciones] titulo, texto, confirmar, cancelar, icono.
   * @returns {Promise<boolean>} true si el usuario confirma.
   */
  function confirmar(opciones) {
    var o = opciones || {};

    if (!window.Swal) {
      return Promise.resolve(window.confirm((o.titulo ? o.titulo + '\n' : '') + (o.texto || '')));
    }

    return Swal.fire({
      title: o.titulo || '¿Confirmas la acción?',
      text: o.texto || '',
      icon: o.icono || 'question',
      showCancelButton: true,
      confirmButtonText: o.confirmar || 'Sí, continuar',
      cancelButtonText: o.cancelar || 'Cancelar',
      reverseButtons: true
    }).then(function (result) { return result.isConfirmed === true; });
  }

  /* ── Red ───────────────────────────────────────────────────────────
     Devuelve siempre {ok, status, data} para que el llamador no tenga
     que repetir el parseo del JSON ni manejar dos objetos distintos
     según la respuesta came bien o mal. El cuerpo puede ser un objeto
     plano (se codifica), un FormData o un URLSearchParams.
     {signal} cancela la petición (main.js aborta la anterior de los filtros
     automáticos) y {texto: true} devuelve {ok, status, texto} en vez de
     parsear JSON, para los endpoints que responden HTML. */

  /**
   * Sesión vencida: el front controller (index.php) responde JSON con
   * codigo 'sesion_expirada' cuando una petición AJAX llega sin sesión.
   * Se avisa y se lleva al login. Devuelve una promesa que nunca se
   * resuelve a propósito: así el código que llamó a enviar() no sigue y
   * no muestra un segundo error encima de este aviso; la página igual se
   * está yendo al login.
   */
  function sesionExpirada(mensaje) {
    var base = window.BASE_URL || '/';
    var destino = (base.endsWith('/') ? base : base + '/') + 'auth/login';
    var texto = mensaje || 'Tu sesión expiró. Inicia sesión de nuevo.';
    var irAlLogin = function () { window.location.href = destino; };

    if (window.Swal) {
      Swal.fire({
        icon: 'warning',
        title: 'Sesión expirada',
        text: texto,
        confirmButtonColor: '#0a1440',
        allowOutsideClick: false
      }).then(irAlLogin);
    } else {
      alert(texto);
      irAlLogin();
    }

    return new Promise(function () {});
  }

  function enviar(url, opciones) {
    var o = opciones || {};
    var cuerpo = o.cuerpo;

    if (cuerpo && !(cuerpo instanceof FormData) && !(cuerpo instanceof URLSearchParams)) {
      cuerpo = new URLSearchParams(cuerpo);
    }

    var cabeceras = { 'X-Requested-With': 'XMLHttpRequest' };
    if (o.cabeceras) {
      Object.keys(o.cabeceras).forEach(function (k) { cabeceras[k] = o.cabeceras[k]; });
    }
    if (o.json) {
      cabeceras['Content-Type'] = 'application/json';
      if (cuerpo && !(cuerpo instanceof FormData) && !(cuerpo instanceof URLSearchParams)) {
        cuerpo = JSON.stringify(cuerpo);
      }
    }

    return fetch(url, {
      method: o.metodo || 'GET',
      headers: cabeceras,
      body: cuerpo,
      signal: o.signal
    })
      .then(function (res) {
        if (o.texto) {
          // Los paneles de data-panel responden HTML, no JSON.
          return res.text()
            .catch(function () { return ''; })
            .then(function (txt) {
              return { ok: res.ok, status: res.status, texto: txt, data: null };
            });
        }
        return res.json()
          .catch(function () { return null; })
          .then(function (data) {
            if (data && data.codigo === 'sesion_expirada') {
              return sesionExpirada(data.mensaje);
            }
            return { ok: res.ok && !!(data && data.ok), status: res.status, data: data };
          });
      })
      .catch(function (err) {
        // Fallo de red: se propaga para que el llamador lo distinga de un
        // 4xx/5xx, que llega con ok=false y no lanza.
        throw err;
      });
  }

  /** POST application/x-www-form-urlencoded con un objeto plano. */
  function postForm(url, datos) {
    return enviar(url, { metodo: 'POST', cuerpo: datos || {} });
  }

  /** GET que espera JSON. */
  function getJson(url) {
    return enviar(url, { metodo: 'GET' });
  }

  /**
   * Muestra el aviso de una sola vez que el backend dejó en la sesión y la
   * vista imprimió como <div id="swal-data" data-icon data-title data-text>
   * (app/Views/layouts/auth.php). El PHP lo borra de la sesión al
   * renderizar, así que recargar no lo repite.
   */
  function mostrarFlash(idElemento) {
    var el = document.getElementById(idElemento || 'swal-data');
    if (!el) return;

    mostrarAviso(
      el.dataset.icon || 'info',
      el.dataset.title || 'Aviso',
      el.dataset.text || ''
    );
  }

  /* ── Modales ─────────────────────────────────────────────────────── */

  /** Instancia de un modal por id, o null si no existe o no hay Bootstrap. */
  function instanciaModal(id) {
    var el = typeof id === 'string' ? document.getElementById(id) : id;
    return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
  }

  function mostrarModal(id) {
    var m = instanciaModal(id);
    if (m) m.show();
    return m;
  }

  function ocultarModal(id) {
    var m = instanciaModal(id);
    if (m) m.hide();
    return m;
  }

  /**
   * Ejecuta callback una sola vez al terminar de cerrarse el modal.
   * Se usa para recargar o refrescar después de guardar, cuando el
   * modal de credenciales tapa al de edición.
   */
  function alCerrarModal(id, callback) {
    var el = typeof id === 'string' ? document.getElementById(id) : id;
    if (!el) return;
    el.addEventListener('hidden.bs.modal', function alCerrar() {
      el.removeEventListener('hidden.bs.modal', alCerrar);
      callback();
    });
  }

  /* ── Portapapeles ────────────────────────────────────────────────── */

  /**
   * Copia al portapapeles el texto del elemento apuntado por
   * data-copy-target="id". Va delegado en document para que también
   * funcione con botones que se agreguen después (los modales de
   * credenciales, por ejemplo).
   *
   * Usa navigator.clipboard cuando está disponible y cae a
   * document.execCommand('copy') si la página no es contexto seguro
   * (navigator.clipboard no existe en http:// fuera de localhost), que era
   * justo lo que hacía el copiador que estaba en main.js.
   *
   * @param {string} [selector] por defecto '[data-copy-target]'
   */
  function copiarAlPortapapeles(selector) {
    var sel = selector || '[data-copy-target]';

    function conExecCommand(texto) {
      var area = document.createElement('textarea');
      area.value = texto;
      area.setAttribute('readonly', '');
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      document.execCommand('copy');
      area.remove();
    }

    function escribir(texto) {
      if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(texto);
      }
      // El fallback es sincrono y puede lanzar (navegadores sin
      // execCommand). Envolverlo en una promesa hace que ese error llegue
      // al .catch() de abajo en vez de escaparse al click.
      return Promise.resolve().then(function () { conExecCommand(texto); });
    }

    document.addEventListener('click', function (e) {
      var btn = e.target.closest(sel);
      if (!btn) return;

      var idDestino = btn.dataset.copyTarget;
      var destino = idDestino ? document.getElementById(idDestino) : null;
      if (!destino) return;

      var icono = btn.querySelector('i');
      var claseOriginal = icono ? icono.className : '';

      escribir(destino.textContent || '').then(function () {
        if (icono) icono.className = 'bi bi-check-lg';
        setTimeout(function () {
          if (icono) icono.className = claseOriginal;
        }, 1500);
      }).catch(function () {
        mostrarError('No se pudo copiar al portapapeles.');
      });
    });
  }

  /* ── Utilidades de <select> ──────────────────────────────────────── */

  /**
   * Pinta <option> en un select. Acepta objetos {id, nombre} o
   * {id_ubicacion, nombre} (la cascada de ubicación usa la segunda forma).
   *
   * @param {HTMLSelectElement} select
   * @param {Array<{id?:number, id_ubicacion?:number, nombre:string}>} opciones
   * @param {string} [marcador] texto de la primera opción vacía.
   */
  function rellenarSelect(select, opciones, marcador) {
    if (!select) return;

    select.innerHTML = '';
    var vacia = document.createElement('option');
    vacia.value = '';
    vacia.textContent = marcador || 'Seleccione...';
    select.appendChild(vacia);

    (opciones || []).forEach(function (o) {
      var opcion = document.createElement('option');
      opcion.value = String(o.id != null ? o.id : o.id_ubicacion);
      opcion.textContent = o.nombre;

      /* Los atributos data-* que traiga la opción se copian tal cual. Es lo que
         permite que un mismo filtro (filtrarOpciones) sirva para los títulos
         por grado y para los municipios por estado, sin que cada lista
         tenga su propio código de filtrado. */
      Object.keys(o).forEach(function (clave) {
        if (clave.indexOf('data-') === 0 && o[clave] != null) {
          opcion.setAttribute(clave, String(o[clave]));
        }
      });

      select.appendChild(opcion);
    });

    select.disabled = (opciones || []).length === 0;
  }

  /**
   * Muestra solo las <option> cuyo atributo coincide con el valor dado.
   * Lo usan el filtro de títulos por grado académico y la cascada
   * estado -> municipio -> parroquia: las <option> traen data-id-grado o
   * data-id-estado / data-id-municipio y se ocultan las de los demás.
   *
   * Oculta con `hidden` Y deshabilita con `disabled`, y no es redundante: cuando
   * se elegía el padre, la búsqueda por letra del <select> (teclear "A") iba a
   * parar en la primera opción que empiece por esa letra, aunque estuviera
   * oculta. El síntoma era que, having elegido el municipio, teclear una letra
   * saltaba a cualquier parroquia del país en vez de a la del municipio. `hidden`
   * no es normativo para eso; `disabled` sí, y de paso quita la opción del
   * recorrido del ratón.
   *
   * La selección previa se conserva si su opción sigue siendo válida: al
   * refiltrar por un cambio del padre no se pierde lo que el usuario ya había
   * elegido abajo.
   *
   * @returns {boolean} true si queda al menos una opción visible.
   */
    function filtrarOpciones(select, valor, atributo) {
      if (!select) return false;

      var attr = atributo || 'data-id-grado';
      var objetivo = String(valor);
      var previo = select.value;
      var previoSigueVálido = false;
      var hayVisibles = false;

      for (var i = 0; i < select.options.length; i++) {
        var opcion = select.options[i];
        if (!opcion.value) continue; // la opción vacía siempre se ve
        var coincide = opcion.getAttribute(attr) === objetivo;
        opcion.hidden = !coincide;
        opcion.disabled = !coincide;
        if (coincide) {
          hayVisibles = true;
          if (opcion.value === previo) previoSigueVálido = true;
        }
      }

      // Si la opción elegida dejó de ser válida —o no hay ninguna visible— el
      // select vuelve a la opción vacía.
      if (!previoSigueVálido) {
        select.value = '';
      }

      return hayVisibles;
    }

  /* ── Texto ───────────────────────────────────────────────────────── */

  /**
   * Escapa HTML usando el propio DOM (sin.innerHTML = '' sobre un string
   * con datos, que es lo que hay que evitar). Lo usan los módulos que arman
   * markup con datos que vienen del servidor.
   */
  function escapeHtml(texto) {
    var div = document.createElement('div');
    div.textContent = texto == null ? '' : String(texto);
    return div.innerHTML;
  }

  /* ── Nombres de persona ─────────────────────────────────────────────
     Copia en JavaScript de TextoTrait::capitalizarTitulo().

     Existe por una razón concreta: el servidor normaliza los nombres al
     GUARDAR (EmpleadoController::extractData y compañía), así que la persona
     escribía "JUAN de la CRUZ", veía "JUAN de la CRUZ" en la pantalla de
     confirmación y en la base quedaba "Juan de la Cruz". Nada avisaba del
     cambio: el formulario acceptaba, el registro se guardaba, y la diferencia
     aparecía después, en la lista o en un reporte.

     Las dos implementaciones tienen que decir EXACTAMENTE lo mismo, y por eso
     el comentario de la de PHP avisa de que esta es su gemela. Si se cambia una,
     se cambia la otra. La prueba nombres_normalizado.php corre las dos sobre un
     mismo caso y falla si se separan.

     Reglas, en el mismo orden que la versión PHP:
       1. Espacios repetidos a uno solo y sin espacios en los extremos.
       2. Todo a minúscula.
       3. Cada palabra a inicial mayúscula, salvo:
          - los conectores, que quedan en minúscula (de, del, la, y, ...) si no
            son la primera palabra;
          - las siglas con punto (u.e., s.a.), que se dejan enteras en
            mayúscula, porque "U.e." no se escribe así.
  */
  var CONECTORES_NOMBRE = [
    'de', 'del', 'en', 'da', 'con', 'por', 'para', 'a', 'e', 'i', 'o', 'u', 'y',
    'la', 'las', 'el', 'los', 'lo'
  ];

  /* Mismo criterio que TextoTrait::esSigla(): de tres letras, con punto, sin
     pasar de tres letras por tramo. */
  function esSigla(palabra) {
    return /^[a-záéíóúñ]{1,3}(\.[a-záéíóúñ]{1,3})+\.?$/.test(palabra);
  }

  function normalizarNombre(texto) {
    if (texto == null) return '';
    var limpio = String(texto).replace(/\s+/g, ' ').trim();
    if (limpio === '') return '';

    var palabras = limpio.toLocaleLowerCase('es').split(' ');

    for (var i = 0; i < palabras.length; i++) {
      if (esSigla(palabras[i])) {
        palabras[i] = palabras[i].toLocaleUpperCase('es');
        continue;
      }
      if (i === 0 || CONECTORES_NOMBRE.indexOf(palabras[i]) === -1) {
        palabras[i] = palabras[i].charAt(0).toLocaleUpperCase('es') +
                      palabras[i].slice(1).toLocaleLowerCase('es');
      }
    }

    return palabras.join(' ');
  }

  /* ── Catálogos ───────────────────────────────────────────────────── */

  /**
   * Alta rápida de un catálogo desde un modal anidado (título, institución,
   * cargo...): POST al endpoint indicado, avisa el resultado con el mismo
   * texto que los módulos de catálogos, agrega la <option> nueva al select
   * destino y devuelve el registro creado (o null si falló).
   *
   * La <option> se arma con el nombre que devuelve el backend, ya
   * normalizado, para que el select muestre exactamente lo que quedó guardado
   * (por ejemplo "Recursos Humanos" y no "recursos humanos").
   *
   * @param {string} url           Endpoint completo, p. ej. baseUrl + 'empleados/guardarInstitucion'.
   * @param {object} campos        Pares a enviar en el POST.
   * @param {HTMLSelectElement} [selectDestino] Select al que se le agrega la opción.
   * @param {object} [dataAttr]    Atributos extra de la <option> (ej. { idGrado: 5 } -> data-id-grado).
   * @returns {Promise<object|null>}
   */
  function guardarCatalogoRapido(url, campos, selectDestino, dataAttr) {
    var body = new FormData();
    Object.keys(campos).forEach(function (clave) { body.append(clave, campos[clave]); });

    return enviar(url, { metodo: 'POST', cuerpo: body })
      .then(function (res) {
        var sobre = res.data || {};

        if (!res.ok || !sobre.ok) {
          mostrarError(sobre.mensaje || 'No se pudo guardar el registro.', 'Error al guardar');
          return null;
        }

        var creado = sobre.data || null;

        if (selectDestino && creado && creado.id) {
          var opcion = new Option(String(creado.nombre || campos.nombre || ''), creado.id, true, true);
          Object.keys(dataAttr || {}).forEach(function (clave) {
            opcion.dataset[clave] = String(dataAttr[clave]);
          });
          selectDestino.appendChild(opcion);
        }

        exito(sobre.mensaje || 'Registro creado correctamente.', 'Creado');
        return creado;
      })
      .catch(function (error) {
        console.error('Error al procesar la petición AJAX:', error);
        mostrarError('Ocurrió un fallo de conexión al guardar el registro.');
        return null;
      });
  }

  /* ── Antirrebote ─────────────────────────────────────────────────── */

  /**
   * Envuelve una función para que solo corra tras 'ms' sin nuevas llamadas.
   * Reemplaza los setTimeout dispersos que cada módulo repite.
   *
   * @param {number} ms
   * @param {Function} fn  recibe los argumentos de la última llamada
   * @returns {Function} fn con arranque retardado
   */
  function esDebounced(ms, fn) {
    var timer = null;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  return {
    mostrarError: mostrarError,
    mostrarAviso: mostrarAviso,
    exito: exito,
    mostrarFlash: mostrarFlash,
    confirmar: confirmar,
    enviar: enviar,
    postForm: postForm,
    getJson: getJson,
    instanciaModal: instanciaModal,
    mostrarModal: mostrarModal,
    ocultarModal: ocultarModal,
    alCerrarModal: alCerrarModal,
    copiarAlPortapapeles: copiarAlPortapapeles,
    rellenarSelect: rellenarSelect,
    filtrarOpciones: filtrarOpciones,
    guardarCatalogoRapido: guardarCatalogoRapido,
    esDebounced: esDebounced,
    escapeHtml: escapeHtml,
    normalizarNombre: normalizarNombre
  };
})();