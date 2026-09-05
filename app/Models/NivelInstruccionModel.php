<?php
require_once __DIR__ . '/CatalogoModel.php';

class NivelInstruccionModel extends CatalogoModel
{
    protected string $table = 'nivel_instruccion';
    protected string $primaryKey = 'id_nivel_instruccion';
    protected string $campoNombre = 'nombre';
}