<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * CupoAgotadoException
 *
 * Se lanza cuando la combinación de Estructura Académica que se eligió no tiene
 * lugar: o no está registrada, o ya llegó a su cupo.
 *
 * Existe por la misma razón que DocumentoDuplicadoException: el cupo se revisa
 * dos veces, una antes de la transacción (el validate() del controlador, que es
 * lo que llena el mensaje de errores del formulario) y otra adentro, con la fila
 * bloqueada (reservarCupo(), en CupoEstructuraTrait). La segunda es la que
 * decide, porque es la que serializa a las peticiones simultáneas.
 *
 * Sin esta excepción, el rechazo por cupo caería en el catch genérico de
 * Throwable del controlador y respondería 500 con "ocurrió un problema al
 * procesar la solicitud", que no le dice nada a la secretaria. Con ella, el
 * controlador responde 422 y muestra el mensaje real: cuántas personas hay y
 * cuántas caben.
 *
 * @author Logística
 * @package App\Exceptions
 */
class CupoAgotadoException extends RuntimeException
{
    /**
     * @param string      $mensaje Texto que ya se puede mostrar a la secretaria.
     * @param string|null $campo   Formulario al que colgar el error, para que
     *                             el wizard lo marque en el select.
     */
    public function __construct(
        string $mensaje,
        private readonly ?string $campo = 'id_turno'
    ) {
        parent::__construct($mensaje);
    }

    /** Formulario al que colgar el error en la respuesta 422. */
    public function campo(): ?string
    {
        return $this->campo;
    }
}
