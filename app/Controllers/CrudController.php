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
 * CrudController
 *
 * Capa técnica de escritura: create/store/edit/update/delete/
 * toggleStatus, con las mismas garantías 405 de siempre cuando el
 * modelo no implementa la interfaz correspondiente.
 *
 * El listado paginado (index()) NO vive acá: vive en
 * ListableIndexTrait, compartido con ReadOnlyController. Listar y
 * escribir son capacidades independientes — un módulo puede necesitar
 * la primera sin la segunda (ver BitacoraController, que extiende
 * ReadOnlyController en vez de esta clase).
 *
 * Contrato con los modelos (por interfaz, verificado con instanceof):
 *  - store()        → Creatable::create()
 *  - update()       → Updatable::update()
 *  - delete()       → Deletable::delete()
 *  - toggleStatus() → Toggleable::toggleStatus()
 * Si el modelo no la implementa, la acción responde 405 en vez de fallar
 * con un error de método inexistente. Así un mismo controlador puede
 * exponer solo lo que su modelo realmente soporta.
 *
 * Contrato con los controladores hijos:
 *  - Sobrescribir extractData() y validate() si usan store() o update().
 *  - Definir $viewPath y $routeName (ver BaseController).
 *  - Si necesitan un comportamiento distinto, sobrescriben la acción
 *    completa (como hace CatalogoController con index(), edit() y update()).
 *
 * Convención de respuestas: las acciones de escritura responden JSON
 * (las consume el front con fetch); create() y edit() renderizan vistas.
 * Los errores inesperados se registran en log con jsonError() o handleError().
 *
 * @author Logística
 * @package App\Controllers
 */
abstract class CrudController extends BaseController
{
    use ListableIndexTrait;

    /**
     * Muestra el formulario de creación.
     * Ruta: {controlador}/create (GET).
     *
     * Solo renderiza Views/{viewPath}/create.php. La creación real ocurre
     * en store().
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
     * Crea un registro nuevo.
     * Ruta: {controlador}/store (POST, responde JSON).
     *
     * Flujo:
     *  1. El modelo debe implementar Creatable, o respondo 405.
     *  2. extractData() arma los datos a partir de $_POST.
     *  3. Si los datos vienen vacíos, lo trato como error del hijo (olvidó
     *     sobrescribir extractData()) y respondo 422.
     *  4. validate() devuelve los errores por campo; si hay, respondo 422.
     *  5. Guardo con create(); si el modelo lanza, jsonError() lo registra
     *     y responde 500 genérico.
     */
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

    /**
     * Muestra el formulario de edición de un registro.
     * Ruta: {controlador}/edit/{id} (GET).
     *
     * Busca el registro con getById(). Si el id no es válido, el modelo no
     * tiene ese método o no existe el registro, dejo un mensaje flash de
     * error y redirijo al listado. La vista recibe el registro en $item.
     */
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

    /**
     * Actualiza un registro existente.
     * Ruta: {controlador}/update (POST, responde JSON).
     *
     * Mismo flujo que store(), con dos diferencias: el modelo debe
     * implementar Updatable y el ID llega en $_POST['id'] (400 si es
     * inválido).
     *
     * Ojo: validate() se llama solo con $data, sin el ID. Si un hijo valida
     * unicidad (nombre duplicado, cédula repetida), su propio registro se
     * marcaría como duplicado; en ese caso el hijo debe sobrescribir update()
     * y pasar el ID a su validate() (ver CatalogoController).
     */
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

    /**
     * Elimina un registro.
     * Ruta: {controlador}/delete (POST, responde JSON).
     *
     * Requiere que el modelo implemente Deletable. Si el módulo prefiere
     * desactivar en lugar de borrar, su modelo simplemente no implementa esa
     * interfaz y se usa toggleStatus().
     */
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

    /**
     * Activa o desactiva un registro (ACTIVO/INACTIVO).
     * Ruta: {controlador}/toggleStatus (POST, responde JSON).
     *
     * Requiere que el modelo implemente Toggleable. El cambio de estado lo
     * decide el modelo: aquí solo valido y delego.
     */
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

    // Los hijos que usen store()/update() DEBEN sobrescribir estos dos ganchos.

    /**
     * Gancho: extrae y normaliza del formulario los campos que se van a guardar.
     *
     * Devuelvo un arreglo vacío a propósito: store() y update() lo detectan y
     * abortan con 422, así un hijo que olvide sobrescribirlo no inserta
     * registros vacíos.
     *
     * @param array $source Normalmente $_POST.
     * @return array Datos listos para validate() y para el modelo.
     */
    protected function extractData(array $source): array
    {
        return [];
    }

    /**
     * Gancho: valida los datos extraídos.
     *
     * Por defecto no reporta errores. Los hijos lo sobrescriben y devuelven
     * un arreglo campo => mensaje; si viene vacío, se considera válido.
     *
     * @param array $data Datos devueltos por extractData().
     * @return array<string, string> Errores por campo.
     */
    protected function validate(array $data): array
    {
        return [];
    }
}