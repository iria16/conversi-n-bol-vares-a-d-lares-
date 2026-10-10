/* Comportamientos compartidos por los formularios tipo wizard (empleado, estudiante y familiar)
 * y por los sub-modales que cuelgan de ellos. Antes vivían duplicados en cada wizard: al corregir
 * un comportamiento había que acordarse de cambiar los tres sitios.
*/

window.SIGDEWizardUI = (function () {
  'use strict';

  /* ---------- Foto: elegir, previsualizar y quitar ----------*/

  /**
 * Conecta un <input type="file"> de avatar con su preview y su botón de
 * "Quitar foto".
 *
 * Usa URL.createObjectURL() en lugar de FileReader/dataURL: no lee el
 * archivo entero a memoria y la URL anterior se revoca, así que abrir y
 * cerrar el selector repetidamente no deja blobs huérfanos.
 *
 * @param {Object} opciones
 * @param {string} opciones.inputId id del input file
 * @param {string} opciones.previewId id del div donde se pinta la imagen
 * @param {string} [opciones.quitarId] id del botón de quitar (opcional)
 * @param {string} [opciones.inputName]Deprecated y sin efecto: el `name` se lee
 * del propio input, no se deduce. Se
 * mantiene en la firma para no romper
 * llamadas que lo pasarían.
 * @param {string} [opciones.vacio] HTML del estado sin foto
 * @param {Function}[opciones.onChange] se llama tras elegir o quitar
 * @param {Function}[opciones.alReiniciar] se llama al limpiar el formulario
*/
  function initFoto(opciones) {
    var o = opciones || {};
    var input   = document.getElementById(o.inputId);
    var preview = document.getElementById(o.previewId);
    if (!input || !preview) return null;

    var quitar   = o.quitarId ? document.getElementById(o.quitarId) : null;
    var vacio    = o.vacio || '<i class="bi bi-person"></i>';
    var objectUrl = null;

  /* El `name` REAL del input, leído del markup una sola vez al iniciar. Antes se deducía del id
 y, como ningún módulo pasa inputName, quedaba siendo 'fotoInput': al quitar la foto se
 escribía ese valor sobre el atributo y el formulario enviaba un campo que no existía.*/
    var nombreOriginal = input.getAttribute('name') || '';

    /* Vuelve a poner el nombre bueno. Se llama en AMBOS pintados, no solo al quitar:
 si otra cosa del código lo cambiara, elegir una foto nueva lo dejaría en
 cualquier valor y volveríamos al mismo fallo.*/
    function restaurarNombre() {
      if (nombreOriginal) {
        input.setAttribute('name', nombreOriginal);
      }
    }

    function liberar() {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
    }

    /* El botón de quitar solo se ve cuando HAY foto, así que su visibilidad depende de
 eso y no del motivo del repintado.

 El flag es otra cosa: es lo que le dice al servidor "bórrala" cuando la quitaron
 del formulario. Se separa del botón porque las dos cosas no siempre van juntas —
 `vaciar()` desde `alReiniciar` también repinta a vacío, y ahí lo que se quiere es
 dejarla como estaba, no pedir que se borre.*/
    function pintarBotonQuitar(visible) {
      if (quitar) quitar.hidden = !visible;
    }

    /* Marca si la foto se quitó (1) o si se chose otra / sigue igual (0).
 El flag se busca por el nombre ORIGINAL del input (`<name>Eliminar`) y ya no por
 `id + 'Eliminar'`, que daba 'fotoInputEliminar' y no existía en ningún markup:
 esa parte llevaba tiempo sin hacer nada.*/
    function marcarFotoEliminada(eliminada) {
      var flag = nombreOriginal ? document.getElementById(nombreOriginal + 'Eliminar') : null;
      if (flag) flag.value = eliminada ? '1' : '0';
    }

    function pintarVacia(eliminada) {
      liberar();
      preview.innerHTML = vacio;
      input.value = '';
      restaurarNombre();
      pintarBotonQuitar(false);
      marcarFotoEliminada(eliminada === true);
    }

    function pintarArchivo(archivo) {
      liberar();
      objectUrl = URL.createObjectURL(archivo);
      preview.innerHTML = '<img src="' + objectUrl + '" alt="Vista previa">';
      restaurarNombre();
      pintarBotonQuitar(true);
      marcarFotoEliminada(false);
    }

    input.addEventListener('change', function () {
      var archivo = input.files[0];
      if (archivo) {
        pintarArchivo(archivo);
      } else {
        /* Se canceló el diálogo de archivos: no es que la secretaria haya quitado la
 foto, así que no se marca para que el servidor la borre.*/
        pintarVacia(false);
      }
      if (typeof o.onChange === 'function') o.onChange(archivo || null);
    });

    if (quitar) {
      quitar.addEventListener('click', function () {
        /* Esto sí es una baja explícita: el flag queda en 1 para que el servidor sepa
 que tiene que borrar la imagen anterior.*/
        pintarVacia(true);
        if (typeof o.onChange === 'function') o.onChange(null);
      });
    }

    if (typeof o.alReiniciar === 'function') {
      /* Al reiniciar el formulario la foto tampoco se "quita": se limpia el estado
 del wizard, así que se deja el flag como estaba.*/
      o.alReiniciar(function () { pintarVacia(false); });
    }

    return { vaciar: pintarVacia, alCambiar: function () { return objectUrl; } };
  }

  /* ---------- Tarjetas seleccionables (sexo y similares) ----------*/

  /**
 * Marca la tarjeta .is-selected según el radio que esté marcado dentro de
 * ella. Se sincroniza el grupo completo en cada cambio, para que nunca
 * queden dos tarjetas resaltadas.
 *
 * @param {string} [selector] por defecto '[data-sexo-card]'
*/
  function initTarjetasSeleccionables(selector) {
    var sel = selector || '[data-sexo-card]';
    var tarjetas = document.querySelectorAll(sel);
    if (!tarjetas.length) return;

    function sincronizar() {
      tarjetas.forEach(function (tarjeta) {
        var radio = tarjeta.querySelector('input[type="radio"]');
        if (radio) tarjeta.classList.toggle('is-selected', radio.checked);
      });
    }

    tarjetas.forEach(function (tarjeta) {
      var radio = tarjeta.querySelector('input[type="radio"]');
      if (radio) radio.addEventListener('change', sincronizar);
  /* Al hacer click en la tarjeta (no en el radio) se marca el radio, que dispara 'change' y reentra
 en sincronizar. El radio deshabilitado se respeta a propósito: una persona ya registrada tiene
 el sexo bloqueado.*/
      tarjeta.addEventListener('click', function () {
        var input = tarjeta.querySelector('input[type="radio"]');
        if (input && !input.disabled && !input.checked) input.checked = true;
        sincronizar();
      });
    });

    sincronizar();
  }

  /* ---------- Tipo de documento: solo la letra cuando está cerrado ----------*/

  /**
 * El <select> de tipo de documento muestra únicamente la letra (V, E, CE)
 * mientras está cerrado, y el texto completo ("V - Venezolano",
 * "E - Extranjero", "CE - Cédula escolar") solo al desplegarlo, en el
 * focus. Así el campo ocupa lo mismo que una letra, que es como el
 * estudiante la ve en la cédula, sin perder la claridad de qué está
 * eligiendo.
 *
 * El texto original de cada <option> queda guardado, así que el select
 * siempre puede alternar entre letra y texto completo, y arranca siempre
 * con la letra.
 *
 * @param {Object} opciones
 * @param {string} opciones.selectId id del <select>
*/
  function initTipoDocumentoLetra(opciones) {
    var o = opciones || {};
    var select = o.selectId ? document.getElementById(o.selectId) : null;
    if (!select) return;

    var originales = Array.from(select.options).map(function (opcion) {
      return { value: opcion.value, texto: opcion.textContent };
    });

    function pintar(mostrarTexto) {
      // Se guarda el valor porque reescribir el textContent de las opciones
      // puede resetear la selección en algunos navegadores.
      var valorActual = select.value;

      originales.forEach(function (opcion, i) {
        select.options[i].textContent = mostrarTexto ? opcion.texto : opcion.value;
      });

      select.value = valorActual;
    }

    pintar(false);                      // arranca mostrando solo la letra
    select.addEventListener('focus', function () { pintar(true); });
    select.addEventListener('blur', function () { pintar(false); });
    select.addEventListener('change', function () { pintar(false); });
  }

  /* ---------- Filtros de escritura ----------*/

  /**
 * Deja pasar solo dígitos, hasta 7, en los inputs de teléfono.
 *
 * El componente phone-input.php ya pone pattern="[0-9]{7}" y maxlength="7",
 * que hacen que checkValidity() marque el campo; esto evita además que la
 * letra llegue a escribirse.
 *
 * @param {string} [selector] por defecto 'input[pattern="[0-9]{7}"]'
*/
  function initSoloDigitos(selector) {
    var sel = selector || 'input[pattern="[0-9]{7}"]';
    document.addEventListener('input', function (e) {
      if (!e.target.matches(sel)) return;
      var limpio = e.target.value.replace(/\D/g, '').slice(0, 7);
      if (limpio !== e.target.value) e.target.value = limpio;
    });
  }

  /**
 * Solo letras y espacios en los campos de nombre/apellido.
 *
 * @param {string} [selector] por defecto '#primerNombre, #segundoNombre, #primerApellido, #segundoApellido'
*/
  function initSoloLetras(selector) {
    var sel = selector || '#primerNombre, #segundoNombre, #primerApellido, #segundoApellido';
    document.addEventListener('input', function (e) {
      if (!e.target.matches(sel)) return;
      var limpio = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
      if (limpio !== e.target.value) e.target.value = limpio;
    });
  }

  /**
 * Textos de cada nivel de la cascada. Mientras falta el padre, el select no
 * solo queda deshabilitado: su única opción explica qué hay que elegir
 * primero, para que se entienda que no está roto.
*/
  var TEXTOS_UBICACION = {
    municipio: { esperando: 'Seleccione primero el estado', cargando: 'Cargando municipios...', vacio: 'Sin municipios registrados', elegir: 'Seleccione municipio' },
    parroquia: { esperando: 'Seleccione primero el municipio', cargando: 'Cargando parroquias...', vacio: 'Sin parroquias registradas', elegir: 'Seleccione parroquia' }
  };

  function textoUbicacion(tipo, clave) {
    return (TEXTOS_UBICACION[tipo] || {})[clave] || 'Seleccione...';
  }

  /**
 * Cascada estado -> municipio -> parroquia de la DIRECCIÓN.
 *
 * Está en el módulo compartido porque la usan dos wizards: el de empleados
 * (alta y edición) y el de inscripciones, en el sub-modal de familiares. Es
 * el mismo endpoint, el mismo comportamiento y los mismos textos.
 *
 * Los tres <select> están habilitados desde el principio. Los municipios y las
 * parroquias se piden completos (334 y 42 filas) una sola vez por formulario
 * y cada opción trae el id de su padre (data-id-estado / data-id-municipio);
 * al elegir arriba, el de abajo se queda solo con las opciones que dependen
 * de esa elección, usando el MISMO filtro de los títulos por grado
 * (SIGDEUI.filtrarOpciones).
 *
 * Antes se pedía el nivel hijo al elegir el padre y por eso el municipio
 * arrancaba deshabilitado con "Seleccione primero el estado". Con listas
 * completas no hace falta: se elige en cualquier orden y el filtro se encarga
 * de que la combinación siga siendo válida.
 *
 * @param {object} opciones
 * @param {string} opciones.estadoId id del <select> de estado.
 * @param {string} opciones.municipioId id del <select> de municipio.
 * @param {string} opciones.parroquiaId id del <select> de parroquia.
 * @param {string} opciones.url endpoint que devuelve el nivel.
 * @returns {object} API: { cargando, elegirParroquia, reiniciar }.
*/
  function initCascadaUbicacion(opciones) {
    var selEstado    = document.getElementById(opciones.estadoId);
    var selMunicipio = document.getElementById(opciones.municipioId);
    var selParroquia = document.getElementById(opciones.parroquiaId);
    /* Se acepta el endpoint con o sin '?' final y se normaliza acá: armarlo con
 dos produce "?tipo=municipio" con el signo de interrogación dentro del
 nombre del parámetro, y el servidor no ve el tipo.*/
    var url = String(opciones.url || '').replace(/[?&]+$/, '');
    var etiqueta = function (campo) { return (campo && campo.value) || ''; };
      var sinFiltro = function (select) {
        /* Deshace lo que hace SIGDEUI.filtrarOpciones. Tiene que limpiar el
 `disabled` además del `hidden`: si solo limpiara el `hidden`, las
 opciones seguirían sin poder elegirse con el ratón aunque se vieran,
 que es el mismo tipo de desincronización que se corrigió en el
 filtro.*/
        Array.prototype.forEach.call(select.options, function (o) {
          o.hidden = false;
          o.disabled = false;
        });
      };

    if (!selEstado || !selMunicipio || !selParroquia || !url) {
      return {
        cargando: Promise.resolve(false),
        elegirParroquia: function () { return Promise.resolve(false); },
        reiniciar: function () {}
      };
    }

    /**
 * Al elegir arriba, abajo solo deja pasar lo que le corresponde. Si la
 * elección que había abajo deja de ser válida, el propio filtro la limpia
 * (y por eso hay que filtrar también el nieto: quedó sin elegir).
*/
      function filtrarHijos() {
        var hayMunicipios = SIGDEUI.filtrarOpciones(selMunicipio, etiqueta(selEstado), 'data-id-estado');
        var hayParroquias = SIGDEUI.filtrarOpciones(selParroquia, etiqueta(selMunicipio), 'data-id-municipio');

        explicarVacio(selMunicipio, hayMunicipios, !etiqueta(selEstado) ? 'Seleccione el estado primero' : '');
        explicarVacio(selParroquia, hayParroquias, !etiqueta(selMunicipio) ? 'Seleccione el municipio primero' : 'Sin parroquías para este municipio');
      }

      /**
 * Pone el texto del placeholder según si el nivel tiene algo que mostrar.
 *
 * Un <select> sin opciones visibles parece una cascada rota, y aquí va a
 * pasar: el catálogo de parroquías solo tiene datos de dos municipios, así
 * que para los demás la lista queda vacía de verdad. Mejor decirlo en el
 * campo que dejar que quien registra piense que se rompió la página. El
 * texto original se guarda la primera vez y se restaura cuando el nivel
 * vuelve a tener opciones.
*/
      function explicarVacio(select, hay, texto) {
        if (!select) return;
        var vacia = select.options[0];
        if (!vacia) return;
        if (hay || !texto) {
          if (vacia.dataset.textoOriginal) vacia.textContent = vacia.dataset.textoOriginal;
          return;
        }
        if (!vacia.dataset.textoOriginal) vacia.dataset.textoOriginal = vacia.textContent;
        vacia.textContent = texto;
      }

    /**
 * Pide un nivel completo, lo deja en el <select> sin filtro y lo habilita.
 * No deja el select deshabilitado esperando al padre: el padre ya no hace
 * falta para traer los datos, solo para filtrarlos.
*/
    function pedirNivel(select, tipo, marcador) {
      return SIGDEUI.enviar(url + '?tipo=' + encodeURIComponent(tipo))
        .then(function (r) {
          if (!r.ok || !r.data || !r.data.ok || !Array.isArray(r.data.data)) {
            SIGDEUI.mostrarError((r.data && r.data.mensaje) || 'No se pudieron cargar las opciones.', 'Ubicación');
            SIGDEUI.rellenarSelect(select, [], marcador);
            return false;
          }
          SIGDEUI.rellenarSelect(select, r.data.data, r.data.data.length ? marcador : 'Sin opciones');
          sinFiltro(select);
          select.disabled = false;
          return r.data.data.length > 0;
        })
        .catch(function () {
          SIGDEUI.rellenarSelect(select, [], 'Sin opciones');
          SIGDEUI.mostrarError('Fallo de conexión al cargar las opciones de ubicación.', 'Ubicación');
          return false;
        });
    }

    /* Las dos listas se piden en paralelo: no hay dependencia entre ellas.*/
    var cargando = Promise.all([
      pedirNivel(selMunicipio, 'municipio', 'Seleccione el municipio'),
      pedirNivel(selParroquia, 'parroquia', 'Seleccione la parroquia')
    ]).then(function (r) {
      filtrarHijos();
      return r[0] && r[1];
    });

    /* Los dos cambios pasan por filtrarHijos(), no solo por filtrarOpciones()
 del nivel que toca: el texto del placeholder se actualiza ahí, y con el
 atajo anterior la parroquia se quedaba diciendo "seleccione el municipio
 primero" con el municipio ya elegido.*/
    selEstado.addEventListener('change', filtrarHijos);
    selMunicipio.addEventListener('change', filtrarHijos);

    /**
 * Deja la cascada con los tres niveles elegidos, para cuando ya se sabe la
 * parroquia (por ejemplo, al cargar los datos de un familiar que está
 * registrado). Se eligen de arriba hacia abajo para que cada filtro vea el
 * valor del padre; al terminar se comprueba que los tres quedaron, porque
 * asignar a un <select> un valor que no está en la lista no da error: lo
 * ignora en silencio.
 *
 * @param {number} estadoId
 * @param {number} municipioId
 * @param {number} parroquiaId
 * @returns {Promise<boolean>} true si los tres niveles quedaron elegidos.
*/
    function elegirParroquia(estadoId, municipioId, parroquiaId) {
      return cargando.then(function () {
        if (estadoId > 0) selEstado.value = String(estadoId);
        if (municipioId > 0) selMunicipio.value = String(municipioId);
        filtrarHijos();
        if (parroquiaId > 0) selParroquia.value = String(parroquiaId);

        return (!estadoId    || selEstado.value === String(estadoId))
            && (!municipioId || selMunicipio.value === String(municipioId))
            && (!parroquiaId || selParroquia.value === String(parroquiaId));
      });
    }

  /* Deja un nivel como recién llegado: sin valor y con la lista filtrada por el padre que haya.
 Antes hacía `sinFiltro` y se veía al abrir el formulario: el sub-modal del familiar y el del
 empleado llamaban a reiniciar esperando una lista vacía.*/
      function reiniciar(select) {
        if (!select) return;
        select.selectedIndex = 0;
        filtrarHijos();
        select.disabled = false;
      }

    return { cargando: cargando, elegirParroquia: elegirParroquia, reiniciar: reiniciar };
  }

  /* ---------- Cascada del lugar de NACIMIENTO del estudiante ----------

  Los tres selects los imprime components/lugar-nacimiento-form.php y son los mismos
  en el alta de inscripción y en el modal de edición de ratificación, porque las dos
  pantallas reutilizan el paso 1. Los ids vivían escritos en enrollments.js, y como esa
  pantalla no se carga en ratificación el modal de edición se quedaba sin cascada: al
  elegir la entidad federal no cargaban ni los municipios ni las parroquias.

  Con los ids en un solo lugar, los dos módulos llaman a esta función con SU endpoint y
  no pueden quedar desincronizados. El endpoint se recibe como parámetro justamente por
  eso: no es el mismo en los dos módulos. */
  function initCascadaNacimiento(url) {
    return initCascadaUbicacion({
      url: url,
      estadoId: 'nacimientoEstado',
      municipioId: 'nacimientoMunicipio',
      parroquiaId: 'nacimientoParroquia'
    });
  }

  return {
    initCascadaNacimiento: initCascadaNacimiento,
    initFoto: initFoto,
    initTarjetasSeleccionables: initTarjetasSeleccionables,
    initSoloDigitos: initSoloDigitos,
    initSoloLetras: initSoloLetras,
    initTipoDocumentoLetra: initTipoDocumentoLetra,
    initCascadaUbicacion: initCascadaUbicacion
  };
})();
