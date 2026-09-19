<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

abstract class Model
{
    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}