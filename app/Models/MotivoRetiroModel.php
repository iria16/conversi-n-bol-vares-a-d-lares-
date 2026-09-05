<?php
require_once __DIR__ . '/CatalogoModel.php';

class MotivoRetiroModel extends CatalogoModel
{
    protected string $table = 'motivo_retiro';
    protected string $primaryKey = 'id_motivo_retiro';
    protected string $campoNombre = 'nombre';
}