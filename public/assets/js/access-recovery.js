/* SIGDE - comportamiento de Recuperacion de Acceso */

document.addEventListener('DOMContentLoaded', function () {
  var baseUrl = window.BASE_URL || '';
  var routeUrl = baseUrl + 'index.php/';

  function enviarAccion(id, endpoint) {
    return fetch(routeUrl + 'accessRecovery/' + endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(id)
    }).then(function (response) {
      return response.json();
    });
  }

  function mostrarCredenciales(data, nombreUsuario) {
    var usuarioTexto = document.getElementById('credencialUsuarioSpan');
    var passwordTexto = document.getElementById('passwordProvisionalValue');
    var botonCopiar = document.getElementById('btnCopiarPassword');
    var modal = document.getElementById('credencialesGeneradasModal');

    if (usuarioTexto) usuarioTexto.textContent = nombreUsuario || 'Usuario';
    if (passwordTexto) passwordTexto.textContent = data.password_temporal;
    if (botonCopiar) botonCopiar.setAttribute('data-clipboard-text', data.password_temporal);
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  document.querySelectorAll('.aprobar-solicitud').forEach(function (button) {
    button.addEventListener('click', function () {
      var id = button.dataset.solicitudId;
      button.disabled = true;

      enviarAccion(id, 'aprobarAjax')
        .then(function (data) {
          if (!data.ok) {
            alert(data.mensaje || 'No se pudo aprobar la solicitud.');
            return;
          }
          mostrarCredenciales(data, button.dataset.usuarioNombre);
        })
        .catch(function () {
          alert('Error de conexión al aprobar la solicitud.');
        })
        .finally(function () {
          button.disabled = false;
        });
    });
  });

  document.querySelectorAll('.rechazar-solicitud').forEach(function (button) {
    button.addEventListener('click', function () {
      enviarAccion(button.dataset.solicitudId, 'rechazarAjax')
        .then(function (data) {
          if (!data.ok) {
            alert(data.mensaje || 'No se pudo rechazar la solicitud.');
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          alert('Error de conexión al rechazar la solicitud.');
        });
    });
  });

  document.querySelectorAll('.ver-solicitud').forEach(function (button) {
    button.addEventListener('click', function () {
      var modal = document.getElementById('recoveryDetailModal');
      var id = button.dataset.solicitudId;

      fetch(routeUrl + 'accessRecovery/getDetalleAjax?id=' + encodeURIComponent(id))
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data.ok) {
            alert(data.mensaje || 'No se pudo cargar el detalle.');
            return;
          }

          var solicitud = data.datos;
          var setText = function (selector, value) {
            var element = document.querySelector(selector);
            if (element) element.textContent = value || '—';
          };

          setText('#recoveryDetail__name', solicitud.nombre);
          setText('#recoveryDetail__initials', solicitud.iniciales);
          setText('#recoveryDetail__username', solicitud.usuario);
          setText('#recoveryDetail__fullName', solicitud.nombre);
          setText('#recoveryDetail__cargo', solicitud.cargo);
          setText('#recoveryDetail__requestedAt', solicitud.fecha + ' · ' + solicitud.hora);
          setText('#recoveryDetail__resolvedAt', solicitud.fecha_resolucion || 'Pendiente');

          var estado = (solicitud.estado || 'pendiente').toLowerCase();
          var estadoBadge = document.getElementById('recoveryDetail__status');
          if (estadoBadge) {
            var claseEstado = estado === 'aprobada' ? 'approved' :
              (estado === 'rechazada' ? 'rejected' : 'pending');
            estadoBadge.className = 'status-badge status-badge--' + claseEstado;
            estadoBadge.textContent = estado.charAt(0).toUpperCase() + estado.slice(1);
          }

          if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        })
        .catch(function () {
          alert('Error de conexión al cargar el detalle.');
        });
    });
  });

  var form = document.getElementById('formRestablecerClave');
  var modalInicial = document.getElementById('restablecerClaveModal');
  var modalCredenciales = document.getElementById('credencialesGeneradasModal');
  var boton = document.getElementById('btnRestablecerClave');
  var usuarioSelect = document.getElementById('restablecerClave__usuario');

  if (!form) return;

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    boton.disabled = true;
    var solicitudId = form.dataset.solicitudId || '';
    var endpoint = solicitudId ? 'aprobarAjax' : 'storeManualAjax';
    var formData = new FormData(form);
    if (solicitudId) formData.set('id', solicitudId);

    fetch(routeUrl + 'accessRecovery/' + endpoint, {
      method: 'POST',
      body: formData
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.ok) {
          alert(data.mensaje || 'No se pudo restablecer la contraseña.');
          return;
        }

        var option = usuarioSelect.options[usuarioSelect.selectedIndex];
        mostrarCredenciales(data, option ? option.textContent.trim() : 'Usuario');
        if (modalInicial) bootstrap.Modal.getOrCreateInstance(modalInicial).hide();
        if (modalCredenciales) bootstrap.Modal.getOrCreateInstance(modalCredenciales).show();
        form.reset();
        delete form.dataset.solicitudId;
        form.classList.remove('was-validated');
      })
      .catch(function () {
        alert('Error de conexión al restablecer la contraseña.');
      })
      .finally(function () {
        boton.disabled = false;
      });
  });
});
