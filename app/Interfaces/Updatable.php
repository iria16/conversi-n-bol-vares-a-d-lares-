<?php
namespace App\Interfaces;

interface Updatable
{
    /**
     * Actualiza el registro identificado por $id con los datos
     * ya validados.
     */
    public function update(int $id, array $data): bool;
}