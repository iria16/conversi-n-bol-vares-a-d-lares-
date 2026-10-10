/* ==========================================================================
 SIGDE - main.js
 Comportamiento general reutilizable en todas las vistas.
 ==========================================================================*/

document.addEventListener('DOMContentLoaded', function () {
  initPasswordToggles();
  initFormValidation();
  initSubmitEnableOnValid();
  initPasswordRequirementsChecklist();
  initPasswordMatchValidation();
  initAutoFilters();
  SIGDEUI.mostrarFlash();
  initModalFocusFix();
});

function initAutoFilters() {
  document.querySelectorAll('form.data-panel__filters, form.auto-filters').forEach(function (form) {
    // La entrada en el buscador espera a que el usuario deje de escribir.
    var buscarConRetraso = SIGDEUI.esDebounced(350, function () {
      applyFilters(1, false);
    });
    var searchInput = form.querySelector('input[type="search"]');
    var activeAbortController = null;
    var panel = form.closest('.data-panel') || document.querySelector('.data-panel');

    function applyFilters(page, pushHistory, skipHistory) {
      var action = form.getAttribute('action');
      if (!action) return;

      var params = new URLSearchParams(new FormData(form));
      if (page) {
        params.set('page', page);
      } else {
        params.set('page', '1');
      }

      // Limpiar parámetros vacíos para que la URL sea limpia
      var cleanParams = new URLSearchParams();
      params.forEach(function (value, key) {
        if (value.trim() !== '') {
          if (key === 'page' && value === '1') return;
          cleanParams.set(key, value.trim());
        }
      });

      var baseUrl = action.split('?')[0];
      var query = cleanParams.toString();
      var targetUrl = baseUrl + (query ? '?' + query : '');

      if (activeAbortController) {
        activeAbortController.abort();
      }
      activeAbortController = new AbortController();

      if (panel) {
        panel.classList.add('data-panel--loading');
      }

      SIGDEUI.enviar(targetUrl, {
        signal: activeAbortController.signal,
        texto: true
      })
      .then(function (r) {
        if (!r.ok) throw new Error('Error al cargar datos');
        return r.texto;
      })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');

        if (panel) {
          // Ubicamos el MISMO form de filtros en el HTML devuelto y subimos
          // desde ahí con closest(), en vez de tomar el primer '.data-panel'
          // del documento: en vistas con más de un '.data-panel' (ej.
          // catalogo/index.php, que también tiene el panel del selector de
          // tipo de catálogo arriba), querySelector('.data-panel') podía
          // devolver el panel equivocado y el reemplazo de body/footer
          // fallaba en silencio.
          var newForm = doc.querySelector('form.data-panel__filters, form.auto-filters');
          var newPanel = newForm ? newForm.closest('.data-panel') : doc.querySelector('.data-panel');

          if (newPanel) {
            var currentBody = panel.querySelector('.data-panel__body');
            var newBody = newPanel.querySelector('.data-panel__body');
            if (currentBody && newBody) {
              currentBody.innerHTML = newBody.innerHTML;
            }

            var currentFooter = panel.querySelector('.data-panel__footer');
            var newFooter = newPanel.querySelector('.data-panel__footer');
            if (currentFooter && newFooter) {
              currentFooter.innerHTML = newFooter.innerHTML;
            } else if (!currentFooter && newFooter) {
              panel.appendChild(newFooter.cloneNode(true));
            } else if (currentFooter && !newFooter) {
              currentFooter.remove();
            }
          }
        } else {
          var currentTable = document.querySelector('table');
          var newTable = doc.querySelector('table');
          if (currentTable && newTable) {
            var currentTbody = currentTable.querySelector('tbody');
            var newTbody = newTable.querySelector('tbody');
            if (currentTbody && newTbody) {
              currentTbody.innerHTML = newTbody.innerHTML;
            }
          }
        }

        if (!skipHistory) {
          if (pushHistory) {
            window.history.pushState({ path: targetUrl }, '', targetUrl);
          } else {
            window.history.replaceState({ path: targetUrl }, '', targetUrl);
          }
        }

        document.dispatchEvent(new CustomEvent('data-panel:updated', {
          detail: { form: form, url: targetUrl, panel: panel }
        }));
      })
      .catch(function (err) {
        if (err.name === 'AbortError') return;
        console.error('Error al aplicar filtros:', err);
      })
      .finally(function () {
        if (panel) {
          panel.classList.remove('data-panel--loading');
        }
      });
    }

    // Expone applyFilters para que otros scripts (p. ej. recuperacion.js
    // tras aprobar/rechazar) puedan refrescar el panel sin recargar la
    // página y sin perder los filtros/página actuales. skipHistory=true
    // porque la URL ya refleja el filtro/página vigente; no hace falta
    // volver a escribirla en el historial.
    form.refreshDataPanel = function () {
      var currentUrl = new URL(window.location.href);
      var page = currentUrl.searchParams.get('page') || 1;
      applyFilters(page, false, true);
    };

    // Evitar recarga completa de página al presionar Enter en el formulario
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      applyFilters(1, true);
    });

    // Filtros de selección inmediata
    form.querySelectorAll('select').forEach(function (select) {
      /* Un filtro marcado con data-carga-completa recarga la página entera por su cuenta, y aquí se le
 quita la escucha a propósito: el refresco por AJAX solo reemplaza el cuerpo y el pie del
 panel, así que lo que está por fuera se quedaría con el valor anterior.*/
      if (select.hasAttribute('data-carga-completa')) return;

      select.addEventListener('change', function () {
        applyFilters(1, true);
      });
    });

    // Filtros de fechas inmediatas
    form.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.addEventListener('change', function () {
        applyFilters(1, true);
      });
    });

    // Búsqueda con debounce sin perder foco ni recargar
    if (searchInput) {
      searchInput.addEventListener('input', buscarConRetraso);
    }

    // Interceptar clics de paginación dentro del panel para que tampoco recarguen la página
    if (panel) {
      panel.addEventListener('click', function (e) {
        var pageLink = e.target.closest('.pagination .page-link, .data-panel__footer .page-link');
        if (!pageLink) return;

        var href = pageLink.getAttribute('href');
        if (!href || href === '#' || pageLink.closest('.disabled') || pageLink.closest('.active')) {
          e.preventDefault();
          return;
        }

        e.preventDefault();
        var pageMatch = href.match(/[?&]page=(\d+)/);
        var page = pageMatch ? pageMatch[1] : (pageLink.dataset.page || pageLink.textContent.trim());
        if (page && !isNaN(page)) {
          applyFilters(page, true);
        }
      });
    }

    // Sincronizar formulario y tabla si el usuario usa Atrás / Adelante en el navegador
    window.addEventListener('popstate', function () {
      var currentUrl = new URL(window.location.href);
      form.querySelectorAll('input, select').forEach(function (field) {
        if (!field.name) return;
        var val = currentUrl.searchParams.get(field.name) || '';
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = (val === field.value);
        } else {
          field.value = val;
        }
      });
      var page = currentUrl.searchParams.get('page') || 1;
      applyFilters(page, false, true);
    });
  });
}

  /* Muestra/oculta el valor de un input de contraseña, por el marcado data-password-field dentro
 de un .input-icon-group y el botón data-password-toggle. El input se busca por ese atributo y
 no por id, porque el mismo componente se repite en varias vistas.*/
