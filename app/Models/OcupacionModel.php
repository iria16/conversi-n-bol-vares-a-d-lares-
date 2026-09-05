<?php
require_once __DIR__ . '/CatalogoModel.php';

class OcupacionModel extends CatalogoModel
{
    protected string $table = 'ocupacion';
    protected string $primaryKey = 'id_ocupacion';
    protected string $campoNombre = 'nombre';
}