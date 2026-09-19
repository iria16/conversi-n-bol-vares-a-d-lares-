<?php
/**
 * Componente: data-panel.php
 * Contenedor de tabla con filtros, búsqueda y paginación (_data-panel.scss).
 *
 * - string $panelFilters      HTML de los <select> de filtro, ya armados (NO se escapa)
 * - string $panelFormAction   opcional. Si se define, envuelve filtros+búsqueda en un <form method="get">
 * - string $panelFormId       opcional. id del <form> de filtros, para módulos con
 *                              filtrado/paginado AJAX (ej. staff.js) que necesitan
 *                              engancharse a un selector estable. Sin efecto si
 *                              $panelFormAction === '' (no hay <form> que etiquetar).
 * - string $panelSearchName   name/id del input de búsqueda (default 'buscar')
 * - string $panelSearchValue  valor actual del input (opcional)
 * - string $panelSearchPlaceholder
 * - string $panelColgroup     opcional. HTML de <colgroup><col>...</colgroup> ya armado
 *                              (NO se escapa), para vistas que necesiten fijar anchos
 *                              de columna. Si no se pasa, no se imprime nada y la
 *                              tabla se comporta igual que siempre.
 * - string $panelTableHead    HTML del <thead>, ya armado (NO se escapa)
 * - string $panelTableBody    HTML del <tbody>, ya armado (NO se escapa)
 * - string $panelTableBodyId  opcional. id del <tbody>, para módulos con AJAX que
 *                              reemplazan solo las filas (ej. staff.js).
 * - string $panelSummary      texto tipo "Mostrando 1 a 7 de 7 empleados"
 * - string $panelSummaryId    opcional. id del <span> del resumen, para módulos con
 *                              AJAX que actualizan solo ese texto (ej. staff.js).
 * - array  $panelPagination   ['pagina_actual','total_paginas'] opcional, null = sin paginación
 */
$panelFilters           = $panelFilters ?? '';
$panelFormAction        = $panelFormAction ?? '';
$panelFormId            = $panelFormId ?? '';
$panelSearchName        = $panelSearchName ?? 'buscar';
$panelSearchValue       = $panelSearchValue ?? '';
$panelSearchPlaceholder = $panelSearchPlaceholder ?? 'Buscar...';
$panelColgroup          = $panelColgroup ?? '';
$panelTableHead         = $panelTableHead ?? '';
$panelTableBody         = $panelTableBody ?? '';
$panelTableBodyId       = $panelTableBodyId ?? '';
$panelSummary           = $panelSummary ?? '';
$panelSummaryId         = $panelSummaryId ?? '';
$panelPagination        = $panelPagination ?? null;

$panelToolbarTag = $panelFormAction !== '' ? 'form' : 'div';
?>
<div class="data-panel">
  <div class="data-panel__toolbar">
    <<?php echo $panelToolbarTag; ?>
      class="data-panel__filters"
      <?php if ($panelFormAction !== ''): ?>
        method="get" action="<?php echo htmlspecialchars($panelFormAction); ?>"
        <?php if ($panelFormId !== ''): ?>id="<?php echo htmlspecialchars($panelFormId); ?>"<?php endif; ?>
      <?php endif; ?>
    >
      <?php echo $panelFilters; ?>
      <div class="data-panel__search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input
          type="search"
          class="form-control"
          name="<?php echo htmlspecialchars($panelSearchName); ?>"
          value="<?php echo htmlspecialchars($panelSearchValue); ?>"
          placeholder="<?php echo htmlspecialchars($panelSearchPlaceholder); ?>"
        >
      </div>
    </<?php echo $panelToolbarTag; ?>>
  </div>

  <div class="data-panel__body">
    <table class="table data-panel__table">
      <?php echo $panelColgroup; ?>
      <thead>
        <?php echo $panelTableHead; ?>
      </thead>
      <tbody<?php if ($panelTableBodyId !== ''): ?> id="<?php echo htmlspecialchars($panelTableBodyId); ?>"<?php endif; ?>>
        <?php echo $panelTableBody; ?>
      </tbody>
    </table>
  </div>

  <?php if ($panelSummary !== '' || $panelPagination !== null): ?>
    <div class="data-panel__footer">
      <?php if ($panelSummary !== ''): ?>
        <span<?php if ($panelSummaryId !== ''): ?> id="<?php echo htmlspecialchars($panelSummaryId); ?>"<?php endif; ?>><?php echo htmlspecialchars($panelSummary); ?></span>
      <?php endif; ?>
      <?php if ($panelPagination !== null): ?>
        <?php
          $paginacion = $panelPagination;
          $paginacionAriaLabel = 'Paginación de ' . $panelSearchName;
          include __DIR__ . '/../partials/pagination.php';
        ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php
unset($panelFilters, $panelFormAction, $panelFormId, $panelSearchName, $panelSearchValue, $panelSearchPlaceholder, $panelColgroup, $panelTableHead, $panelTableBody, $panelTableBodyId, $panelSummary, $panelSummaryId, $panelPagination, $panelToolbarTag);
?>