<?php

declare(strict_types=1);

namespace App\Models;

class TipoAsignacionModel extends CatalogoModel
{
    protected string $table = 'tipo_asignacion';
    protected string $primaryKey = 'id_tipo_asignacion';
    protected string $campoNombre = 'nombre';
}