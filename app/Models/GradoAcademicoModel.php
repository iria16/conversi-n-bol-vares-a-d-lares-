<?php

declare(strict_types=1);

namespace App\Models;

class GradoAcademicoModel extends CatalogoModel
{
    protected string $table = 'grado_academico';
    protected string $primaryKey = 'id_grado_academico';
    protected string $campoNombre = 'nombre';
}