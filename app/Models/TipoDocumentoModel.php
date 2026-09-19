<?php

declare(strict_types=1);

namespace App\Models;

class TipoDocumentoModel extends CatalogoModel
{
    protected string $table = 'tipo_documento';
    protected string $primaryKey = 'id_tipo_documento';
    protected string $campoNombre = 'nombre';
}