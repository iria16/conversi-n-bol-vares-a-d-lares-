<?php
require_once __DIR__ . '/CatalogoModel.php';

class GradeModel extends CatalogoModel
{
    protected string $table = 'grado';
    protected string $primaryKey = 'id_grado';
}