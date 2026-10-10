<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Nacionalidad de la persona (Venezolano, Colombiano, Brasileño, ...).
 *
 * Es un catálogo como OccupationModel y NivelInstruccionModel: la tabla se llena y se
 * administra desde Configuración → Catálogos.
 *
 * Dos cosas que no son obvias:
 *
 * - El nombre va en género ADJETIVO, "Venezolano", no "Venezuela". Describe a la
 *   persona, no el país de nacimiento: son dos datos distintos y el mismo alumno puede
 *   haber nacido en Caracas y ser brasileiro por ascendencia. Por eso esta tabla
 *   no reemplaza al lugar de nacimiento, lo complementa.
 *
 * - El id 1 es "Venezolano" y está fijado en la migración a propósito. Es el valor por
 *   defecto del formulario del estudiante, y depender del AUTO_INCREMENT para eso
 *   sería fragile: una base estrenada o una restaurada desde un respaldo puede
 *   arrancar el contador en otro lado.
 */
class NacionalidadModel extends CatalogoModel
{
    protected string $table = 'nacionalidad';
    protected string $primaryKey = 'id_nacionalidad';
    protected string $campoNombre = 'nombre';

    /** Id del valor por defecto. Lo busca por nombre en vez de hardcodear el 1. */
    public const ID_POR_DEFECTO = 1;
}
