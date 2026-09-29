<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\InstitucionModel;
use App\Support\Instituciones;
use PDO;
use Throwable;

/**
 * InstitucionTrait
 *
 * Alta rápida de una institución desde cualquier wizard que la necesite
 * como catálogo de apoyo (institución de procedencia de un estudiante en
 * InscripcionesController, institución de formación académica en
 * EmpleadoController, o cualquier módulo futuro que registre instituciones
 * al vuelo).
 *
 * Por qué un trait y no un servicio inyectado: todos los controladores del
 * sistema ya extienden BaseController y resuelven sus dependencias (PDO,
 * jsonResponse, capitalizarTitulo) a través de `$this`, no por constructor.
 * Un trait reutiliza ese mismo mecanismo sin introducir un patrón distinto
 * solo para este caso.
 *
 * Contrato con la clase que lo usa (ver los `abstract` al final): debe
 * extender BaseController, directa o indirectamente, para heredar
 * getModel(), jsonResponse(), jsonError() y capitalizarTitulo() (esta
 * última vía TextoTrait, que BaseController ya incluye). No hace falta
 * declarar nada más: los `abstract` solo documentan la dependencia y
 * permiten que el editor resuelva las llamadas dentro de este archivo.
 *
 * Lo que varía por módulo es la regla de qué tipo de institución es válido
 * en ese contexto. Esa regla no vive aquí: cada controlador la aporta como
 * closure ($validarTipo) y recibe el CÓDIGO del tipo. App\Support\
 * Instituciones es el enum con los tipos y sus reglas (TIPO_TITULO_ACADEMICO,
 * TIPO_PREESCOLAR, TIPO_ESCUELA) y `institucion.tipo` guarda ese código, así
 * que no hay nada que traducir: este trait solo resuelve lo que es común a
 * cualquier alta de institución (datos obligatorios, tipo válido, nombre
 * duplicado, inserción y respuesta JSON).
 *
 * @author Logística
 * @package App\Traits
 */
trait InstitucionTrait
{
    /**
     * Valida, crea y responde el alta de una institución.
     *
     * Flujo:
     *  1. Nombre y tipo son obligatorios (422 si falta alguno).
     *  2. El tipo tiene que ser uno del enum; si no, 422 (el <select> se
     *     puede manipular desde el navegador).
     *  3. $validarTipo decide si ese tipo es válido EN ESE CONTEXTO; recibe
     *     el código y debe devolver null si es válido o el mensaje de error
     *     si no lo es.
     *  4. Nombre duplicado (409): mismo criterio en todos los módulos, una
     *     institución es la misma entidad la registre quien la registre.
     *  5. Inserción; cualquier excepción del modelo se registra con
     *     jsonError() y corta la ejecución con un mensaje neutro.
     *
     * @param callable(string): ?string $validarTipo Regla de tipo propia del
     *        módulo que llama, sobre el código del enum. Devuelve el mensaje de
     *        error, o null si el tipo es válido en ese contexto.
     */
    private function guardarInstitucionComun(callable $validarTipo): void
    {
        $nombre = $this->capitalizarTitulo((string) ($_POST['nombre'] ?? ''));
        $tipo   = trim((string) ($_POST['tipo'] ?? ''));

        if ($nombre === '') {
            $this->jsonResponse(
                false,
                ['nombre' => 'Escribe el nombre de la institución.'],
                'Faltan datos de la institución.',
                422
            );
        }

        if ($tipo === '') {
            $this->jsonResponse(
                false,
                ['tipo' => 'Selecciona el tipo de institución.'],
                'Faltan datos de la institución.',
                422
            );
        }

        if (!Instituciones::esValido($tipo)) {
            $this->jsonResponse(
                false,
                ['tipo' => 'El tipo de institución seleccionado no existe.'],
                'Tipo de institución no válido.',
                422
            );
        }

        $model = new InstitucionModel($this->conexion());

        $errorTipo = $validarTipo($tipo);
        if ($errorTipo !== null) {
            $this->jsonResponse(
                false,
                ['tipo' => $errorTipo],
                'Tipo de institución no válido.',
                422
            );
        }

        if ($model->existeNombre($nombre)) {
            $this->jsonResponse(
                false,
                ['nombre' => 'Ya existe una institución con ese nombre.'],
                'Esa institución ya está registrada.',
                409
            );
        }

        try {
            $ok = $model->create(['nombre' => $nombre, 'tipo' => $tipo]);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        if (!$ok) {
            $this->jsonResponse(false, null, 'No se pudo guardar la institución.', 500);
        }

        $this->jsonResponse(true, [
            'id'     => (int) $this->pdo->lastInsertId(),
            'nombre' => $nombre,
        ], 'Institución registrada correctamente.');
    }


    // ---------- Contrato con BaseController ----------
    // Sin cuerpo a propósito: no reimplementan nada, solo declaran la firma
    // exacta que este trait necesita de la clase que lo use, para que el
    // editor resuelva las llamadas y para que PHP falle en tiempo de
    // declaración (no en producción) si algún día se usa en una clase que
    // no las tenga.

    abstract protected function conexion(): PDO;

    abstract protected function jsonResponse(bool $ok, mixed $data = null, ?string $message = null, int $code = 200): never;

    abstract protected function jsonError(Throwable $e, string $seccion, int $code = 500): never;

    abstract protected function capitalizarTitulo(string $texto): string;
}