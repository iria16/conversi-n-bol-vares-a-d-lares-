<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * DocumentoDuplicadoException
 *
 * Se lanza cuando la BD rechaza un INSERT en `persona` por violar la
 * restricción UNIQUE de `numero_documento` (SQLSTATE 23000).
 *
 * Existe para cubrir la condición de carrera entre la validación de
 * EmpleadoController::validate() (que consulta existeDocumento() ANTES de
 * insertar) y el INSERT real dentro de la transacción de
 * EmpleadoModel::create(). Si dos peticiones con la misma cédula llegan casi
 * al mismo tiempo, ambas pueden pasar la validación antes de que cualquiera
 * inserte; la segunda en llegar al INSERT choca con la restricción UNIQUE.
 *
 * Sin esta excepción, ese choque se propagaría como un PDOException genérico
 * y el controlador respondería 500 (jsonError) en vez de un 422 amigable de
 * "cédula duplicada". EmpleadoModel la lanza al detectar el SQLSTATE 23000
 * sobre numero_documento, y EmpleadoController::store() la captura antes que
 * el catch genérico de Throwable para dar la respuesta correcta.
 *
 * @author Logística
 * @package App\Exceptions
 */
class DocumentoDuplicadoException extends RuntimeException
{
}