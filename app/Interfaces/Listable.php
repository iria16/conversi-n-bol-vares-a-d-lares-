<?php
namespace App\Interfaces;

interface Listable
{
    /**
     * @param array $filters Filtros ya saneados por el controlador.
     * @param int   $page    Número de página (desde 1).
     * @param int   $perPage Filas por página.
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters, int $page, int $perPage): array;

    /** Total de filas que cumplen los filtros (para la paginación). */
    public function countAll(array $filters): int;
}