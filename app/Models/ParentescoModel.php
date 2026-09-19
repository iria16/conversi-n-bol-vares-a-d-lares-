<?php

declare(strict_types=1);

namespace App\Models;

class ParentescoModel extends CatalogoModel
{
    protected string $table = 'parentesco';
    protected string $primaryKey = 'id_parentesco';
    protected string $campoNombre = 'nombre';
}