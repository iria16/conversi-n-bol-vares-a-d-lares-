<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Telefonos
 * -----------------------------------------------------------------
 * Única fuente de verdad de los prefijos telefónicos del sistema.
 *
 * La lista es exactamente el ENUM de las columnas
 * `contacto.codigo_telefono_principal` y `codigo_telefono_alternativo`.
 * Antes vivía solo en el componente phone-input.php (que la pintaba) y el
 * validador del servidor la recibía como parámetro, con el riesgo de que
 * ambos se desincronizasen; ahora la leen los dos desde acá.
 *
 * Si algún día se agrega o retira un código, es en este archivo y en el
 * ENUM de la tabla (nada más).
 */
final class Telefonos
{
    /**
     * Prefijos permitidos.
     *
     * @var array<int, string>
     */
    public const CODIGOS = [
        '0412', '0414', '0416', '0422', '0424', '0426',
        '0212',
    ];

    /** Longitud del número local (sin el prefijo). */
    public const LARGO_NUMERO = 7;

    /** @param string $codigo Prefijo a comprobar. */
    public static function esCodigoValido(string $codigo): bool
    {
        return in_array(trim($codigo), self::CODIGOS, true);
    }

    /** @param string $numero Número local, sin prefijo. */
    public static function esNumeroValido(string $numero): bool
    {
        return (bool) preg_match('/^[0-9]{' . self::LARGO_NUMERO . '}$/', trim($numero));
    }
}
