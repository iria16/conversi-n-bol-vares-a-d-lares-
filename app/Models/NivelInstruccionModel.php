<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Nivel de instrucción de la persona (Sin instrucción, Primaria, Secundaria,
 * Bachillerato, Técnico, Universitario, Postgrado, Maestría, Doctorado).
 *
 * Mismo caso que OcupacionModel: la tabla estaba llena y `persona_estudiante`
 * la tenía como FK NOT NULL, pero sin forma de elegirla. Se administra desde
 * Configuración → Catálogos.
 *
 * El orden importa y no se cambia: la institución usa la lista como escala
 * (de "Sin instrucción" a "Doctorado"), y los select la muestran en el orden de
 * la tabla.
 */
class NivelInstruccionModel extends CatalogoModel
{
    protected string $table = 'nivel_instruccion';
    protected string $primaryKey = 'id_nivel_instruccion';
    protected string $campoNombre = 'nombre';
}
