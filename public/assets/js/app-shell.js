/* ==========================================================================
   SIGDE - app-shell.js
   Comportamiento del layout administrativo (sidebar responsive).
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
  initSidebarToggle();
});

function initSidebarToggle() {
  var sidebar = document.getElementById('appSidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var toggle = document.getElementById('sidebarToggle');

  if (!sidebar || !overlay || !toggle) return;

  function openSidebar() {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-hidden', 'false');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Cerrar menú de navegación');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    overlay.setAttribute('aria-hidden', 'true');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir menú de navegación');
    document.body.style.overflow = '';
  }

  function isSidebarOpen() {
    return sidebar.classList.contains('is-open');
  }

  toggle.addEventListener('click', function () {
    if (isSidebarOpen()) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  overlay.addEventListener('click', closeSidebar);

  sidebar.querySelectorAll('.sidebar__link:not([aria-disabled="true"])').forEach(function (link) {
    link.addEventListener('click', function () {
      if (window.matchMedia('(max-width: 991.98px)').matches) {
        closeSidebar();
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && isSidebarOpen()) {
      closeSidebar();
      toggle.focus();
    }
  });

  window.addEventListener('resize', function () {
    if (window.matchMedia('(min-width: 992px)').matches) {
      closeSidebar();
    }
  });
}