function initPasswordToggles() {
  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group = btn.closest('.input-icon-group');
      var input = group ? group.querySelector('[data-password-field]') : null;
      var icon = btn.querySelector('i');
      if (!input) return;

      var isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');

      if (icon) {
        icon.classList.toggle('bi-eye', !isHidden);
        icon.classList.toggle('bi-eye-slash', isHidden);
      }
    });
  });
}

/**
 * Activa la validación nativa HTML5 + estilos de Bootstrap para cualquier
 * formulario marcado con la clase "needs-validation".
 * El navegador valida required / minlength / type / pattern de forma
 * nativa; esto solo se encarga de mostrar el feedback visual de Bootstrap
 * y de bloquear el envío mientras el formulario no sea válido.
*/
function initFormValidation() {
  document.querySelectorAll('.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });
}

/**
 * Mantiene deshabilitado el botón de submit de un formulario mientras
 * este no sea válido (campos requeridos vacíos, minlength no alcanzado,
 * etc.), y lo habilita en cuanto el usuario completa todo correctamente.
 * Se reevalúa en cada "input" para dar respuesta inmediata mientras se
 * escribe, sin esperar al submit.
*/
function initSubmitEnableOnValid() {
  document.querySelectorAll('.needs-validation').forEach(function (form) {
    var submitBtn = form.querySelector('[type="submit"]');
    if (!submitBtn) return;

    function toggleSubmitState() {
      submitBtn.disabled = !form.checkValidity();
    }

    toggleSubmitState();
    form.addEventListener('input', toggleSubmitState);
  });
}

  /* Actualiza en vivo el checklist de requisitos de una contraseña (mínimo de caracteres, letra,
 número) mientras se escribe. El checklist se marca con data-password-requirements y el input
 apunta a él con data-password-requirements-target; el minlength real lo pone el navegador.*/
function initPasswordRequirementsChecklist() {
  var input = document.querySelector('[data-password-requirements-target]');
  var list = document.querySelector('[data-password-requirements]');
  if (!input || !list) return;

  var minLength = parseInt(input.getAttribute('minlength'), 10) || 8;

  function setRequirementMet(name, met) {
    var item = list.querySelector('[data-requirement="' + name + '"]');
    if (item) item.classList.toggle('is-met', met);
  }

  function evaluate() {
    var value = input.value;
    setRequirementMet('length', value.length >= minLength);
    setRequirementMet('letter', /[a-zA-Z]/.test(value));
    setRequirementMet('number', /\d/.test(value));
  }

  input.addEventListener('input', evaluate);
}

/**
 * Valida que dos campos de contraseña coincidan, usando la Constraint
 * Validation API nativa (setCustomValidity) para que se integre solo
 * con initFormValidation()/initSubmitEnableOnValid() sin lógica extra.
 * Marcado esperado:
 * <input id="nuevaPassword" ...>
 * <input data-match-target="#nuevaPassword" ...>
*/
function initPasswordMatchValidation() {
  document.querySelectorAll('[data-match-target]').forEach(function (input) {
    var target = document.querySelector(input.getAttribute('data-match-target'));
    if (!target) return;

    function validateMatch() {
      var mismatch = input.value !== '' && input.value !== target.value;
      input.setCustomValidity(mismatch ? 'no-match' : '');
    }

    input.addEventListener('input', validateMatch);
    target.addEventListener('input', validateMatch);
  });
}

/**
 * Evita la advertencia de accesibilidad "Blocked aria-hidden on an
 * element because its descendant retained focus" que lanza Bootstrap
 * al cerrar un modal mientras el foco sigue en un elemento interno
 * (por ejemplo el botón .btn-close). Se le quita el foco al elemento
 * activo justo antes de que Bootstrap le ponga aria-hidden al modal.
*/
function initModalFocusFix() {
  document.addEventListener('hide.bs.modal', function (event) {
    if (document.activeElement && event.target.contains(document.activeElement)) {
      document.activeElement.blur();
    }
  });
}