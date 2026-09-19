<?php
namespace App\Interfaces;

interface Toggleable
{
    /**
     * Alterna el estado (activo/inactivo) del registro
     * identificado por $id.
     */
    public function toggleStatus(int $id): bool;
}