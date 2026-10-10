<?php
declare(strict_types=1);

namespace App\Interfaces;

interface Findable
{
    public function getById(int $id): mixed;
}