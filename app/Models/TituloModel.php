<?php

declare(strict_types=1);

namespace App\Models;

class TituloModel extends CatalogoModel
{
    protected string $table = 'titulo';
    protected string $primaryKey = 'id_titulo';
    protected string $campoNombre = 'nombre';
}