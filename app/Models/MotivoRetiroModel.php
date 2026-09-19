<?php

declare(strict_types=1);

namespace App\Models;

class MotivoRetiroModel extends CatalogoModel
{
    protected string $table = 'motivo_retiro';
    protected string $primaryKey = 'id_motivo_retiro';
    protected string $campoNombre = 'nombre';
}