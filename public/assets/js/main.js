/* ==========================================================================
   SIGDE - main.js
   Comportamiento general reutilizable en todas las vistas.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initPasswordToggles();
  initFormValidation();
  initSubmitEnableOnValid();
  initPasswordRequirementsChecklist();
  initPasswordMatchValidation();
  initClipboardCopy();
  initAutoFilters();
  initSwalFromData();
});

function initAutoFilters() {
  document.querySelectorAll('form.data-panel__filters, form.auto-filters').forEach(function (form) {
    var searchTimer;
    var searchInput = form.querySelector('input[type="search"]');

    function applyFilters() {
      var action = form.getAttribute('action');
      if (!action) return;

      var params = new URLSearchParams(new FormData(form));
      window.location.href = action + (params.toString() ? '?' + params.toString() : '');
    }

    form.querySelectorAll('select').forEach(function (select) {
      select.addEventListener('change', function () {
        applyFilters();
      });
    });

    form.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.addEventListener('change', function () {
        applyFilters();
      });
    });

    if (searchInput) {
      if (searchInput.value) {
        searchInput.focus();
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
      }

      searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
          var value = searchInput.value.trim();
          if (value === '' || value.length >= 2) applyFilters();
        }, 900);
      });
    }
  });
}

function initClipboardCopy() {
  document.querySelectorAll('[data-clipboard-text]').forEach(function (button) {
    button.addEventListener('click', async function () {
      var text = button.getAttribute('data-clipboard-text') || '';
      if (!text) return;

      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          var input = document.createElement('textarea');
          input.value = text;
          input.setAttribute('readonly', '');
          input.style.position = 'fixed';
          input.style.opacity = '0';
          document.body.appendChild(input);
          input.select();
          document.execCommand('copy');
          input.remove();
        }

        button.classList.add('is-copied');
        button.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(function () {
          button.classList.remove('is-copied');
          button.innerHTML = '<i class="bi bi-clipboard"></i>';
        }, 2000);
      } catch (error) {
        alert('No se pudo copiar el contenido.');
      }
    });
  });
}

/**
 * Muestra/oculta el valor de un input de contraseña.
 * Marcado esperado:
 * <div class="input-icon-group">
 *   <i class="bi bi-lock"></i>
 *   <input type="password" class="form-control" data-password-field>
 *   <button type="button" class="input-icon-group__toggle" data-password-toggle
 *           aria-label="Mostrar contraseña">
 *     <i class="bi bi-eye"></i>
 *   </button>
 * </div>
 */
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

/**
 * Actualiza en vivo el checklist de requisitos de una contraseña
 * (mínimo de caracteres, letra, número) mientras el usuario escribe.
 * Marcado esperado:
 * <input data-password-requirements-target minlength="6" ...>
 * <ul data-password-requirements>
 *   <li data-requirement="length">...</li>
 *   <li data-requirement="letter">...</li>
 *   <li data-requirement="number">...</li>
 * </ul>
 */
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
 * Muestra una alerta SweetAlert2 con los datos que el backend dejó en el
 * DOM a través de un elemento oculto (ver partial de vista correspondiente).
 * Marcado esperado:
 * <div id="swal-data"
 *      data-icon="success|error|warning|info|question"
 *      data-title="..."
 *      data-text="...">
 * </div>
 */
function initSwalFromData() {
  var swalData = document.getElementById('swal-data');
  if (!swalData) return;

  Swal.fire({
    icon: swalData.dataset.icon,
    title: swalData.dataset.title,
    text: swalData.dataset.text,
    confirmButtonColor: '#0a1440'
  });
}