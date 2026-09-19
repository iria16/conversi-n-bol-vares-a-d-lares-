/**
 * Bitácora del Sistema (audit/index.php)
 *
 * Los filtros de fecha y de acción son inputs/select sueltos, sin
 * botón "Aplicar" propio (el buscador de texto ya tiene su propio
 * manejo dentro de data-panel.php). Este script hace que, al
 * cambiar cualquiera de ellos, se reenvíe el formulario de filtros
 * automáticamente — y resetea la página a 1 para no quedar pidiendo
 * una página que ya no existe con el nuevo filtro aplicado.
 */
document.addEventListener('DOMContentLoaded', () => {
    const idsFiltro = ['filtroDesde', 'filtroHasta', 'filtroAccion'];

    idsFiltro.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;

        el.addEventListener('change', () => {
            const form = el.closest('form');
            if (!form) return;

            const inputPagina = form.querySelector('input[name="page"]');
            if (inputPagina) inputPagina.value = '1';

            // Si el formulario no tiene auto-filtro AJAX en main.js, solicita el envío
            if (!form.matches('.data-panel__filters, .auto-filters')) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    });
});