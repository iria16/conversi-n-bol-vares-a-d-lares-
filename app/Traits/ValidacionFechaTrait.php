<?php

declare(strict_types=1);

namespace App\Traits;

use DateTimeImmutable;

/**
 * Validaciones de fecha compartidas entre controladores (empleados,
 * inscripción de estudiantes, familiares/representantes).
 */
trait ValidacionFechaTrait
{
    /**
     * Comprueba que $fecha tenga formato Y-m-d y sea una fecha real
     * (rechaza, por ejemplo, 2024-02-31).
     */
    protected function fechaValida(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Comprueba que $fecha sea válida (fechaValida()) y además esté dentro
     * de un rango razonable: entre $minima y $maxima (ambas Y-m-d).
     *
     * Pensada para fechas de eventos pasados con un mínimo histórico
     * plausible (ingreso laboral, obtención de un título, etc.), donde el
     * problema real no es el formato sino valores como "0001-06-16" o una
     * fecha futura que createFromFormat() acepta sin quejarse.
     *
     * @param string      $fecha   Fecha a validar (Y-m-d).
     * @param string      $minima  Fecha mínima aceptada (Y-m-d).
     * @param string|null $maxima  Fecha máxima aceptada (Y-m-d). Por
     *                             defecto, el día de hoy (no se aceptan
     *                             fechas futuras).
     */
    protected function fechaEnRango(string $fecha, string $minima = '1950-01-01', ?string $maxima = null): bool
    {
        if (!$this->fechaValida($fecha)) {
            return false;
        }

        $d   = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        $min = DateTimeImmutable::createFromFormat('Y-m-d', $minima);
        $max = $maxima !== null
            ? DateTimeImmutable::createFromFormat('Y-m-d', $maxima)
            : new DateTimeImmutable('today');

        if ($d === false || $min === false || $max === false) {
            return false;
        }

        return $d >= $min && $d <= $max;
    }
}