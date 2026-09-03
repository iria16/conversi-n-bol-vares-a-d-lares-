<?php

declare(strict_types=1);

/**
 * Calcula los metadatos de paginación (desde/hasta/total_paginas) a
 * partir del total de registros, la página actual y el tamaño de
 * página. Reutilizable por cualquier controlador del sistema.
 */
final class Paginador
{
    public static function calcular(int $total, int $paginaActual, int $perPage): array
    {
        $paginaActual = max(1, $paginaActual);
        $perPage      = max(1, $perPage);

        return [
            'desde'         => $total > 0 ? (($paginaActual - 1) * $perPage) + 1 : 0,
            'hasta'         => min($paginaActual * $perPage, $total),
            'total'         => $total,
            'pagina_actual' => $paginaActual,
            'total_paginas' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}