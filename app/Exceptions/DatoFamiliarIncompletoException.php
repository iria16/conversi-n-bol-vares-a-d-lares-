<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * DatoFamiliarIncompletoException
 *
 * Se lanza cuando un familiar no se puede guardar tal como llegó, porque un
 * dato obligatorio no está o no existe en el catálogo del que debería venir.
 * Hoy son tres:
 *
 *   1. el parentesco vacío sin ser "no es familiar" (regla: la única manera de
 *      que el parentesco esté vacío es esa);
 *   2. un parentesco que no está en el catálogo `parentesco`;
 *   3. el nivel de instrucción o la ocupación del representante legal, que son
 *      obligatorios para él y se resuelven por nombre contra el catálogo.
 *
 * En los tres casos, seguir guardando significaría escribir un dato falso: un
 * vínculo vacío, o el primer registro de un catálogo cualquiera como si
 * alguien lo hubiera elegido.
 *
 * El navegador y el controlador ya lo rechazan (el campo es `required` y
 * validarRepresentanteLegal() lo comprueba). Esta excepción cubre el hueco que
 * queda después de resolver el nombre: si el payload trae un parentesco que no
 * existe en el catálogo —porque el catálogo cambió entre que se abrió el
 * formulario y se guardó, o porque el JSON se armó a mano— el modelo lo
 * resolvería a NULL y escribiría la fila con el parentesco vacío en silencio.
 * Antes se documentaba como "best-effort: solo con parentesco y catálogos
 * resolubles", que es justo el comportamiento que esta regla prohíbe.
 *
 * Se lanza desde InscripcionesModel::insertFamiliares() —el único sitio que
 * escribe— para que la garantía no dependa de quién llamó. create() la
 * propaga tras hacer rollBack (la inscripción no se guarda), y CrudController
 * la convierte en un 422 con el mensaje, en vez de un 500.
 *
 * @author Logística
 * @package App\Exceptions
 */
class DatoFamiliarIncompletoException extends RuntimeException
{
}
