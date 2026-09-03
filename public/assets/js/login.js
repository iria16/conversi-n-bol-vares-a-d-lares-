/* ==========================================================================
   SIGDE - login.js
   Comportamiento específico del formulario de inicio de sesión.
   La habilitación del botón de envío y el feedback visual dependen de
   las validaciones HTML (required, etc.) vía .needs-validation en main.js.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initLoginSubmitLock();
});

/**
 * Bloquea el botón de submit en cuanto el formulario válido se envía para
 * evitar envíos múltiples por doble click. Si el formulario no cumple las
 * restricciones HTML, no se toca el botón (main.js ya impide el envío).
 */
function initLoginSubmitLock() {
  var form      = document.querySelector('.login-form');
  var submitBtn = form ? form.querySelector('[type="submit"]') : null;

  if (!form || !submitBtn) return;

  form.addEventListener('submit', function () {
    if (!form.checkValidity()) return;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Verificando...';
  });
}
