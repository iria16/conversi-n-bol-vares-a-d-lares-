<?php
require_once __DIR__ . '/CatalogoModel.php';

class InstitutionTypeModel extends CatalogoModel
{
    protected string $table = 'tipo_institucion';
    protected string $primaryKey = 'id_tipo_institucion';
    protected string $campoNombre = 'nombre';
}