<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Traits\ListableIndexTrait;

/**
 * Base para módulos que solo listan/exportan datos y nunca los
 * escriben desde el frontend (ej. bitácora de auditoría).
 *
 * A diferencia de CrudController, no expone create/store/edit/update/
 * delete/toggleStatus: si el front controller enruta por error hacia
 * alguno de esos, PHP falla con "método no encontrado" en vez de un
 * 405 silencioso. La ausencia del método documenta la intención mejor
 * que un guard en tiempo de ejecución.
 */
abstract class ReadOnlyController extends BaseController
{
    use ListableIndexTrait;
}