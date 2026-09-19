<?php

declare(strict_types=1);

namespace App\Models;

class SeccionModel extends CatalogoModel
{
    protected string $table = 'seccion';
    protected string $primaryKey = 'id_seccion';
    protected string $campoNombre = 'nombre';
}