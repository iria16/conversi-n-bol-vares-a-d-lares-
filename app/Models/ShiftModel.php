<?php
require_once __DIR__ . '/CatalogoModel.php';

class ShiftModel extends CatalogoModel
{
    protected string $table = 'turno';
    protected string $primaryKey = 'id_turno';
}