<?php
require_once __DIR__ . '/CatalogoModel.php';

class SectionModel extends CatalogoModel
{
    protected string $table = 'seccion';
    protected string $primaryKey = 'id_seccion';
}