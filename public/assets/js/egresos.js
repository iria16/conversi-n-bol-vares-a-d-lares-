/* SIGDE - comportamiento de Egresos / Graduación */
document.addEventListener('DOMContentLoaded', function () {
  var checkAll  = document.getElementById('checkAllEgresos');
  var checks    = document.querySelectorAll('.check-egreso:not(:disabled)');
  var btnMasivo = document.getElementById('btnGraduacionMasiva');

  if (!checkAll || !btnMasivo) return;

  function actualizarBotonMasivo() {
    var seleccionados = document.querySelectorAll('.check-egreso:checked').length;
    btnMasivo.disabled = seleccionados === 0;
  }

  checkAll.addEventListener('change', function () {
    checks.forEach(function (c) { c.checked = checkAll.checked; });
    actualizarBotonMasivo();
  });

  checks.forEach(function (c) {
    c.addEventListener('change', actualizarBotonMasivo);
  });

  btnMasivo.addEventListener('click', function () {
    var ids = Array.from(document.querySelectorAll('.check-egreso:checked')).map(function (c) {
      return c.value;
    });
    if (ids.length === 0) return;

    SIGDEUI.confirmar({
      icono: 'warning',
      titulo: '¿Registrar graduación masiva?',
      texto: 'Se registrará el egreso de ' + ids.length + ' estudiante(s). Esta acción no se puede deshacer.',
      confirmar: 'Sí, graduar'
    }).then(function (confirmado) {
      if (!confirmado) return;

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = (window.BASE_URL || '') + 'egresos/graduarMasivo';

      ids.forEach(function (id) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
      });

      document.body.appendChild(form);
      form.submit();
    });
  });
});
