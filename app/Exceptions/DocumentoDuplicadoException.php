<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * DocumentoDuplicadoException
 *
 * Se lanza cuando la BD rechaza un INSERT en `persona` por violar la
 * restricción UNIQUE de `numero_documento` (SQLSTATE 23000).
 *
 * Existe para cubrir la condición de carrera entre la validación de
 * EmpleadoController::validate() y el INSERT real dentro de la transacción.
 *
 * @author Logística
 * @package App\Exceptions
*/
class DocumentoDuplicadoException extends RuntimeException
{
    /**
 * Sobrescribe el constructor para inyectar un mensaje amigable por defecto.
*/
    public function __construct(
        string $message = 'Ya existe una persona registrada con este número de documento.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code,$previous);
    }
}