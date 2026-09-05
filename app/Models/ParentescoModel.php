<?php
require_once __DIR__ . '/CatalogoModel.php';

class ParentescoModel extends CatalogoModel
{
    protected string $table = 'parentesco';
    protected string $primaryKey = 'id_parentesco';
    protected string $campoNombre = 'nombre';
}