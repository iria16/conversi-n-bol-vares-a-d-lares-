<?php

declare(strict_types=1);

namespace App\Models;

class TurnoModel extends CatalogoModel
{
    protected string $table = 'turno';
    protected string $primaryKey = 'id_turno';
    protected string $campoNombre = 'nombre';
}