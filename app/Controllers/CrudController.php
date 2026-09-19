<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Creatable;
use App\Interfaces\Deletable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Traits\ListableIndexTrait;
use Throwable;

/**
 * Capa técnica de escritura: create/store/edit/update/delete/
 * toggleStatus, con las mismas garantías 405 de siempre cuando el
 * modelo no implementa la interfaz correspondiente.
 *
 * El listado paginado (index()) NO vive acá: vive en
 * ListableIndexTrait, compartido con ReadOnlyController. Listar y
 * escribir son capacidades independientes — un módulo puede necesitar
 * la primera sin la segunda (ver BitacoraController, que extiende
 * ReadOnlyController en vez de esta clase).
 */
abstract class CrudController extends BaseController
{
    use ListableIndexTrait;

    public function create(): void
    {
        try {
            require __DIR__ . "/../Views/{$this->viewPath}/create.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    public function store(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Creatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite creación de registros.', 405);
        }

        $data = $this->extractData($_POST);

        // Red de seguridad: si el hijo no sobrescribió extractData(), no se
        // insertan registros vacíos en silencio.
        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para guardar.', 422);
        }

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->create($data);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro creado correctamente.');
    }

    public function edit(): void
    {
        try {
            $id    = (int) ($_GET['id'] ?? 0);
            $model = $this->getModel();

            // Verifica que exista el método en el modelo antes de invocarlo
            $item = ($id > 0 && method_exists($model, 'getById')) ? $model->getById($id) : null;

            if (!$item) {
                $this->setFlash('error', 'El registro solicitado no existe.');
                $this->redirect('index');
            }

            require __DIR__ . "/../Views/{$this->viewPath}/edit.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    public function update(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para actualizar.', 422);
        }

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->update($id, $data);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro actualizado correctamente.');
    }

    public function delete(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Deletable) {
            $this->jsonResponse(false, null, 'Esta sección no admite eliminación de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $model->delete($id);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro eliminado correctamente.');
    }

    public function toggleStatus(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Toggleable) {
            $this->jsonResponse(false, null, 'Esta sección no admite cambio de estado.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $model->toggleStatus($id);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Estado actualizado correctamente.');
    }

    /**
     * Los hijos que usen store()/update() DEBEN sobrescribir estos dos ganchos.
     */
    protected function extractData(array $source): array
    {
        return [];
    }

    protected function validate(array $data): array
    {
        return [];
    }
}