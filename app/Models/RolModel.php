<?php

declare(strict_types=1);

namespace App\Models;

class RolModel extends CatalogoModel
{
    protected string $table = 'rol';
    protected string $primaryKey = 'id_rol';
    protected string $campoNombre = 'nombre';
}