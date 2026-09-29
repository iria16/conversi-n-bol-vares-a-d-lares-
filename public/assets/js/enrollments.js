/* SIGDE - comportamiento del wizard de inscripciones.
   El listado (filtros, búsqueda y paginación) lo aplica automáticamente
   main.js (initAutoFilters) sobre el form del data-panel, igual que en el
   resto de módulos; aquí solo vive la lógica del asistente de 7 pasos. */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'inscripciones/';

  /* ══════════════════════════════════════════════════════════════════
     Helpers
     ══════════════════════════════════════════════════════════════════ */
  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function textoSeleccionado(id) {
    var el = document.getElementById(id);
    return el && el.selectedOptions && el.selectedOptions[0]
      ? el.selectedOptions[0].textContent.trim()
      : '—';
  }

  /* El nombre pelado de una opción, sin lo que se le haya añadido a la etiqueta.
     El select del turno lleva "12/30" al final, que en el formulario sirve,
     pero en el resumen del paso 7 sería ruido: ahí se lee el nombre. */
  function nombreSencilloDe(id) {
    var el = document.getElementById(id);
    var opcion = el && el.selectedOptions && el.selectedOptions[0];
    if (!opcion) { return '—'; }
    return (opcion.dataset.nombre || opcion.textContent).trim();
  }

  /* ══════════════════════════════════════════════════════════════════
     WIZARD "Registrar Nueva Inscripción" (7 pasos)
     ══════════════════════════════════════════════════════════════════ */
  var wizardModalEl = document.getElementById('nuevaInscripcionModal');
  var form          = document.getElementById('formNuevaInscripcion');

  if (!form || !wizardModalEl) return;

  var pasoActual        = 1;
  var totalPasos        = 7;
  var familiaresAgregados = [];   // acumulador -> se envía como JSON

  /* Estado de la lista de familiares:
       - editandoFamiliarIndice: la fila que el sub-modal está editando, o null
         si se está agregando uno nuevo;
       - estadoFamiliar: si la fila que se está editable está activa o
         desactivada (persona_estudiante.estado_relacion);
       - pendienteFamiliar: el familiar que quedó a medio camino cuando hay
         que cambiar de representante legal (ver guardarFamiliarEnLaLista). */
  var editandoFamiliarIndice = null;
  var pendienteFamiliar = null;
  var estadoFamiliar = 'ACTIVO';

  var esperar = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
  var editandoDesdePaso = null;   // modo edición desde resumen

  // Inicializar el wizard mostrando el paso 1 al cargar la página
  mostrarPaso(1);

  /* ---------- Navegación entre pasos ---------- */
  function mostrarPaso(numero) {
    wizardModalEl.querySelectorAll('.wizard-panel').forEach(function (panel) {
      panel.classList.toggle('is-active', Number(panel.dataset.step) === numero);
    });

    wizardModalEl.querySelectorAll('.wizard-steps__step').forEach(function (step) {
      var n = Number(step.dataset.stepIndicator);
      step.classList.toggle('is-active', n === numero);
      step.classList.toggle('is-completed', n < numero);
      var circle = step.querySelector('.wizard-steps__circle');
      circle.innerHTML = n < numero ? '<i class="bi bi-check-lg"></i>' : n;
    });

    document.getElementById('btnWizardAtras').classList.toggle('d-none', numero === 1);
    document.getElementById('btnWizardSiguiente').classList.toggle('d-none', numero === totalPasos);
    document.getElementById('btnWizardFinalizar').classList.toggle('d-none', numero !== totalPasos);

    if (numero === totalPasos) llenarResumen();
    pasoActual = numero;
  }

  function validarPasoActual() {
    // El representante legal es obligatorio: sin él no se avanza ni se envía
    // la inscripción. Se revisa en el paso 6 y también al finalizar, que es
    // donde el formulario ya está completo.
    if (pasoActual === 6 && !hayRepresentanteLegal()) {
      SIGDEUI.mostrarAviso(
        'warning',
        'Falta el representante legal',
        'Debe marcar un familiar como representante legal.'
      );
      return false;
    }

    var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + pasoActual + '"]');
    var valido = true;
    panel.querySelectorAll('[required]').forEach(function (campo) {
      if (!campo.checkValidity()) valido = false;
    });
    if (!valido) {
      panel.querySelectorAll('[required]').forEach(function (campo) {
        campo.classList.toggle('is-invalid', !campo.checkValidity());
      });
      SIGDEUI.mostrarAviso('warning', 'Campos incompletos', 'Completa los campos obligatorios (*) antes de continuar.');
    }
    return valido;
  }

  document.getElementById('btnWizardSiguiente').addEventListener('click', function () {
    if (!validarPasoActual()) return;
    if (editandoDesdePaso !== null) {
      editandoDesdePaso = null;
      mostrarPaso(totalPasos);
    } else if (pasoActual < totalPasos) {
      mostrarPaso(pasoActual + 1);
    }
  });

  document.getElementById('btnWizardAtras').addEventListener('click', function () {
    if (pasoActual > 1) mostrarPaso(pasoActual - 1);
  });

  /* Delegación: botones "Editar" del resumen (paso 7) */
  wizardModalEl.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-editar-paso]');
    if (!btn) return;
    editandoDesdePaso = totalPasos;
    mostrarPaso(Number(btn.dataset.editarPaso));
  });

  /* Reset al cerrar el modal */
  SIGDEUI.alCerrarModal(wizardModalEl, function reiniciarAlCerrar() {
    pasoActual         = 1;
    familiaresAgregados = [];
    editandoDesdePaso  = null;
    renderFamiliares();
    form.reset();
    form.classList.remove('was-validated');
    mostrarPaso(1);
    var previewEl = document.getElementById('fotoPreview');
    if (previewEl) previewEl.innerHTML = '<i class="bi bi-person"></i>';
    /* Restaurar bloque procedencia por si se ocultó */
    var bloqueProc = document.getElementById('bloqueInstitucionProcedencia');
    if (bloqueProc) bloqueProc.style.display = '';
    var ingresoPV = document.getElementById('ingresoPrimeraVez');
    if (ingresoPV) ingresoPV.checked = false;
  });

  /* ---------- Paso 3: grado, sección y turno, con los cupos ----------
     Las opciones las pone el servidor desde `estructura_academica` (ver
     step-3-academico.php): cada sección y cada turno llegan como un <option> por
     combinación, con la clave "grado|seccion" y "grado|seccion|turno". Por eso
     se puede filtrar por prefijo: al elegir el grado, se muestran solo las
     secciones que existen para ese grado, y al elegir la sección, solo los
     turnos de esa combinación.

     El cupo se escribe en la ETIQUETA del turno y el que está lleno queda
     deshabilitado. La etiqueta se reescribe en cada cambio porque el mismo turno
     puede estar lleno en una sección y libre en otra: el número depende de la
     combinación elegida, no del turno solo.

     La lista se arma con los datos que ya venían en el HTML, así que no hace
     falta ninguna petición: el conteo de ocupados es el del momento en que se
     abrió el wizard. Si durante la inscripción se aprueba un retiro, el cupo se
     libera al recargar la página, que es cuando se vuelve a contar. */
  (function cascadaAcademica() {
    var contenedor = document.querySelector('[data-academico-cascada]');
    if (!contenedor) return;

    var selGrado = document.getElementById('gradoCursar');
    var selSeccion = document.getElementById('seccion');
    var selTurno = document.getElementById('turno');
    if (!selGrado || !selSeccion || !selTurno) return;

    var cupos = {};
    try { cupos = JSON.parse(contenedor.dataset.cupos || '{}'); } catch (e) { cupos = {}; }

    /* La clave de la opción ELEGIDA.
       Ojo: el `value` de sección y de turno NO es único, porque la misma
       sección aparece en varios grados (una <option> por combinación). Si algo
       asigna `select.value = 3` —la restauración del navegador, un reset, o un
       programmatic set— el navegador elige la PRIMERA opción con ese valor,
       que puede ser la de otro grado y estar oculta. Por eso se mira el
       `selectedIndex`, que es lo que deja el clic, y si la opción elegida
       está oculta se devuelve la clave vacía: es preferible no filtrar a
      ver combinaciones de otro grado que a mostrar combinaciones que no son. */
    var comboDe = function (select) {
      var o = select.options[select.selectedIndex];
      if (!o || o.hidden) { return ''; }
      return o.dataset.combinacion || '';
    };

      /**
       * Muestra solo las opciones cuya clave empieza por el prefijo dado.
       *
       * Oculta con `hidden` Y deshabilita con `disabled`, por el mismo motivo que
       * SIGDEUI.filtrarOpciones: si solo se ocultan, la búsqueda por letra del
       * <select> puede aterrizar en una opción escondida, y el usuario ve un
       * turno o una sección de otro grado saltando a la primera que empiece por
       * la letra que tecleó.
       *
       * Las dos marcas van SIEMPRE juntas, en los dos sentidos. Poner el
       * `disabled` solo cuando la opción se oculta y no quitarlo cuando vuelve a
       * aparecer dejaba la sección inutilizable al segundo grado: se veía en la
       * lista pero venía con el `disabled` de la pasada anterior pegado, y no
       * había forma de elegirla.
       *
       * Con el turno hay un detalle de orden: el `disabled` de las opciones
       * VISIBLES lo decide reescribirTurnos() según el cupo, y por eso siempre se
       * la llama DESPUÉS de este filtro — así lo sobrescribe. Las ocultas las
       * deshabilita esta función y reescribirTurnos() no las toca, porque no
       * tienen combinación en la sección elegida.
       */
      var filtrarPorPrefijo = function (select, prefijo) {
        Array.prototype.forEach.call(select.options, function (o) {
          if (!o.value) return;                       // el placeholder siempre
          var clave = o.dataset.combinacion || '';
          var visible = prefijo !== '' && clave.indexOf(prefijo + '|') === 0;
          o.hidden = !visible;
          o.disabled = !visible;
        });
      };

    var reescribirTurnos = function () {
      var clave = comboDe(selSeccion);              // "grado|seccion"
      var delPar = clave ? (cupos[clave] || []) : [];

      Array.prototype.forEach.call(selTurno.options, function (o) {
        if (!o.value) return;
        var base = o.dataset.nombre || o.textContent;
        var combo = null;
        for (var i = 0; i < delPar.length; i++) {
          if (String(delPar[i].id_turno) === String(o.value)) { combo = delPar[i]; break; }
        }
        if (!combo) { o.textContent = base; return; }

        var texto = base + ' (' + combo.ocupados + '/' + combo.capacidad + ')';
        if (combo.disponibles <= 0) {
          texto += ' · sin cupo';
          o.disabled = true;                        // no se puede elegir
        } else {
          o.disabled = false;
        }
        o.textContent = texto;
      });
    };

    selGrado.addEventListener('change', function () {
      filtrarPorPrefijo(selSeccion, selGrado.value);
      selSeccion.value = '';
      filtrarPorPrefijo(selTurno, '');
      selTurno.value = '';
      reescribirTurnos();
    });

    selSeccion.addEventListener('change', function () {
      filtrarPorPrefijo(selTurno, comboDe(selSeccion));
      selTurno.value = '';
      reescribirTurnos();
    });

    // Estado inicial: sin grado elegido no se ve ninguna sección ni turno.
    filtrarPorPrefijo(selSeccion, '');
    filtrarPorPrefijo(selTurno, '');
    reescribirTurnos();
  })();

  /* ---------- Fotos: estudiante (paso 1) y familiar (sub-modal) ----------
     Previsualización, "quitar foto" y filtros de escritura delegan en
     public/assets/js/wizard-ui.js, el mismo módulo que consume el wizard
     de empleado: el comportamiento es idéntico en los tres formularios. */
  var fotoFamiliar = null; // manejador de SIGDEWizardUI, para limpiar al abrir

  /* Cédula cuyos datos están cargados ahora mismo en el sub-modal. Mientras no
     haya ninguna, el formulario está vacío y no hay nada que limpiar. Permite
     distinguir "la secretary está escribiendo una cédula nueva" de "se están
     corrigiendo los datos de otra persona": en el primer caso no se toca nada,
     en el segundo se vacía. */
  var cedulaCargada = null;

  /* Campos que identifican a la persona (nombres y fecha de nacimiento). Cuando
     la cédula ya está registrada se bloquean: son los datos con los que está en
     el sistema y no tienen sentido cambiarlos desde acá. El contacto y el
     domicilio sí se pueden modificar, porque cambian. */
  var CAMPOS_IDENTIDAD = [
    'familiarPrimerNombre', 'familiarSegundoNombre',
    'familiarPrimerApellido', 'familiarSegundoApellido',
    'familiarFechaNacimiento'
  ];

  /* Los campos que SIGUEN editables cuando la persona ya está registrada: el
     contacto y el domicilio. Es la lista que compara el aviso de diferencias y
     la que se escucha para repintarlo (ver buscarFamiliar). El prefijo del
     teléfono principal también va, porque cambiar el código cambia el número
     que se guarda. */
  var CAMPOS_EDITABLES = [
    'familiarCodigoTelefonoPrincipal', 'familiarTelefonoPrincipal',
    'familiarCodigoTelefonoAlternativo', 'familiarTelefonoAlternativo',
    'familiarCorreo',
    'familiarDireccionEstado', 'familiarDireccionMunicipio', 'familiarDireccionParroquia',
    'familiarDireccionSector', 'familiarDireccionCalle',
    'familiarDireccionCasa', 'familiarDireccionReferencia'
  ];


  /* ---------- El familiar que ya está registrado ----------
   * Estas piezas viven en el scope del módulo y no dentro de buscarFamiliar()
   * porque las usan dos caminos distintos: la búsqueda por cédula, que llena el
   * formulario, y vaciarSubModalFamiliar(), que lo limpia. Si estuvieran
   * dentro del IIFE, el reset no podría olvidar a quién pertenecía el aviso y el
   * panel seguiría anunciando datos de una cédula que ya no está en el campo. */

  function soloDigitos(valor) { return String(valor || '').replace(/\D/g, ''); }

  /* La persona de la última búsqueda y su nombre, para poder repintar el aviso
     de diferencias cuando la secretaria edita el contacto o el domicilio (eso
     no dispara la búsqueda, que solo corre con la cédula). */
  var personaEncontrada = null;
  var nombreEncontrado = '';

  /* Si la cascada NO se pudo dejar con los tres niveles (el estado guardado no
     tiene municipios, la parroquia ya no está en el catálogo, o el endpoint
     falló), queda en false y el aviso lo dice. Sin esto la secretaria ve dos
     <select> vacíos y no tiene por qué saber que su domicilio no se cargó. */
  var cascadaArmada = null;

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
   * @param {object} antes          Foto del formulario (tomarLoEscrito()).
   * @param {object} encontrado    Lo que devolvió buscarPersona.
   * @param {boolean} vacioEsCambio Si un campo vacío cuenta como cambio.
   *        Es false en la comparación inicial (antes de llenar, un vacío es
   *        "todavía no escribió") y true al repintar el aviso con el
   *        formulario ya lleno (ahí vaciar el correo de alguien que lo tenía
   *        sí es un cambio).
   * @returns {string[]} Etiquetas de lo que difiere.
   */
  function diferencias(antes, encontrado, vacioEsCambio) {
    var lista = [];

    /* Solo se comparan el contacto y el domicilio: son los únicos datos que
       se pueden cambiar de una persona ya registrada. Los nombres, el sexo y
       la fecha de nacimiento vienen bloqueados y no se tocan, así que no hay
       nada que comparar de ellos. */
    function difiere(escrito, guardadoCampo) {
      var escritoTxt  = String(escrito || '').trim();
      var guardadoTxt = String(guardadoCampo || '').trim();

      if (escritoTxt === '') return vacioEsCambio && guardadoTxt !== '';

      return escritoTxt !== guardadoTxt;
    }

    if (encontrado.contacto) {
      var telEscrito = soloDigitos(antes.familiarTelefonoPrincipal);
      if ((vacioEsCambio || telEscrito !== '')
          && telEscrito !== soloDigitos(encontrado.contacto.numero)) {
        lista.push('teléfono principal');
      }
      if (difiere(antes.familiarCorreo, encontrado.contacto.correo)) {
        lista.push('correo');
      }
    }

    if (encontrado.direccion) {
      var mapa = { sector: 'familiarDireccionSector', calle: 'familiarDireccionCalle',
                   casa: 'familiarDireccionCasa', referencia: 'familiarDireccionReferencia' };
      Object.keys(mapa).forEach(function (clave) {
        if (difiere(antes[mapa[clave]], encontrado.direccion[clave])) {
          lista.push(clave === 'referencia' ? 'punto de referencia' : clave);
        }
      });
      /* Estado, municipio y parroquia se comparan por su id, que es lo que
         guarda cada <select>, no por el nombre que muestran. Como los tres
         niveles van en cascada, alcanza con avisar del más alto que cambió:
         si cambió el estado, el municipio y la parroquia son otra cosa.

         Cada nivel trae el id del <select> (para leer lo escrito) y la clave
         con la que viene en la respuesta (estado, municipio, parroquia). */
      var niveles = [
        ['familiarDireccionEstado', 'estado'],
        ['familiarDireccionMunicipio', 'municipio'],
        ['familiarDireccionParroquia', 'parroquia']
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

  /**
   * Foto de lo que hay escrito en el formulario, antes de llenarlo con lo
   * guardado. Solo del contacto y el domicilio, que son los únicos campos
   * editables de una persona ya registrada.
   */
  function tomarLoEscrito() {
    var foto = {};
    CAMPOS_EDITABLES.forEach(function (id) {
      foto[id] = (document.getElementById(id) || {}).value || '';
    });
    return foto;
  }

  /**
   * Escribe el aviso de "ya está registrada", con la lista de datos que van a
   * cambiar. Si no se le pasa esa lista, la calcula del estado actual del
   * formulario, que es lo que hace cuando la secretaria edita algo.
   *
   * @param {string[]} [cambios] Lista ya calculada (la de la búsqueda).
   */
  function pintarAviso(cambios) {
    var panel = document.getElementById('personaEncontrada');
    var texto = document.getElementById('personaEncontradaTexto');
    if (!personaEncontrada || !panel || !texto) return;

    var lista = cambios || diferencias(tomarLoEscrito(), personaEncontrada, true);

    /* Si no hay domicilio guardado no hay nada que comparar, y tampoco nada que
       "coincida": los tres <select> de la cascada se quedan vacíos porque no
       hay levels que elegir, no porque fallara la carga. */
    var conDomicilio = !!personaEncontrada.direccion;

    /* Aquí solo va lo que la secretaria tiene que hacer algo con. Los datos
       personales bloqueados se ven bloqueados, y que el contacto y el
       domicilio coincidan con lo registrado no es información: se ve solo en
       que los campos están llenos. Los avisos que sí quedan son los que
       piden una decisión: que falta el domicilio, o qué va a cambiar. */
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
       "su domicilio no se pudo cargar". */
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

      if (bloquear) {
        campo.setAttribute('readonly', '');
      } else {
        campo.removeAttribute('readonly');
      }
    });

    /* El sexo son dos <input type="radio"> sin id, se toman por su name. Sin
       habilitarlos no se pueden marcar, y la tarjeta se ve atenuada con la
       clase is-locked (ver .selectable-card.is-locked en app.css). */
    var sexos = document.querySelectorAll('input[name="familiar_sexo"]');
    Array.prototype.forEach.call(sexos, function (radio) { radio.disabled = bloquear; });

    var tarjetas = document.querySelectorAll('[data-familiar-sexo-card]');
    Array.prototype.forEach.call(tarjetas, function (tarjeta) {
      tarjeta.classList.toggle('is-locked', bloquear);
    });
  }

  SIGDEWizardUI.initFoto({
    inputId: 'fotoInput',
    previewId: 'fotoPreview',
    quitarId: 'btnQuitarFotoEstudiante',
    onChange: function () { if (pasoActual === totalPasos) llenarResumen(); }
  });

  fotoFamiliar = SIGDEWizardUI.initFoto({
    inputId: 'fotoFamiliarInput',
    previewId: 'fotoFamiliarPreview',
    quitarId: 'btnQuitarFotoFamiliar'
  });

  /* ---------- Documento del estudiante (paso 1) ----------
   El select de tipo viene con "Cédula escolar" por defecto, porque el
   estudiante que se inscribe es menor y casi nunca tiene cédula propia:
   en ese caso el campo de número se deshabilita y el número lo genera el
   servidor al finalizar el wizard (depende del representante legal del
   paso 6, así que no se puede calcular en el navegador).

   Si el estudiante ya tiene cédula, se elige V o E en ese mismo select y el
   campo vuelve a habilitarse. No hay un interruptor aparte: el tipo de
   documento ES la decisión, en el cliente y en el servidor. */
  (function documentoEstudiante() {
    var select = document.getElementById('tipoDocumento');
    var numero = document.getElementById('numeroDocumento');
    if (!select || !numero) return;

    var TIPO_CE = 'CE';

    // El aviso va en el propio campo, en el placeholder: con la cédula escolar
    // se deshabilita y dice que la genera el sistema; con V o E se habilita y
    // aparece solo el ejemplo del número (App\Support\Documento::
    // PLACEHOLDER_HTML), que se replica aquí porque JS no puede leer la
    // constante de PHP. La nota del prefijo opcional vive únicamente en el
    // title del campo.
    var PLACEHOLDERS = {
      CE: 'Se genera automáticamente',
      V:  'Ej: 25415866',
      E:  'Ej: 25415866'
    };

    function sincronizar() {
      var esEscolar = select.value === TIPO_CE;

      numero.placeholder = PLACEHOLDERS[select.value] || PLACEHOLDERS[TIPO_CE];

      // Se deshabilita (y no solo se oculta) para que el campo no quede con
      // `required` dentro de un bloque invisible y trabe el avance del wizard.
      numero.disabled = esEscolar;
      if (esEscolar) {
        numero.value = '';
        numero.removeAttribute('required');
        numero.classList.remove('is-invalid');
      } else {
        numero.setAttribute('required', '');
      }
    }

    select.addEventListener('change', sincronizar);
    sincronizar();
  })();

  /* El tipo de documento se muestra solo con la letra mientras el select está
   cerrado, igual que en los formularios de empleados. El del familiar es un
   select aparte (dentro de su sub-modal), así que se inicializa aparte. */
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'tipoDocumento' });
  SIGDEWizardUI.initTipoDocumentoLetra({ selectId: 'familiarTipoDocumento' });

  SIGDEWizardUI.initTarjetasSeleccionables('[data-sexo-card]');
  SIGDEWizardUI.initTarjetasSeleccionables('[data-familiar-sexo-card]');
  SIGDEWizardUI.initSoloDigitos();
  SIGDEWizardUI.initSoloLetras();

  /* ---------- Toggle "Ingreso por primera vez" (paso 2) ---------- */
  var ingresoPrimeraVez   = document.getElementById('ingresoPrimeraVez');
  var bloqueInstitucion   = document.getElementById('bloqueInstitucionProcedencia');
  var instSelect          = document.getElementById('institucionProcedencia');

  if (ingresoPrimeraVez && bloqueInstitucion) {
    ingresoPrimeraVez.addEventListener('change', function () {
      var esPrimera = ingresoPrimeraVez.checked;
      bloqueInstitucion.style.display = esPrimera ? 'none' : '';
      /* Quitar/poner required en campos del bloque */
      bloqueInstitucion.querySelectorAll('[required]').forEach(function (campo) {
        if (esPrimera) {
          campo.removeAttribute('required');
          campo.dataset.requiredSaved = 'true';
        } else if (campo.dataset.requiredSaved) {
          campo.setAttribute('required', '');
          delete campo.dataset.requiredSaved;
        }
      });
    });
  }

  /* ---------- Tarjetas expandibles — Información Adicional (paso 4) ---------- */
  wizardModalEl.querySelectorAll('[data-extra-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key  = btn.dataset.extraToggle;
      var body = document.getElementById('extraBody-' + key);
      var card = btn.closest('.wizard-extra-card');
      if (!body || !card) return;
      var abierto = !body.hidden;
      body.hidden = abierto;
      card.classList.toggle('is-open', !abierto);
    });
  });

  /* ---------- Recaudos (paso 5): toggle observación ---------- */
  var recaudosTbody = document.getElementById('recaudosTbody');
  if (recaudosTbody) {
    recaudosTbody.addEventListener('change', function (e) {
      var toggle = e.target.closest('[data-recaudo-toggle]');
      if (!toggle) return;
      var row         = toggle.closest('[data-recaudo-row]');
      var observacion = row.querySelector('[data-recaudo-observacion]');
      var pendiente   = row.querySelector('[data-recaudo-pendiente]');

      if (observacion) observacion.classList.toggle('d-none', !toggle.checked);
      if (pendiente)   pendiente.classList.toggle('d-none', toggle.checked);
      if (toggle.checked && observacion) observacion.focus();
      else if (observacion) observacion.value = '';

      /* Actualizar contador del badge */
      var totalEl      = document.getElementById('recaudosTotalCount');
      var consigEl     = document.getElementById('recaudosConsignadosCount');
      var toggles      = recaudosTbody.querySelectorAll('[data-recaudo-toggle]');
      var consignados  = recaudosTbody.querySelectorAll('[data-recaudo-toggle]:checked');
      if (totalEl)  totalEl.textContent  = toggles.length;
      if (consigEl) consigEl.textContent = consignados.length;
    });
  }

  /* ---------- Alta rápida de la institución de procedencia (paso 2) ----------
   Igual que en el wizard de empleados: un modal anidado con el tipo
   (acotado a preescolar/primaria) y el nombre. Al guardar, la institución
   nueva se agrega al <select> de procedencia y queda elegida, para no tener
   que buscarla en la lista. */
  var btnGuardarNuevaInstProcedencia = document.getElementById('btnGuardarNuevaInstitucionProcedencia');

  if (btnGuardarNuevaInstProcedencia) {
    btnGuardarNuevaInstProcedencia.addEventListener('click', async function () {
      var elNombre = document.getElementById('nuevaInstitucionProcedenciaNombre');
      var elTipo   = document.getElementById('nuevaInstitucionProcedenciaTipo');
      var nombre   = (elNombre && elNombre.value || '').trim();
      var tipo     = (elTipo && elTipo.value) || '';

      if (!nombre) {
        SIGDEUI.mostrarAviso('warning', 'Nombre requerido', 'Escribe el nombre de la institución.');
        return;
      }
      if (!tipo) {
        SIGDEUI.mostrarAviso('warning', 'Tipo requerido', 'Selecciona el tipo de institución.');
        return;
      }

      var creado = await SIGDEUI.guardarCatalogoRapido(
        route + 'guardarInstitucion',
        { nombre: nombre, tipo: tipo },
        document.getElementById('institucionProcedencia')
      );

      if (creado) {
        if (elNombre) elNombre.value = '';
        if (elTipo) elTipo.value = '';
        SIGDEUI.ocultarModal('modalNuevaInstitucionProcedencia');
      }
    });
  }

  /* Apertura de los modales anidados (js-abrir-modal-anidado) y restauracion
   del modal-open del wizard: mismo comportamiento que en empleado.js, para
   que "Nueva Institucion" se abra ENCIMA del wizard sin cerrarlo. */
  document.addEventListener('click', function (evento) {
    var boton = evento.target.closest('.js-abrir-modal-anidado');
    if (!boton) return;

    var destino = boton.dataset.bsTarget ? document.querySelector(boton.dataset.bsTarget) : null;
    if (destino) SIGDEUI.mostrarModal(destino);
  });

  document.addEventListener('hidden.bs.modal', function (evento) {
    if (wizardModalEl && wizardModalEl.classList.contains('show')) {
      document.body.classList.add('modal-open');
    }
  });

  /* ---------- Cascada de dirección del familiar: estado -> municipio -> parroquia ----------
   Es la misma del formulario de empleados (SIGDEWizardUI.initCascadaUbicacion), con
   su propio endpoint. Se conserva el handle porque al cargar los datos de un
   familiar registrado hay que dejar los tres niveles elegidos. */
  var cascadaFamiliar = SIGDEWizardUI.initCascadaUbicacion({
    url: route + 'getUbicacion',
    estadoId: 'familiarDireccionEstado',
    municipioId: 'familiarDireccionMunicipio',
    parroquiaId: 'familiarDireccionParroquia'
  });

  /* ---------- Sub-modal de Familiares (paso 6) ---------- */
  var btnAbrirFamiliar = document.getElementById('btnAbrirAgregarFamiliar');
  var modalFamiliarEl  = document.getElementById('modalAgregarFamiliar');

  /**
   * Deja el sub-modal como recién abierto: sin nombres, sin contacto, sin
   * domicilio y sin los datos que se hubieran cargado de otra cédula.
   *
   * Se usa en dos momentos, y no son lo mismo:
   *  - al abrirlo, se revierte TODO, incluido el tipo de documento y el
   *    parentesco;
   *  - al borrar la cédula, se conservan el tipo de documento y el parentesco,
   *    porque son decisiones de quien escribe, no datos cargados de la persona
   *    equivocada. Lo que sí se va es el resto, incluido el domicilio y el
   *    teléfono, que sí venían de esa cédula.
   *
   * @param {boolean}  conservarDecisiones true para no tocar tipo y parentesco.
   * @param {HTMLElement} [excepto] Campo que NO se toca. Hace falta porque el
   *        aviso se dispara desde el input de la cédula: si se vaciara también,
   *        se borraría a sí mismo en cada tecla y el campo parecería bloqueado.
   */
  function vaciarSubModalFamiliar(conservarDecisiones, excepto) {
    if (!modalFamiliarEl) return;

    /* Los datos que había en el formulario eran de esta cédula: si se vacía el
       formulario, esa cédula ya no tiene nada cargado, y lo que estaba
       bloqueado vuelve a poder escribirse. */
    cedulaCargada = null;
    bloquearIdentidad(false);

    modalFamiliarEl.querySelectorAll('input[type="text"], input[type="email"], input[type="date"], textarea')
      .forEach(function (el) { if (el !== excepto) el.value = ''; });
    modalFamiliarEl.querySelectorAll('input[type="radio"]').forEach(function (el) { el.checked = false; });
    modalFamiliarEl.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

    if (conservarDecisiones) {
      /* El domicilio sí venía de la persona encontrada, así que la cascada
         vuelve a su estado inicial: estado sin elegir, municipio y parroquia
         deshabilitados explicando qué falta. El prefijo telefónico también es
         un dato cargado y vuelve al primero. */
      var estado = document.getElementById('familiarDireccionEstado');
      if (estado) estado.selectedIndex = 0;
      if (cascadaFamiliar) {
        // Los dos hijos vuelven a su estado inicial, cada uno explicando qué
        // falta elegir, igual que cuando se abre el formulario de empleados.
        cascadaFamiliar.reiniciar(document.getElementById('familiarDireccionMunicipio'));
        cascadaFamiliar.reiniciar(document.getElementById('familiarDireccionParroquia'));
      }
      ['familiarCodigoTelefonoPrincipal', 'familiarCodigoTelefonoAlternativo'].forEach(function (id) {
        var prefijo = document.getElementById(id);
        if (prefijo) prefijo.selectedIndex = 0;
      });
    } else {
      modalFamiliarEl.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
      if (cascadaFamiliar) {
        cascadaFamiliar.reiniciar(document.getElementById('familiarDireccionMunicipio'));
        cascadaFamiliar.reiniciar(document.getElementById('familiarDireccionParroquia'));
      }
    }

    if (fotoFamiliar) fotoFamiliar.vaciar();

    /* El aviso de "persona encontrada" y el panel de diferencias son de la
       cédula anterior, así que también se van, y se olvida a quién pertenecían
       (ver forgetPersonaEncontrada en buscarFamiliar). */
    forgetPersonaEncontrada();

    /* Y se reaplican las reglas del representante y de "no es familiar",
       porque el reset desmarca los checkbox y los switch se verían apagados. */
    if (bloqueRepresentanteUI) bloqueRepresentanteUI.sincronizar();
  }

  if (btnAbrirFamiliar && modalFamiliarEl) {
    btnAbrirFamiliar.addEventListener('click', function (e) {
      e.stopPropagation();
      vaciarSubModalFamiliar(false);
      editandoFamiliarIndice = null;
      var titulo = document.getElementById('modalAgregarFamiliarLabel');
      if (titulo) titulo.textContent = 'Agregar familiar';
      var boton = document.getElementById('btnGuardarFamiliar');
      if (boton) boton.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i> Guardar familiar';
      SIGDEUI.mostrarModal(modalFamiliarEl);
    });
  }

  /* ---------- Buscar al familiar por su cédula ----------
   Si esa cédula ya está registrada, se llenan sus datos (nombres, sexo y,
   cuando es el representante legal, contacto y domicilio) y se muestra en
   qué difiere de lo que hay en la base, que es lo que la secretaria debe
   revisar antes de guardar. Si no está registrada, se avisa y se sigue con
   el alta normal. */
  (function buscarFamiliar() {
    var input = document.getElementById('familiarNumeroDocumento');
    var panel = document.getElementById('personaEncontrada');
    var texto = document.getElementById('personaEncontradaTexto');
    if (!input || !panel || !texto) return;

    /* Campos que identifican a la persona. Cuando la cédula ya está
       registrada se bloquean: son los datos que tiene el sistema y no tienen
       sentido cambiarlos desde acá. El contacto y el domicilio sí se pueden
       modificar, porque cambian (mudanza, otro teléfono). */


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

      SIGDEUI.enviar(route + 'buscarPersona?documento=' + encodeURIComponent(documento), { metodo: 'GET' })
        .then(function (r) {
          // Si mientras se consultaba se borró o se corrigió la cédula, la
          // respuesta ya no corresponde a lo que hay en el campo: se descarta,
          // porque llenarla dejaría los datos de una persona que ya no está.
          if (soloDigitos(input.value) !== documento) return;

          // SIGDEUI.enviar() devuelve el sobre completo de la respuesta, así
          // que la persona viene en r.data.data (igual que en el resto del
          // proyecto: r.data.ok, r.data.mensaje, r.data.data).
          var encontrado = r.data ? r.data.data : null;

          if (!r.ok || !encontrado) {
            texto.innerHTML = '<strong>No está registrada.</strong> Se creará al guardar el familiar.';
            panel.classList.remove('d-none');
            return;
          }

          var persona = encontrado.persona || {};
          var textoNombre = [persona.primer_nombre, persona.segundo_nombre, persona.primer_apellido, persona.segundo_apellido]
            .filter(Boolean).join(' ');

          // Primero se ve qué difiere de lo escrito y después se cargan los
          // datos guardados: la secretaria necesita saber qué se cambió. Con
          // vacioEsCambio = false, porque en este momento un campo vacío es
          // "todavía no escribió", no "lo borró".
          var cambios = diferencias(tomarLoEscrito(), encontrado, false);

          /* Los nombres van por el mismo orden que CAMPOS_IDENTIDAD: los cuatro
             campos de persona, en la misma lista que se bloquea después. */
          var claves = ['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'];
          CAMPOS_IDENTIDAD.slice(0, 4).forEach(function (id, i) { poner(id, persona[claves[i]]); });
          poner('familiarFechaNacimiento', persona.fecha_nacimiento);
          marcarSexo(persona.sexo);

          if (encontrado.contacto) {
            poner('familiarCodigoTelefonoPrincipal', encontrado.contacto.codigo);
            poner('familiarTelefonoPrincipal', encontrado.contacto.numero);
            poner('familiarCodigoTelefonoAlternativo', encontrado.contacto.codigo_alt);
            poner('familiarTelefonoAlternativo', encontrado.contacto.numero_alt);
            poner('familiarCorreo', encontrado.contacto.correo);
          }

          if (encontrado.direccion) {
            poner('familiarDireccionSector', encontrado.direccion.sector);
            poner('familiarDireccionCalle', encontrado.direccion.calle);
            poner('familiarDireccionCasa', encontrado.direccion.casa);
            poner('familiarDireccionReferencia', encontrado.direccion.referencia);

            /* La cascada se arma de arriba hacia abajo: con la parroquia
               guardada el servidor devuelve a qué estado y municipio
               pertenecen, y se eligen los tres niveles. Son dos peticiones
               encadenadas, así que el aviso se pinta al final: si no pudo
               completarse, el aviso lo dice en vez de dejar los selects a
               medias. */
            if (cascadaFamiliar) {
              cascadaFamiliar.elegirParroquia(
                Number(encontrado.direccion.estado || 0),
                Number(encontrado.direccion.municipio || 0),
                Number(encontrado.direccion.parroquia || 0)
              ).then(function (ok) {
                cascadaArmada = ok === true;
                pintarAviso(cambios);
              });
            } else {
              poner('familiarDireccionParroquia', encontrado.direccion.parroquia);
              cascadaArmada = true;
            }
          }

          /* Desde acá hay datos de ESTA persona en el formulario: si la cédula
             se borra o se corrige, se limpian (ver revisarCedula()). */
          cedulaCargada = documento;

          /* Sus datos de identidad no se tocan: ya están en el sistema y
             cambiarlos desde acá dejaría el registro inconsistente con el resto
             de los módulos que lo usan. El contacto y el domicilio sí quedan
             editables, que es lo que realmente cambia. */
          bloquearIdentidad(true);

          /* Se guarda a quién pertenecen estos datos y se pinta el aviso con
             la comparación que se hizo antes de llenar el formulario. */
          personaEncontrada = encontrado;
          nombreEncontrado = textoNombre;

          /* Si hay domicilio, el aviso espera a que la cascada termine de
             armarse (ver el .then de elegirParroquia): si no, se pintaría
             antes de saber si la ubicación se pudo cargar. */
          if (!encontrado.direccion || !cascadaFamiliar) pintarAviso(cambios);
        })
        .catch(function () {
          texto.innerHTML = '<strong>No se pudo consultar el registro.</strong> Revisa la conexión o inténtalo de nuevo.';
          panel.classList.remove('d-none');
        });
    });

    /**
       * Si la cédula del campo ya no es la misma cuyos datos se cargaron, esos
       * datos no le pertenecen a nadie y se limpian. Cubre los tres casos en que
       * pasa:
       *
       *   - se equivoca de cédula y la borra del todo;
       *   - la borra a medias (le queda un dígito, o solo el "V-");
       *   - se da cuenta del error y corrige un dígito en vez de borrarla.
       *
       * Ese último era el hueco: como la búsqueda solo corre con 7 u 8 dígitos,
       * una cédula a medio corregir dejaba los datos de la persona anterior en el
       * formulario, y se podían guardar con la cédula nueva.
       *
       * Escribir una cédula desde cero NO limpia nada, porque en ese momento no
       * hay datos cargados (cedulaCargada es null): por eso el campo se puede
       * escribir dígito a dígito.
    */
    function revisarCedula() {
      var documento = soloDigitos(input.value);
      forgetPersonaEncontrada();

      if (cedulaCargada === null) return;   // no hay nada cargado
      if (documento === cedulaCargada) return; // la cédula es la misma

      vaciarSubModalFamiliar(true, input);
      }

    input.addEventListener('input', function () { revisarCedula(); buscar(); });
    input.addEventListener('change', function () { revisarCedula(); buscar(); });

    /* Editar el contacto o el domicilio de una persona ya registrada cambia
       la lista de "estos datos van a cambiar", así que el aviso se repinta al
       vuelo. Sin esto el aviso quedaba con la comparación del momento de la
       búsqueda: se cambiaba el teléfono y el panel seguía diciendo que todo
       coincidía (o seguía anunciando un cambio que ya se había corregido).

       Solo repinta si hay una persona cargada: antes de la primera búsqueda
       esto no tiene nada que pintar. Y los campos de identidad no se
       escuchan, porque están bloqueados cuando la persona está encontrada. */
    CAMPOS_EDITABLES.forEach(function (id) {
      var campo = document.getElementById(id);
      if (!campo) return;

      campo.addEventListener('input', function () { pintarAviso(); });
      campo.addEventListener('change', function () { pintarAviso(); });
    });
  })();

  /* ---------- Representante legal: lo elige quien registra ----------
     El interruptor de arriba es libre: la secretaria marca quién es el
     representante legal. Las reglas que se ajustan con él son:
       - el permiso de retiro se marca solo si es el representante;
       - el domicilio pasa a ser obligatorio si es el representante, y queda
         opcional para los demás familiares (el teléfono principal siempre es
         obligatorio, para cualquiera).
     Los obligatorios se reaplican en cada apertura porque el sub-modal se
     reutiliza. */
  /* ¿El parentesco elegido es el del padre o el de la madre?
     Se comparan con el NOMBRE del catálogo, que es lo que travels el <select> y
     lo que viaja en el payload, y no con el id: los ids de `parentesco` pueden
     cambiar si alguien rehace el catálogo, y esta regla no debe depender de
     ellos. El PHP decide lo mismo (ver InscripcionesModel), porque el payload
     es un JSON que se puede armar a mano. */
  function esPadreOMadreSegun(parentesco) {
    var nombre = String(parentesco || '').trim().toLowerCase();
    return nombre === 'madre' || nombre === 'padre';
  }

  var bloqueRepresentanteUI = (function bloqueRepresentante() {
    var switchEl = document.getElementById('familiarRepresentantePrincipal');
    var bloqueEl = document.getElementById('bloqueContactoRepresentante');
    // El domicilio va en su propio bloque para poder esconderlo entero cuando el
    // familiar no es el representante legal (misma clase de bloque en el resto del
    // wizard: el contacto se ve siempre, el domicilio no).
    var direccionEl = document.getElementById('bloqueDireccionFamiliar');
    if (!switchEl || !bloqueEl) return;

    var retirosEl = document.getElementById('familiarAutorizadoRetiros');

    var noFamiliarEl = document.getElementById('familiarSoloAutorizado');
    var viveConEl = document.getElementById('familiarViveConEstudiante');
    /* Qué había en el interruptor de representante en la sincronización
       anterior. Sirve para una sola cosa: detectar el momento en que deja de
       ser representante, que es cuando "vive con el estudiante" hay que
       apagarse. */
    var representanteEra = switchEl.checked;
    // Los dos selects del bloque "Datos del representante".
    var datosRepresentanteEl = document.getElementById('bloqueDatosRepresentante');
    var tituloDatosEl = document.getElementById('tituloDatosRepresentante');
    var nivelEl = document.getElementById('familiarNivelInstruccion');
    var ocupacionEl = document.getElementById('familiarOcupacion');

    var parentescoEl  = document.getElementById('familiarParentesco');

    // Se marcan por id y no con un atributo del markup porque el prefijo
    // telefónico lo genera components/phone-input.php. Son los mismos campos
    // que pide el formulario de empleados para el contacto y el domicilio.
    // El teléfono principal y los datos de la persona se piden siempre; el
    // domicilio depende de si es el representante legal.
    var REQUERIDOS_SIEMPRE = [
      'familiarCodigoTelefonoPrincipal', 'familiarTelefonoPrincipal',
      // Su cédula es el dato con el que el sistema arma la cédula escolar del
      // menor, así que sin ella no se podría generar.
      'familiarNumeroDocumento',
      // Es una persona real y mayor de edad, igual que en el resto de los
      // formularios de persona del sistema.
      'familiarFechaNacimiento'
    ];

    var DIRECCION = [
      'familiarDireccionEstado', 'familiarDireccionMunicipio', 'familiarDireccionParroquia',
      'familiarDireccionSector', 'familiarDireccionCalle', 'familiarDireccionCasa'
    ];

    /**
     * Reglas del interruptor "No es familiar" (migración 0002).
     *
     * Al marcarlo, el parentesco se deshabilita y se limpia: esa persona no
     * tiene vínculo con el menor, y no tiene otro motivo para estar en la lista
     * que el permiso de retiro, que se le fuerza. Al desmarcarlo, el permiso
     * vuelve a ser libre salvo que la persona siga siendo la representante
     * (que siempre lo tiene).
     *
     * También es incompatible con "representante legal": un representante sin
     * parentesco no significa nada en este sistema. Aquí se avisa, y el
     * servidor lo rechaza igual (InscripcionesController), porque los switch
     * del navegador se pueden manipular.
     */
    function sincronizarNoFamiliar() {
      if (!noFamiliarEl) return;

      /* Las dos opciones son incompatibles: una persona sin parentesco no puede
         tener la representación legal de un menor. Por eso el bloqueo va en las
         DOS direcciones: al marcar el representante, "no es familiar" se
         deshabilita y se desmarca; al marcar "no es familiar", el interruptor
         del representante hace lo mismo. Quien quiera el otro, primero
         desmarca este.

         Se hace en el DOM y no solo al guardar, porque el error ocurre en el
         momento de marcar, no al apretar el botón: si se corrigiera al final,
         el formulario aceptaría un registro incoherente. */
      /* Si por lo que sea quedaron los dos encendidos, gana el que NO está
         bloqueado: un interruptor bloqueado no se pudo marcar con el dedo, así
         que el marcado a la fuerza no puede ser el que decidió. Esto solo pasa
         con un DOM armado a mano; en la pantalla normal el que se marca primero
         bloquea al otro antes de que se pueda marcar. */
      if (switchEl.checked && noFamiliarEl.checked) {
        if (switchEl.disabled) switchEl.checked = false;
        else noFamiliarEl.checked = false;
      }

      /* Y el perdedor se desmarca. El bloqueo se recalcula al final, con el
         estado ya limpio: que el `disabled` sea consecuencia y no la entrada
         evita que un estado viejo del DOM decida por quién. */
      if (switchEl.checked) noFamiliarEl.checked = false;
      else if (noFamiliarEl.checked) switchEl.checked = false;

      switchEl.disabled     = noFamiliarEl.checked;
      noFamiliarEl.disabled = switchEl.checked;

      var noEsFamiliar = noFamiliarEl.checked;

      if (parentescoEl) {
        parentescoEl.disabled = noEsFamiliar;
        if (noEsFamiliar) parentescoEl.value = '';
      }

      // El asterisco del parentesco: es opcional en general, y en esta
      // situación ni siquiera tiene sentido ofrecerlo. Para el representante sí
      // es obligatorio, porque no puede ser "no familiar" y su parentesco es lo
      // que dice quién es.
      var asteriscos = document.querySelectorAll('[data-requerido-parentesco]');
      Array.prototype.forEach.call(asteriscos, function (el) {
        el.classList.toggle('d-none', noEsFamiliar);
      });
    }

    function sincronizar() {
      var esRepresentante = switchEl.checked;

      /* El nivel de instrucción y la ocupación se piden en dos casos: cuando el
         familiar es el REPRESENTANTE LEGAL, y cuando es el padre o la madre del
         menor. Lo segundo no es un capricho: aunque no sea el representante, el
         padre y la madre son los dos datos que la institución quiere del hogar
         del menor, y son los únicos dos parentescos que casi siempre la
         institución tiene o quiere tener. Para el resto de los familiares no se
         preguntan. */
      var esPadreOMadre = esPadreOMadreSegun(parentescoEl ? parentescoEl.value : '');
      if (noFamiliarEl) sincronizarNoFamiliar();

      // El contacto se ve siempre (el teléfono es cómo se localiza a cualquiera);
      // el domicilio SOLO cuando el familiar es el representante legal, y ahí es
      // obligatorio. Para los demás no se muestra: la dirección que importa es la
      // del representante, y pedirla de cada familiar obliga a escribir la misma
      // casa varias veces. El bloque se esconde con la clase d-none de Bootstrap,
      // igual que el resto del sistema, y no con hidden, para que no queden
      // campos required ocultos (el navegador no valida lo que no ve, pero
      // deshabilitarlos además evita que el Back del navegador los rellene).
      if (direccionEl) direccionEl.classList.toggle('d-none', !esRepresentante);

      /* El nivel de instrucción y la ocupación son del REPRESENTANTE. Se
         esconden enteros para los demás, en vez de mostrarse vacíos: son datos
         que la institución no le pide a un familiar corriente, y verlos
         apagados. */
      var pideDatosPersonales = esRepresentante || esPadreOMadre;
      if (datosRepresentanteEl) datosRepresentanteEl.classList.toggle('d-none', !pideDatosPersonales);

      [nivelEl, ocupacionEl].forEach(function (campo) {
        if (!campo) return;
        if (pideDatosPersonales) campo.setAttribute('required', '');
        else {
          campo.removeAttribute('required');
          campo.value = '';
        }
      });

      var asteriscosRepresentante = document.querySelectorAll('[data-requerido-datos-representante]');
      Array.prototype.forEach.call(asteriscosRepresentante, function (el) {
        el.classList.toggle('d-none', !pideDatosPersonales);
      });

      /* El título del bloque sigue a lo que se está pidiendo: no es lo mismo
         "datos del representante" si quien lo llena es el padre y no el
         representante legal. */
      if (tituloDatosEl) {
        tituloDatosEl.textContent = !pideDatosPersonales
          ? 'Datos personales'
          : esRepresentante ? 'Datos del representante' : 'Datos del padre o la madre';
      }

      /* El parentesco es obligatorio, y la única excepción es el interruptor de
         "no es familiar": o se dice cuál es el vínculo, o se dice explícitamente
         que no hay ninguno. Lo que ya no se puede es guardar a alguien como
         "Familiar" sin parentesco, porque ese registro no dice nada: en la
         tabla terminaba como "Familiar · Sin parentesco", que son dos
         afirmaciones que se contradicen.

         Para el representante además es obligatorio por otra razón: no puede ser
         "no es familiar", así que el parentesco es lo único que deja escrito
         quién es el responsable del menor. */
      if (parentescoEl) {
        if (noFamiliarEl && noFamiliarEl.checked) parentescoEl.removeAttribute('required');
        else parentescoEl.setAttribute('required', '');
      }

      REQUERIDOS_SIEMPRE.concat(esRepresentante ? DIRECCION : []).forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) campo.setAttribute('required', '');
      });

      (esRepresentante ? [] : DIRECCION).forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) campo.removeAttribute('required');
      });

      /* Y además se deshabilitan, para que el DOM no mande un domicilio que la
         secretaria no ve. El enabled se repone al marcar representante. */
      DIRECCION.forEach(function (id) {
        var campo = document.getElementById(id);
        if (campo) campo.disabled = !esRepresentante;
      });

      // Los asteriscos del domicilio, para que se vea qué es obligatorio y qué
      // no sin tener que probarlo.
      var asteriscos = bloqueEl.querySelectorAll('[data-requerido-direccion]');
      Array.prototype.forEach.call(asteriscos, function (el) {
        el.classList.toggle('d-none', !esRepresentante);
      });

      /* El permiso de retiro depende de DOS cosas, y por eso se decide en un solo
         sitio: el representante legal siempre puede retirar, y quien está marcado
         como "no es familiar" está en la lista precisamente para eso. Si cada
         regla se decidiera por su lado, la del representante se comería a la otra
         y dejaría el permiso apagado. */
      /* "Vive con el estudiante" se marca solo para el REPRESENTANTE, y por la
         misma razón que el permiso de retiros: el menor vive con su
         representante legal, así que ahí no hay nada que preguntar.

         Para los demás es una pregunta libre y nace apagada (migración 0004): se
         registra lo que la secretaria sabe, no lo que el sistema supone. Un
         hermano que vive con el menor también se marca, y por eso el apagado
         ocurre SOLO en el momento en que deja de ser representante: si se
         apagara en cada sincronización, cualquier cambio de otro campo borraría
         lo que la secretaria acababa de marcar. */
      if (viveConEl) {
        if (esRepresentante) {
          viveConEl.checked  = true;
          viveConEl.disabled = true;
        } else {
          viveConEl.disabled = false;
          if (representanteEra) viveConEl.checked = false;
        }
        representanteEra = esRepresentante;
      }

      var noEsFamiliar = !!(noFamiliarEl && noFamiliarEl.checked);
      if (retirosEl) {
        if (esRepresentante || noEsFamiliar) {
          retirosEl.checked  = true;
          retirosEl.disabled = true;
        } else {
          // Al dejar de serlo se desmarca: ese permiso lo había puesto el sistema,
          // no quien escribe, así que dejarlo prendido le regalaría a un familiar
          // corriente la autorización de retiros.
          retirosEl.checked  = false;
          retirosEl.disabled = false;
        }
      }
    }

    switchEl.addEventListener('change', sincronizar);
    if (noFamiliarEl) noFamiliarEl.addEventListener('change', sincronizar);
    /* También el parentesco: el bloque de nivel y ocupación aparece con "Padre"
       o "Madre", así que elegirlo tiene que reevaluar las reglas. Sin este
       listener, el bloque solo aparecería al tocar otro interruptor. */
    if (parentescoEl) parentescoEl.addEventListener('change', sincronizar);
    document.addEventListener('DOMContentLoaded', sincronizar);
    sincronizar();

    // Se devuelve para poder reaplicar las reglas cuando se abre el
    // sub-modal o cuando se limpia: el reset general desmarca los checkbox y
    // dejaría al representante y al retiro apagados en pantalla.
    return { sincronizar: sincronizar };
  })();

  var btnGuardarFamiliar = document.getElementById('btnGuardarFamiliar');
  var formFamiliar       = document.getElementById('formAgregarFamiliar');

  if (formFamiliar) {
    /* El botón de guardar es type="button", así que sin esto el Enter en
       cualquier campo disparaba el envío implícito del <form> y recargaba la
       página, perdiendo todo el wizard. Con Enter se guarda el familiar, que
       es lo que se espera. */
    /* Al cerrar el sub-modal (por el botón o por el fondo) se sale del modo
     edición: si se cerrara y se volviera a abrir con "Agregar familiar", no
     debe quedar editando la fila anterior por el camino. */
  if (modalFamiliarEl) {
    SIGDEUI.alCerrarModal(modalFamiliarEl, function () { editandoFamiliarIndice = null; });
  }

  formFamiliar.addEventListener('submit', function (evento) {
      evento.preventDefault();
      if (btnGuardarFamiliar) btnGuardarFamiliar.click();
    });
  }

  /** Arma el objeto del familiar con lo que hay en el sub-modal. Se separa del
      manejador de guardar porque el guardado necesita armar el objeto en dos
      momentos distintos: al guardar, y al dejar pendiente a un familiar cuando
      hay que cambiar de representante legal. */
  function armarFamiliar() {
    var soloAutorizado = (document.getElementById('familiarSoloAutorizado') || {}).checked === true;
    var parentescoEl    = document.getElementById('familiarParentesco');
    var parentesco      = soloAutorizado ? '' : (parentescoEl ? parentescoEl.value : '');
    var esRepresentante = (document.getElementById('familiarRepresentantePrincipal') || {}).checked === true;
    var valor = function (id) { return (document.getElementById(id) || {}).value || ''; };
    // Misma condición que sincronizar(): representante legal, o padre o madre.
    var pideDatosPersonales = esRepresentante || esPadreOMadreSegun(parentesco);
    var primerNombre    = valor('familiarPrimerNombre');
    var segundoNombre   = valor('familiarSegundoNombre');
    var primerApellido  = valor('familiarPrimerApellido');
    var segundoApellido = valor('familiarSegundoApellido');
    var numeroDocumento = valor('familiarNumeroDocumento');
    var nombre = [primerNombre, segundoNombre, primerApellido, segundoApellido].filter(Boolean).join(' ') || 'Sin nombre';

      // igual que en el formulario de empleados.
    var direccion = {
        estado:    valor('familiarDireccionEstado'),
        municipio: valor('familiarDireccionMunicipio'),
        parroquia: valor('familiarDireccionParroquia'),
        sector:    valor('familiarDireccionSector'),
        calle:     valor('familiarDireccionCalle'),
        casa:      valor('familiarDireccionCasa'),
        referencia: valor('familiarDireccionReferencia')
    };
    /* El domicilio solo va si es el representante legal (migración 0003 y la
         regla del formulario): para los demás el bloque ni siquiera está
         visible, y aunque quedara algo escrito de una apertura anterior, no se
         manda. Antes mandaba lo que hubiera en el DOM si el campo tenía algo. */
    var tieneDireccion = esRepresentante && Object.keys(direccion).some(function (k) { return direccion[k] !== ''; });

    var familiar = {
        parentesco:              parentesco,
        // Sin parentesco, la columna del vínculo muestra "Sin parentesco" en vez
        // de un guion que no diría nada.
        parentesco_texto:        parentesco
          ? parentescoEl.selectedOptions[0].textContent.trim()
          : 'Sin parentesco',
        // Para que el servidor sepa que el parentesco vacío fue una decisión y
        // no un dato que se olvidó mandar.
        solo_autorizado:         soloAutorizado ? 1 : 0,
        representante_principal: esRepresentante ? 1 : 0,
        // Si esa persona vive con el menor (migración 0003). Es de la relación
        // estudiante-persona, no de la persona, por eso va con el familiar y no
        // en el payload del estudiante. Viene marcado por defecto.
        vive_con_estudiante:     (document.getElementById('familiarViveConEstudiante') || {}).checked === true ? 1 : 0,
        /* Nivel de instrucción y ocupación, por NOMBRE del catálogo (igual que
           el parentesco): el modelo los resuelve a id. Solo se mandan si el
           familiar es el representante; para los demás los selects ni siquiera
           están a la vista y vienen vacíos. Antes el modelo se inventaba el
           primer registro de cada tabla. */
        nivel_instruccion:       pideDatosPersonales ? valor('familiarNivelInstruccion') : '',
        ocupacion:               pideDatosPersonales ? valor('familiarOcupacion') : '',
        autorizado_retiros:      esRepresentante ? 1 : (document.getElementById('familiarAutorizadoRetiros') || {}).checked ? 1 : 0,
        nombre_referencia:       nombre,
        // Los cuatro nombres por separado: es lo que se guarda en persona. Antes
        // solo se guardaba el primer nombre y el primer apellido, y los
        // segundos se perdían.
        primer_nombre:           primerNombre,
        segundo_nombre:          segundoNombre,
        primer_apellido:         primerApellido,
        segundo_apellido:        segundoApellido,
        numero_documento:        numeroDocumento,
        // Con la letra (V/E) de su documento, el servidor arma la cédula
        // escolar del menor; el orden entre los hijos lo cuenta él solo.
        tipo_documento:          valor('familiarTipoDocumento'),
        sexo:                    (document.querySelector('input[name="familiar_sexo"]:checked') || {}).value || 'M',
        // Se guarda en persona.fecha_nacimiento: es una persona real, no un
        // dato de relleno.
        fecha_nacimiento:        valor('familiarFechaNacimiento'),
        // El teléfono principal es obligatorio para cualquier familiar; el
        // correo y el alternativo son opcionales.
        contacto: {
          codigo:     valor('familiarCodigoTelefonoPrincipal'),
          numero:     valor('familiarTelefonoPrincipal'),
          codigo_alt: valor('familiarCodigoTelefonoAlternativo'),
          numero_alt: valor('familiarTelefonoAlternativo'),
          correo:     valor('familiarCorreo')
        },
        direccion: tieneDireccion ? direccion : null
    };

    /* El estado de la fila (activo o desactivado) viaja con el familiar. Es
       el mismo `estado_relacion` de la tabla: desactivar es la alternativa
       a borrar, y se decide en la fila de la lista, no en el sub-modal. */
    familiar.estado_relacion = estadoFamiliar;

    return familiar;
  }

  async function guardarFamiliarEnLaLista() {
      /* El parentesco es OPCIONAL (migración 0002). Quien no tiene vínculo con
         el menor se registra con el interruptor "No es familiar", que lo deja en
         blanco a propósito y le fuerza la autorización de retiros. */
      var soloAutorizado = (document.getElementById('familiarSoloAutorizado') || {}).checked === true;
      var parentescoEl    = document.getElementById('familiarParentesco');
      var parentesco      = soloAutorizado ? '' : (parentescoEl ? parentescoEl.value : '');

      var primerNombre   = (document.getElementById('familiarPrimerNombre')    || {}).value || '';
      var primerApellido = (document.getElementById('familiarPrimerApellido')   || {}).value || '';
      var segundoNombre  = (document.getElementById('familiarSegundoNombre')    || {}).value || '';
      var segundoApellido= (document.getElementById('familiarSegundoApellido')  || {}).value || '';
      var numeroDocumento = (document.getElementById('familiarNumeroDocumento') || {}).value || '';

      // Quién es el representante legal lo elige quien registra. El permiso de
      // retiro se fuerza para el representante (siempre puede retirar al
      // estudiante) y queda libre para los demás.
      var esRepresentante = (document.getElementById('familiarRepresentantePrincipal') || {}).checked === true;

      var nombre = [primerNombre, segundoNombre, primerApellido, segundoApellido].filter(Boolean).join(' ') || 'Sin nombre';

      // No se puede ser "no familiar" y representante legal a la vez: se avisa
      // aquí para que el mensaje le llegue a quien registra, y no como un 422 al
      // final. El servidor lo rechaza igual (InscripcionesController), porque los
      // switch del navegador se pueden manipular.
      if (soloAutorizado && esRepresentante) {
        SIGDEUI.mostrarAviso(
          'warning',
          'No puede ser las dos cosas',
          'Una persona sin parentesco no puede ser la representante legal. ' +
          'Desmarque "representante legal" o deje de marcar "no es familiar".'
        );
        return;
      }

      var valor = function (id) { return (document.getElementById(id) || {}).value || ''; };

      // El representante legal tiene que dejar el contacto y el domicilio: son
      // los datos con los que la institución ubica y se comunica con el
      // estudiante, que al ser menor no tiene los suyos. Es el mismo juego de
      // campos que pide el formulario de empleados.
      var faltantes = [];
      if (!valor('familiarCodigoTelefonoPrincipal') || !valor('familiarTelefonoPrincipal')) {
        faltantes.push('el teléfono principal');
      }
      // El domicilio solo es obligatorio para el representante legal: el
      // estudiante es menor y no tiene domicilio propio, así que ese es el
      // único con el que se puede llegar a su casa.
      if (esRepresentante) {
        var sinUbicacion = !valor('familiarDireccionEstado') || !valor('familiarDireccionMunicipio')
          || !valor('familiarDireccionParroquia');
        var sinCasa = !valor('familiarDireccionSector') || !valor('familiarDireccionCalle')
          || !valor('familiarDireccionCasa');

        if (sinUbicacion && sinCasa) {
          faltantes.push('el domicilio completo (estado, municipio, parroquia, sector, calle y N°)');
        } else if (sinUbicacion) {
          faltantes.push('el estado, el municipio y la parroquia');
        } else if (sinCasa) {
          faltantes.push('el sector, la calle y el número de casa');
        }
      }
      /* El parentesco, salvo que se haya marcado "no es familiar". Va aquí y no
         solo como atributo required porque el botón de guardar es type="button":
         el navegador no valida nada solo, y sin esto el error llegaría como un
         422 al final del wizard, con todo lo demás ya escrito. */
      if (!soloAutorizado && !valor('familiarParentesco')) {
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

      // El botón es type="button", así que el pattern del navegador no se
      // dispara solo: la cédula se revisa aquí con el mismo criterio del
      // servidor (App\Support\Documento: 7 u 8 dígitos, con o sin prefijo).
      var digitosCedula = valor('familiarNumeroDocumento').replace(/\D/g, '');
      if (valor('familiarNumeroDocumento') !== '' && (digitosCedula.length < 7 || digitosCedula.length > 8)) {
        SIGDEUI.mostrarAviso(
          'warning',
          'Cédula incompleta',
          'La cédula del representante debe tener 7 u 8 dígitos.'
        );
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

      /* Solo puede haber un representante legal por estudiante, y eso no se
         resuelve cambiando una fila por otra: se cambia de rol explícitamente.
         Si ya hay un representante, este NO se guarda; se abre el anterior en
         modo edición para que le quiten la opción, y en cuanto lo guarden sin
         esa opción, se vuelve a abrir este con los datos ya escritos.

         Lo que no se hace es reemplazar al anterior por sorpresa: dejaría al
         menor con un representante legal distinto del que la secretaria cree,
         y esa decisión no se toma sola. Tampoco se le borra la fila, porque
         sigue siendo familiar del menor aunque deje de representarlo. */
      if (esRepresentante) {
        var anteriorIndice = -1;
        familiaresAgregados.forEach(function (f, i) {
          if (f.representante_principal && anteriorIndice < 0) anteriorIndice = i;
        });

        if (anteriorIndice >= 0 && anteriorIndice !== editandoFamiliarIndice) {
          var anterior = familiaresAgregados[anteriorIndice];
          pendienteFamiliar = { datos: armarFamiliar(), estado: estadoFamiliar };

          SIGDEUI.mostrarAviso(
            'warning',
            'Solo un representante legal',
            anterior.nombre_referencia + ' ya es el representante legal. '
            + 'Para cambiarlo, quítele esa opción y guarde los cambios; '
            + 'después podrá guardar este.'
          );

          abrirFamiliarParaEditar(anteriorIndice, true);
          return;
        }
      }

      // El domicilio se manda solo si tiene algo: para un familiar que no es el
      // representante es opcional, y mandar campos vacíos haría que se guardara
      // (o borrara) un domicilio en blanco.
      // Estado y municipio viajan solo para el wizard: `direccion` guarda un
      // único FK (id_parroquia) y los otros dos niveles se deducen de ella,



      var familiar = armarFamiliar();

      /* Editar sustituye la fila; agregar la agrega al final. Nunca se borra
         una: eso es lo que se reemplaza con editar y con activar/desactivar. */
      if (editandoFamiliarIndice !== null && familiaresAgregados[editandoFamiliarIndice]) {
        familiaresAgregados[editandoFamiliarIndice] = familiar;
      } else {
        familiaresAgregados.push(familiar);
      }
      editandoFamiliarIndice = null;

      renderFamiliares();
      SIGDEUI.ocultarModal(modalFamiliarEl);

      /* Si este guardado era el final del cambio de representante, el familiar
         que quedó pendiente se vuelve a abrir con sus datos ya escritos: quitar
         el rol al anterior es lo único que faltaba. */
      if (pendienteFamiliar) {
        var pendiente = pendienteFamiliar;
        pendienteFamiliar = null;
        await esperar(250);
        if (pendiente.estado) estadoFamiliar = pendiente.estado;
        await llenarSubModalDesdeFamiliar(pendiente.datos);
        SIGDEUI.mostrarModal(modalFamiliarEl);
      }

  }

  if (btnGuardarFamiliar && modalFamiliarEl) {
    btnGuardarFamiliar.addEventListener('click', guardarFamiliarEnLaLista);
  }

  /** ¿La lista tiene ya un familiar marcado como representante legal?
      Solo cuenta si está ACTIVO: un representante desactivado no puede
      autorizar nada, así que para el wizard es como si no hubiera ninguno. */
  function hayRepresentanteLegal() {
    return familiaresAgregados.some(function (f) {
      return f.representante_principal && f.estado_relacion !== 'INACTIVO';
    });
  }


  /** Escribe en el sub-modal los datos de una fila de la lista. */
  function llenarSubModalDesdeFamiliar(f) {
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

    var sexo = document.querySelector('input[name="familiar_sexo"][value="' + (f.sexo || 'M') + '"]');
    if (sexo) sexo.checked = true;

    // El select del parentesco guarda el NOMBRE del catálogo, que es lo que
    // lleva la fila en `parentesco`.
    if (f.parentesco) poner('familiarParentesco', f.parentesco);

    poner('familiarNivelInstruccion', f.nivel_instruccion || '');
    poner('familiarOcupacion', f.ocupacion || '');

    marcar('familiarRepresentantePrincipal', f.representante_principal);
    marcar('familiarSoloAutorizado', f.solo_autorizado);
    marcar('familiarAutorizadoRetiros', f.autorizado_retiros);
    marcar('familiarViveConEstudiante', f.vive_con_estudiante);

    var c = f.contacto || {};
    poner('familiarCodigoTelefonoPrincipal', c.codigo || '');
    poner('familiarTelefonoPrincipal', c.numero || '');
    poner('familiarCodigoTelefonoAlternativo', c.codigo_alt || '');
    poner('familiarTelefonoAlternativo', c.numero_alt || '');
    poner('familiarCorreo', c.correo || '');

    var d = f.direccion || {};
    poner('familiarDireccionSector', d.sector || '');
    poner('familiarDireccionCalle', d.calle || '');
    poner('familiarDireccionCasa', d.casa || '');
    poner('familiarDireccionReferencia', d.referencia || '');

    /* La cascada se llena con la misma función que usa el prefill por cédula,
       para que editar y recargar se comporten igual. Es asíncrona porque
       consulta el catálogo, así que se espera antes de abrir el modal: si se
       abriera antes, la secretaria vería la cascada vacía un instante. */
    var promesa = d.parroquia && cascadaFamiliar
      ? cascadaFamiliar.elegirParroquia(d.estado, d.municipio, d.parroquia)
      : Promise.resolve(false);

    return promesa.then(function () {
      if (bloqueRepresentanteUI) bloqueRepresentanteUI.sincronizar();
    });
  }

  /**
   * Abre el sub-modal en modo edición sobre una fila.
   *
   * `quitarRol` es para el cambio de representante legal: cuando alguien intenta
   * guardar un segundo representante, el anterior se abre para editarse con el
   * interruptor YA APAGADO. Es lo que hace que el aviso que dice "quítele esa
   * opción" sea cierto en vez de una instrucción que hay que cumplir a mano: la
   * secretaria solo tiene que guardar.
   */
  function abrirFamiliarParaEditar(indice, quitarRol) {
    var f = familiaresAgregados[indice];
    if (!f) return;

    vaciarSubModalFamiliar(false);
    editandoFamiliarIndice = indice;
    estadoFamiliar = f.estado_relacion || 'ACTIVO';

    if (quitarRol) {
      var sw = document.getElementById('familiarRepresentantePrincipal');
      if (sw) sw.checked = false;
      f.representante_principal = 0;
    }

    var titulo = document.getElementById('modalAgregarFamiliarLabel');
    if (titulo) titulo.textContent = 'Editar familiar';
    var boton = document.getElementById('btnGuardarFamiliar');
    if (boton) boton.innerHTML = '<i class="bi bi-check-lg me-1" aria-hidden="true"></i> Guardar cambios';

    llenarSubModalDesdeFamiliar(f).then(function () {
      SIGDEUI.mostrarModal(modalFamiliarEl);
    });
  }

  function renderFamiliares() {
    var tbodyFam = document.getElementById('tablaFamiliaresBody');
    if (!tbodyFam) return;
    if (familiaresAgregados.length === 0) {
      tbodyFam.innerHTML = '<tr id="tablaFamiliaresVacio"><td colspan="4" class="text-center text-support py-4">Aún no has agregado ningún familiar.</td></tr>';
      return;
    }
    /* Los roles se derivan de lo que cada persona ES, no de una etiqueta fija.
       Antes el fallback era "Familiar" para cualquiera que no fuera la
       representante, y por eso alguien que solo venía a autorizar un retiro
       aparecía como familiar en la tabla. */
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
         registró", no "no vive". Ponerla en el caso contrario afirmaría algo que
         el sistema no sabe. */
      if (f.vive_con_estudiante) roles.push('Vive con el estudiante');
      return roles;
    }

    /* Desactivar, en vez de borrar. La fila guarda su estado en
       `persona_estudiante.estado_relacion`: INACTIVO sigue siendo familiar del
       menor, con su historial, pero deja de contar para el withdrawals y para
       los cálculos. Un familiar desactivado se ve tachado para que se note de
       un vistazo, y el representante no se puede desactivar: mientras sea el
       responsable legal del menor está activo por definición (si dejó de
       serlo, se le quita el rol y se desactiva en dos pasos, no en uno). */
    tbodyFam.innerHTML = familiaresAgregados.map(function (f, i) {
      var roles = rolesDe(f);
      var inactivo = f.estado_relacion === 'INACTIVO';
      return '<tr' + (inactivo ? ' class="table-row--inactive"' : '') + '>' +
        '<td>' + escHtml(f.nombre_referencia) + '</td>' +
        '<td>' + escHtml(f.parentesco_texto) + '</td>' +
        '<td>' + (roles.length ? roles.map(function (r) { return '<span class="status-badge status-badge--active">' + r + '</span>'; }).join(' ') : '—') + '</td>' +
        '<td class="text-center">' +
          '<div class="d-inline-flex align-items-center gap-3">' +
            '<label class="wizard-switch wizard-switch--sm" title="' +
              (inactivo ? 'Activar este familiar' : 'Desactivar este familiar') + '">' +
              '<input type="checkbox" data-estado-familiar="' + i + '"' +
                (inactivo ? '' : ' checked') +
                (f.representante_principal && !inactivo ? ' disabled' : '') + '>' +
              '<span class="wizard-switch__slider"></span>' +
            '</label>' +
            '<button type="button" class="action-btn action-btn--edit" data-editar-familiar="' + i + '" title="Editar familiar">' +
              '<i class="bi bi-pencil"></i></button>' +
          '</div>' +
        '</td>' +
      '</tr>';
    }).join('');

    tbodyFam.querySelectorAll('[data-editar-familiar]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        abrirFamiliarParaEditar(Number(btn.dataset.editarFamiliar));
      });
    });

    tbodyFam.querySelectorAll('[data-estado-familiar]').forEach(function (sw) {
      sw.addEventListener('change', function () {
        var f = familiaresAgregados[Number(sw.dataset.estadoFamiliar)];
        if (!f) return;
        f.estado_relacion = sw.checked ? 'ACTIVO' : 'INACTIVO';
        renderFamiliares();
      });
    });
  }

  /* ---------- Resumen (paso 7) ---------- */
  function llenarResumen() {
    var f   = new FormData(form);
    var setR = function (campo, valor) {
      var el = wizardModalEl.querySelector('[data-resumen="' + campo + '"]');
      if (el) el.textContent = valor || '—';
    };

    /* Personales */
    setR('nombre_completo',
      [f.get('primer_nombre'), f.get('segundo_nombre'), f.get('primer_apellido'), f.get('segundo_apellido')]
        .filter(Boolean).join(' ')
    );
    setR('documento', (f.get('tipo_documento') || '') + '-' + (f.get('numero_documento') || ''));
    setR('fecha_nacimiento', f.get('fecha_nacimiento'));
    setR('sexo', f.get('sexo') === 'M' ? 'Masculino' : (f.get('sexo') === 'F' ? 'Femenino' : '—'));

    /* Procedencia */
    var ingresoPV = document.getElementById('ingresoPrimeraVez');
    if (ingresoPV && ingresoPV.checked) {
      setR('tipo_ingreso', 'Primera vez');
      setR('institucion_anterior', '—');
    } else {
      setR('tipo_ingreso', 'Traslado');
      setR('institucion_anterior', textoSeleccionado('institucionProcedencia'));
    }

    /* Académico */
    setR('grado',       textoSeleccionado('gradoCursar'));
    setR('seccion',     textoSeleccionado('seccion'));
    setR('turno',       nombreSencilloDe('turno'));
    setR('anio_escolar', f.get('anio_escolar') || (document.getElementById('anioEscolarAcademico') ? document.getElementById('anioEscolarAcademico').value : '') || '—');

    /* Talla uniforme y ficha médica (paso 4) */
    var resumenTalla = new FormData(form);
    setR('talla_camisa',   resumenTalla.get('talla_camisa'));
    setR('talla_pantalon', resumenTalla.get('talla_pantalon'));
    setR('talla_zapato',   resumenTalla.get('talla_zapato'));
    setR('tipo_sangre',    resumenTalla.get('tipo_sangre'));
    setR('alergias',       resumenTalla.get('alergias'));
    setR('enfermedades',   resumenTalla.get('enfermedades'));

    /* Familiares */
    var resumenFam = document.getElementById('resumenFamiliares');
    if (resumenFam) {
      if (familiaresAgregados.length === 0) {
        resumenFam.innerHTML = '<p class="text-support mb-0">Sin familiares registrados.</p>';
      } else {
        resumenFam.innerHTML = '<div class="wizard-summary__grid">' +
          familiaresAgregados.map(function (f) {
            var detalle = escHtml(f.nombre_referencia);
            if (f.representante_principal) {
              var tel = f.contacto && f.contacto.numero ? f.contacto.numero : 'sin teléfono';
              detalle += '<br><span class="text-support">Representante legal · ' + escHtml(tel) + '</span>';
            } else if (f.solo_autorizado) {
              detalle += '<br><span class="text-support">No familiar · autorizado para retiros</span>';
            }
            if (f.vive_con_estudiante) {
              detalle += '<br><span class="text-support">Vive con el estudiante</span>';
            }
            if (f.estado_relacion === 'INACTIVO') {
              detalle += '<br><span class="text-support">Desactivado</span>';
            }
            return '<div class="wizard-summary__item">' +
              '<div class="wizard-summary__label">' + escHtml(f.parentesco_texto) + '</div>' +
              '<div class="wizard-summary__value">' + detalle + '</div>' +
            '</div>';
          }).join('') +
        '</div>';
      }
    }
  }

  /* ---------- Envío final ---------- */
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!validarPasoActual()) return;

    // El paso 6 es el único que registra al representante legal, y es
    // obligatorio: si falta, se devuelve al usuario ahí en vez de dejar que
    // el servidor rechace la inscripción.
    if (!hayRepresentanteLegal()) {
      SIGDEUI.mostrarAviso(
        'warning',
        'Falta el representante legal',
        'Debe marcar un familiar como representante legal.'
      );
      mostrarPaso(6);
      return;
    }

    var jsonInput = document.getElementById('familiaresJson');
    if (jsonInput) jsonInput.value = JSON.stringify(familiaresAgregados);

    var btnFinalizar = document.getElementById('btnWizardFinalizar');
    if (btnFinalizar) {
      btnFinalizar.disabled = true;
      btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    SIGDEUI.enviar(route + 'store', { metodo: 'POST', cuerpo: new FormData(form) })
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          SIGDEUI.mostrarError(result.data.mensaje || 'No se pudo registrar la inscripción.');
          return;
        }
        return SIGDEUI.exito(result.data.mensaje, 'Inscripción registrada')
          .then(function () { window.location.reload(); });
      })
      .catch(function () {
        SIGDEUI.mostrarError('No se pudo conectar con el servidor.');
      })
      .finally(function () {
        if (btnFinalizar) {
          btnFinalizar.disabled = false;
          btnFinalizar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Inscripción';
        }
      });
  });
});
