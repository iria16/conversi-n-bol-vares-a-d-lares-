<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando se intenta dejar la capacidad de una sección por debajo de los alumnos que ya
 * la ocupan.
 *
 * Existe por la misma razón que CupoAgotadoException: la regla se revisa dos veces, una en el
 * controlador antes de abrir la transacción y otra en el modelo dentro de ella. Con datos
 * reales hoy no hay ninguna sección en esa situación, así que la segunda comprobación solo se
 * ve forzando la capacidad a mano; sin la excepción, el INSERT fallaría por una llave
 * foránea y el error saldría como 500 en lugar de un 409 con el motivo.*/
class CapacidadInsuficienteException extends RuntimeException
{
    /**
 * @param string $mensaje Texto listo para mostrar.
 * @param int $ocupados Cuántos alumnos hay en la sección ahora mismo.
 * @param string|null $campo Formulario al que colgar el error.
*/
    public function __construct(
        string $mensaje,
        private readonly int $ocupados = 0,
        private readonly ?string $campo = 'capacidad_maxima'
    ) {
        parent::__construct($mensaje);
    }

    /** Alumnos que hay en la sección; el frontend lo usa en el mensaje de confirmación.*/
    public function ocupados(): int
    {
        return $this->ocupados;
    }

    /** Formulario al que colgar el error en la respuesta 422.*/
    public function campo(): ?string
    {
        return $this->campo;
    }
}