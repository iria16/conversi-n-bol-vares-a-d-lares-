<?php

declare(strict_types=1);

namespace App\Http;

/**
 * JsonResponse
 *
 * Respuesta JSON inmutable que uso en todos los endpoints AJAX del sistema.
 * Los controladores no la construyen directamente: pasan por los atajos
 * jsonResponse(), jsonRaw() y jsonError() de BaseController.
 *
 * Formato estándar (success y error):
 *
 *     { "ok": true|false, "mensaje": string|null, "data": mixed }
 *
 * Ojo con los nombres: la clave es `mensaje` (en español), no `message`.
 * El front (fetch + SweetAlert2) lee `ok`, `mensaje` y `data`.
 *
 * Se crea con uno de los tres constructores estáticos (success, error, raw)
 * y se emite con send(). El constructor es privado para que nadie arme un
 * payload con otro formato por accidente; si un caso necesita un formato
 * propio, existe raw().
 *
 * Uso típico:
 *
 *     JsonResponse::success($datos, 'Guardado correctamente')->send();
 *     JsonResponse::error('Hay errores en el formulario.', $errores, 422)->send();
 *
 * @author Logística
 * @package App\Http
 */
final class JsonResponse
{
    /**
     * @param array $payload Contenido que se serializa a JSON.
     * @param int   $code    Código de estado HTTP de la respuesta.
     */
    private function __construct(
        private readonly array $payload,
        private readonly int $code
    ) {}

    /**
     * Respuesta de éxito con el formato estándar (ok = true).
     *
     * @param mixed       $data    Datos a devolver al front (opcional).
     * @param string|null $message Mensaje legible para el usuario (opcional).
     * @param int         $code    Código HTTP (200 por defecto).
     */
    public static function success(mixed $data = null, ?string $message = null, int $code = 200): self
    {
        return new self(['ok' => true, 'mensaje' => $message, 'data' => $data], $code);
    }

    /**
     * Respuesta de error con el formato estándar (ok = false).
     *
     * Cuidado con el orden de los argumentos: aquí el mensaje va primero y
     * los datos después (por ejemplo, los errores por campo), al revés que en
     * success(). BaseController::jsonResponse() se encarga de ese cambio.
     *
     * @param string|null $message Mensaje legible para el usuario.
     * @param mixed       $data    Detalle del error, como errores de validación (opcional).
     * @param int         $code    Código HTTP (400 por defecto).
     */
    public static function error(?string $message = null, mixed $data = null, int $code = 400): self
    {
        return new self(['ok' => false, 'mensaje' => $message, 'data' => $data], $code);
    }

    /**
     * Respuesta con un payload propio, sin el formato estándar.
     *
     * Para consumidores que esperan una estructura específica, o cuando el
     * modelo ya devuelve el arreglo listo (por ejemplo, la creación de
     * cuentas de usuario).
     *
     * @param array $payload Arreglo que se serializa tal cual.
     * @param int   $code    Código HTTP (200 por defecto).
     */
    public static function raw(array $payload, int $code = 200): self
    {
        return new self($payload, $code);
    }

    /**
     * Envía la respuesta al navegador y termina la ejecución.
     *
     * Antes de escribir limpio todos los buffers de salida activos, para que
     * cualquier texto o advertencia impreso antes no contamine el JSON y
     * rompa el parseo en el front. Después fijo el Content-Type y el código
     * HTTP, imprimo el payload y hago exit.
     *
     * Como devuelve `never`, el código que venga después de llamarla no se
     * ejecuta; por eso los controladores no necesitan return tras responder.
     */
    public function send(): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        http_response_code($this->code);
        echo json_encode($this->payload);
        exit;
    }
}