<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Única forma de leer y normalizar una cédula (V o E): acepta "12345678", "V12345678", "v-12.345.678" o "E 87654321".
 * El prefijo es opcional y se descarta al guardar: `tipo_documento` dice el tipo y `numero_documento` guarda solo dígitos,
 * así no hay que migrar filas ni duplicar un número con y sin letra (rompería el UNIQUE).
 */
final class Documento
{
    /** Tipos de documento que son cédula de identidad o extranjería. */
    public const TIPOS_CEDULA = ['V', 'E'];

    /** Letra que se asume cuando la cédula viene sin prefijo. */
    public const LETRA_POR_DEFECTO = 'V';

    /** Cédula ya normalizada: 7 u 8 dígitos (el prefijo y los separadores se quitan antes, en normalizar()). */
    public const PATRON = '/^\d{7,8}$/';

    /**
     * Mismo criterio para el atributo `pattern` del HTML: prefijo V/E opcional, separadores libres y 7 u 8 dígitos.
     * Va sin ^ $ porque `pattern` valida el valor completo; el `(?=...)` cuenta los dígitos igual que PATRON.
     */
    public const PATRON_HTML = '[\s.\-]*[VEve]?[\s.\-]*(?=(?:[^0-9]*[0-9]){7,8}\s*$)[0-9\s.\-]*';

    /** Texto del `title` del campo de documento, igual en todos los formularios. */
    public const TITULO_HTML = 'Debe contener entre 7 y 8 dígitos. El prefijo V o E es opcional.';

    /** `placeholder` del campo de documento: solo el número; la explicación del prefijo va en el `title`. */
    public const PLACEHOLDER_HTML = 'Ej: 25415866';

    /** Deja solo los dígitos de una cédula: "v-12.345.678" → "12345678". */
    public static function normalizar(string $cedula): string
    {
        return preg_replace('/\D/', '', $cedula) ?? '';
    }

    /** La letra del documento: la del prefijo o, si no vino, la indicada por defecto (V). */
    public static function letraDe(string $cedula, string $porDefecto = self::LETRA_POR_DEFECTO): string
    {
        $primera = strtoupper(substr(trim($cedula), 0, 1));

        if (in_array($primera, self::TIPOS_CEDULA, true)) {
            return $primera;
        }

        $porDefecto = strtoupper(trim($porDefecto));

        return in_array($porDefecto, self::TIPOS_CEDULA, true) ? $porDefecto : self::LETRA_POR_DEFECTO;
    }

    /** ¿Es una cédula de identidad o extranjería? Se valida sobre el número ya normalizado. */
    public static function esCedula(string $cedula): bool
    {
        return preg_match(self::PATRON, self::normalizar($cedula)) === 1;
    }
}