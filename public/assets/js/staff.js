/* SIGDE - comportamiento de empleados */
document.addEventListener('DOMContentLoaded', function () {
  var route = (window.BASE_URL || '') + 'index.php/staff/';
  var form = document.getElementById('formNuevoEmpleado');

  /* Antepone BASE_URL a una ruta de foto guardada en BD (ej. /uploads/empleados/x.jpg). */
  function urlFoto(foto) {
    if (!foto) return '';
    return (window.BASE_URL || '').replace(/\/$/, '') + foto;
  }

  /* ══════════════════════════════════════════════════════════════════
     FILTROS Y BÚSQUEDA (tabla de empleados)
     ══════════════════════════════════════════════════════════════════ */
  var filtrosForm    = document.getElementById('filtrosEmpleados');
  var tbody          = document.getElementById('tablaEmpleadosBody');
  var paginacionInfo = document.getElementById('paginacionInfo');
  var paginacionNav  = document.querySelector('.data-panel__footer nav');

  if (filtrosForm && tbody) {
    var searchInput  = filtrosForm.querySelector('input[name="q"]');
    var cargoSelect  = filtrosForm.querySelector('select[name="cargo"]');
    var estadoSelect = filtrosForm.querySelector('select[name="estado"]');
    var debounceTimer = null;
    var paginaActual  = 1;

    /* Evita el submit clásico del form */
    filtrosForm.addEventListener('submit', function (e) { e.preventDefault(); });

    function buildParams(page) {
      var p = new URLSearchParams();
      var q = searchInput ? searchInput.value.trim() : '';
      if (q.length >= 2) p.set('q', q);
      if (cargoSelect && cargoSelect.value)  p.set('cargo',  cargoSelect.value);
      if (estadoSelect && estadoSelect.value) p.set('estado', estadoSelect.value);
      if (page > 1) p.set('page', page);
      return p;
    }

    function renderFila(emp) {
      var estadoClass = emp.estado === 'activo' ? 'active' : 'inactive';
      var mutedClass  = emp.estado === 'inactivo' ? ' class="is-muted"' : '';
      var avatarHtml  = emp.foto
        ? '<span class="avatar avatar--md avatar--primary"><img src="' + escHtml(urlFoto(emp.foto)) + '" alt="' + escHtml(emp.nombre) + '"></span>'
        : '<span class="avatar avatar--md avatar--primary">' + escHtml(emp.iniciales) + '</span>';
      return '<tr' + mutedClass + '>' +
        '<td><div class="avatar-group">' +
          avatarHtml +
          '<span class="avatar-group__name">' + escHtml(emp.nombre) + '</span>' +
        '</div></td>' +
        '<td class="text-support">' + escHtml(emp.cedula) + '</td>' +
        '<td class="text-support">' + escHtml(emp.cargo) + '</td>' +
        '<td><span class="status-badge status-badge--' + estadoClass + '">' + capitalize(emp.estado) + '</span></td>' +
        '<td class="text-center"><div class="data-panel__actions">' +
          '<button type="button" class="action-btn action-btn--view btn-ver-empleado" title="Ver detalle" data-id="' + emp.id + '"><i class="bi bi-eye"></i></button>' +
          '<button type="button" class="action-btn action-btn--edit btn-editar-empleado" title="Editar empleado" data-id="' + emp.id + '"><i class="bi bi-pencil"></i></button>' +
          '<div class="form-check form-switch table-switch mb-0">' +
            '<input class="form-check-input toggle-empleado" type="checkbox" role="switch" data-id="' + emp.id + '" ' + (emp.estado === 'activo' ? 'checked' : '') + ' aria-label="Cambiar estado de ' + escHtml(emp.nombre) + '">' +
          '</div>' +
        '</div></td>' +
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
          tbody.innerHTML = data.empleados.length
            ? data.empleados.map(renderFila).join('')
            : '<tr><td colspan="5" class="text-center text-support py-4">No se encontraron empleados registrados.</td></tr>';
          if (paginacionInfo) {
            paginacionInfo.textContent = 'Mostrando ' + data.paginacion.desde + ' a ' + data.paginacion.hasta + ' de ' + data.paginacion.total + ' empleados';
          }
          renderPaginacion(data.paginacion);
        })
        .catch(function () { /* mantiene la tabla actual en caso de error de red */ });
    }

    /* Delegación de clics en paginación */
    document.querySelector('.data-panel__footer').addEventListener('click', function (e) {
      var link = e.target.closest('[data-page]');
      if (!link) return;
      e.preventDefault();
      fetchLista(Number(link.dataset.page));
    });

    /* Búsqueda con debounce */
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var val = searchInput.value.trim();
        if (val.length === 0 || val.length >= 2) {
          debounceTimer = setTimeout(function () { fetchLista(1); }, 900);
        }
      });
    }

    /* Filtros instantáneos */
    if (cargoSelect)  cargoSelect.addEventListener('change',  function () { fetchLista(1); });
    if (estadoSelect) estadoSelect.addEventListener('change', function () { fetchLista(1); });
  }

  function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  /* ══════════════════════════════════════════════════════════════════
     WIZARD "Registrar Nuevo Empleado" (6 pasos)
     ══════════════════════════════════════════════════════════════════ */
  var wizardModalEl = document.getElementById('nuevoEmpleadoModal');

  if (form && wizardModalEl) {
    var pasoActual = 1;
    var totalPasos = 6;
    var titulosAgregados = []; // acumulador -> se envía como JSON en titulos_json
    var editandoDesdePaso = null; // cuando no es null, "Siguiente" regresa al resumen

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

    /* checkValidity() de HTML5 no es confiable para campos dentro de un
       panel con display:none: el navegador los considera "no renderizados"
       y los excluye de la validación de restricciones, reportando siempre
       válido sin importar su contenido -- esto hacía que validarTodosLosPasos()
       nunca detectara un campo requerido vacío en un paso distinto al activo
       (ej. el cargo del paso 4 al presionar "Finalizar" desde el paso 6).
       Por eso el estado "vacío" se verifica manualmente aquí, funcione o no
       el panel esté visible; checkValidity() solo se usa como validación
       extra de formato (email, fecha, etc.) cuando el panel sí está visible. */
    function campoEstaVacio(campo) {
      if (campo.type === 'radio') {
        var grupo = wizardModalEl.querySelectorAll('input[name="' + campo.name + '"]');
        return !Array.from(grupo).some(function (el) { return el.checked; });
      }
      if (campo.type === 'checkbox') return !campo.checked;
      return campo.value === null || String(campo.value).trim() === '';
    }

    function validarPaso(numero) {
      var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + numero + '"]');
      if (!panel) return true;
      var esPanelVisible = panel.classList.contains('is-active');
      var valido = true;
      panel.querySelectorAll('[required]').forEach(function (campo) {
        var invalido = campoEstaVacio(campo) || (esPanelVisible && !campo.checkValidity());
        if (invalido) valido = false;
        campo.classList.toggle('is-invalid', invalido);
      });
      return valido;
    }

    /* Hace scroll y foco al primer campo [required] inválido del paso dado,
       para que el usuario lo ubique de inmediato. */
    function enfocarPrimerCampoInvalido(numero) {
      var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + numero + '"]');
      if (!panel) return;
      var campo = panel.querySelector('[required].is-invalid');
      if (campo) {
        campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        campo.focus({ preventScroll: true });
      }
    }

    /* Inserta (o reutiliza) un banner de aviso al inicio del panel indicado,
       usando el mismo estilo info-alert--warning que ya existe en el
       resumen. Reemplaza el popup de SweetAlert para que el error quede
       visible junto a los campos, sin taparlos con un modal encima. */
    function mostrarErrorPaso(numero, mensaje) {
      var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + numero + '"]');
      if (!panel) return;
      var alerta = panel.querySelector('.wizard-inline-alert');
      if (!alerta) {
        alerta = document.createElement('div');
        alerta.className = 'info-alert info-alert--warning wizard-inline-alert mb-3';
        alerta.innerHTML =
          '<div class="info-alert__icon"><i class="bi bi-exclamation-circle"></i></div>' +
          '<div><div class="info-alert__title">Campos incompletos</div>' +
          '<p class="info-alert__text wizard-inline-alert__text mb-0"></p></div>';
        panel.insertBefore(alerta, panel.firstChild);
      }
      alerta.querySelector('.wizard-inline-alert__text').textContent = mensaje;
    }

    /* Quita el banner de aviso del paso indicado (si existe). */
    function limpiarErrorPaso(numero) {
      var panel = wizardModalEl.querySelector('.wizard-panel[data-step="' + numero + '"]');
      if (!panel) return;
      var alerta = panel.querySelector('.wizard-inline-alert');
      if (alerta) alerta.remove();
    }

    /* Limpia el estado inválido de un campo en cuanto el usuario lo corrige
       (input/change), y si ya no queda ningún campo inválido en su paso,
       retira también el banner de ese paso. Delegado sobre todo el modal
       para cubrir cualquier campo [required] de cualquier paso. */
    function limpiarValidacionCampo(campo) {
      if (!campo || !campo.hasAttribute || !campo.hasAttribute('required')) return;
      if (!campo.checkValidity()) return;
      campo.classList.remove('is-invalid');
      var panel = campo.closest('.wizard-panel');
      if (!panel) return;
      if (!panel.querySelector('[required].is-invalid')) {
        limpiarErrorPaso(Number(panel.dataset.step));
      }
    }
    wizardModalEl.addEventListener('input', function (e) { limpiarValidacionCampo(e.target); });
    wizardModalEl.addEventListener('change', function (e) { limpiarValidacionCampo(e.target); });

    /* Valida el paso donde el usuario está parado actualmente (uso normal
       de "Siguiente" / botones "Editar" del resumen). Muestra/oculta el
       banner inline según corresponda, en vez de un popup. */
    function validarPasoActual() {
      var valido = validarPaso(pasoActual);
      if (valido) {
        limpiarErrorPaso(pasoActual);
      } else {
        mostrarErrorPaso(pasoActual, 'Completa los campos obligatorios (*) antes de continuar.');
        enfocarPrimerCampoInvalido(pasoActual);
      }
      return valido;
    }

    /* Revalida los pasos 1 a totalPasos-1 (el último paso es solo el
       resumen y no tiene inputs). Se usa al presionar "Finalizar" en vez
       de validarPasoActual(), porque ese solo miraba el panel activo (el
       resumen) y dejaba pasar campos obligatorios vacíos que hubieran
       quedado incompletos en pasos anteriores -- por ejemplo si el
       usuario entró por un botón "Editar" del resumen, borró un campo
       requerido y cerró/regresó sin volver a pasar por "Siguiente" en
       ese paso. El paso 5 (Formación Académica) no tiene [required]
       propios en su panel, así que siempre pasa: es intencionalmente
       opcional.
       Si en algún momento se decide hacer obligatorio al menos un
       título, aquí es donde se agregaría: revisar
       `titulosAgregados.length === 0` y saltar al paso 5 con un aviso. */
    function validarTodosLosPasos() {
      for (var n = 1; n < totalPasos; n++) {
        if (!validarPaso(n)) {
          mostrarPaso(n);
          mostrarErrorPaso(n, 'Faltan campos obligatorios (*) en este paso. Complétalos antes de finalizar.');
          enfocarPrimerCampoInvalido(n);
          return false;
        }
      }
      return true;
    }

    document.getElementById('btnWizardSiguiente').addEventListener('click', function () {
      if (!validarPasoActual()) return;
      if (editandoDesdePaso !== null) {
        // Modo edición: volver directo al resumen
        editandoDesdePaso = null;
        mostrarPaso(totalPasos);
      } else if (pasoActual < totalPasos) {
        mostrarPaso(pasoActual + 1);
      }
    });

    document.getElementById('btnWizardAtras').addEventListener('click', function () {
      if (pasoActual > 1) mostrarPaso(pasoActual - 1);
    });

    /* Listener delegado para los botones "Editar" del resumen (paso 6).
       Usa delegación en wizardModalEl para funcionar con cualquier botón
       renderizado en el paso 6, sin necesidad de re-bindear. */
    wizardModalEl.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-editar-paso]');
      if (!btn) return;
      editandoDesdePaso = totalPasos;
      mostrarPaso(Number(btn.dataset.editarPaso));
    });

    wizardModalEl.addEventListener('hidden.bs.modal', function () {
      pasoActual = 1;
      titulosAgregados = [];
      editandoDesdePaso = null;
      renderTitulos();
      form.reset();
      form.classList.remove('was-validated');
      wizardModalEl.querySelectorAll('.wizard-inline-alert').forEach(function (el) { el.remove(); });
      wizardModalEl.querySelectorAll('[required].is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      mostrarPaso(1);
      var previewEl = document.getElementById('fotoPreview');
      if (previewEl) previewEl.innerHTML = '<i class="bi bi-person"></i>';
    });

    /* ---------- Foto de perfil ---------- */
    var fotoInput = document.getElementById('fotoInput');
    if (fotoInput) fotoInput.addEventListener('change', function (e) {
      var file = e.target.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (ev) {
        document.getElementById('fotoPreview').innerHTML = '<img src="' + ev.target.result + '" alt="Foto">';
      };
      reader.readAsDataURL(file);
    });

    /* ---------- Tarjetas seleccionables (Sexo) ---------- */
    wizardModalEl.querySelectorAll('[data-sexo-card]').forEach(function (card) {
      card.addEventListener('click', function () {
        wizardModalEl.querySelectorAll('[data-sexo-card]').forEach(function (c) { c.classList.remove('is-selected'); });
        card.classList.add('is-selected');
        card.querySelector('input[type="radio"]').checked = true;
      });
    });

    /* ---------- Cascada Estado -> Municipio -> Parroquia ---------- */
    var selectEstado = document.getElementById('direccionEstado');
    var selectMunicipio = document.getElementById('direccionMunicipio');
    var selectParroquia = document.getElementById('direccionParroquia');

    if (selectEstado && selectMunicipio && selectParroquia) {
      selectEstado.addEventListener('change', function () {
        selectMunicipio.innerHTML = '<option value="">Cargando...</option>';
        selectParroquia.innerHTML = '<option value="">Seleccione parroquia</option>';
        selectMunicipio.disabled = true;
        selectParroquia.disabled = true;
        if (!selectEstado.value) return;

        fetch(route + 'municipiosPorEstadoAjax?id_estado=' + selectEstado.value)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            selectMunicipio.innerHTML = '<option value="">Seleccione municipio</option>';
            (data.municipios || []).forEach(function (m) {
              var opt = document.createElement('option');
              opt.value = m.id_municipio;
              opt.textContent = m.nombre;
              selectMunicipio.appendChild(opt);
            });
            selectMunicipio.disabled = false;
          })
          .catch(function () { Swal.fire('Error', 'No se pudieron cargar los municipios.', 'error'); });
      });

      selectMunicipio.addEventListener('change', function () {
        selectParroquia.innerHTML = '<option value="">Cargando...</option>';
        selectParroquia.disabled = true;
        if (!selectMunicipio.value) return;

        fetch(route + 'parroquiasPorMunicipioAjax?id_municipio=' + selectMunicipio.value)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            selectParroquia.innerHTML = '<option value="">Seleccione parroquia</option>';
            (data.parroquias || []).forEach(function (p) {
              var opt = document.createElement('option');
              opt.value = p.id_parroquia;
              opt.textContent = p.nombre;
              selectParroquia.appendChild(opt);
            });
            selectParroquia.disabled = false;
          })
          .catch(function () { Swal.fire('Error', 'No se pudieron cargar las parroquias.', 'error'); });
      });
    }

    /* ---------- Títulos académicos (paso 5) ---------- */
    var btnAbrirAgregarTitulo = document.getElementById('btnAbrirAgregarTitulo');
    if (btnAbrirAgregarTitulo) btnAbrirAgregarTitulo.addEventListener('click', function (e) {
      e.stopPropagation();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarTitulo')).show();
    });
    function renderTitulos() {
      var tbody = document.getElementById('tablaTitulosBody');
      if (!tbody) return;
      if (titulosAgregados.length === 0) {
        tbody.innerHTML = '<tr id="tablaTitulosVacio"><td colspan="4" class="text-center text-support py-4">Aún no has agregado ningún título.</td></tr>';
        return;
      }
      tbody.innerHTML = titulosAgregados.map(function (t, i) {
        return '<tr>' +
          '<td>' + t.titulo_texto + '</td>' +
          '<td>' + t.institucion_texto + '</td>' +
          '<td>' + (t.fecha_obtencion || '—') + '</td>' +
          '<td class="text-center">' +
            '<button type="button" class="action-btn action-btn--danger" data-eliminar-titulo="' + i + '"><i class="bi bi-trash"></i></button>' +
          '</td>' +
        '</tr>';
      }).join('');

      tbody.querySelectorAll('[data-eliminar-titulo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          titulosAgregados.splice(Number(btn.dataset.eliminarTitulo), 1);
          renderTitulos();
        });
      });
    }

    var btnConfirmarTitulo = document.getElementById('btnConfirmarTitulo');
    if (btnConfirmarTitulo) btnConfirmarTitulo.addEventListener('click', function () {
      var idTitulo = document.getElementById('tituloSelect').value;
      var idGrado = document.getElementById('tituloGradoAcademico').value;
      var idInstitucion = document.getElementById('tituloInstitucion').value;
      var fecha = document.getElementById('tituloFechaObtencion').value;

      if (!idTitulo || !idGrado || !idInstitucion || !fecha) {
        Swal.fire('Campos incompletos', 'Completa grado académico, título, institución y fecha.', 'warning');
        return;
      }

      titulosAgregados.push({
        id_titulo: idTitulo,
        titulo_texto: document.getElementById('tituloSelect').selectedOptions[0].textContent,
        id_grado_academico: idGrado,
        id_institucion: idInstitucion,
        institucion_texto: document.getElementById('tituloInstitucion').selectedOptions[0].textContent,
        fecha_obtencion: fecha,
      });
      renderTitulos();

      document.getElementById('tituloSelect').value = '';
      document.getElementById('tituloGradoAcademico').value = '';
      document.getElementById('tituloInstitucion').value = '';
      document.getElementById('tituloFechaObtencion').value = '';

      bootstrap.Modal.getInstance(document.getElementById('modalAgregarTitulo')).hide();
    });

    /* ---------- Sub-modales anidados: abrir sin cerrar padres ---------- */
    // Los botones "+" usan IDs en lugar de data-bs-toggle
    // para evitar que Bootstrap propague el evento de cierre al modal padre.
    var btnAbrirNuevoTitulo = document.getElementById('btnAbrirNuevoTitulo');
    if (btnAbrirNuevoTitulo) btnAbrirNuevoTitulo.addEventListener('click', function (e) {
      e.stopPropagation();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevoTitulo')).show();
    });

    var btnAbrirNuevaInstitucion = document.getElementById('btnAbrirNuevaInstitucion');
    if (btnAbrirNuevaInstitucion) btnAbrirNuevaInstitucion.addEventListener('click', function (e) {
      e.stopPropagation();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevaInstitucion')).show();
    });

    var btnAbrirNuevoTipoInstitucion = document.getElementById('btnAbrirNuevoTipoInstitucion');
    if (btnAbrirNuevoTipoInstitucion) btnAbrirNuevoTipoInstitucion.addEventListener('click', function (e) {
      e.stopPropagation();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevoTipoInstitucion')).show();
    });

    /* ---------- Nuevo Título (catálogo) ---------- */
    var btnGuardarNuevoTitulo = document.getElementById('btnGuardarNuevoTitulo');
    if (btnGuardarNuevoTitulo) btnGuardarNuevoTitulo.addEventListener('click', function () {
      var nombre = document.getElementById('nuevoTituloNombre').value.trim();
      if (!nombre) { Swal.fire('Error', 'Escribe el nombre del título.', 'warning'); return; }

      fetch(route + 'storeTituloAjax', { method: 'POST', body: new URLSearchParams({ nombre: nombre }) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) { Swal.fire('Error', data.mensaje || 'No se pudo registrar el título.', 'error'); return; }
          var selectTitulo = document.getElementById('tituloSelect');
          // Verificar si ya existe en el select
          var optExistente = Array.from(selectTitulo.options).find(function (o) { return o.value == data.titulo.id_titulo; });
          if (!optExistente) {
            var opt = document.createElement('option');
            opt.value = data.titulo.id_titulo;
            opt.textContent = data.titulo.nombre;
            selectTitulo.appendChild(opt);
            opt.selected = true;
          } else {
            optExistente.selected = true;
          }
          document.getElementById('nuevoTituloNombre').value = '';
          bootstrap.Modal.getInstance(document.getElementById('modalNuevoTitulo')).hide();
          Swal.fire({ icon: 'success', title: 'Título listo', text: data.mensaje || 'Título agregado al catálogo correctamente.', timer: 1400, showConfirmButton: false });
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    });

    /* ---------- Nuevo Tipo de Institución (catálogo) ---------- */
    var btnGuardarNuevoTipoInstitucion = document.getElementById('btnGuardarNuevoTipoInstitucion');
    if (btnGuardarNuevoTipoInstitucion) btnGuardarNuevoTipoInstitucion.addEventListener('click', function () {
      var nombre = document.getElementById('nuevoTipoInstitucionNombre').value.trim();
      if (!nombre) { Swal.fire('Error', 'Escribe el nombre del tipo de institución.', 'warning'); return; }

      fetch(route + 'storeTipoInstitucionAjax', { method: 'POST', body: new URLSearchParams({ nombre: nombre }) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) { Swal.fire('Error', data.mensaje || 'No se pudo registrar el tipo de institución.', 'error'); return; }
          var selectTipo = document.getElementById('nuevaInstitucionTipo');
          var optExistente = Array.from(selectTipo.options).find(function (o) { return o.value == data.tipo_institucion.id_tipo_institucion; });
          if (!optExistente) {
            var opt = document.createElement('option');
            opt.value = data.tipo_institucion.id_tipo_institucion;
            opt.textContent = data.tipo_institucion.nombre;
            selectTipo.appendChild(opt);
            opt.selected = true;
          } else {
            optExistente.selected = true;
          }
          document.getElementById('nuevoTipoInstitucionNombre').value = '';
          bootstrap.Modal.getInstance(document.getElementById('modalNuevoTipoInstitucion')).hide();
          Swal.fire({ icon: 'success', title: 'Tipo registrado', text: data.mensaje || 'Tipo de institución guardado correctamente.', timer: 1400, showConfirmButton: false });
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    });

    /* ---------- Nueva Institución (catálogo) ---------- */
    var btnGuardarNuevaInstitucion = document.getElementById('btnGuardarNuevaInstitucion');
    if (btnGuardarNuevaInstitucion) btnGuardarNuevaInstitucion.addEventListener('click', function () {
      var nombre = document.getElementById('nuevaInstitucionNombre').value.trim();
      var idTipo = document.getElementById('nuevaInstitucionTipo').value;
      if (!nombre || !idTipo) { Swal.fire('Error', 'Completa nombre y tipo de institución.', 'warning'); return; }

      fetch(route + 'storeInstitucionAjax', { method: 'POST', body: new URLSearchParams({ nombre: nombre, id_tipo_institucion: idTipo }) })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.ok) { Swal.fire('Error', data.mensaje || 'No se pudo registrar la institución.', 'error'); return; }
          var selectInst = document.getElementById('tituloInstitucion');
          var optExistente = Array.from(selectInst.options).find(function (o) { return o.value == data.institucion.id_institucion; });
          if (!optExistente) {
            var opt = document.createElement('option');
            opt.value = data.institucion.id_institucion;
            opt.textContent = data.institucion.nombre;
            selectInst.appendChild(opt);
            opt.selected = true;
          } else {
            optExistente.selected = true;
          }
          document.getElementById('nuevaInstitucionNombre').value = '';
          document.getElementById('nuevaInstitucionTipo').value = '';
          bootstrap.Modal.getInstance(document.getElementById('modalNuevaInstitucion')).hide();
          Swal.fire({ icon: 'success', title: 'Institución registrada', text: data.mensaje || 'Institución guardada correctamente.', timer: 1400, showConfirmButton: false });
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    });

    /* ---------- Resumen (paso 6) ---------- */
    function textoSeleccionado(id) {
      var el = document.getElementById(id);
      return el && el.selectedOptions && el.selectedOptions[0] ? el.selectedOptions[0].textContent : '—';
    }

    function llenarResumen() {
      var f = new FormData(form);
      var setR = function (campo, valor) {
        var el = wizardModalEl.querySelector('[data-resumen="' + campo + '"]');
        if (el) el.textContent = valor || '—';
      };

      setR('nombre_completo', [f.get('primer_nombre'), f.get('segundo_nombre'), f.get('primer_apellido'), f.get('segundo_apellido')].filter(Boolean).join(' '));
      setR('documento', f.get('tipo_documento') + '-' + f.get('numero_documento'));
      setR('fecha_nacimiento', f.get('fecha_nacimiento'));
      setR('sexo', f.get('sexo') === 'M' ? 'Masculino' : (f.get('sexo') === 'F' ? 'Femenino' : '—'));

      setR('telefono_principal', f.get('codigo_telefono_principal') + '-' + f.get('numero_telefono_principal'));
      setR('correo', f.get('correo_electronico'));

      setR('parroquia', textoSeleccionado('direccionParroquia'));
      setR('direccion', [f.get('sector_urbanizacion'), f.get('calle_avenida'), f.get('nro_casa_apto')].filter(Boolean).join(', '));

      setR('cargo', textoSeleccionado('cargoAsignado'));
      setR('fecha_ingreso', f.get('fecha_ingreso'));

      var resumenTitulos = document.getElementById('resumenTitulos');
      if (resumenTitulos) {
        if (titulosAgregados.length === 0) {
          resumenTitulos.innerHTML = '<p class="text-support mb-0">Sin títulos registrados.</p>';
        } else {
          resumenTitulos.innerHTML = titulosAgregados.map(function (t) {
            return '' +
              '<div class="wizard-summary__titulo-item">' +
                '<div class="wizard-summary__item"><div class="wizard-summary__label">Contenido</div><div class="wizard-summary__value">' + t.titulo_texto + '</div></div>' +
                '<div class="wizard-summary__item"><div class="wizard-summary__label">Institución</div><div class="wizard-summary__value">' + t.institucion_texto + '</div></div>' +
                '<div class="wizard-summary__item"><div class="wizard-summary__label">Año de graduación</div><div class="wizard-summary__value">' + (t.fecha_obtencion ? t.fecha_obtencion.slice(0, 4) : '—') + '</div></div>' +
              '</div>';
          }).join('');
        }
      }
    }

    /* ---------- Envío final ---------- */
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!validarTodosLosPasos()) return;

      document.getElementById('titulosJson').value = JSON.stringify(titulosAgregados);

      var wizardInstancia = bootstrap.Modal.getOrCreateInstance(wizardModalEl);

      fetch(route + 'storeAjax', { method: 'POST', body: new FormData(form) })
        .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
        .then(function (result) {
          wizardInstancia.hide();
          if (!result.ok || !result.data.ok) {
            Swal.fire('Error', result.data.mensaje || 'No se pudo registrar el empleado.', 'error');
            return;
          }
          Swal.fire({ icon: 'success', title: 'Empleado registrado', text: result.data.mensaje, timer: 1600, showConfirmButton: false })
            .then(function () { window.location.reload(); });
        })
        .catch(function () {
          wizardInstancia.hide();
          Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        });
    });
  }

  /* ══════════════════════════════════════════════════════════════════
     Modal ver detalle de empleado
     ══════════════════════════════════════════════════════════════════ */
  var modalVerEl = document.getElementById('verEmpleadoModal');

  if (modalVerEl) {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-ver-empleado');
      if (!btn) return;

      fetch(route + 'getByIdAjax?id=' + encodeURIComponent(btn.dataset.id))
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.ok) {
            Swal.fire('Error', result.data.mensaje || 'No se pudo cargar el detalle.', 'error');
            return;
          }
          pintarDetalleEmpleado(result.data.datos);
          bootstrap.Modal.getOrCreateInstance(modalVerEl).show();
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    });
  }

  function pintarDetalleEmpleado(d) {
    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val || '—';
    };

    var nombreCompleto = [d.primer_nombre, d.segundo_nombre, d.primer_apellido, d.segundo_apellido]
      .filter(Boolean).join(' ');
    var iniciales = ((d.primer_nombre || '').charAt(0) + (d.primer_apellido || '').charAt(0)).toUpperCase();
    var cedula = (d.tipo_documento || '') + '-' + (d.numero_documento || '');
    var sexoMap = { 'F': 'Femenino', 'M': 'Masculino' };
    var telefono = d.codigo_telefono_principal && d.numero_telefono_principal
      ? d.codigo_telefono_principal + '-' + d.numero_telefono_principal
      : null;

    var avatarEl = document.getElementById('verEmpleado__avatar');
    if (avatarEl) {
      avatarEl.className = 'avatar avatar--xl avatar--primary';
      avatarEl.innerHTML = d.foto
        ? '<img src="' + urlFoto(d.foto) + '" alt="' + (nombreCompleto || 'Empleado') + '">'
        : iniciales;
    }
    set('verEmpleado__iniciales',    iniciales);
    set('verEmpleado__nombre',       nombreCompleto);
    set('verEmpleado__cargo',        d.cargo || '—');
    set('verEmpleado__nombreCompleto', nombreCompleto);
    set('verEmpleado__cedula',       cedula);
    set('verEmpleado__nacimiento',   formatFecha(d.fecha_nacimiento));
    set('verEmpleado__sexo',         sexoMap[d.sexo] || d.sexo);
    set('verEmpleado__nacionalidad', d.nacionalidad);
    set('verEmpleado__cargoDetalle', d.cargo || '—');
    set('verEmpleado__fechaIngreso', formatFecha(d.fecha_ingreso));
    set('verEmpleado__telefono',     telefono);
    set('verEmpleado__correo',       d.correo_electronico);

    var badge = document.getElementById('verEmpleado__estadoBadge');
    var esActivo = (d.estado || '').toUpperCase() === 'ACTIVO';
    if (badge) {
      badge.className = 'status-badge status-badge--' + (esActivo ? 'active' : 'inactive');
      badge.textContent = esActivo ? 'Activo' : 'Inactivo';
    }
    set('verEmpleado__estadoValor', esActivo ? 'Personal Activo' : 'Personal Inactivo');

    var formacion = '—';
    if (d.formaciones && d.formaciones.length > 0) {
      formacion = d.formaciones.map(function (f) {
        var texto = (f.titulo_nombre || 'Título') + ' (' + (f.institucion_nombre || 'Institución') + ')';
        if (f.fecha_obtencion) texto += ' · ' + formatFecha(f.fecha_obtencion);
        return texto;
      }).join(' | ');
    } else if (d.titulo_nombre || d.id_titulo) {
      formacion = (d.titulo_nombre || 'Título registrado');
      if (d.institucion_nombre) formacion += ' (' + d.institucion_nombre + ')';
      if (d.fecha_obtencion) formacion += ' · ' + formatFecha(d.fecha_obtencion);
    }
    set('verEmpleado__formacion', formacion);
  }

  function formatFecha(valor) {
    if (!valor) return null;
    var f = new Date(valor.replace(' ', 'T'));
    if (isNaN(f)) return valor;
    return f.toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  /* ══════════════════════════════════════════════════════════════════
     Modal editar empleado
     ══════════════════════════════════════════════════════════════════ */
  var modalEditarEl = document.getElementById('editarEmpleadoModal');
  var formEditar    = document.getElementById('formEditarEmpleado');

  if (modalEditarEl && formEditar) {

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.btn-editar-empleado');
      if (!btn) return;

      var id = btn.dataset.id;

      formEditar.classList.remove('was-validated');

      fetch(route + 'getByIdAjax?id=' + encodeURIComponent(id))
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.ok) {
            Swal.fire('Error', result.data.mensaje || 'No se pudo cargar los datos del empleado.', 'error');
            return;
          }
          rellenarFormEditar(result.data.datos);
          bootstrap.Modal.getOrCreateInstance(modalEditarEl).show();
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
    });

    /* ---------- Foto de perfil (modal editar) ---------- */
    var fotoInputEditar   = document.getElementById('editEmpleado__foto');
    var fotoPreviewEditar = document.getElementById('editEmpleado__fotoPreview');
    var fotoQuitarEditar  = document.getElementById('editEmpleado__quitarFoto');
    var fotoActualEditar  = document.getElementById('editEmpleado__fotoActual');
    var FOTO_ICONO_VACIO  = '<i class="bi bi-person"></i>';

    if (fotoInputEditar && fotoPreviewEditar && fotoQuitarEditar) {
      fotoInputEditar.addEventListener('change', function () {
        var file = fotoInputEditar.files && fotoInputEditar.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
          Swal.fire('Imagen muy pesada', 'La foto no debe superar 2 MB.', 'warning');
          fotoInputEditar.value = '';
          return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
          fotoPreviewEditar.innerHTML = '<img src="' + e.target.result + '" alt="Foto">';
          fotoQuitarEditar.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
      });

      fotoQuitarEditar.addEventListener('click', function () {
        var idEmpleado = document.getElementById('editEmpleado__id') && document.getElementById('editEmpleado__id').value;
        var fotoAnterior = fotoActualEditar ? fotoActualEditar.value : '';

        // Limpiar UI de inmediato
        fotoInputEditar.value = '';
        if (fotoActualEditar) fotoActualEditar.value = '';
        fotoPreviewEditar.innerHTML = FOTO_ICONO_VACIO;
        fotoQuitarEditar.classList.add('d-none');

        // Eliminar archivo del servidor si el empleado ya tenía foto guardada
        if (idEmpleado && fotoAnterior) {
          fetch(route + 'removePhotoAjax', {
            method: 'POST',
            body: new URLSearchParams({ id: idEmpleado })
          }).catch(function () { /* silencioso */ });
        }
      });
    }

    formEditar.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!formEditar.checkValidity()) { formEditar.classList.add('was-validated'); return; }

      var btn = document.getElementById('btnGuardarEdicionEmpleado');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...'; }

      fetch(route + 'updateAjax', { method: 'POST', body: new FormData(formEditar) })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.ok) {
            Swal.fire('Error', result.data.mensaje || 'No se pudo actualizar el empleado.', 'error');
            return;
          }
          bootstrap.Modal.getOrCreateInstance(modalEditarEl).hide();
          Swal.fire({ icon: 'success', title: 'Empleado actualizado', text: result.data.mensaje, timer: 1600, showConfirmButton: false })
            .then(function () { window.location.reload(); });
        })
        .catch(function () { Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); })
        .finally(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-floppy me-1"></i> Guardar cambios'; }
        });
    });

    modalEditarEl.addEventListener('hidden.bs.modal', function () {
      formEditar.classList.remove('was-validated');
      /* Resetea el preview de foto para que no se arrastre al abrir otro empleado */
      if (fotoPreviewEditar) fotoPreviewEditar.innerHTML = FOTO_ICONO_VACIO;
      if (fotoQuitarEditar)  fotoQuitarEditar.classList.add('d-none');
      if (fotoInputEditar)   fotoInputEditar.value = '';
    });
  }

  function rellenarFormEditar(d) {
    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.value = val || '';
    };

    var setSelect = function (id, val) {
      var el = document.getElementById(id);
      if (!el) return;
      var match = Array.from(el.options).find(function (o) { return o.value == val; });
      el.value = match ? val : '';
    };

    set('editEmpleado__id',             d.id_empleado);
    setSelect('editEmpleado__tipoDocumento', d.tipo_documento);
    set('editEmpleado__documento',       d.numero_documento);
    set('editEmpleado__nacimiento',      d.fecha_nacimiento);
    set('editEmpleado__primerNombre',    d.primer_nombre);
    set('editEmpleado__segundoNombre',   d.segundo_nombre);
    set('editEmpleado__primerApellido',  d.primer_apellido);
    set('editEmpleado__segundoApellido', d.segundo_apellido);
    setSelect('editEmpleado__sexo',      d.sexo);
    set('editEmpleado__nacionalidad',    d.nacionalidad || 'Venezolano');

    setSelect('editEmpleado__cargo',     d.id_cargo);
    set('editEmpleado__fechaIngreso',    d.fecha_ingreso);

    setSelect('editEmpleado__codigoPrincipal',  d.codigo_telefono_principal || '0412');
    set('editEmpleado__telefonoPrincipal',       d.numero_telefono_principal);
    set('editEmpleado__correo',                  d.correo_electronico);

    setSelect('editEmpleado__titulo',         d.id_titulo);
    setSelect('editEmpleado__gradoAcademico', d.id_grado_academico);
    setSelect('editEmpleado__institucion',    d.id_institucion);
    set('editEmpleado__fechaObtencion',       d.fecha_obtencion);

    /* Foto actual: preview + campo oculto para conservarla si no se cambia */
    var previewEl     = document.getElementById('editEmpleado__fotoPreview');
    var fotoActualEl  = document.getElementById('editEmpleado__fotoActual');
    var quitarBtn     = document.getElementById('editEmpleado__quitarFoto');
    if (previewEl) {
      previewEl.innerHTML = d.foto
        ? '<img src="' + urlFoto(d.foto) + '" alt="Foto">'
        : '<i class="bi bi-person"></i>';
    }
    if (fotoActualEl) fotoActualEl.value = d.foto || '';
    if (quitarBtn)    quitarBtn.classList.toggle('d-none', !d.foto);
  }

  /* ══════════════════════════════════════════════════════════════════
     Toggle estado empleado
     ══════════════════════════════════════════════════════════════════ */
  document.querySelectorAll('.toggle-empleado').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      var checked = checkbox.checked;
      Swal.fire({ icon: 'question', title: checked ? '¿Activar empleado?' : '¿Desactivar empleado?', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' }).then(function (result) {
        if (!result.isConfirmed) { checkbox.checked = !checked; return; }
        fetch(route + 'toggleStatusAjax', { method: 'POST', body: new URLSearchParams({ id: checkbox.dataset.id }) })
          .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
          .then(function (answer) { if (!answer.ok || !answer.data.ok) { checkbox.checked = !checked; Swal.fire('Error', answer.data.mensaje || 'No se pudo actualizar el estado.', 'error'); return; } window.location.reload(); })
          .catch(function () { checkbox.checked = !checked; Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error'); });
      });
    });
  });

  /* ══════════════════════════════════════════════════════════════════
     Modales anidados: control de scroll y z-index dinámico
     ══════════════════════════════════════════════════════════════════ */
  document.addEventListener('hidden.bs.modal', function () {
    if (document.querySelectorAll('.modal.show').length > 0) {
      document.body.classList.add('modal-open');
    }
  });

  document.addEventListener('show.bs.modal', function (event) {
    var modalesAbiertos = document.querySelectorAll('.modal.show').length;
    var zIndex = 1050 + (15 * (modalesAbiertos + 1));
    event.target.style.zIndex = zIndex;
    setTimeout(function () {
      var backdrops = document.querySelectorAll('.modal-backdrop');
      if (backdrops.length > 0) {
        var ultimoBackdrop = backdrops[backdrops.length - 1];
        ultimoBackdrop.style.zIndex = zIndex - 1;
      }
    }, 0);
  });
});