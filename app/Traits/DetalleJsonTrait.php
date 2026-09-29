<?php

declare(strict_types=1);

namespace App\Traits;

use Throwable;

/**
 * DetalleJsonTrait
 *
 * Responde el detalle de un registro en JSON con el flujo estándar:
 * id inválido → 400, no encontrado → 404, error del modelo → 500
 * (vía jsonError) y 200 con los datos en éxito. Compartido por los
 * módulos cuyo "ver detalle" es un endpoint AJAX (cuentas de usuario
 * y recuperación de acceso).
 *
 * Requiere que la clase que lo usa (un BaseController) provea
 * getModel(), jsonResponse() y jsonError().
 */
trait DetalleJsonTrait
{
    protected function responderDetalle(
        string $metodoModelo,
        string $mensajeIdInvalido,
        string $mensajeNoEncontrado,
        string $seccion
    ): void {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, null, $mensajeIdInvalido, 400);
        }

        $modelo = $this->getModel();

        try {
            $datos = $modelo->$metodoModelo($id);

            if ($datos === null || $datos === false) {
                $this->jsonResponse(false, null, $mensajeNoEncontrado, 404);
            }

            $this->jsonResponse(true, $datos);
        } catch (Throwable $e) {
            $this->jsonError($e, $seccion);
        }
    }
}