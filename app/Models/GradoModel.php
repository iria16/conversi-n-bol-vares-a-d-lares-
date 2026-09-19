<?php

declare(strict_types=1);

namespace App\Models;

class GradoModel extends CatalogoModel
{
    protected string $table = 'grado';
    protected string $primaryKey = 'id_grado';
    protected string $campoNombre = 'nombre';
}