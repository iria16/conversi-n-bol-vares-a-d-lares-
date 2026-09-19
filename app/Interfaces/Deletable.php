<?php
namespace App\Interfaces;

interface Deletable
{
    /**
     * Elimina (física o lógicamente, según implemente el modelo)
     * el registro identificado por $id.
     */
    public function delete(int $id): bool;
}