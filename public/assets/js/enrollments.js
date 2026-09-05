/* SIGDE - comportamiento de inscripciones */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'index.php/inscripciones/';

  /* ══════════════════════════════════════════════════════════════════
     FILTROS Y BÚSQUEDA (tabla de inscripciones)
     ══════════════════════════════════════════════════════════════════ */
  var filtrosForm    = document.getElementById('filtrosInscripciones');
  var tbody          = document.getElementById('tablaInscripcionesBody');
  var paginacionInfo = document.getElementById('paginacionInfo');
  var paginacionNav  = document.querySelector('.data-panel__footer nav');

  if (filtrosForm && tbody) {
    var searchInput  = filtrosForm.querySelector('input[name="q"]');
    var anioSelect   = filtrosForm.querySelector('select[name="anio"]');
    var gradoSelect  = filtrosForm.querySelector('select[name="grado"]');
    var seccionSelect = filtrosForm.querySelector('select[name="seccion"]');
    var debounceTimer = null;
    var paginaActual  = 1;

    filtrosForm.addEventListener('submit', function (e) { e.preventDefault(); });

    function buildParams(page) {
      var p = new URLSearchParams();
      var q = searchInput ? searchInput.value.trim() : '';
      if (q.length >= 2)              p.set('q',      q);
      if (anioSelect   && anioSelect.value)    p.set('anio',    anioSelect.value);
      if (gradoSelect  && gradoSelect.value)   p.set('grado',   gradoSelect.value);
      if (seccionSelect && seccionSelect.value) p.set('seccion', seccionSelect.value);
      if (page > 1) p.set('page', page);
      return p;
    }

    function renderFila(ins) {
      var esCompleta = ins.estado === 'completa';
      var estadoClass = esCompleta ? 'active' : 'pending';
      var estadoLabel = esCompleta ? 'Completa' : 'Incompleta';
      var accionBtn = esCompleta
        ? '<a href="' + escHtml((window.BASE_URL || '') + 'index.php/inscripciones/ver/' + ins.id) + '" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i> Ver</a>'
        : '<a href="' + escHtml((window.BASE_URL || '') + 'index.php/inscripciones/continuar/' + ins.id) + '" class="btn btn-primary btn-sm">Continuar <i class="bi bi-arrow-right"></i></a>';

      return '<tr>' +
        '<td><div class="avatar-group">' +
          '<span class="avatar avatar--md avatar--' + escHtml(ins.avatar_color) + '">' + escHtml(ins.iniciales) + '</span>' +
          '<div><div class="avatar-group__name">' + escHtml(ins.nombre) + '</div>' +
          '<div class="avatar-group__meta">' + escHtml(ins.cedula) + '</div></div>' +
        '</div></td>' +
        '<td class="text-support">' + escHtml(ins.grado) + '</td>' +
        '<td class="text-support">' + escHtml(ins.seccion) + '</td>' +
        '<td class="text-support">' + escHtml(ins.fecha_inscripcion) + '</td>' +
        '<td><span class="status-badge status-badge--' + estadoClass + '">' + estadoLabel + '</span></td>' +
        '<td class="text-center">' + accionBtn + '</td>' +
      '</tr>';
    }

    function renderPaginacion(pag) {
      if (!paginacionNav) return;
      var items = '';
      var prev = pag.pagina_actual <= 1;
      items += '<li class="page-item' + (prev ? ' disabled' : '') + '">' +
        '<a class="page-link" href="#" data-page="' + Math.max(1, pag.pagina_actual - 1) + '" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a></li>';
      for (var p = 1; p <= pag.total_paginas; p++) {
        if (p > 3 && p < pag.total_paginas && pag.pagina_actual <= 3) {
          if (p === 4) items += '<li class="page-item disabled"><span class="page-link">…</span></li>';
          continue;
        }
        items += '<li class="page-item' + (p === pag.pagina_actual ? ' active' : '') + '">' +
          '<a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>';
      }
      var next = pag.pagina_actual >= pag.total_paginas;
      items += '<li class="page-item' + (next ? ' disabled' : '') + '">' +
        '<a class="page-link" href="#" data-page="' + Math.min(pag.total_paginas, pag.pagina_actual + 1) + '" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></a></li>';
      paginacionNav.querySelector('ul').innerHTML = items;
    }

    function fetchLista(page) {
      paginaActual = page || 1;
      fetch(route + 'listAjax?' + buildParams(paginaActual).toString())
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) return;
          tbody.innerHTML = data.inscripciones.length
            ? data.inscripciones.map(renderFila).join('')
            : '<tr><td colspan="6" class="text-center text-support py-4">No se encontraron inscripciones con los filtros seleccionados.</td></tr>';
          if (paginacionInfo) {
            paginacionInfo.textContent = 'Mostrando ' + data.paginacion.desde + ' a ' + data.paginacion.hasta + ' de ' + data.paginacion.total + ' inscripciones';
          }
          renderPaginacion(data.paginacion);
        })
        .catch(function () { /* mantiene la tabla actual en caso de error de red */ });
    }

    var footer = document.querySelector('.data-panel__footer');
    if (footer) {
      footer.addEventListener('click', function (e) {
        var link = e.target.closest('[data-page]');
        if (!link) return;
        e.preventDefault();
        fetchLista(Number(link.dataset.page));
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var val = searchInput.value.trim();
        if (val.length === 0 || val.length >= 2) {
          debounceTimer = setTimeout(function () { fetchLista(1); }, 900);
        }
      });
    }

    if (anioSelect)    anioSelect.addEventListener('change',    function () { fetchLista(1); });
    if (gradoSelect)   gradoSelect.addEventListener('change',   function () { fetchLista(1); });
    if (seccionSelect) seccionSelect.addEventListener('change', function () { fetchLista(1); });
  }

  /* ══════════════════════════════════════════════════════════════════
     Helpers
     ══════════════════════════════════════════════════════════════════ */
  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function textoSeleccionado(id) {
    var el = document.getElementById(id);
    return el && el.selectedOptions && el.selectedOptions[0]
      ? el.selectedOptions[0].textContent.trim()
      : '—';
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
    var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + pasoActual + '"]');
    var valido = true;
    panel.querySelectorAll('[required]').forEach(function (campo) {
      if (!campo.checkValidity()) valido = false;
    });
    if (!valido) {
      panel.querySelectorAll('[required]').forEach(function (campo) {
        campo.classList.toggle('is-invalid', !campo.checkValidity());
      });
      Swal.fire('Campos incompletos', 'Completa los campos obligatorios (*) antes de continuar.', 'warning');
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
  wizardModalEl.addEventListener('hidden.bs.modal', function () {
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

  /* ---------- Foto de perfil ---------- */
  var fotoInput = document.getElementById('fotoInput');
  if (fotoInput) {
    fotoInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        document.getElementById('fotoPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Foto">';
      };
      reader.readAsDataURL(file);
    });
  }

  /* ---------- Foto del familiar ---------- */
  var fotoFamiliarInput = document.getElementById('fotoFamiliarInput');
  if (fotoFamiliarInput) {
    fotoFamiliarInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        document.getElementById('fotoFamiliarPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Foto">';
      };
      reader.readAsDataURL(file);
    });
  }

  /* ---------- Tarjetas seleccionables (Sexo) ---------- */
  wizardModalEl.querySelectorAll('[data-sexo-card]').forEach(function (card) {
    card.addEventListener('click', function () {
      wizardModalEl.querySelectorAll('[data-sexo-card]').forEach(function (c) {
        c.classList.remove('is-selected');
      });
      card.classList.add('is-selected');
      card.querySelector('input[type="radio"]').checked = true;
    });
  });

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

  /* ---------- Sub-modal de Familiares (paso 6) ---------- */
  var btnAbrirFamiliar = document.getElementById('btnAbrirAgregarFamiliar');
  var modalFamiliarEl  = document.getElementById('modalAgregarFamiliar');

  if (btnAbrirFamiliar && modalFamiliarEl) {
    btnAbrirFamiliar.addEventListener('click', function (e) {
      e.stopPropagation();
      /* Limpiar campos del sub-modal antes de abrir */
      modalFamiliarEl.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(function (el) {
        el.value = '';
      });
      modalFamiliarEl.querySelectorAll('select').forEach(function (el) {
        el.selectedIndex = 0;
      });
      modalFamiliarEl.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (el) {
        el.checked = false;
      });
      modalFamiliarEl.querySelectorAll('[data-familiar-sexo-card]').forEach(function (c) {
        c.classList.remove('is-selected');
      });
      var prevFamiliar = document.getElementById('fotoFamiliarPreview');
      if (prevFamiliar) prevFamiliar.innerHTML = '<i class="bi bi-person"></i>';
      bootstrap.Modal.getOrCreateInstance(modalFamiliarEl).show();
    });

    /* Tarjetas seleccionables de sexo dentro del sub-modal */
    modalFamiliarEl.querySelectorAll('[data-familiar-sexo-card]').forEach(function (card) {
      card.addEventListener('click', function () {
        modalFamiliarEl.querySelectorAll('[data-familiar-sexo-card]').forEach(function (c) {
          c.classList.remove('is-selected');
        });
        card.classList.add('is-selected');
        card.querySelector('input[type="radio"]').checked = true;
      });
    });
  }

  var btnGuardarFamiliar = document.getElementById('btnGuardarFamiliar');
  if (btnGuardarFamiliar && modalFamiliarEl) {
    btnGuardarFamiliar.addEventListener('click', function () {
      var parentescoEl = document.getElementById('familiarParentesco');
      var parentesco   = parentescoEl ? parentescoEl.value : '';

      if (!parentesco) {
        Swal.fire('Campo requerido', 'Selecciona el parentesco antes de guardar.', 'warning');
        return;
      }

      var primerNombre   = (document.getElementById('familiarPrimerNombre')    || {}).value || '';
      var primerApellido = (document.getElementById('familiarPrimerApellido')   || {}).value || '';
      var segundoNombre  = (document.getElementById('familiarSegundoNombre')    || {}).value || '';
      var segundoApellido= (document.getElementById('familiarSegundoApellido')  || {}).value || '';
      var esRepresentante = (document.getElementById('familiarRepresentantePrincipal') || {}).checked || false;
      var esAutorizado    = (document.getElementById('familiarAutorizadoRetiros')      || {}).checked || false;

      var nombre = [primerNombre, segundoNombre, primerApellido, segundoApellido].filter(Boolean).join(' ') || 'Sin nombre';

      familiaresAgregados.push({
        parentesco:              parentesco,
        parentesco_texto:        parentescoEl.selectedOptions[0].textContent.trim(),
        representante_principal: esRepresentante,
        autorizado_retiros:      esAutorizado,
        nombre_referencia:       nombre,
      });

      renderFamiliares();
      bootstrap.Modal.getInstance(modalFamiliarEl).hide();
    });
  }

  function renderFamiliares() {
    var tbodyFam = document.getElementById('tablaFamiliaresBody');
    if (!tbodyFam) return;
    if (familiaresAgregados.length === 0) {
      tbodyFam.innerHTML = '<tr id="tablaFamiliaresVacio"><td colspan="4" class="text-center text-support py-4">Aún no has agregado ningún familiar.</td></tr>';
      return;
    }
    tbodyFam.innerHTML = familiaresAgregados.map(function (f, i) {
      var roles = [];
      if (f.representante_principal) roles.push('Representante');
      if (f.autorizado_retiros)      roles.push('Autorizado');
      return '<tr>' +
        '<td>' + escHtml(f.nombre_referencia) + '</td>' +
        '<td>' + escHtml(f.parentesco_texto) + '</td>' +
        '<td>' + (roles.length ? roles.map(function (r) { return '<span class="status-badge status-badge--active">' + r + '</span>'; }).join(' ') : '—') + '</td>' +
        '<td class="text-center">' +
          '<button type="button" class="action-btn action-btn--danger" data-eliminar-familiar="' + i + '"><i class="bi bi-trash"></i></button>' +
        '</td>' +
      '</tr>';
    }).join('');

    tbodyFam.querySelectorAll('[data-eliminar-familiar]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        familiaresAgregados.splice(Number(btn.dataset.eliminarFamiliar), 1);
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
    setR('turno',       textoSeleccionado('turno'));
    setR('anio_escolar', f.get('anio_escolar') || document.getElementById('anioEscolarAcademico').value || '—');

    /* Familiares */
    var resumenFam = document.getElementById('resumenFamiliares');
    if (resumenFam) {
      if (familiaresAgregados.length === 0) {
        resumenFam.innerHTML = '<p class="text-support mb-0">Sin familiares registrados.</p>';
      } else {
        resumenFam.innerHTML = '<div class="wizard-summary__grid">' +
          familiaresAgregados.map(function (f) {
            return '<div class="wizard-summary__item">' +
              '<div class="wizard-summary__label">' + escHtml(f.parentesco_texto) + '</div>' +
              '<div class="wizard-summary__value">' + escHtml(f.nombre_referencia) + '</div>' +
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

    var jsonInput = document.getElementById('familiaresJson');
    if (jsonInput) jsonInput.value = JSON.stringify(familiaresAgregados);

    var btnFinalizar = document.getElementById('btnWizardFinalizar');
    if (btnFinalizar) {
      btnFinalizar.disabled = true;
      btnFinalizar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
    }

    fetch(route + 'storeAjax', { method: 'POST', body: new FormData(form) })
      .then(function (response) {
        return response.json().then(function (data) { return { ok: response.ok, data: data }; });
      })
      .then(function (result) {
        if (!result.ok || !result.data.ok) {
          Swal.fire('Error', result.data.mensaje || 'No se pudo registrar la inscripción.', 'error');
          return;
        }
        Swal.fire({
          icon: 'success',
          title: 'Inscripción registrada',
          text: result.data.mensaje,
          timer: 1600,
          showConfirmButton: false,
        }).then(function () { window.location.reload(); });
      })
      .catch(function () {
        Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
      })
      .finally(function () {
        if (btnFinalizar) {
          btnFinalizar.disabled = false;
          btnFinalizar.innerHTML = '<i class="bi bi-check-lg"></i> Confirmar Inscripción';
        }
      });
  });
});
