<?php
/**
 * Partial: partials/pagination.php
 * Espera: array $paginacion ['pagina_actual','total_paginas']
 * Espera opcionalmente: string $paginacionAriaLabel
 */
$paginacionAriaLabel = $paginacionAriaLabel ?? 'Paginación';
?>
<nav aria-label="<?= htmlspecialchars($paginacionAriaLabel) ?>">
  <ul class="pagination data-panel__pagination mb-0">
    <li class="page-item <?= $paginacion['pagina_actual'] <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= max(1, $paginacion['pagina_actual'] - 1) ?>" aria-label="Anterior">
        <i class="bi bi-chevron-left"></i>
      </a>
    </li>
    <?php for ($p = 1; $p <= $paginacion['total_paginas']; $p++): ?>
      <?php if ($p > 3 && $p < $paginacion['total_paginas'] && $paginacion['pagina_actual'] <= 3): ?>
        <?php if ($p === 4): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <?php continue; ?>
      <?php endif; ?>
      <li class="page-item <?= $p === $paginacion['pagina_actual'] ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $paginacion['pagina_actual'] >= $paginacion['total_paginas'] ? 'disabled' : '' ?>">
      <a class="page-link" href="?page=<?= min($paginacion['total_paginas'], $paginacion['pagina_actual'] + 1) ?>" aria-label="Siguiente">
        <i class="bi bi-chevron-right"></i>
      </a>
    </li>
  </ul>
</nav>