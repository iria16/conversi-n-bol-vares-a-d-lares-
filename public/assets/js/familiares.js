/**
 * Sub-modal de familiares, compartido por el alta de inscripción y la edición desde
 * ratificación. Las reglas de los familiares son de negocio, no de pantalla: una sola copia
 * evita que las dos pantallas se desincronicen. Inscripción ARMA la lista y ratificación la
 * EDITA; quien llama lo decide con `contactoEditable`.
 * @see app/Traits/FamiliaresModelTrait.php (las mismas reglas en el servidor)*/
(function (global) {
  'use strict';

  /* Si el módulo ya está cargado se respeta: dos <script> con el mismo archivo
 no pueden crear dos listas de familiares sobre el mismo formulario.*/
  if (global.FamiliaresUI) return;

    /* Una instancia por modal. Un segundo init() sobre la misma página volvería a enlazar los
 botones y el guardado correría dos veces, así que se corta aquí.*/
  var instanciasPorModal = new WeakMap();

  var instancia = null;

  /* ---------------------------------------------------------------------
 * Utilidades
 * ------------------------------------------------------------------*/

  function escHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function soloDigitos(valor) { return String(valor || '').replace(/\D/g, ''); }

  function esperar(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
  }

      /* ¿El parentesco es el del padre o el de la madre? Se compara con el NOMBRE del catálogo,
 que es lo que viaja en el payload, y no con el id: los ids cambian si se rehace el
 catálogo. El PHP aplica la misma regla porque el JSON se puede armar a mano.*/
  function esPadreOMadreSegun(parentesco) {
    var nombre = String(parentesco || '').trim().toLowerCase();
    return nombre === 'madre' || nombre === 'padre';
  }

  /* ---------------------------------------------------------------------
 * Módulo
 * ------------------------------------------------------------------*/

  function crear(config) {
    config = config || {};

    /* `baseRoute` debe terminar en '/': es la ruta del módulo, a la que se le
 pegan 'buscarPersona' y 'getUbicacion'.*/
    var baseRoute = (config.baseRoute || '').replace(/\/*$/, '/');

 /* Permite desactivar un familiar en vez de borrarlo. En inscripción es un alta y "dejar de
 ser familiar" es la operación de verdad; en ratificación también y lo decide quien llama.*/
      var permitirDesactivar = config.permitirDesactivar !== false;

            /* Desvincular, en vez de desactivar (hallazgo MEDIO 4.7). Solo la usa la pantalla con la
 lista ya guardada; en inscripción sigue el switch porque ahí quitar a alguien es "me
 equivoqué al escribirlo". La papelera lo reemplaza: el switch lo dejaba en el payload.*/
      var permitirDesvinculo = config.permitirDesvinculo === true;

        /* ¿Se puede editar el contacto de ESTE familiar? En inscripción siempre; en ratificación solo
 el representante legal. Esto apaga los campos en pantalla, pero la regla la aplica
 RatificacionController: el formulario se puede mandar a mano.*/
    function contactoEditable(familiar) {
      if (typeof config.contactoEditable === 'function') {
        return config.contactoEditable(familiar) === true;
      }
      return true;
    }

        /**
 * ¿Esta persona ya existe? Si trae id_persona (viene del servidor) su identidad queda
 * bloqueada: nombres, fecha y sexo, porque el sexo decide cómo aparece en listas y
 * documentos. El nivel y la ocupación no se bloquean: son de la relación con este menor.
*/
    function esFamiliarRegistrado(f) {
      return !!(f && f.id_persona && Number(f.id_persona) > 0);
    }

        /**
 * Id del estudiante en edición, si esta pantalla lo tiene (hidden #editEstudianteId). En el
 * alta no existe todavía: el paso 6 va antes, así que se devuelve null y el servidor
 * resuelve con la relación más reciente.
*/
    function idEstudianteContexto() {
      var campo = document.getElementById('editEstudianteId');
      var valor = campo ? String(campo.value || '').trim() : '';
      return valor !== '' && valor !== '0' ? valor : null;
    }

    function alCambiar() {
      if (typeof config.alCambiar === 'function') config.alCambiar(familiares);
    }

    /**
 * Escribe el JSON en el hidden configurado.
 *
 * El hidden es lo que viaja al servidor, así que esta es la fuente de verdad de
 * lo que se guarda: `cargar()` la llama, y no solo el envío final, para que el
 * hidden no se quede con el contenido de la lista anterior mientras se edita.
*/
            /**
 * Los familiares que van en el payload. Las filas marcadas NO van: eso hace que el
 * backend las marque INACTIVO (sincronizarFamiliares, guarda 2). cargar() sustituye la
 * lista entera, así que el marcador no sobrevive a un guardado.
*/
      function losQueSeGuardan() {
        return familiares
          .filter(function (f) { return !f._desvincular; })
          .map(sinCamposInternos);
      }

          /**
 * Copia sin las claves internas. Se copia en vez de filtrar en el sitio: _desvincular debe
 * seguir vivo en el original, del que dependen la fila tachada y el botón de deshacer.
*/
      function sinCamposInternos(f) {
        var copia = {};
        for (var clave in f) {
          if (!Object.prototype.hasOwnProperty.call(f, clave)) continue;
          if (clave.charAt(0) === '_') continue;
          copia[clave] = f[clave];
        }
        return copia;
      }

          /**
 * Escribe el JSON en el hidden, que es lo que viaja al servidor. La llama cargar() y no solo
 * el envío final, para que el hidden no conserve la lista anterior mientras se edita.
*/
      function serializar() {
        var input = config.jsonInputId ? document.getElementById(config.jsonInputId) : null;
        var contenido = JSON.stringify(losQueSeGuardan());
        if (input) input.value = contenido;
        return contenido;
      }

    // ---------- Estado ----------
    var familiares = [];        // la lista; es la fuente de verdad
    var editandoIndice = null;  // fila en edición, o null si se está agregando
    var estadoFamiliar = 'ACTIVO';
    var pendiente = null;       // familiar a reabrir tras cambiar de representante

        /**
 * Cédula con datos cargados ahora en el sub-modal. Distingue "está escribiendo una cédula
 * nueva" de "corrige los datos de otra": en el primer caso no se toca nada.
*/
    var cedulaCargada = null;

    /* La persona de la última búsqueda y su nombre, para poder repintar el aviso
 de diferencias cuando la secretaria edita el contacto o el domicilio (eso
 no dispara la búsqueda, que solo corre con la cédula).*/
    var personaEncontrada = null;
    var nombreEncontrado = '';

        /* Si la cascada no pudo dejar los tres niveles, queda en false y el aviso lo dice: si no, la
 secretaria ve dos select vacíos sin saber que su domicilio no cargó.*/
    var cascadaArmada = null;

    /* Nivel de instrucción y ocupación que ya tenía la persona encontrada por
 cédula. Se guardan aparte de los <select> porque sincronizar() los vacía
 mientras el bloque no aplica (solo se pregunta al representante y a la madre y
 al padre), y una persona se busca antes de marcar su rol. Ver buscarFamiliar.*/
    var nivelPrecargado = '';
    var ocupacionPrecargada = '';

    var cascada = null;      // SIGDEWizardUI.initCascadaUbicacion
    var foto = null;         // SIGDEWizardUI.initFoto
    var bloqueRepresentanteUI = null;

    var btnAbrir = document.getElementById(config.botonAbrirId || 'btnAbrirAgregarFamiliar');
    var modalEl = document.getElementById(config.modalId || 'modalAgregarFamiliar');
    var formFamiliar = document.getElementById('formAgregarFamiliar');

    /* Campos que identifican a la persona (nombres y fecha de nacimiento). Cuando
 la cédula ya está registrada se bloquean: son los datos con los que está en
 el sistema y no tienen sentido cambiarlos desde acá. El contacto y el
 domicilio sí se pueden modificar, porque cambian.*/
    var CAMPOS_IDENTIDAD = [
      'familiarPrimerNombre', 'familiarSegundoNombre',
      'familiarPrimerApellido', 'familiarSegundoApellido',
      'familiarFechaNacimiento'
    ];

    /* Los campos que SIGUEN editables cuando la persona ya está registrada: el
 contacto y el domicilio. Es la lista que compara el aviso de diferencias y
 la que se escucha para repintarlo. El prefijo del teléfono principal también
 va, porque cambiar el código cambia el número que se guarda.*/
    var CAMPOS_CONTACTO = [
      'familiar_codigo', 'familiar_numero',
      'familiar_codigo_alt', 'familiar_numero_alt',
      'familiarCorreoElectronico',
      'familiarEstado', 'familiarMunicipio', 'familiarParroquia',
      'familiarSector', 'familiarCalle',
      'familiarCasa', 'familiarReferencia'
    ];

    // =================================================================
    // El familiar que ya está registrado
    // =================================================================

    function forgetPersonaEncontrada() {
      personaEncontrada = null;
      nombreEncontrado = '';
      cascadaArmada = null;
      var aviso = document.getElementById('personaEncontrada');
      if (aviso) aviso.classList.add('d-none');
    }

    /**
 * Compara lo que la secretaria escribió contra lo que hay guardado y
 * devuelve las etiquetas de los campos que difieren. Se calcula ANTES de
 * llenar el formulario con los datos guardados, que es justo lo que hay
 * que revisar.
 *
 * @param {object} antes Foto del formulario (tomarLoEscrito()).
 * @param {object} encontrado Lo que devolvió buscarPersona.
 * @param {boolean} vacioEsCambio Si un campo vacío cuenta como cambio.
 * Es false en la comparación inicial (antes de llenar, un vacío es
 * "todavía no escribió") y true al repintar el aviso con el
 * formulario ya lleno (ahí vaciar el correo de alguien que lo tenía
 * sí es un cambio).
 * @returns {string[]} Etiquetas de lo que difiere.
*/
    function diferencias(antes, encontrado, vacioEsCambio) {
      var lista = [];

      /* Solo se comparan el contacto y el domicilio: son los únicos datos que
 se pueden cambiar de una persona ya registrada. Los nombres, el sexo y la
 fecha de nacimiento vienen bloqueados y no se tocan, así que no hay
 nada que comparar de ellos.*/
      function difiere(escrito, guardadoCampo) {
        var escritoTxt = String(escrito || '').trim();
        var guardadoTxt = String(guardadoCampo || '').trim();

        if (escritoTxt === '') return vacioEsCambio && guardadoTxt !== '';

        return escritoTxt !== guardadoTxt;
      }

      if (encontrado.contacto) {
        var telEscrito = soloDigitos(antes.familiar_numero);
        if ((vacioEsCambio || telEscrito !== '')
            && telEscrito !== soloDigitos(encontrado.contacto.numero)) {
          lista.push('teléfono principal');
        }
        if (difiere(antes.familiarCorreoElectronico, encontrado.contacto.correo)) {
          lista.push('correo');
        }
      }

      if (encontrado.direccion) {
        var mapa = { sector: 'familiarSector', calle: 'familiarCalle',
                     casa: 'familiarCasa', referencia: 'familiarReferencia' };
        Object.keys(mapa).forEach(function (clave) {
          if (difiere(antes[mapa[clave]], encontrado.direccion[clave])) {
            lista.push(clave === 'referencia' ? 'punto de referencia' : clave);
          }
        });
        /* Estado, municipio y parroquia se comparan por su id, que es lo que
 guarda cada <select>, no por el nombre que muestran. Como los tres
 niveles van en cascada, alcanza con avisar del más alto que cambió: si
 cambió el estado, el municipio y la parroquia son otra cosa.*/
        var niveles = [
          ['familiarEstado', 'estado'],
          ['familiarMunicipio', 'municipio'],
          ['familiarParroquia', 'parroquia']
        ];

        for (var i = 0; i < niveles.length; i++) {
          var nivelEscrito = String(antes[niveles[i][0]] || '');
          if ((vacioEsCambio || nivelEscrito !== '')
              && nivelEscrito !== String(encontrado.direccion[niveles[i][1]])) {
            lista.push(niveles[i][1]);
            break;
          }
        }
      }

      return lista;
    }

    /** Foto de lo que hay escrito en el formulario, antes de llenarlo con lo guardado.*/
    function tomarLoEscrito() {
      var fotoFormulario = {};
      CAMPOS_CONTACTO.forEach(function (id) {
        fotoFormulario[id] = (document.getElementById(id) || {}).value || '';
      });
      return fotoFormulario;
    }

    /**
 * Escribe el aviso de "ya está registrada", con la lista de datos que van a
 * cambiar. Si no se le pasa esa lista, la calcula del estado actual del
 * formulario, que es lo que hace cuando la secretaria edita algo.
*/
    function pintarAviso(cambios) {
      var panel = document.getElementById('personaEncontrada');
      var texto = document.getElementById('personaEncontradaTexto');
      if (!personaEncontrada || !panel || !texto) return;

      var lista = cambios || diferencias(tomarLoEscrito(), personaEncontrada, true);

      /* Si no hay domicilio guardado no hay nada que comparar, y tampoco nada que
 "coincida": los tres <select> de la cascada se quedan vacíos porque no hay
 niveles que elegir, no porque fallara la carga.*/
      var conDomicilio = !!personaEncontrada.direccion;

      /* Aquí solo va lo que la secretaria tiene que hacer algo con. Los datos
 personales bloqueados se ven bloqueados, y que el contacto y el
 domicilio coincidan con lo registrado no es información: se ve solo en
 que los campos están llenos. Los avisos que sí quedan son los que piden
 una decisión: que falta el domicilio, o qué va a cambiar.*/
      var html = '<strong>Ya está registrada: ' + escHtml(nombreEncontrado) + '</strong>';
      if (personaEncontrada.representante_de > 0) {
        html += '<br>Es representante legal de ' + personaEncontrada.representante_de + ' estudiante(s).';
      }

      if (!conDomicilio) {
        html += '<br><strong>No tiene domicilio registrado:</strong> escríbaselo en el formulario. '
              + 'Si la marca como representante legal, es obligatorio.';
      }

      if (lista.length) {
        html += '<br>Van a cambiar: ' + escHtml(lista.join(', ')) + '.';
      }

      /* La ubicación se carga en cascada y con dos peticiones: si no pudo
 completarse, avisarlo es la diferencia entre "no hay nada que elegir" y
 "su domicilio no se pudo cargar".*/
      if (cascadaArmada === false && conDomicilio) {
        html += '<br><strong>No se pudo cargar su ubicación:</strong> el estado, el municipio '
              + 'o la parroquia que tiene registrados ya no están en el catálogo. Elija los tres '
              + 'niveles a mano para corregirlos.';
      }

      texto.innerHTML = html;
      panel.classList.remove('d-none');
    }

    /**
 * Bloquea o desbloquea los datos que identifican a la persona. Se usa
 * readonly en los <input> (así el valor sigue siendo legible y se puede
 * copiar) y disabled en los radios del sexo, que no tienen readonly.
*/
    function bloquearIdentidad(bloquear) {
      CAMPOS_IDENTIDAD.forEach(function (id) {
        var campo = document.getElementById(id);
        if (!campo) return;

        if (bloquear) campo.setAttribute('readonly', '');
        else campo.removeAttribute('readonly');
      });

    /* El sexo son dos <input type="radio"> sin id, se toman por su name. Sin habilitarlos no se
 pueden marcar con el teclado y la tarjeta queda con `is-locked`, que además lleva
 pointer-events: none. El atributo es `data-sexo-card`, sin prefijo: lo emite persona-form.php
 tal cual y no admite uno por parámetro.*/
        var sexos = (modalEl || document).querySelectorAll('input[name="familiar_sexo"]');
        Array.prototype.forEach.call(sexos, function (radio) { radio.disabled = bloquear; });

        var tarjetas = (modalEl || document).querySelectorAll('[data-sexo-card]');
        Array.prototype.forEach.call(tarjetas, function (tarjeta) {
          tarjeta.classList.toggle('is-locked', bloquear);
          tarjeta.setAttribute('aria-disabled', bloquear ? 'true' : 'false');
        });
    }

    /**
 * Apaga el contacto y el domicilio de un familiar que en esta pantalla NO se
 * pueden cambiar (en ratificación, todos menos el representante).
 *
 * Se usa `disabled` y no solo `readonly` porque son <select> y el input del
 * teléfono, que no soportan readonly. Además se deja anotado en la fila con
 * `contactoBloqueado`, para que al armar el familiar se reenvíe lo que ya
 * estaba guardado en vez de lo que quedó en el DOM (que puede venir vacío y
 * borraría el teléfono de alguien).
*/
    function bloquearContacto(bloquear) {
      CAMPOS_CONTACTO.forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) campo.disabled = bloquear;
      });

      var aviso = document.getElementById('familiarInfoBloqueo');
      if (aviso) aviso.classList.toggle('d-none', !bloquear);
    }

    // =================================================================
    // Sub-modal
    // =================================================================

    /**
 * Deja el sub-modal como recién abierto: sin nombres, sin contacto, sin
 * domicilio y sin los datos que se hubieran cargado de otra cédula.
 *
 * Se usa en dos momentos, y no son lo mismo:
 * - al abrirlo, se revierte TODO, incluido el tipo de documento y el
 * parentesco;
 * - al borrar la cédula, se conservan el tipo de documento y el parentesco,
 * porque son decisiones de quien escribe, no datos cargados de la persona
 * equivocada. Lo que sí se va es el resto, incluido el domicilio y el
 * teléfono, que sí venían de esa cédula.
 *
 * @param {boolean} conservarDecisiones true para no tocar tipo y parentesco.
 * @param {HTMLElement} [excepto] Campo que NO se toca. Hace falta porque el
 * aviso se dispara desde el input de la cédula: si se vaciara también,
 * se borraría a sí mismo en cada tecla y el campo parecería bloqueado.
*/
    function vaciarSubModal(conservarDecisiones, excepto) {
      if (!modalEl) return;

      /* Los datos que había en el formulario eran de esta cédula: si se vacía el
 formulario, esa cédula ya no tiene nada cargado, y lo que estaba bloqueado
 vuelve a poder escribirse.*/
      cedulaCargada = null;
      bloquearIdentidad(false);
      bloquearContacto(false);
      editandoIndice = null;
      nivelPrecargado = '';
      ocupacionPrecargada = '';
      ['familiarIndiceEdicion', 'familiarIdRelacion', 'familiarEstadoRelacion'].forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) campo.value = '';
      });

      modalEl.querySelectorAll('input[type="text"], input[type="email"], input[type="date"], textarea')
        .forEach(function (el) { if (el !== excepto) el.value = ''; });
      modalEl.querySelectorAll('input[type="radio"]').forEach(function (el) { el.checked = false; });
      modalEl.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

      if (conservarDecisiones) {
        /* El domicilio sí venía de la persona encontrada, así que la cascada vuelve
 a su estado inicial: estado sin elegir, municipio y parroquia
 deshabilitados explicando qué falta. El prefijo telefónico también es un
 dato cargado y vuelve al primero.*/
        var estado = document.getElementById('familiarEstado');
        if (estado) estado.selectedIndex = 0;
        if (cascada) {
          cascada.reiniciar(document.getElementById('familiarMunicipio'));
          cascada.reiniciar(document.getElementById('familiarParroquia'));
        }
        /* El lugar de nacimiento también venía de la persona encontrada y se iba
        como el domicilio. Sin esto, abrir el sub-modal para OTRO familiar dejaba el
        estado de nacimiento del anterior: es un <select>, y el reset de arriba solo
        vacía input de texto. */
        var nacEstado = document.getElementById('familiarEstadoNacimiento');
        if (nacEstado) nacEstado.selectedIndex = 0;
        ['familiar_codigo', 'familiar_codigo_alt'].forEach(function (id) {
          var prefijo = document.getElementById(id);
          if (prefijo) prefijo.selectedIndex = 0;
        });

        /* El nivel de instrucción y la ocupación también venían de esa persona.*/
        ['familiarNivelInstruccion', 'familiarOcupacion'].forEach(function (id) {
          var campo = document.getElementById(id);
          if (campo) campo.selectedIndex = 0;
        });
      } else {
        modalEl.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
        if (cascada) {
          cascada.reiniciar(document.getElementById('familiarMunicipio'));
          cascada.reiniciar(document.getElementById('familiarParroquia'));
        }
      }

      if (foto) foto.vaciar();

      /* El aviso de "persona encontrada" y el panel de diferencias son de la cédula
 anterior, así que también se van, y se olvida a quién pertenecían.*/
      forgetPersonaEncontrada();

      /* Y se reaplican las reglas del representante y de "no es familiar", porque
 el reset desmarca los checkbox y los switch se verían apagados.*/
      if (bloqueRepresentanteUI) bloqueRepresentanteUI.sincronizar();
    }

    /* ---------- Cascada de dirección del familiar ----------
 Es la misma del formulario de empleados (SIGDEWizardUI.initCascadaUbicacion),
 con su propio endpoint. Se conserva el handle porque al cargar los datos de
 un familiar registrado hay que dejar los tres niveles elegidos.*/
    function iniciarCascada() {
      cascada = SIGDEWizardUI.initCascadaUbicacion({
        url: baseRoute + 'getUbicacion',
        estadoId: 'familiarEstado',
        municipioId: 'familiarMunicipio',
        parroquiaId: 'familiarParroquia'
      });
    }

    /* ---------- Abrir para agregar ----------*/
    if (btnAbrir && modalEl) {
      btnAbrir.addEventListener('click', function (e) {
        e.stopPropagation();
        vaciarSubModal(false);
        estadoFamiliar = 'ACTIVO';
        var titulo = document.getElementById('modalAgregarFamiliarLabel');
        if (titulo) titulo.textContent = 'Agregar familiar';
        var boton = document.getElementById('btnGuardarFamiliar');
        if (boton) boton.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i> Guardar familiar';
        SIGDEUI.mostrarModal(modalEl);
      });
    }

    /* ---------- Buscar al familiar por su cédula ----------
 Si esa cédula ya está registrada, se llenan sus datos (nombres, sexo y,
 cuando es el representante legal, contacto y domicilio) y se muestra en qué
 difiere de lo que hay en la base, que es lo que la secretaria debe revisar
 antes de guardar. Si no está registrada, se avisa y se sigue con el alta
 normal.*/
    function iniciarBusquedaPorCedula() {
      var input = document.getElementById('familiarNumeroDocumento');
      var panel = document.getElementById('personaEncontrada');
      var texto = document.getElementById('personaEncontradaTexto');
      if (!input || !panel || !texto) return;

      function poner(id, valor) {
        var campo = document.getElementById(id);
        if (campo) campo.value = valor == null ? '' : valor;
      }

      function marcarSexo(sexo) {
        var radio = document.querySelector('input[name="familiar_sexo"][value="' + sexo + '"]');
        if (radio) radio.checked = true;
      }

      var buscar = SIGDEUI.esDebounced(500, function () {
        var documento = soloDigitos(input.value);
        forgetPersonaEncontrada();

        if (documento.length < 7 || documento.length > 8) return;

        /* El estudiante viaja como contexto porque el nivel de instrucción y la
 ocupación del familiar se leen de la relación persona-estudiante, no de la
 persona: sin esto el servidor no sabría de cuál menor tomarlos. Solo
 ratificación tiene un id de estudiante en la página; en el alta todavía no
 hay menor, y allí el servidor usa la relación más reciente.*/
        var url = baseRoute + 'buscarPersona?documento=' + encodeURIComponent(documento);
        var estudiante = idEstudianteContexto();
        if (estudiante) url += '&id_estudiante=' + encodeURIComponent(estudiante);

        SIGDEUI.enviar(url, { metodo: 'GET' })
          .then(function (r) {
            /* Si mientras se consultaba se borró o se corrigió la cédula, la
 respuesta ya no corresponde a lo que hay en el campo: se descarta,
 porque llenarla dejaría los datos de una persona que ya no está.*/
            if (soloDigitos(input.value) !== documento) return;

            /* Los errores del endpoint no significan que la persona no exista.
 Se muestran por separado para evitar crear registros duplicados
 cuando falla la consulta automática.*/
            if (!r.ok || !r.data || r.data.ok !== true) {
              texto.innerHTML = '<strong>No se pudo consultar el registro.</strong> '
                + escHtml((r.data && r.data.mensaje) || 'Revisa la conexión o inténtalo de nuevo.');
              panel.classList.remove('d-none');
              return;
            }

            var encontrado = r.data.data;
            if (encontrado === null) {
              texto.innerHTML = '<strong>No está registrada.</strong> Se creará al guardar el familiar.';
              panel.classList.remove('d-none');
              return;
            }
            if (!encontrado || typeof encontrado !== 'object') {
              texto.innerHTML = '<strong>Respuesta inválida.</strong> No se pudo verificar el documento.';
              panel.classList.remove('d-none');
              return;
            }

            var persona = encontrado.persona || {};
            var textoNombre = [persona.primer_nombre, persona.segundo_nombre,
                              persona.primer_apellido, persona.segundo_apellido]
              .filter(Boolean).join(' ');

            /* Primero se ve qué difiere de lo escrito y después se cargan los datos
 guardados: la secretaria necesita saber qué se cambió. Con
 vacioEsCambio = false, porque en este momento un campo vacío es "todavía
 no escribió", no "lo borró".*/
            var cambios = diferencias(tomarLoEscrito(), encontrado, false);

            /* Los nombres van por el mismo orden que CAMPOS_IDENTIDAD: los cuatro
 campos de persona, en la misma lista que se bloquea después.*/
            var claves = ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'];
            CAMPOS_IDENTIDAD.slice(0, 4).forEach(function (id, i) { poner(id, persona[claves[i]]); });
            poner('familiarFechaNacimiento', persona.fecha_nacimiento);
            /* El familiar declara solo la entidad federal. El select se llama por id con
               prefijo y su name lleva `familiar_`, que es lo que viaja en el payload. */
            poner('familiarEstadoNacimiento', persona.id_estado_nacimiento);
            /* La nacionalidad también se rellena sola si la persona ya está registrada:
               es lo mismo que la fecha, un dato que viene del backend y no algo que la
               secretaria tenga que volver a escribir. */
            poner('familiarNacionalidad', persona.id_nacionalidad);
            marcarSexo(persona.sexo);

            if (encontrado.contacto) {
              poner('familiar_codigo', encontrado.contacto.codigo);
              poner('familiar_numero', encontrado.contacto.numero);
              poner('familiar_codigo_alt', encontrado.contacto.codigo_alt);
              poner('familiar_numero_alt', encontrado.contacto.numero_alt);
              poner('familiarCorreoElectronico', encontrado.contacto.correo);
            }

    /* Nivel de instrucción y ocupación: son datos DE ESA persona y no del parentesco, así que se
 traen. No se bloquean, a diferencia de los nombres y el sexo: cambian (dejó de estudiar,
 cambió de oficio) y quien registra es quien mejor los conoce.*/
            nivelPrecargado = encontrado.nivel_instruccion || '';
            ocupacionPrecargada = encontrado.ocupacion || '';

            /* Si el rol ya estaba marcado ANTES de buscar la cédula (representante
 legal, padre o madre), el bloque ya está visible y nadie volvía a llamar
 a sincronizar(): los selects se quedaban vacíos aunque el dato existiera.
 Se reaplican las reglas aquí. sincronizar() solo rellena selects VACÍOS,
 así que no pisa lo que se haya elegido a mano.*/
            if (bloqueRepresentanteUI) bloqueRepresentanteUI.sincronizar();

            if (encontrado.direccion) {
              poner('familiarSector', encontrado.direccion.sector);
              poner('familiarCalle', encontrado.direccion.calle);
              poner('familiarCasa', encontrado.direccion.casa);
              poner('familiarReferencia', encontrado.direccion.referencia);

              /* La cascada se arma de arriba hacia abajo: con la parroquia guardada
 el servidor devuelve a qué estado y municipio pertenecen, y se
 eligen los tres niveles. Son dos peticiones encadenadas, así que el
 aviso se pinta al final: si no pudo completarse, el aviso lo dice en
 vez de dejar los selects a medias.*/
              if (cascada) {
                cascada.elegirParroquia(
                  Number(encontrado.direccion.estado || 0),
                  Number(encontrado.direccion.municipio || 0),
                  Number(encontrado.direccion.parroquia || 0)
                ).then(function (ok) {
                  cascadaArmada = ok === true;
                  pintarAviso(cambios);
                });
              } else {
                poner('familiarParroquia', encontrado.direccion.parroquia);
                cascadaArmada = true;
              }
            }

            /* Desde acá hay datos de ESTA persona en el formulario: si la cédula se
 borra o se corrige, se limpian (ver revisarCedula()).*/
            cedulaCargada = documento;

            /* Sus datos de identidad no se tocan: ya están en el sistema y cambiarlos
 desde acá dejaría el registro inconsistente con el resto de los módulos
 que lo usan. El contacto y el domicilio sí quedan editables, que es lo
 que realmente cambia. Esto incluye el sexo (radios + tarjetas).*/
            bloquearIdentidad(true);

            /* Se guarda a quién pertenecen estos datos y se pinta el aviso con la
 comparación que se hizo antes de llenar el formulario.*/
            personaEncontrada = encontrado;
            nombreEncontrado = textoNombre;

            /* Si hay domicilio, el aviso espera a que la cascada termine de armarse: si
 no, se pintaría antes de saber si la ubicación se pudo cargar.*/
            if (!encontrado.direccion || !cascada) pintarAviso(cambios);
          })
          .catch(function () {
            texto.innerHTML = '<strong>No se pudo consultar el registro.</strong> Revisa la conexión o inténtalo de nuevo.';
            panel.classList.remove('d-none');
          });
      });

    /* Si la cédula del campo ya no es la misma cuyos datos se cargaron, se limpian: esos datos no
 le pertenecen a nadie. Cierra el hueco de corregir un dígito sin borrar del todo, porque la
 búsqueda solo corre con 7 u 8 dígitos y una cédula a medias dejaba los datos pegados.*/
      function revisarCedula() {
        var documento = soloDigitos(input.value);
        forgetPersonaEncontrada();

        if (cedulaCargada === null) return;   // no hay nada cargado
        if (documento === cedulaCargada) return; // la cédula es la misma

        vaciarSubModal(true, input);
      }

      input.addEventListener('input', function () { revisarCedula(); buscar(); });
      input.addEventListener('change', function () { revisarCedula(); buscar(); });

      /* Editar el contacto o el domicilio de una persona ya registrada cambia la
 lista de "estos datos van a cambiar", así que el aviso se repinta al vuelo.
 Sin esto el aviso quedaba con la comparación del momento de la búsqueda: se
 cambiaba el teléfono y el panel seguía diciendo que todo coincidía.

 Solo repinta si hay una persona cargada: antes de la primera búsqueda esto
 no tiene nada que pintar. Y los campos de identidad no se escuchan, porque
 están bloqueados cuando la persona está encontrada.*/
      CAMPOS_CONTACTO.forEach(function (id) {
        var campo = document.getElementById(id);
        if (!campo) return;

        campo.addEventListener('input', function () { pintarAviso(); });
        campo.addEventListener('change', function () { pintarAviso(); });
      });
    }

    /* Representante legal: lo elige quien registra. El interruptor de arriba es libre, y las reglas
 que se ajustan con él son que el permiso de retiro se marca solo si es el representante, el
 domicilio se pide al representante, la madre o el padre, y los demás familiares solo
 requieren el teléfono principal.*/
    function iniciarBloqueRepresentante() {
      var switchEl = document.getElementById('familiarRepresentantePrincipal');
      var bloqueEl = document.getElementById('bloqueContactoRepresentante');
      if (!switchEl || !bloqueEl) return;

      /* El domicilio va en su propio bloque para poder esconderlo entero cuando el
 familiar no es el representante legal (misma clase de bloque en el resto del
 wizard: el contacto se ve siempre, el domicilio no).*/
      var direccionEl = document.getElementById('bloqueDireccionFamiliar');
      var retirosEl = document.getElementById('familiarAutorizadoRetiros');
      var viveConEl = document.getElementById('familiarViveConEstudiante');

      /* Qué había en el interruptor de representante en la sincronización anterior.
 Sirve para una sola cosa: detectar el momento en que deja de ser
 representante, que es cuando "vive con el estudiante" hay que apagarlo.*/
      var representanteEra = switchEl.checked;
      var datosRepresentanteEl = document.getElementById('bloqueDatosRepresentante');
      var nivelEl = document.getElementById('familiarNivelInstruccion');
      var ocupacionEl = document.getElementById('familiarOcupacion');
      var parentescoEl = document.getElementById('familiarParentesco');
      var nacimientoEl = document.getElementById('bloqueLugarNacimientoFamiliar');

      var DIRECCION = [
        'familiarEstado', 'familiarMunicipio', 'familiarParroquia',
        'familiarSector', 'familiarCalle', 'familiarCasa'
      ];

      /* El lugar de nacimiento se declara en los MISMOS casos que el domicilio: el
      representante legal, la madre y el padre. Se listan los tres niveles aunque el
      familiar solo muestre la entidad federal: si mañana el campo crece, el bloque se
      limpia y se bloquea entero sin volver a tocar el JavaScript, y getElementById
      devuelve null de los que no existan. */
      var NACIMIENTO = [
        'familiarEstadoNacimiento', 'familiarMunicipioNacimiento', 'familiarParroquiaNacimiento'
      ];

      /* Un solo mecanismo para los bloques cuya aparición depende de la misma regla.
      hacer, en el mismo orden y para los dos, lo que el domicilio ya hacía:
        1. mostrar u ocultar el bloque entero
        2. poner `required` a sus campos cuando aplica
        3. quitarlo cuando no aplica
        4. limpiar los valores, para que no viaje uno viejo de una apertura anterior
        5. deshabilitar los campos, para que ni el autocompletado del navegador los
           rellene por detrás
      De paso mueve los asteriscos, que es lo que le dice a la secretaria qué es
      obligatorio sin tener que probarlo.

      `llevarLaVista` es la única diferencia real entre los dos: el nacimiento está
      arriba, con los datos de la persona, y el control que lo revela está más abajo, así
      que al aparecer hay que llevar la vista hasta él. El domicilio está justo debajo
      del control y no lo necesita. */
      var BLOQUES = {
        domicilio: {
          bloque: direccionEl,
          campos: DIRECCION,
          asterisco: 'data-requerido-direccion'
        },
        nacimiento: {
          bloque: nacimientoEl,
          campos: NACIMIENTO,
          asterisco: 'data-requerido-nacimiento',
          llevarLaVista: true
        }
      };

      function aplicarBloque(bloque, aplica) {
        if (!bloque.bloque) return;
        var visible = !bloque.bloque.classList.contains('d-none');
        bloque.bloque.classList.toggle('d-none', !aplica);

        bloque.campos.forEach(function (id) {
          var campo = document.getElementById(id);
          if (!campo) return;
          if (aplica) {
            campo.setAttribute('required', '');
            campo.disabled = false;
          } else {
            campo.removeAttribute('required');
            campo.disabled = true;
            campo.value = '';
          }
        });

        var asteriscos = bloque.bloque.querySelectorAll('[' + bloque.asterisco + ']');
        Array.prototype.forEach.call(asteriscos, function (el) {
          el.classList.toggle('d-none', !aplica);
        });

        /* Solo cuando acaba de aparecer, para que el formulario no salte en cada
        sincronización. */
        if (aplica && !visible && bloque.llevarLaVista) {
          if (typeof bloque.bloque.scrollIntoView === 'function') {
            bloque.bloque.scrollIntoView({ block: 'nearest' });
          }
          var primero = document.getElementById(bloque.campos[0]);
          if (primero && typeof primero.focus === 'function') {
            primero.focus({ preventScroll: true });
          }
        }
      }

      function sincronizar() {
        var esRepresentante = switchEl.checked;

        /* El nivel de instrucción y la ocupación se piden en dos casos: cuando el
 familiar es el REPRESENTANTE LEGAL, y cuando es el padre o la madre del
 menor. Lo segundo no es un capricho: aunque no sea el representante, el
 padre y la madre son los dos datos que la institución quiere del hogar del
 menor, y son los únicos dos parentescos que casi siempre la institución
 tiene o quiere tener. Para el resto de los familiares no se preguntan.*/
        var esPadreOMadre = esPadreOMadreSegun(parentescoEl ? parentescoEl.value : '');

        /* El domicilio, el nivel de instrucción y el lugar de nacimiento se preguntan a
        los MISMOS tres: el representante legal, la madre y el padre. Una sola variable
        para los tres, y no tres copias de la misma condición: cuando la regla cambie
        basta editar una línea y no queda ninguna por detrás. El contacto, en cambio, se
        ve siempre. */
        var pide = esRepresentante || esPadreOMadre;

        /* Los dos bloques con la misma regla pasan por el mismo mecanismo. Antes cada
        uno tenía su propio bloque de código, cuatro renglones más abajo el otro, y por
        eso divergieron: el del nacimiento no limpiaba la parroquia, no ponía `disabled`
        y no movía los asteriscos. */
        aplicarBloque(BLOQUES.domicilio, pide);
        aplicarBloque(BLOQUES.nacimiento, pide);

        /* El nivel de instrucción y la ocupación se esconden enteros para los demás, en
        vez de mostrarse vacíos. No van por aplicarBloque() porque no son un bloque con
        cascada: son dos select sueltos. */
        var pideDatosPersonales = pide;
        if (datosRepresentanteEl) datosRepresentanteEl.classList.toggle('d-none', !pideDatosPersonales);

        [nivelEl, ocupacionEl].forEach(function (campo) {
          if (!campo) return;
          if (pideDatosPersonales) {
            campo.setAttribute('required', '');
          } else {
            campo.removeAttribute('required');
            campo.value = '';
          }
        });

        /* Si el bloque aplica y los selectores están vacíos, se pone lo que traía la
 persona encontrada por cédula. Solo rellena un select VACÍO, así que no
 pisa lo que la secretaria haya elegido a mano.*/
        if (pideDatosPersonales) {
          if (nivelEl && nivelEl.value === '' && nivelPrecargado !== '') nivelEl.value = nivelPrecargado;
          if (ocupacionEl && ocupacionEl.value === '' && ocupacionPrecargada !== '') {
            ocupacionEl.value = ocupacionPrecargada;
          }
        }

        var asteriscosRepresentante = document.querySelectorAll('[data-requerido-instruccion]');
        Array.prototype.forEach.call(asteriscosRepresentante, function (el) {
          el.classList.toggle('d-none', !pideDatosPersonales);
        });

        /* El parentesco es obligatorio, y la única excepción era el interruptor de "no
 es familiar": o se dice cuál es el vínculo, o se dice explícitamente que no
 hay ninguno. Lo que ya no se puede es guardar a alguien como "Familiar" sin
 parentesco, porque ese registro no dice nada.

 Para el representante además es obligatorio por otra razón: no puede ser
 "no es familiar", así que el parentesco es lo único que deja escrito quién
 es el responsable del menor.*/
        if (parentescoEl) parentescoEl.setAttribute('required', '');

    /* "Vive con el estudiante" se marca solo para el REPRESENTANTE, igual que el permiso de retiro:
 el menor vive con su representante, así que ahí no hay nada que preguntar. Para los demás
 es una pregunta libre y nace apagada: se registra lo que la secretaria sabe.*/
        if (viveConEl) {
          if (esRepresentante) {
            viveConEl.checked = true;
            viveConEl.disabled = true;
          } else {
            viveConEl.disabled = false;
            if (representanteEra) viveConEl.checked = false;
          }
          representanteEra = esRepresentante;
        }

        /* El permiso de retiro depende de DOS cosas, y por eso se decide en un solo
 sitio: el representante legal siempre puede retirar, y quien está marcado
 como "no es familiar" está en la lista precisamente para eso. Si cada regla
 se decidiera por su lado, la del representante se comería a la otra y
 dejaría el permiso apagado.*/
        if (retirosEl) {
          if (esRepresentante) {
            retirosEl.checked = true;
            retirosEl.disabled = true;
          } else {
            /* Al dejar de serlo se desmarca: ese permiso lo había puesto el sistema, no
 quien escribe, así que dejarlo prendido le regalaría a un familiar
 corriente la autorización de retiros.*/
            retirosEl.checked = false;
            retirosEl.disabled = false;
          }
        }
      }

      switchEl.addEventListener('change', sincronizar);
      /* También el parentesco: el bloque de nivel y ocupación aparece con "Padre" o
 "Madre", así que elegirlo tiene que reevaluar las reglas. Sin este listener,
 el bloque solo aparecería al tocar otro interruptor.*/
      if (parentescoEl) parentescoEl.addEventListener('change', sincronizar);
      document.addEventListener('DOMContentLoaded', sincronizar);
      sincronizar();

      /* Se devuelve para poder reaplicar las reglas cuando se abre el sub-modal o
 cuando se limpia: el reset general desmarca los checkbox y dejaría al
 representante y al retiro apagados en pantalla.*/
      return { sincronizar: sincronizar };
    }

    // =================================================================
    // Armar y guardar un familiar
    // =================================================================

    /**
 * La fila de la lista que se está editando, o null si se está agregando.
 *
 * Se usa para dos cosas: leer el contacto y el domicilio de una persona cuyo
 * contacto NO se puede cambiar en esta pantalla (ver contactoEditable), y
 * distinguir una edición de un alta.
 *
 * No se manda su `id_persona`: el servidor identifica a la persona por su
 * número de documento (FamiliaresModelTrait::buscarOCrearPersonaFamiliar), que
 * es el dato que además no se puede cambiar de una persona ya registrada.
*/
    function filaEnEdicion() {
      return (editandoIndice !== null && familiares[editandoIndice])
        ? familiares[editandoIndice]
        : null;
    }

    /**
 * Arma el objeto del familiar con lo que hay en el sub-modal. Se separa del
 * manejador de guardar porque el guardado necesita armar el objeto en dos
 * momentos distintos: al guardar, y al dejar pendiente a un familiar cuando hay
 * que cambiar de representante legal.
*/
    function armarFamiliar() {
      var base = filaEnEdicion();
      var parentescoEl = document.getElementById('familiarParentesco');
      var parentesco = parentescoEl ? parentescoEl.value : '';
      var esRepresentante = (document.getElementById('familiarRepresentantePrincipal') || {}).checked === true;
      var valor = function (id) { return (document.getElementById(id) || {}).value || ''; };

      /* Misma condición que sincronizar(): representante legal, o padre o madre.*/
      var pideDatosPersonales = esRepresentante || esPadreOMadreSegun(parentesco);
      var primerNombre = valor('familiarPrimerNombre');
      var segundoNombre = valor('familiarSegundoNombre');
      var primerApellido = valor('familiarPrimerApellido');
      var segundoApellido = valor('familiarSegundoApellido');
      var numeroDocumento = valor('familiarNumeroDocumento');
      var nombre = [primerNombre, segundoNombre, primerApellido, segundoApellido]
        .filter(Boolean).join(' ') || 'Sin nombre';

      var direccion = {
        estado: valor('familiarEstado'),
        municipio: valor('familiarMunicipio'),
        parroquia: valor('familiarParroquia'),
        sector: valor('familiarSector'),
        calle: valor('familiarCalle'),
        casa: valor('familiarCasa'),
        referencia: valor('familiarReferencia')
      };
      /* El domicilio solo se manda para representante, madre o padre; para los demás
 no se envían datos que pudieran quedar de una apertura anterior.*/
      var pideDireccion = esRepresentante || esPadreOMadreSegun(parentesco);
      var tieneDireccion = pideDireccion
        && Object.keys(direccion).some(function (k) { return direccion[k] !== ''; });

      /* Si el contacto está apagado (en ratificación, un familiar que no es el
 representante), lo que hay en el DOM es de lectura nada más: se reenvía lo que
 ya estaba guardado en la fila. Si se mandara lo del DOM, un campo vacío por
 cualquier razón borraría el teléfono de alguien.*/
      var contactoBloqueado = base && !contactoEditable(base) ? base : null;

      var contacto;
      if (contactoBloqueado && !esRepresentante) {
        contacto = contactoBloqueado.contacto
          || { codigo: '', numero: '', codigo_alt: '', numero_alt: '', correo: '' };
      } else {
        contacto = {
          codigo: valor('familiar_codigo'),
          numero: valor('familiar_numero'),
          codigo_alt: valor('familiar_codigo_alt'),
          numero_alt: valor('familiar_numero_alt'),
          correo: valor('familiarCorreoElectronico')
        };
      }

      /* Igual para el domicilio.*/
      var direccionFinal;
      if (contactoBloqueado && !esRepresentante) {
        direccionFinal = contactoBloqueado.direccion || null;
      } else {
        direccionFinal = tieneDireccion ? direccion : null;
      }

      var familiar = {
        parentesco: parentesco,
        /* Sin parentesco, la columna del vínculo muestra "Sin parentesco" en vez de un
 guion que no diría nada.*/
        parentesco_texto: parentescoEl.selectedOptions[0].textContent.trim(),
        /* El modal nuevo no tiene el interruptor "no es familiar": el parentesco es
 obligatorio para todo el que se agrega. Se manda siempre en 0 para que el
 servidor no tenga que adivinarlo.*/
        solo_autorizado:         0,
        representante_principal: esRepresentante ? 1 : 0,
        /* Si esa persona vive con el menor (migración 0003). Es de la relación
 estudiante-persona, no de la persona, por eso va con el familiar y no en el
 payload del estudiante.*/
        vive_con_estudiante: (document.getElementById('familiarViveConEstudiante') || {}).checked === true ? 1 : 0,
        /* Nivel de instrucción y ocupación, por NOMBRE del catálogo (igual que el
 parentesco): el modelo los resuelve a id. Solo se mandan si el familiar es el
 representante o el padre o la madre; para los demás los selects ni siquiera
 están a la vista y vienen vacíos.*/
        nivel_instruccion: pideDatosPersonales ? valor('familiarNivelInstruccion') : '',
        ocupacion: pideDatosPersonales ? valor('familiarOcupacion') : '',
        autorizado_retiros: esRepresentante ? 1 : (document.getElementById('familiarAutorizadoRetiros') || {}).checked ? 1 : 0,
        nombre_referencia: nombre,
        /* Los cuatro nombres por separado: es lo que se guarda en persona.*/
        primer_nombre: primerNombre,
        segundo_nombre: segundoNombre,
        primer_apellido: primerApellido,
        segundo_apellido: segundoApellido,
        numero_documento: numeroDocumento,
        /* Con la letra (V/E) de su documento, el servidor arma la cédula escolar del
 menor; el orden entre los hijos lo cuenta él solo.*/
        tipo_documento: valor('familiarTipoDocumento'),
        /* Los radios de sexo pueden estar deshabilitados (persona ya registrada), pero
 :checked sigue encontrándolos, así que el valor se lee igual.*/
        sexo: (document.querySelector('input[name="familiar_sexo"]:checked') || {}).value || 'M',
        /* Se guarda en persona.fecha_nacimiento: es una persona real, no un dato de
 relleno.*/
        fecha_nacimiento: valor('familiarFechaNacimiento'),

        /* Al familiar solo se le declara la entidad federal: la parroquia es un dato
        que el sistema necesita del estudiante, no de sus familiares. El name lleva el
        prefijo `familiar_` porque el sub-modal reutiliza persona-form.php con prefijo.*/
        id_estado_nacimiento: parseInt(valor('familiar_id_estado_nacimiento'), 10) || 0,

        /* La nacionalidad se le pregunta a toda persona, al familiar included. El name
        lleva el prefijo `familiar_` porque el sub-modal reutiliza persona-form.php.*/
        id_nacionalidad: parseInt(valor('familiar_id_nacionalidad'), 10) || 0,
        /* El teléfono principal es obligatorio para cualquier familiar; el correo y el
 alternativo son opcionales.*/
        contacto: contacto,
        direccion: direccionFinal,

        /* El estado de la fila (activo o desactivado) viaja con el familiar. Es el
 mismo `estado_relacion` de la tabla: desactivar es la alternativa a borrar, y
 se decide en la fila de la lista, no en el sub-modal.*/
        estado_relacion: estadoFamiliar
      };

      /* Al editar una fila que ya estaba registrada se conserva su id_persona, para que
 la identidad siga bloqueada cuando se vuelva a abrir.*/
      if (base && base.id_persona) familiar.id_persona = base.id_persona;

      return familiar;
    }

    async function guardarFamiliarEnLaLista() {
      /* El parentesco es obligatorio: el modal nuevo quitó el interruptor "no es
 familiar", así que todo el que se agrega declara su vínculo con el menor.*/
      var parentescoEl = document.getElementById('familiarParentesco');
      var parentesco = parentescoEl ? parentescoEl.value : '';

      /* Quién es el representante legal lo elige quien registra. El permiso de retiro
 se fuerza para el representante (siempre puede retirar al estudiante) y queda
 libre para los demás.*/
      var esRepresentante = (document.getElementById('familiarRepresentantePrincipal') || {}).checked === true;

      var valor = function (id) { return (document.getElementById(id) || {}).value || ''; };

      /* El representante legal tiene que dejar el contacto y el domicilio: son los datos
 con los que la institución ubica y se comunica con el estudiante, que al ser
 menor no tiene los suyos.*/
      var faltantes = [];

      /* Si el contacto está apagado (familiar que ya estaba y no es representante), NO se
 pide nada: sus datos no se pueden cambiar desde acá y ya están guardados.*/
      var filaBase = filaEnEdicion();
      var contactoEditableAhora = !(filaBase && !contactoEditable(filaBase) && !esRepresentante);
      if (contactoEditableAhora
          && (!valor('familiar_codigo') || !valor('familiar_numero'))) {
        faltantes.push('el teléfono principal');
      }

      /* El domicilio se pide al representante legal, a la madre o al padre.*/
      if (esRepresentante || esPadreOMadreSegun(parentesco)) {
        var sinUbicacion = !valor('familiarEstado') || !valor('familiarMunicipio')
          || !valor('familiarParroquia');
        var sinCasa = !valor('familiarSector') || !valor('familiarCalle')
          || !valor('familiarCasa');

        if (sinUbicacion && sinCasa) {
          faltantes.push('el domicilio completo (estado, municipio, parroquia, sector, calle y N°)');
        } else if (sinUbicacion) {
          faltantes.push('el estado, el municipio y la parroquia');
        } else if (sinCasa) {
          faltantes.push('el sector, la calle y el número de casa');
        }
      }

      /* El parentesco va aquí y no solo como atributo required porque el botón de
 guardar es type="button": el navegador no valida nada solo, y sin esto el
 error llegaría como un 422 al final del wizard, con todo lo demás ya escrito.*/
      if (!valor('familiarParentesco')) {
        faltantes.push('el parentesco con el estudiante');
      }
      if (esRepresentante || esPadreOMadreSegun(parentesco)) {
        if (!valor('familiarNivelInstruccion')) faltantes.push('el nivel de instrucción');
        if (!valor('familiarOcupacion')) faltantes.push('la ocupación');
      }
      if (!valor('familiarNumeroDocumento')) {
        faltantes.push('su número de documento');
      }
      if (!valor('familiarFechaNacimiento')) {
        faltantes.push('su fecha de nacimiento');
      }
      /* Se le pide a todo familiar, no solo al que declara el lugar de nacimiento. Por
         eso va fuera del bloque de representante/madre/padre. */
      if (!valor('familiarNacionalidad')) {
        faltantes.push('la nacionalidad');
      }

      /* El botón es type="button", así que el pattern del navegador no se dispara solo:
 la cédula se revisa aquí con el mismo criterio del servidor.*/
      var digitosCedula = valor('familiarNumeroDocumento').replace(/\D/g, '');
      if (valor('familiarNumeroDocumento') !== '' && (digitosCedula.length < 7 || digitosCedula.length > 8)) {
        SIGDEUI.mostrarAviso('warning', 'Cédula incompleta', 'La cédula del representante debe tener 7 u 8 dígitos.');
        return;
      }
      if (faltantes.length) {
        SIGDEUI.mostrarAviso(
          'warning',
          esRepresentante ? 'Faltan datos del representante legal' : 'Faltan datos del familiar',
          'Completa ' + faltantes.join(' y ') + '.'
        );
        return;
      }

    /* Solo puede haber un representante legal por estudiante, y no se resuelve cambiando una fila
 por otra: se cambia de rol explícitamente. Si ya hay uno, este no se guarda; se abre el
 anterior para que le quiten la opción y, en cuanto lo guarden sin ella, se vuelve a abrir
 este. Reemplazar al anterior por sorpresa dejaría al menor con dos representantes.*/
      if (esRepresentante) {
        var anteriorIndice = -1;
        familiares.forEach(function (f, i) {
          if (f.representante_principal && anteriorIndice < 0) anteriorIndice = i;
        });

        if (anteriorIndice >= 0 && anteriorIndice !== editandoIndice) {
          var anterior = familiares[anteriorIndice];
          pendiente = { datos: armarFamiliar(), estado: estadoFamiliar };

          SIGDEUI.mostrarAviso(
            'warning',
            'Solo un representante legal',
            anterior.nombre_referencia + ' ya es el representante legal. '
            + 'Para cambiarlo, quítele esa opción y guarde los cambios; '
            + 'después podrá guardar este.'
          );

          abrirParaEditar(anteriorIndice, true);
          return;
        }
      }

      var familiar = armarFamiliar();

      /* Editar sustituye la fila; agregar la agrega al final. Nunca se borra una: eso
 es lo que se reemplaza con editar y con activar/desactivar.*/
      if (editandoIndice !== null && familiares[editandoIndice]) {
        familiares[editandoIndice] = familiar;
      } else {
        familiares.push(familiar);
      }
      editandoIndice = null;

      render();
      SIGDEUI.ocultarModal(modalEl);
      alCambiar();

      /* Si este guardado era el final del cambio de representante, el familiar que
 quedó pendiente se vuelve a abrir con sus datos ya escritos: quitar el rol al
 anterior es lo único que faltaba.*/
      if (pendiente) {
        var pendienteActual = pendiente;
        pendiente = null;
        await esperar(250);
        if (pendienteActual.estado) estadoFamiliar = pendienteActual.estado;
        await llenarDesdeFamiliar(pendienteActual.datos);
        SIGDEUI.mostrarModal(modalEl);
      }
    }

    /**
 * ¿La lista tiene ya un familiar marcado como representante legal?
 *
 * Solo cuenta si está ACTIVO: un representante desactivado no puede autorizar
 * nada, así que para el formulario es como si no hubiera ninguno.
*/
    function hayRepresentante() {
      return familiares.some(function (f) {
          return f.representante_principal && f.estado_relacion !== 'INACTIVO' && !f._desvincular;
      });
    }

    /** Escribe en el sub-modal los datos de una fila de la lista.*/
    function llenarDesdeFamiliar(f) {
      var poner = function (id, v) {
        var el = document.getElementById(id);
        if (el) el.value = v == null ? '' : v;
      };
      var marcar = function (id, v) {
        var el = document.getElementById(id);
        if (el) el.checked = v === true || v === 1;
      };

      poner('familiarTipoDocumento', f.tipo_documento || 'V');
      poner('familiarNumeroDocumento', f.numero_documento || '');
      poner('familiarPrimerNombre', f.primer_nombre || '');
      poner('familiarSegundoNombre', f.segundo_nombre || '');
      poner('familiarPrimerApellido', f.primer_apellido || '');
      poner('familiarSegundoApellido', f.segundo_apellido || '');
      poner('familiarFechaNacimiento', f.fecha_nacimiento || '');
      poner('familiarNacionalidad', f.id_nacionalidad || '');

      var sexo = document.querySelector('input[name="familiar_sexo"][value="' + (f.sexo || 'M') + '"]');
      if (sexo) sexo.checked = true;

      /* El select del parentesco guarda el NOMBRE del catálogo, que es lo que lleva la
 fila en `parentesco`.*/
      if (f.parentesco) poner('familiarParentesco', f.parentesco);

      poner('familiarNivelInstruccion', f.nivel_instruccion || '');
      poner('familiarOcupacion', f.ocupacion || '');

      marcar('familiarRepresentantePrincipal', f.representante_principal);
      marcar('familiarAutorizadoRetiros', f.autorizado_retiros);
      marcar('familiarViveConEstudiante', f.vive_con_estudiante);

    /* Si la persona ya está en el sistema su identidad se bloquea también aquí, no solo al
 buscarla por cédula. Antes, editar un familiar desde la lista dejaba el sexo escribible y
 se podía cambiar el de alguien ya registrado desde ratificación.*/
      var yaEsta = esFamiliarRegistrado(f);
      bloquearIdentidad(yaEsta);

      var c = f.contacto || {};
      poner('familiar_codigo', c.codigo || '');
      poner('familiar_numero', c.numero || '');
      poner('familiar_codigo_alt', c.codigo_alt || '');
      poner('familiar_numero_alt', c.numero_alt || '');
      poner('familiarCorreoElectronico', c.correo || '');

      var d = f.direccion || {};
      poner('familiarSector', d.sector || '');
      poner('familiarCalle', d.calle || '');
      poner('familiarCasa', d.casa || '');
      poner('familiarReferencia', d.referencia || '');

      /* Si en esta pantalla el contacto de ESTE familiar no se puede cambiar, se apaga
 y se guarda aparte la fila, para reenviarla tal cual al guardar.*/
      bloquearContacto(!contactoEditable(f));

      /* La cascada se llena con la misma función que usa el prefill por cédula, para
 que editar y recargar se comporten igual. Es asíncrona porque consulta el
 catálogo, así que se espera antes de abrir el modal: si se abriera antes, la
 secretaria vería la cascada vacía un instante.*/
      var parroquia = d.parroquia || d.id_parroquia;
      var promesa = parroquia && cascada
        ? cascada.elegirParroquia(d.estado || 0, d.municipio || 0, Number(parroquia))
        : Promise.resolve(false);

      return promesa.then(function () {
        /* La cascada vuelve a habilitar o deshabilitar los <select> según su estado, así
 que el apagado de contacto se reaplica al final, o el domicilio del
 representante quedaría editable cuando no debe.*/
        bloquearContacto(!contactoEditable(f));
        if (bloqueRepresentanteUI) bloqueRepresentanteUI.sincronizar();
      });
    }

    /**
 * Abre el sub-modal en modo edición sobre una fila.
 *
 * `quitarRol` es para el cambio de representante legal: cuando alguien intenta
 * guardar un segundo representante, el anterior se abre para editarse con el
 * interruptor YA APAGADO. Es lo que hace que el aviso que dice "quítele esa opción"
 * sea cierto en vez de una instrucción que hay que cumplir a mano: la secretaria
 * solo tiene que guardar.
*/
    function abrirParaEditar(indice, quitarRol) {
      var f = familiares[indice];
      if (!f) return;

      vaciarSubModal(false);
      editandoIndice = indice;
      estadoFamiliar = f.estado_relacion || 'ACTIVO';

      /* Los tres ocultos del modal (#familiarIndiceEdicion, #familiarIdRelacion y
 #familiarEstadoRelacion) son el contrato que el propio markup declara para
 saber en qué fila se está. Se llenan aquí y se vacían al abrir en blanco, para
 que el DOM diga lo mismo que el estado del módulo y no haya dos verdades.*/
      document.getElementById('familiarIndiceEdicion').value = indice;
      document.getElementById('familiarIdRelacion').value = f.id_persona || '';
      document.getElementById('familiarEstadoRelacion').value = estadoFamiliar;

      if (quitarRol) {
        var sw = document.getElementById('familiarRepresentantePrincipal');
        if (sw) sw.checked = false;
        f.representante_principal = 0;
      }

      var titulo = document.getElementById('modalAgregarFamiliarLabel');
      if (titulo) titulo.textContent = 'Editar familiar';
      var boton = document.getElementById('btnGuardarFamiliar');
      if (boton) boton.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i> Guardar cambios';

      llenarDesdeFamiliar(f).then(function () {
        SIGDEUI.mostrarModal(modalEl);
      });
    }

    // =================================================================
    // Tabla de la lista
    // =================================================================

    function rolesDe(f) {
      var roles = [];
      if (f.representante_principal) {
        roles.push('Representante legal');
      } else if (f.solo_autorizado) {
        roles.push('No familiar');
      } else {
        roles.push('Familiar');
      }
      if (f.autorizado_retiros) roles.push('Autorizado para retiros');
      /* Solo se rotula cuando se registró que sí: el interruptor nace apagado
 (migración 0004), así que la ausencia de la etiqueta significa "no se
 registró", no "no vive".*/
      if (f.vive_con_estudiante) roles.push('Vive con el estudiante');
      return roles;
    }

      /**
 /**
 * Botón de papelera / deshacer de una fila.
 *
 * Son el MISMO botón en dos estados, no dos botones: cuando la fila está
 * marcada cambia de icono y de título. Dos botones harían que el espacio en la
 * fila cambiara al marcar, y ese salto es justo lo que hace dudar de si la
 * operación llegó a aplicarse.
 *
 * En el representante legal NO se usa el atributo `disabled` a propósito.
 * El CSS del sistema pone `pointer-events:none` en `.action-btn:disabled`, y un
 * botón deshabilitado tampoco dispara click: con cualquiera de las dos cosas,
 * el clic se pierde en silencio y la secretaria no tiene forma de aprender por
 * qué ese familiar no se quita. Con `aria-disabled` + una clase propia se ve
 * igual de deshabilitado, pero el clic sí llega y el handler explica.
 *
 * @param {object} f el familiar
 * @param {number} indice su posición en la lista
*/
      function htmlPapelera(f, indice) {
        var marcado = !!f._desvincular;
        var esRepresentante = !!f.representante_principal && !marcado;

        var icono = marcado ? 'bi-arrow-counterclockwise' : 'bi-trash';
        var clase = marcado
          ? 'action-btn action-btn--undo'
          : (esRepresentante ? 'action-btn action-btn--locked' : 'action-btn action-btn--danger');
        var titulo = marcado
          ? 'Deshacer: volver a guardar este familiar'
          : (esRepresentante
              ? 'No se puede desvincular al representante legal'
              : 'Desvincular de este alumno');

        return '<button type="button" class="' + clase + '" data-desvincular="' + indice + '"'
          + (esRepresentante ? ' aria-disabled="true" data-motivo-bloqueo="representante"' : '')
          + ' title="' + escHtml(titulo) + '">'
          + '<i class="bi ' + icono + '" aria-hidden="true"></i>'
          + '<span class="visually-hidden">' + escHtml(titulo) + '</span>'
          + '</button>';
      }


    function render() {
      var tbodyFam = document.getElementById('tablaFamiliaresBody');
      if (!tbodyFam) return;

      if (familiares.length === 0) {
        tbodyFam.innerHTML = '<tr id="tablaFamiliaresVacio"><td colspan="4" class="text-center text-support py-4">'
          + 'Aún no has agregado ningún familiar.</td></tr>';
        return;
      }

    /* Desactivar o desvincular, según la pantalla. Las dos cosas se leen de
 `persona_estudiante`: la marca de desactivado viaja en la lista, la de desvinculado se
 aplica en el servidor.*/
      tbodyFam.innerHTML = familiares.map(function (f, i) {
        var roles = rolesDe(f);
        var inactivo = f.estado_relacion === 'INACTIVO';
        var papelera = permitirDesvinculo ? htmlPapelera(f, i) : '';
        var switchDesactivar = (!permitirDesvinculo && permitirDesactivar)
          ? '<label class="wizard-switch wizard-switch--sm" title="'
              + (inactivo ? 'Activar este familiar' : 'Desactivar este familiar') + '">' +
              '<input type="checkbox" data-estado-familiar="' + i + '"'
                + (inactivo ? '' : ' checked')
                + (f.representante_principal && !inactivo ? ' disabled' : '') + '>' +
              '<span class="wizard-switch__slider"></span>' +
            '</label>'
          : '';

        var seMuestraInactiva = inactivo || !!f._desvincular;

        return '<tr' + (seMuestraInactiva ? ' class="table-row--inactive"' : '') + '>' +
          '<td>' + escHtml(f.nombre_referencia)
            /* La marca de "se va a desvincular" va en la propia celda del nombre, y no
 como fila aparte, para que la tabla no cambie de alto mientras la
 secretaria va marcando: si la fila desapareciera o saltara, el objetivo
 (que vea QUÉ va a perder antes de guardar) se pierde.*/
            + (f._desvincular
                ? ' <span class="status-badge status-badge--inactive ms-1">'
                  + '<i class="bi bi-trash me-1" aria-hidden="true"></i>Se va a desvincular</span>'
                : '') +
          '</td>' +
          '<td>' + escHtml(f.parentesco_texto) + '</td>' +
          '<td>' + (roles.length ? roles.map(function (r) {
            return '<span class="status-badge status-badge--active">' + escHtml(r) + '</span>';
          }).join(' ') : '—') + '</td>' +
          '<td class="text-center">' +
            '<div class="d-inline-flex align-items-center gap-3">' +
              switchDesactivar + papelera +
              '<button type="button" class="action-btn action-btn--edit" data-editar-familiar="' + i
                + '" title="Editar familiar"><i class="bi bi-pencil"></i></button>' +
            '</div>' +
          '</td>' +
        '</tr>';
      }).join('');

      tbodyFam.querySelectorAll('[data-editar-familiar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          abrirParaEditar(Number(btn.dataset.editarFamiliar));
        });
      });

      /* Papelera de desvincular (hallazgo MEDIO 4.7). Va delegada sobre el tbody
 porque render() rehace el HTML de las filas en cada cambio: si el listener
 se enganchara por fila, el siguiente render() lo perdería.*/
      tbodyFam.querySelectorAll('[data-desvincular]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var f = familiares[Number(btn.dataset.desvincular)];
          if (!f) return;

          if (f._desvincular) {
            /* Deshacer: la fila vuelve a la lista y se manda con el resto.*/
            delete f._desvincular;
            render();
            /* El hidden es lo que viaja al servidor, así que tiene que reflejar el
 cambio YA: sin esto, marcar una fila no movería el payload hasta el
 siguiente guardado, y la marca parecería no hacer nada.*/
            serializar();
            alCambiar();
            return;
          }

          /* El representante legal no se desvincula desde acá. No por capricho: el
 backend lo rechaza igual (guarda 2 de sincronizarFamiliares), y es mejor
 decirlo antes que devolver un error al guardar, con el formulario entero
 ya cheio. Si es el único, el aviso lo dice explícitamente.*/
          if (f.representante_principal) {
            var otros = familiares.filter(function (otro) {
              return otro.representante_principal && !otro._desvincular;
            }).length;
            SIGDEUI.mostrarAviso(
              'warning',
              otros > 1 ? 'No se puede desvincular al representante legal' : 'Es el único representante legal',
              otros > 1
                ? 'Un alumno solo puede tener un representante legal. Designe otro antes de desvincular a este.'
                : 'Si lo desvincula, el alumno queda sin representante legal y no podrá autorizar un retiro ni firmar documentos. Designe otro representante primero.'
            );
            return;
          }

          /* Marcada, no borrada. La fila sigue en pantalla hasta que se guarde o se
 deshaga, para que la secretaria vea lo que va a perder.*/
          f._desvincular = true;
          render();
          serializar();
          alCambiar();
        });
      });


      tbodyFam.querySelectorAll('[data-estado-familiar]').forEach(function (sw) {
        sw.addEventListener('change', function () {
          var f = familiares[Number(sw.dataset.estadoFamiliar)];
          if (!f) return;
          f.estado_relacion = sw.checked ? 'ACTIVO' : 'INACTIVO';
          render();
          alCambiar();
        });
      });
    }

    // =================================================================
    // Arranque
    // =================================================================

    function arrancar() {
      if (!modalEl) return false;   // esta pantalla no tiene el sub-modal

      /* Previsualización, "quitar foto" y filtros de escritura delegan en
 public/assets/js/wizard-ui.js, el mismo módulo que consume el wizard de
 empleado: el comportamiento es idéntico en los tres formularios.*/
      foto = SIGDEWizardUI.initFoto({
        inputId: 'familiarFotoInput',
        previewId: 'familiarFotoPreview',
        quitarId: 'btnQuitarFotoFamiliar'
      });

      iniciarCascada();
      iniciarBusquedaPorCedula();
      bloqueRepresentanteUI = iniciarBloqueRepresentante();

      var btnGuardar = document.getElementById('btnGuardarFamiliar');

      /* Al cerrar el sub-modal (por el botón o por el fondo) se sale del modo edición:
 si se cerrara y se volviera a abrir con "Agregar familiar", no debe quedar
 editando la fila anterior por el camino.*/
      SIGDEUI.alCerrarModal(modalEl, function () { editandoIndice = null; });

      if (formFamiliar) {
        /* El botón de guardar es type="button", así que sin esto el Enter en cualquier
 campo disparaba el envío implícito del <form> y recargaba la página,
 perdiendo todo el formulario. Con Enter se guarda el familiar, que es lo que
 se espera.*/
        formFamiliar.addEventListener('submit', function (evento) {
          evento.preventDefault();
          if (btnGuardar) btnGuardar.click();
        });
      }

      if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarFamiliarEnLaLista);
      }

      return true;
    }

    arrancar();

    // =================================================================
    // API pública
    // =================================================================

    return {
      /** Carga la lista desde el servidor y la pinta (edición de un registro ya existente).*/
      cargar: function (lista) {
        familiares = Array.isArray(lista) ? lista.slice() : [];
        editandoIndice = null;
        pendiente = null;
        estadoFamiliar = 'ACTIVO';
        render();
        serializar();
        alCambiar();
      },

      /** La lista tal como está, para que quien llama la use como quiera.*/
      lista: function () { return familiares; },

            /**
 * Escribe el JSON en el hidden, que es lo que viaja al servidor. La llama cargar(), el
 * envío final y la papelera al marcar o deshacer, o la marca se vería sin que se guardara.
*/
      serializar: serializar,

      /** ¿Hay un representante legal activo? Lo exige el alta de inscripción.*/
      hayRepresentante: hayRepresentante,

      /** Pinta la tabla sin cambiar la lista.*/
      render: render,

      /** Abre el sub-modal en modo edición, por índice de la lista.*/
      editar: function (indice) { abrirParaEditar(indice, false); },

      /** Abre el sub-modal directamente sobre el representante legal activo.*/
      editarRepresentante: function () {
        var indice = familiares.findIndex(function (familiar) {
          return familiar.representante_principal && familiar.estado_relacion !== 'INACTIVO';
        });
        if (indice < 0) return false;
        abrirParaEditar(indice, false);
        return true;
      },

      /** El aviso de "no se puede editar el contacto" que se muestra en el sub-modal.*/
      avisoNoEditable: 'familiarInfoBloqueo'
    };
  }

  global.FamiliaresUI = {
    /**
 * Arranca el módulo UNA vez. Las dos pantallas viven en páginas distintas, así que
 * no hay competencia por la instancia; se devuelve por si el mismo documento
 * tuviera los dos formularios.
*/
    init: function (config) {
      var modal = document.getElementById((config && config.modalId) || 'modalAgregarFamiliar');

      /* Sin sub-modal en esta pantalla no hay nada que arrancar (así se puede
 llamar sin riesgo desde una vista que no lo tenga), pero tampoco hace
 falta devolver nada.*/
      if (!modal) return null;

      var previa = instanciasPorModal.get(modal);
      if (previa) {
        return previa;
      }

      instancia = crear(config);
      instanciasPorModal.set(modal, instancia);
      return instancia;
    },
    obtener: function () { return instancia; }
  };

})(window);