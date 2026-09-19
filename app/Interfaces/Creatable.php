<?php
namespace App\Interfaces;

interface Creatable
{
    /**
     * Crea un nuevo registro a partir de los datos ya validados.
     * @return int ID del registro creado.
     */
    public function create(array $data): bool;
}






