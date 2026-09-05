<?php
require_once __DIR__ . '/CatalogoModel.php';

class RoleModel extends CatalogoModel
{
    protected string $table = 'rol';
    protected string $primaryKey = 'id_rol';
    protected string $campoNombre = 'nombre';
}