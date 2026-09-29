<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Instituciones
 * -----------------------------------------------------------------
 * Tipos de institución del sistema. Son un ENUM FIJO en código, no un catálogo
 * administrable: cada tipo dispara una regla de validación distinta, así que
 * agregar un tipo nuevo es cambiar el código (y su regla), no llenar una tabla
 * desde la interfaz de catálogos.
 *
 *   TIPO_TITULO_ACADEMICO  Módulo de empleados: institución donde el empleado
 *                          obtuvo un título. Sin restricción de grado; el nivel
 *                          del título lo aporta el catálogo `titulo` con su
 *                          `grado_academico`, no el tipo de institución.
 *
 *   TIPO_PREESCOLAR        Módulo de estudiantes: procedencia. Sin límite de
 *                          grado, porque el preescolar está antes de la
 *                          primaria (no tiene grados 1-6).
 *
 *   TIPO_ESCUELA           Módulo de estudiantes: procedencia. El grado
 *                          anterior tiene que ser uno inferior al grado de
 *                          inscripción, dentro del rango 1-6 (ver
 *                          validarGradoProcedencia()).
 *
 * Estos códigos son lo que guarda la columna `institucion.tipo`, así que el
 * enum y la base no pueden desincronizarse: la tabla `tipo_institucion` quedó
 * como histórico y nada la lee (ver migraciones/0001_institucion_tipo_enum.sql).
 *
 * La misma clase resuelve las dos cosas que los módulos necesitan de este
 * dominio: qué tipos son válidos en cada contexto, y la regla del grado de
 * procedencia. Clase final con métodos estáticos, sin estado ni base de datos,
 * igual que Telefonos o Documento.
 */
final class Instituciones
{
    /** Institución donde el empleado obtuvo un título. */
    public const TIPO_TITULO_ACADEMICO = 'titulo_academico';

    /** Institución de procedencia del estudiante, etapa inicial. */
    public const TIPO_PREESCOLAR = 'preescolar';

    /** Institución de procedencia del estudiante, primaria. */
    public const TIPO_ESCUELA = 'escuela';

    /** Todos los tipos del sistema, en el orden en que se ofrecen. */
    public const TIPOS = [
        self::TIPO_TITULO_ACADEMICO,
        self::TIPO_PREESCOLAR,
        self::TIPO_ESCUELA,
    ];

    /** Los que son etapas del sistema escolar: de aquí viene el estudiante. */
    public const TIPOS_DE_ESTUDIANTE = [
        self::TIPO_PREESCOLAR,
        self::TIPO_ESCUELA,
    ];

    /** Orden de la primaria: de 1er a 6to grado. */
    public const GRADO_MENOR = 1;
    public const GRADO_MAYOR = 6;

    /** Texto del <select> de tipo. "Escuela" es primaria. */
    private const ETIQUETAS = [
        self::TIPO_TITULO_ACADEMICO => 'Título académico',
        self::TIPO_PREESCOLAR       => 'Preescolar',
        self::TIPO_ESCUELA          => 'Escuela',
    ];

    /** ¿Ese string es uno de los tipos del sistema? */
    public static function esValido(string $tipo): bool
    {
        return in_array(trim($tipo), self::TIPOS, true);
    }

    /** ¿Es una etapa del sistema escolar (procedencia del estudiante)? */
    public static function esDeEstudiante(string $tipo): bool
    {
        return in_array(trim($tipo), self::TIPOS_DE_ESTUDIANTE, true);
    }

    /**
     * ¿Este tipo exige que el grado de procedencia sea inferior al grado de
     * inscripción? Solo la escuela: el preescolar no tiene grados 1-6 y en el
     * módulo de empleados la formación académica tampoco tiene un "grado
     * anterior" que comparar.
     */
    public static function exigeGradoInferior(string $tipo): bool
    {
        return trim($tipo) === self::TIPO_ESCUELA;
    }

    /** Texto del <select> de tipo. */
    public static function etiqueta(string $tipo): string
    {
        return self::ETIQUETAS[trim($tipo)] ?? trim($tipo);
    }

    /**
     * Número de grado que representa un texto del catálogo `grado` o del
     * <select> de procedencia: "1er Grado" y "1er grado" -> 1, "6to" -> 6.
     *
     * Se usa el número escrito y no el id del catálogo a propósito: los ids no
     * siguen el orden (en el catálogo actual el 2do grado es id 6 y el 3er es
     * id 2), así que comparar ids daría resultados equivocados.
     *
     * Devuelve null cuando el texto no es un grado numerado, por ejemplo
     * "Educación Inicial", que está fuera del rango 1-6.
     */
    public static function ordinalGrado(?string $grado): ?int
    {
        $grado = trim((string) $grado);
        if ($grado === '' || preg_match('/(\d+)/', $grado, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /** ¿Ese número cae dentro del rango de la primaria (1-6)? */
    public static function esGradoDePrimaria(?int $ordinal): bool
    {
        return $ordinal !== null
            && $ordinal >= self::GRADO_MENOR
            && $ordinal <= self::GRADO_MAYOR;
    }

    /**
     * Regla de grado de procedencia, compartida por el alta y por la
     * inscripción para que la misma regla se vea igual en los dos lados.
     *
     * Aplica únicamente a TIPO_ESCUELA. En cualquier otro tipo devuelve null
     * (no hay nada que exigir), incluido el caso de que no haya institución
     * o de que el estudiante ingrese por primera vez y no tenga procedencia.
     *
     * @param string      $tipo         Tipo de la institución de procedencia.
     * @param string|null $ultimoGrado  Texto del grado que cursó antes.
     * @param int|null    $ordinalCursar Número del grado de inscripción; null
     *                                   si todavía no se ha elegido (el grado a
     *                                   cursar se pide en un paso posterior del
     *                                   wizard, así que se omite la
     *                                   comparación en vez de inventar un
     *                                   error por un dato que aún no existe).
     * @return string|null Mensaje de error, o null si la combinación es válida.
     */
    public static function validarGradoProcedencia(
        string $tipo,
        ?string $ultimoGrado,
        ?int $ordinalCursar
    ): ?string {
        if (!self::exigeGradoInferior($tipo)) {
            return null;
        }

        $rango = 'entre ' . self::GRADO_MENOR . 'er y ' . self::GRADO_MAYOR . 'to grado';

        $ordinal = self::ordinalGrado($ultimoGrado);
        if ($ordinal === null) {
            return 'Si la institución de procedencia es una escuela, indique el '
                . 'último grado cursado (' . $rango . ').';
        }

        if (!self::esGradoDePrimaria($ordinal)) {
            return 'El grado de procedencia debe estar ' . $rango . '.';
        }

        if ($ordinalCursar !== null && $ordinal >= $ordinalCursar) {
            return 'El grado de procedencia (' . $ordinal . self::ordinalTexto($ordinal)
                . ') debe ser inferior al grado que el estudiante va a cursar ('
                . $ordinalCursar . self::ordinalTexto($ordinalCursar) . ').';
        }

        return null;
    }

    /** "1" -> "er", "2" -> "do", "3" -> "er", "4" -> "to"... para los mensajes. */
    private static function ordinalTexto(int $ordinal): string
    {
        return match ($ordinal) {
            1       => 'er',
            2       => 'do',
            3       => 'er',
            default => 'to',
        };
    }
}
