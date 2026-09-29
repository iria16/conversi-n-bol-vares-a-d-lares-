<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\UbicacionModel;
use PDO;
use Throwable;

/**
 * UbicacionTrait
 *
 * Endpoint de la cascada de dirección (estado -> municipio -> parroquia) para
 * los formularios que la necesitan.
 *
 * Por qué un trait: la cascada es la misma en todos lados. `direccion` solo
 * guarda id_parroquia, así que el estado y el municipio nunca se escriben: se
 * deducen de la parroquia. Eso hace que el endpoint sea idéntico en el alta y
 * la edición del empleado y en el sub-modal de familiares del wizard de
 * inscripciones, y no tenga ninguna regla propia del módulo (lo único que
 * cambia es el nombre de la sección para el log de errores).
 *
 * El endpoint devuelve el nivel COMPLETO (todos los municipios, o todas las
 * parroquias) y el navegador filtra según lo que haya elegido arriba. Son
 * ~334 y ~42 filas: cabe de sobra en una respuesta y se pide una sola vez.
 *
 * El SQL vive en App\Models\UbicacionModel, que es el modelo de esta cascada;
 * este trait solo traduce la petición y la respuesta. Los niveles válidos los
 * declara el propio modelo (UbicacionModel::TIPOS) para que el endpoint y las
 * consultas no puedan desincronizarse.
 *
 * Contrato con la clase que lo usa (ver los `abstract` al final): debe
 * extender BaseController, directa o indirectamente, para heredar conexion(),
 * jsonResponse() y jsonError(). No hace falta declarar nada más.
 *
 * @author Logística
 * @package App\Traits
 */
trait UbicacionTrait
{
    /**
     * Responde un nivel completo de la cascada.
     *
     * Ya no pide el padre: el navegador recibe la lista entera y filtra, así
     * que los tres <select> del formulario están habilitados desde el
     * principio (elegir el estado no "abre" el municipio).
     *
     * @param string $seccion Nombre del módulo para el log de errores.
     */
    private function getUbicacionComun(string $seccion): void
    {
        $tipo = (string) ($_GET['tipo'] ?? '');

        if (!in_array($tipo, UbicacionModel::TIPOS, true)) {
            $this->jsonResponse(false, null, 'Tipo de ubicación inválido.', 400);
        }

        try {
            $opciones = (new UbicacionModel($this->conexion()))->nivelCompleto($tipo);
        } catch (Throwable $e) {
            $this->jsonError($e, $seccion);
        }

        $this->jsonResponse(true, $opciones);
    }

    // ---------- Contrato con BaseController ----------
    // Sin cuerpo a propósito: solo declaran la firma exacta que este trait
    // necesita, para que el editor resuelva las llamadas y para que PHP falle
    // en tiempo de declaración si algún día se usa en una clase sin ellas.

    abstract protected function conexion(): PDO;

    abstract protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never;

    abstract protected function jsonError(Throwable $e, string $seccion, int $code = 500): never;
}
