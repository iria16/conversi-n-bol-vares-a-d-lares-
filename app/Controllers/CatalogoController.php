<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Listable;
use App\Interfaces\Updatable;
use PDO;
use Throwable;

/**
 * Gestión de Catálogos del Sistema — un único controlador para todos
 * los catálogos maestros (grado, sección, turno, etc.), cada uno
 * resuelto a su propia clase de modelo vía self::CATALOGOS.
 *
 * NOTA DE RUTAS: el whitelist del router para 'catalogo' usa los
 * mismos nombres que CrudController (store/update/toggleStatus), así
 * que store() y toggleStatus() quedan heredados sin tocar. update()
 * sí se sobrescribe: a diferencia del resto de módulos, acá hace
 * falta pasarle el id actual a validate() como $excludeId para no
 * marcar el propio registro como "nombre duplicado".
 */
class CatalogoController extends CrudController
{
    protected string $viewPath  = 'configuracion/catalogo';
    protected string $routeName = 'catalogo';

    private const CATALOGOS = [
        'grado'           => ['nombre' => 'Grado',              'icono' => 'bi-mortarboard',       'modelClass' => 'GradoModel'],
        'seccion'         => ['nombre' => 'Sección',            'icono' => 'bi-diagram-3',          'modelClass' => 'SeccionModel'],
        'turno'           => ['nombre' => 'Turno',              'icono' => 'bi-clock',              'modelClass' => 'TurnoModel'],
        'tipo_documento'  => ['nombre' => 'Tipo de Documento',  'icono' => 'bi-file-earmark-text',  'modelClass' => 'TipoDocumentoModel'],
        'grado_academico' => ['nombre' => 'Grado Académico',    'icono' => 'bi-award',              'modelClass' => 'GradoAcademicoModel'],
        'titulo'          => ['nombre' => 'Título',             'icono' => 'bi-person-badge',       'modelClass' => 'TituloModel'],
        'tipo_asignacion' => ['nombre' => 'Tipo de Asignación', 'icono' => 'bi-briefcase',          'modelClass' => 'TipoAsignacionModel'],
        'rol'             => ['nombre' => 'Rol',                'icono' => 'bi-shield-lock',        'modelClass' => 'RolModel'],
        'parentesco'      => ['nombre' => 'Parentesco',         'icono' => 'bi-people',              'modelClass' => 'ParentescoModel'],
        'motivo_retiro'   => ['nombre' => 'Motivo de Retiro',   'icono' => 'bi-box-arrow-right',    'modelClass' => 'MotivoRetiroModel'],
    ];

    private string $catalogoActivo;

    protected function createModel(PDO $pdo): object
    {
        $this->catalogoActivo = $this->resolveTipoActivo();

        return $this->instantiateModel($this->catalogoActivo, $pdo);
    }

    private function resolveTipoActivo(): string
    {
        $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';
        return array_key_exists($tipo, self::CATALOGOS) ? $tipo : array_key_first(self::CATALOGOS);
    }

    private function instantiateModel(string $tipo, ?PDO $pdo = null): object
    {
        $fqcn = 'App\\Models\\' . self::CATALOGOS[$tipo]['modelClass'];

        return new $fqcn($pdo ?? $this->pdo);
    }

