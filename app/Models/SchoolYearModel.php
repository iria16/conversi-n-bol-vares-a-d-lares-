<?php
require_once __DIR__ . '/CatalogoModel.php';

class SchoolYearModel extends CatalogoModel
{
    protected string $table = 'anio_escolar';
    protected string $primaryKey = 'id_anio_escolar';
    protected string $campoNombre = 'nombre_periodo';
}