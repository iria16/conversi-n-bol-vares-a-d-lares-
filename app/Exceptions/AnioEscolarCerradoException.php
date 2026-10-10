<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Se intentó escribir sobre un año escolar que no está abierto.
 *
 * Existe como excepción propia y no como RuntimeException con texto, por una razón práctica: el
 * mensaje es para el log y para la secretaria, pero lo que el sistema necesita es un código
 * concreto para responder 409 y no un 500 genérico.
*/
class AnioEscolarCerradoException extends RuntimeException
{
}