    /**
     * Se sobrescribe en vez de usar getExtraIndexData(): acá los
     * elementos necesitan normalizarse (cada tabla tiene su propia PK,
     * la vista/JS siempre esperan 'id') y hay que instanciar TODOS los
     * catálogos para armar el selector de tipos — dos cosas que el
     * hook del padre no resuelve porque solo agrega variables extra,
     * no transforma $items ni cambia de modelo.
     */
    public function index(): void
    {
        try {
            $model   = $this->getModel();
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $elementos = $model instanceof Listable
                ? $this->normalizarPrimaryKey($model->getAll($filters, $page, $this->perPage))
                : [];
            $total = $model instanceof Listable ? $model->countAll($filters) : 0;

            $paginacion     = $this->buildPagination($total, $page, $this->perPage);
            $catalogoActivo = $this->catalogoActivo;
            $tiposCatalogo  = $this->buildTiposCatalogo();

            require __DIR__ . "/../Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    /**
     * edit() se sobrescribe por lo mismo que ya tenías documentado:
     * normalización de PK, y preservar ?tipo= al redirigir si el id no
     * existe (el edit() del padre redirige a index sin ese query param
     * y manda al admin al primer catálogo en vez de quedarse donde
     * estaba).
     */
    public function edit(): void
    {
        try {
            $model = $this->getModel();
            $id    = (int)($_GET['id'] ?? 0);
            $item  = ($id > 0 && method_exists($model, 'getById')) ? $model->getById($id) : null;

            if (!$item) {
                $this->setFlash('error', 'El registro solicitado no existe.');
                $this->redirect('index', ['tipo' => $this->catalogoActivo]);
            }

            // Misma normalización de PK que index(): un solo lugar
            // decide cómo se mapea la PK real de cada catálogo a 'id',
            // para que listado y formulario de edición no puedan
            // divergir si esa regla cambia.
            [$item] = $this->normalizarPrimaryKey([$item]);

            $catalogoActivo = $this->catalogoActivo;

            require __DIR__ . "/../Views/{$this->viewPath}/edit.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    private function normalizarPrimaryKey(array $elementos): array
    {
        $pk = $this->getModel()->getPrimaryKey();

        foreach ($elementos as &$el) {
            $el['id'] = $el[$pk] ?? null;
        }
        unset($el);

        return $elementos;
    }

    private function buildTiposCatalogo(): array
    {
        $tipos = [];

        foreach (self::CATALOGOS as $clave => $info) {
            $model = $clave === $this->catalogoActivo
                ? $this->getModel()
                : $this->instantiateModel($clave);

            $tipos[] = [
                'clave'  => $clave,
                'nombre' => $info['nombre'],
                'icono'  => $info['icono'],
                'total'  => $model->countAll([]),
            ];
        }

        return $tipos;
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'search' => trim($_GET['q'] ?? $_GET['search'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
        ];
    }

    /**
     * El nombre se capitaliza acá (capitalizarTitulo(), heredado de
     * TextoTrait) antes de validar y guardar, así que tanto store()
     * como update() —ambos parten de extractData()— quedan cubiertos
     * sin duplicar la lógica. "licenciado en informatica" se guarda
     * como "Licenciado en Informática".
     */
    protected function extractData(array $source): array
    {
        return [
            'nombre' => $this->capitalizarTitulo(trim($source['nombre'] ?? '')),
        ];
    }

    protected function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        $model  = $this->getModel();

        $minimo = $this->catalogoActivo === 'seccion' ? 1 : 2;
        $maximo = 50;

        if (empty($data['nombre'])) {
            $errors['nombre'] = 'El nombre del elemento es obligatorio.';
        } elseif (mb_strlen($data['nombre']) < $minimo) {
            $errors['nombre'] = "El nombre debe tener al menos {$minimo} caracter" . ($minimo > 1 ? 'es' : '') . '.';
        } elseif (mb_strlen($data['nombre']) > $maximo) {
            $errors['nombre'] = "El nombre no puede superar los {$maximo} caracteres.";
        } elseif ($model->existeNombre($data['nombre'], $excludeId)) {
            $errors['nombre'] = 'Ya existe un registro con este nombre en el catálogo activo.';
        }

        return $errors;
    }

    /**
     * Se sobrescribe (no queda heredado) porque CrudController::update()
     * llama a validate($data) con un solo argumento, y acá hace falta
     * pasar $id como $excludeId para la validación de nombre duplicado.
     */
    public function update(): void
    {
        $model = $this->getModel();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Este catálogo no admite edición de registros.', 405);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data   = $this->extractData($_POST);
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->update($id, $data);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        // Se devuelve $data (ya pasado por capitalizarTitulo() en
        // extractData()) para que el frontend pueda pintar la fila con
        // el nombre normalizado sin recargar la página; si solo se
        // devolviera null, catalogo.js quedaría obligado a usar el
        // valor crudo del formulario, que no coincide con lo guardado.
        $this->jsonResponse(true, $data, 'Elemento actualizado correctamente.');
    }
}
