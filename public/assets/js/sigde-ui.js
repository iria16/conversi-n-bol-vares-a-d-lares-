/* SIGDE - sigde-ui.js
 * Comportamientos generales de la interfaz, compartidos por todos los módulos. Se carga antes que
 * los scripts de módulo (partials/scripts.php) y publica window.SIGDEUI. Existe para que un
 * comportamiento —filtrar <option>, mostrar errores de un 422, normalizar un nombre— se escriba
 * una vez: antes estaba duplicado en tres módulos y en dos versiones distintas.*/

window.SIGDEUI = (function () {
  'use strict';

  /* ── Avisos Todos toleran que SweetAlert2 no esté cargado (por ejemplo en una
 página de solo lectura) y caen a alert().*/

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

  /* Red: devuelve siempre {ok, status, data} para que el llamador no repita el parseo del JSON ni
 maneje dos objetos distintos según la respuesta came bien o mal.*/

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
            /* `data` nunca puede quedar en null: si el cuerpo no es JSON (una
 página de error de PHP ante un 500, un HTML de mantenimiento, un
 proxy), el `.catch` de arriba devuelve null y todo el código que hace
 `result.data.ok` revienta con un TypeError que el `catch` del
 llamador convierte en "No se pudo conectar con el servidor": un
 diagnóstico falso, porque el servidor SÍ respondió. Con este objeto
 vacío, `data.ok` es false y cada módulo cae en su mensaje propio.*/
            return { ok: res.ok && !!(data && data.ok), status: res.status, data: data || { ok: false } };
          });
      })
      .catch(function (err) {
        // Fallo de red: se propaga para que el llamador lo distinga de un
        // 4xx/5xx, que llega con ok=false y no lanza.
        throw err;
      });
  }

  /** POST application/x-www-form-urlencoded con un objeto plano.*/
  function postForm(url, datos) {
    return enviar(url, { metodo: 'POST', cuerpo: datos || {} });
  }

  /** GET que espera JSON.*/
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

  /* ── Modales*/

  /** Instancia de un modal por id, o null si no existe o no hay Bootstrap.*/
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

  /* ── Portapapeles*/

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

  /* ── Utilidades de <select>*/

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
 tenga su propio código de filtrado.*/
      Object.keys(o).forEach(function (clave) {
        if (clave.indexOf('data-') === 0 && o[clave] != null) {
          opcion.setAttribute(clave, String(o[clave]));
        }
      });

      select.appendChild(opcion);
    });

    select.disabled = (opciones || []).length === 0;
  }

  /* Muestra solo las <option> cuyo atributo coincide con el valor dado. Lo usan el filtro de títulos
 por grado y la cascada estado → municipio → parroquia. Oculta con `hidden` Y deshabilita con
 `disabled`, siempre juntos: si solo se ocultan, la búsqueda por letra del <select> puede
 aterrizar en una opción escondida y el usuario ve un valor de otro grupo.*/
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

  /* ── Texto*/

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

  /* Errores por campo de un 422. Vive aquí y no en cada módulo porque son tres pantallas las que lo
 necesitan y ya se había escrito dos veces con resultados distintos: inscripción mostraba el
 detalle en cada campo, ratificación se quedaba con el texto genérico.*/

  /**
 * Borra las marcas de error de la respuesta anterior.
 *
 * Se llama antes de cada reintento: si no, el mensaje viejo se queda pegado
 * al campo que la secretaria ya corregido, y mezcla lo viejo con lo nuevo.
 *
 * @param {string|Element} [form] contenedor; por defecto, todo el documento.
*/
  function limpiarErroresDeCampos(form) {
    var raiz = form ? (typeof form === 'string' ? document.querySelector(form) : form) : document;
    if (!raiz) return;
    raiz.querySelectorAll('.is-invalid').forEach(function (c) { c.classList.remove('is-invalid'); });
    raiz.querySelectorAll('[data-error-servidor]').forEach(function (n) { n.remove(); });
  }

  /**
 * Muestra el detalle de un 422 en cada campo que falló.
 *
 * Hace cuatro cosas, y las cuatro hacen falta:
 * 1. marca el input con `is-invalid` (TODOS los que comparten el `name`:
 * un grupo de radios es un campo solo para la secretaria),
 * 2. pone el motivo debajo, en un `invalid-feedback` que se reutiliza si ya
 * existía, para que el texto sobreviva aunque el campo no lo trajera en
 * el markup,
 * 3. lista TODOS los mensajes en un aviso, porque los fallos suelen estar
 * repartidos en varias pestañas y corregirlos de uno en uno obliga a
 * reenviar el formulario entero para descubrir el siguiente,
 * 4. lleva a la primera pantalla con fallo, que si no la secretaria
 * corregiría campos que no puede ver.
 *
 * @param {Object} respuesta `data` de SIGDEUI.enviar(): {ok, mensaje, data:{campo:msg}}
 * @param {Object} [opciones]
 * @param {string|Element} [opciones.form] dónde buscar los campos; por defecto document
 * @param {Object} [opciones.alias] {clave del servidor: name en el formulario}
 * @param {Object} [opciones.pasos] {clave del servidor: id de la pantalla}
 * @param {Function}[opciones.alDetectar] (campo, clave) => id de la pantalla
 * @param {Function}[opciones.irAPantalla] (id, campo) => void
 * @param {string} [opciones.titulo='No se pudo guardar']
 * @param {string} [opciones.mensajePorDefecto] qué mostrar cuando la respuesta
 * no trae ni `mensaje` ni mapa: cada módulo redacta el suyo.
 * @returns {boolean} true si había un mapa de errores por campo.
*/
  function mostrarErroresDeCampos(respuesta, opciones) {
    var o = opciones || {};
    var porCampo = respuesta && respuesta.data;
    var titulo = o.titulo || 'No se pudo guardar';
    var propio = o.mensajePorDefecto || (titulo + '.');

    /* Sin desglose (un 500, un 409, un error que no viene como mapa): se muestra
 el mensaje tal cual, que es lo único que hay.*/
    if (!porCampo || typeof porCampo !== 'object' || !Object.keys(porCampo).length) {
      if (window.Swal) {
        Swal.fire({
          icon: 'error',
          title: titulo,
          text: (respuesta && respuesta.mensaje) || propio,
          confirmButtonColor: '#0a1440'
        });
      } else {
        alert((respuesta && respuesta.mensaje) || propio);
      }
      return false;
    }

    var raiz = o.form
      ? (typeof o.form === 'string' ? document.querySelector(o.form) : o.form)
      : document;
    if (!raiz) raiz = document;

    var alias = o.alias || {};
    var pasos = o.pasos || {};
    var mensajes = [];
    var primerDestino = null;

    Object.keys(porCampo).forEach(function (clave) {
      var texto = porCampo[clave];
      mensajes.push(texto);

      /* Un fallo sin input propio (los familiares, la ficha completa) no tiene
 campo que marcar, pero sí indica una pantalla a la que hay que ir.*/
      if (!alias[clave] && !raiz.querySelector('[name="' + clave + '"]') && pasos[clave]) {
        if (primerDestino === null) primerDestino = pasos[clave];
        return;
      }

      var nombre = alias[clave] || clave;
      var campos = raiz.querySelectorAll('[name="' + nombre + '"]');

      /* El servidor puede nombrar un campo que el formulario no tiene. No se
 corta el recorrido: el mensaje ya está en la lista del aviso, que es
 donde la secretaria lo va a leer igual.*/
      if (!campos.length) return;

      campos.forEach(function (campo) {
        campo.classList.add('is-invalid');
        marcarAvisoDeCampo(campo, texto, clave);

        if (primerDestino === null && typeof o.alDetectar === 'function') {
          primerDestino = o.alDetectar(campo, clave);
        }
      });
    });

    /* Lista con <ul> y no `text` con saltos de línea: SweetAlert2 no respeta los
 \n de `text`, y una sola línea de tres errores es tan inútil como no
 mostrarlos. Van escapados porque el mensaje lo escribe el servidor.*/
    if (window.Swal) {
      Swal.fire({
        icon: 'error',
        title: mensajes.length > 1
          ? titulo + ' (' + mensajes.length + ' problemas)'
          : titulo,
        html: '<ul style="text-align:left;margin:0;padding-left:1.2em">'
          + mensajes.map(function (m) { return '<li>' + escapeHtml(m) + '</li>'; }).join('')
          + '</ul>',
        confirmButtonColor: '#0a1440'
      });
    } else {
      alert(mensajes.join('\n'));
    }

    if (primerDestino !== null && primerDestino !== undefined
        && typeof o.irAPantalla === 'function') {
      o.irAPantalla(primerDestino);
    }

    return true;
  }

  /**
 * Escribe (o reutiliza) el motivo del error debajo de un campo.
 * Se separa porque la usan los dos módulos a través de mostrarErroresDeCampos.
*/
  function marcarAvisoDeCampo(campo, texto, clave) {
    var aviso = campo.parentElement
      ? campo.parentElement.querySelector('.invalid-feedback[data-error-servidor]')
      : null;

    if (!aviso) {
      aviso = document.createElement('div');
      aviso.className = 'invalid-feedback d-block';
      aviso.setAttribute('data-error-servidor', clave);
      if (campo.parentElement) campo.parentElement.appendChild(aviso);
    }
    aviso.textContent = texto;
    aviso.classList.remove('d-none');
  }

  /* Nombres de persona. Copia en JavaScript de TextoTrait::capitalizarTitulo(). Existe porque el
 servidor normaliza al GUARDAR, así que la persona escribiría "juan perez" y vería "Juan
 Pérez" solo en la confirmación; normalizando al salir del campo el cambio se ve mientras
 escribe. El orden importa: se normaliza campo por campo y después se une.*/
  var CONECTORES_NOMBRE = [
    'de', 'del', 'en', 'da', 'con', 'por', 'para', 'a', 'e', 'i', 'o', 'u', 'y',
    'la', 'las', 'el', 'los', 'lo'
  ];

  /* Mismo criterio que TextoTrait::esSigla(): de tres letras, con punto, sin
 pasar de tres letras por tramo.*/
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

  /* ── Catálogos*/

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
 * @param {string} url Endpoint completo, p. ej. baseUrl + 'empleados/guardarInstitucion'.
 * @param {object} campos Pares a enviar en el POST.
 * @param {HTMLSelectElement} [selectDestino] Select al que se le agrega la opción.
 * @param {object} [dataAttr] Atributos extra de la <option> (ej. { idGrado: 5 } -> data-id-grado).
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

  /* ── Antirrebote*/

  /**
 * Envuelve una función para que solo corra tras 'ms' sin nuevas llamadas.
 * Reemplaza los setTimeout dispersos que cada módulo repite.
 *
 * @param {number} ms
 * @param {Function} fn recibe los argumentos de la última llamada
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
      mostrarErroresDeCampos: mostrarErroresDeCampos,
      limpiarErroresDeCampos: limpiarErroresDeCampos,
    normalizarNombre: normalizarNombre
  };
})();