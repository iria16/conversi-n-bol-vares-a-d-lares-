<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * CedulaEscolar
 * -----------------------------------------------------------------
 * Genera la cédula escolar de un estudiante que todavía no tiene cédula
 * de identidad propia (menores de 9 años).
 *
 * Formato (11 caracteres, todos obligatorios):
 *
 *     V1|19|1234567
 *     │ │ │  └──── 7 primeros dígitos de la cédula del representante
 *     │ │ └─────── 2 últimos dígitos del año de nacimiento del estudiante
 *     │ └───────── orden del estudiante entre los hijos del representante (1-9)
 *     └─────────── letra del representante: V (venezolano) o E (extranjero)
 *
 * Ejemplos: primer hijo, nacido en 2019, representante con cédula V-12345678
 * → V1191234567; y con una cédula de 7 dígitos, V-1234567 → V1190123456
 * (a los 7 dígitos del representante se les agrega el cero inicial, obligatorio).
 *
 * Y el orden no se recalcula: un representante con la cédula de 7 dígitos
 * sigue siendo el mismo, porque se guarda sin el cero (App\Support\Documento
 * guarda solo los dígitos) y la cédula escolar se arma aparte.
 *
 * La cédula es única: vive en persona.numero_documento, que tiene UNIQUE en
 * la base, y la verificación de duplicados la hace el mismo
 * PersonaValidacionTrait que valida la cédula de identidad. Aquí solo se
 * arma el string; quién lo usa y cuándo es cosa de cada módulo.
 *
 * Dato único en el sistema, sin estado y sin base de datos, igual que
 * Telefonos: por eso es una clase final con métodos estáticos en App\Support
 * y no un trait de controlador.
 */
final class CedulaEscolar
{
    /** Letras admitidas como inicio de la cédula escolar. */
    public const LETRAS = ['V', 'E'];

    /** Orden del hijo: una sola cifra, del 1 al 9. */
    public const ORDEN_MINIMO = 1;
    public const ORDEN_MAXIMO = 9;

    /** Dígitos que se toman de la cédula del representante. */
    public const LARGO_CEDULA_REPRESENTANTE = 7;

    /** Letra que se usa cuando el representante no informa su tipo. */
    public const LETRA_POR_DEFECTO = 'V';

    /**
     * Formato que debe cumplir la cédula escolar una vez generada:
     * letra (V/E) + orden del 1 al 9 + 9 dígitos.
     */
    public const PATRON = '/^[VE][1-9]\d{9}$/';

    /**
     * Los 7 dígitos con los que se arma la cédula escolar, tomados de la
     * cédula del representante.
     *
     * REGLA: si el representante tiene una cédula de 7 dígitos, se le agrega
     * un cero al inicio de forma OBLIGATORIA antes de tomar los 7. Sin eso la
     * cédula escolar de sus hijos saldría con un dígito menos y no coincidiría
     * con el resto del grupo, que usa los 8 dígitos. Ejemplos:
     *
     *     representante 12345678 → 1234567
     *     representante 1234567  → 0123456   (se le agregó el cero)
     *
     * @throws InvalidArgumentException si la cédula no llega a 7 dígitos.
     */
    public static function digitosDeRepresentante(string $cedulaRepresentante): string
    {
        $digitos = self::soloDigitos($cedulaRepresentante);

        if (strlen($digitos) < self::LARGO_CEDULA_REPRESENTANTE) {
            throw new InvalidArgumentException(
                'La cédula del representante debe tener al menos ' . self::LARGO_CEDULA_REPRESENTANTE . ' dígitos.'
            );
        }

        // Con 7 dígitos se completa a la izquierda con un cero, para que todos
        // los representantes aporten los mismos 7 caracteres.
        $digitos = str_pad($digitos, self::LARGO_CEDULA_REPRESENTANTE + 1, '0', STR_PAD_LEFT);

        return substr($digitos, 0, self::LARGO_CEDULA_REPRESENTANTE);
    }

    /**
     * Arma la cédula escolar.
     *
     * @param int         $orden              Qué hijo es, de 1 a 9.
     * @param string      $fechaNacimiento    Fecha de nacimiento del estudiante (Y-m-d).
     * @param string      $cedulaRepresentante Cédula del representante, con o sin letra,
     *                                        con o sin puntos o guiones.
     * @param string|null $letra              V o E. Si se omite, se deduce de la cédula.
     *
     * @throws InvalidArgumentException si algún dato no permite armar la cédula.
     */
    public static function generar(
        int $orden,
        string $fechaNacimiento,
        string $cedulaRepresentante,
        ?string $letra = null
    ): string {
        if ($orden < self::ORDEN_MINIMO || $orden > self::ORDEN_MAXIMO) {
            throw new InvalidArgumentException(
                'El orden del hijo debe ser un número del ' . self::ORDEN_MINIMO . ' al ' . self::ORDEN_MAXIMO . '.'
            );
        }

        $anio = self::anioDeNacimiento($fechaNacimiento);
        $digitos = self::digitosDeRepresentante($cedulaRepresentante);
        $letra = self::resolverLetra($letra, $cedulaRepresentante);

        return $letra . $orden . $anio . $digitos;
    }

    /**
     * ¿La cédula cumple el formato? Se usa para validar el resultado de
     * generar() y también para el dato que venga del formulario.
     */
    public static function esValida(string $cedula): bool
    {
        return (bool) preg_match(self::PATRON, trim($cedula));
    }

    /**
     * Quita la letra y los separadores de una cédula y devuelve solo los
     * dígitos. Sirve para leer cédulas guardadas como "V-12345678".
     */
    public static function soloDigitos(string $cedula): string
    {
        return preg_replace('/\D/', '', $cedula) ?? '';
    }

    /**
     * La letra de la cédula escolar es la del representante. Si el
     * formulario no la informa, se deduce del prefijo de su cédula y, si
     * tampoco se puede, se usa V.
     */
    private static function resolverLetra(?string $letra, string $cedulaRepresentante): string
    {
        $letra = strtoupper(trim((string) $letra));

        if (in_array($letra, self::LETRAS, true)) {
            return $letra;
        }

        $prefijo = strtoupper(trim(substr(trim($cedulaRepresentante), 0, 1)));

        return in_array($prefijo, self::LETRAS, true) ? $prefijo : self::LETRA_POR_DEFECTO;
    }

    /** Los 2 últimos dígitos del año de nacimiento. */
    private static function anioDeNacimiento(string $fechaNacimiento): string
    {
        $fecha = new DateTimeImmutable($fechaNacimiento);

        if ($fecha->format('Y') < 1900) {
            throw new InvalidArgumentException('La fecha de nacimiento del estudiante no es válida.');
        }

        return substr($fecha->format('Y'), -2);
    }
}
