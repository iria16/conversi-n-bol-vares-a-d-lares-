/* SIGDE - comportamiento de Catálogos */

document.addEventListener('DOMContentLoaded', function () {
  var baseUrl = window.BASE_URL || '';
  var routeUrl = baseUrl + 'index.php/';
  var tipo = document.getElementById('tipoCatalogo');
  if (tipo) {
    tipo.addEventListener('change', function () {
      window.location.href = routeUrl + 'catalogo/index?tipo=' + encodeURIComponent(tipo.value);
    });
  }

  document.querySelectorAll('.toggle-estado').forEach(function (input) {
    input.addEventListener('change', function () {
      var checkbox = input;
      var activar = checkbox.checked;
      var id = checkbox.dataset.elementoId;

      Swal.fire({
        icon: 'warning',
        title: activar ? '¿Activar este elemento?' : '¿Desactivar este elemento?',
        text: activar
          ? 'El elemento volverá a estar disponible para su uso.'
          : 'El elemento dejará de estar disponible para nuevas asignaciones.',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (!result.isConfirmed) {
          checkbox.checked = !activar;
          return;
        }

        fetch(routeUrl + 'catalogo/toggleEstadoAjax', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + encodeURIComponent(id) + '&tipo=' + encodeURIComponent(tipo.value)
        })
          .then(function (response) { return response.json(); })
          .then(function (data) {
            if (!data.ok) {
              checkbox.checked = !activar;
              Swal.fire('Error', data.mensaje || 'No se pudo actualizar el estado.', 'error');
              return;
            }
            var row = checkbox.closest('tr');
            if (row) row.classList.toggle('is-muted', !activar);
            var badge = row ? row.querySelector('.status-badge') : null;
            if (badge) {
              badge.className = 'status-badge status-badge--' + (activar ? 'active' : 'inactive');
              badge.textContent = activar ? 'Activo' : 'Inactivo';
            }
          })
          .catch(function () {
            checkbox.checked = !activar;
            Swal.fire('Error', 'Error de conexión al actualizar el estado.', 'error');
          });
      });
    });
  });

  var editModal = document.getElementById('modalEditarElemento');
  var editForm = document.getElementById('formEditarElemento');
  var editId = document.getElementById('editarElementoId');
  var editName = document.getElementById('editarElementoNombre');

  document.querySelectorAll('.editar-elemento').forEach(function (button) {
    button.addEventListener('click', function (event) {
      event.preventDefault();
      if (!editModal || !editId || !editName) return;

      editId.value = button.dataset.elementoId;
      editName.value = button.dataset.elementoNombre;
      editName.classList.remove('is-invalid');
      bootstrap.Modal.getOrCreateInstance(editModal).show();
    });
  });

  if (editForm) {
    editForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!editForm.checkValidity()) {
        editForm.classList.add('was-validated');
        return;
      }

      fetch(routeUrl + 'catalogo/updateAjax', {
        method: 'POST',
        body: new FormData(editForm)
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data.ok) {
            Swal.fire('Error', data.mensaje || 'No se pudo actualizar el elemento.', 'error');
            return;
          }
          window.location.reload();
        })
        .catch(function () {
          Swal.fire('Error', 'Error de conexión al actualizar el elemento.', 'error');
        });
    });
  }

  var form = document.getElementById('formNuevoElemento');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    fetch(routeUrl + 'catalogo/storeAjax', {
      method: 'POST',
      body: new FormData(form)
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.ok) {
          Swal.fire('Error', data.mensaje || 'No se pudo guardar el elemento.', 'error');
          return;
        }
        window.location.reload();
      })
      .catch(function () {
        Swal.fire('Error', 'Error de conexión al guardar el elemento.', 'error');
      });
  });
});
