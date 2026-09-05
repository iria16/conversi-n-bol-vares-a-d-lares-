<?php
require_once __DIR__ . '/CatalogoModel.php';

class AcademicDegreeModel extends CatalogoModel
{
    protected string $table = 'grado_academico';
    protected string $primaryKey = 'id_grado_academico';
    protected string $campoNombre = 'nombre';
}