<?php
require_once __DIR__ . '/CatalogoModel.php';

class TitleModel extends CatalogoModel
{
    protected string $table = 'titulo';
    protected string $primaryKey = 'id_titulo';
    protected string $campoNombre = 'nombre';
}