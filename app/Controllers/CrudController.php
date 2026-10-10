<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Creatable;
use App\Interfaces\Deletable;
use App\Interfaces\Findable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Traits\ListableIndexTrait;
use Throwable;

/**
 * Capa de escritura: create/store/edit/update/delete/toggleStatus. Cada acción
 * exige la interfaz del modelo (Creatable, Updatable, Deletable, Toggleable) y
 * responde 405 si falta. El listado vive en ListableIndexTrait; los hijos
 * sobrescriben extractData() y validate(), y definen $viewPath y$routeName.
 *
 * @author Logística
 * @package App\Controllers
*/
abstract class CrudController extends BaseController
{
    use ListableIndexTrait;

    /**
 * Muestra el formulario de creación. Ruta: {controlador}/create (GET).
 * Solo renderiza create.php; el alta real es store().
*/
    public function create(): void
    {
        try {
            require __DIR__ . "/../Views/{$this->viewPath}/create.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
 * Crea un registro. Ruta: {controlador}/store (POST, JSON).
 * Responde 405 si el modelo no es Creatable, 422 por datos vacíos o
 * errores de validación, 500 si el modelo lanza y 200 si todo sale bien.
*/
    public function store(): void
    {
        $model =$this->getModel();

        if (!$model instanceof Creatable) {$this->jsonResponse(false, null, 'Esta sección no admite creación de registros.', 405);
        }

        $data = $this->extractData($_POST);

        // Si el hijo no sobrescribió extractData(), no se insertan registros vacíos.
        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para guardar.', 422);
        }

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->jsonResponse(false,$errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->create($data);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro creado correctamente.');
    }

    /**
 * Muestra el formulario de edición. Ruta: {controlador}/edit/{id} (GET).
 * Si el id no es válido o el registro no existe, deja un flash de error
 * y vuelve al listado. La vista recibe el registro en $item.
*/
    public function edit(): void
    {
        try {
            $id    = (int) ($_GET['id'] ?? 0);
            $model =$this->getModel();

            // Verifica que el modelo implemente Findable para leer el registro.
            $item = ($id > 0 &&$model instanceof Findable) ? $model->getById($id) : null;

            if (!$item) {
                $this->setFlash('error', 'El registro solicitado no existe.');$this->redirect('index');
            }

            require __DIR__ . "/../Views/{$this->viewPath}/edit.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
 * Actualiza un registro. Ruta: {controlador}/update (POST, JSON).
 * Mismo flujo que store(), con Updatable y el id en $_POST['id'] (400 si
 * es inválido). validate() recibe el id para permitir validar unicidad sin
 * obligar al hijo a sobrescribir este método.
*/
    public function update(): void
    {
        $model =$this->getModel();

        if (!$model instanceof Updatable) {$this->jsonResponse(false, null, 'Esta sección no admite edición de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {$this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para actualizar.', 422);
        }

        // Pasamos el $id a la validación.
        $errors =$this->validate($data,$id);

        if (!empty($errors)) {
            $this->jsonResponse(false,$errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->update($id,$data);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro actualizado correctamente.');
    }

    /**
 * Elimina un registro. Ruta: {controlador}/delete (POST, JSON).
 * Requiere Deletable; si el módulo desactiva en vez de borrar, su
 * modelo no la implementa y se usa toggleStatus().
*/
    public function delete(): void
    {
        $model =$this->getModel();

        if (!$model instanceof Deletable) {$this->jsonResponse(false, null, 'Esta sección no admite eliminación de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {$this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $model->delete($id);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro eliminado correctamente.');
    }

    /**
 * Activa o desactiva un registro. Ruta: {controlador}/toggleStatus (POST, JSON).
 * Requiere Toggleable; el cambio lo decide el modelo. Si devuelve false,
 * responde ok = false y el front debe revisar el campo `ok`.
*/
    public function toggleStatus(): void
    {
        $model =$this->getModel();

        if (!$model instanceof Toggleable) {$this->jsonResponse(false, null, 'Esta sección no admite cambio de estado.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {$this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $this->beforeToggleStatus($id);

        try {
            $ok = $model->toggleStatus($id);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse($ok, null,$ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.');
    }

    /**
 * Gancho previo al cambio de estado (por defecto no hace nada).
 * Los hijos lo usan para bloquear la transición con jsonResponse(),
 * que corta la ejecución antes de tocar el modelo.
*/
    protected function beforeToggleStatus(int $id): void
    {
    }

    // Los hijos que usen store()/update() DEBEN sobrescribir estos dos ganchos.

    /**
 * Gancho: extrae y normaliza los campos a guardar (normalmente de $_POST).
 * Devuelve [] a propósito: store() y update() lo detectan y abortan con
 * 422, para que un hijo que lo olvide no inserte registros vacíos.
*/
    protected function extractData(array $source): array
    {
        return [];
    }

    /**
 * Gancho: valida los datos de extractData() y devuelve campo => mensaje.
 * Recibe el ID del registro en caso de edición para omitirlo en validaciones de unicidad.
 * Por defecto no reporta errores; un arreglo vacío significa válido.
 *
 * @param array<string, mixed> $data
 * @param int|null $id
 * @return array<string, string>
*/
    protected function validate(array $data, ?int $id = null): array
    {
        return [];
    }
}