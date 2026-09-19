<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Listable;
use App\Interfaces\Updatable;
use PDO;
use Throwable;

/**
 * CatalogoController
 *
 * Gestión de Catálogos del Sistema — un único controlador para todos
 * los catálogos maestros (grado, sección, turno, etc.), cada uno
 * resuelto a su propia clase de modelo vía self::CATALOGOS.
 *
 * Cómo funciona:
 *  - El catálogo activo se decide por el parámetro `tipo` (GET o POST).
 *    Si viene vacío o no existe en CATALOGOS, uso el primero de la lista.
 *  - Todos los catálogos comparten el mismo formulario y las mismas reglas:
 *    un solo campo `nombre`, capitalizado y validado igual para todos.
 *  - Para agregar un catálogo nuevo basta con: crear su modelo en
 *    App\Models, añadir una entrada en CATALOGOS y (si aplica) registrar
 *    el tipo en el whitelist del router.
 *
 * Requisitos de cada modelo de catálogo (los asumo, no los verifico en tiempo
 * de ejecución salvo donde se indica):
 *  - Implementar Listable (getAll / countAll).
 *  - Implementar Updatable para poder editar (update() lo comprueba).
 *  - Exponer getPrimaryKey(), getById() y existeNombre().
 *
 * NOTA DE RUTAS: el whitelist del router para 'catalogo' usa los
 * mismos nombres que CrudController (store/update/toggleStatus), así
 * que store() y toggleStatus() quedan heredados sin tocar. update()
 * sí se sobrescribe: a diferencia del resto de módulos, acá hace
 * falta pasarle el id actual a validate() como $excludeId para no
 * marcar el propio registro como "nombre duplicado".
 *
 * @author Logística
 * @package App\Controllers
 */
class CatalogoController extends CrudController
{
    /** Carpeta de vistas del módulo (Views/configuracion/catalogo). */
    protected string $viewPath  = 'configuracion/catalogo';

    /** Segmento de ruta del controlador; lo usa redirect() del BaseController. */
    protected string $routeName = 'catalogo';

    /**
     * Definición de los catálogos disponibles.
     *
     * La clave es el valor del parámetro `tipo`. Cada entrada define:
     *  - nombre:     etiqueta que ve el usuario en el selector de catálogos.
     *  - icono:      clase de Bootstrap Icons para ese catálogo.
     *  - modelClass: nombre de la clase en App\Models (sin namespace).
     *
     * Como el nombre de clase se arma dinámicamente en instantiateModel(),
     * esta lista funciona también como whitelist: solo se puede instanciar
     * lo que esté aquí.
     */
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

    /**
     * Clave del catálogo que se está gestionando en esta petición.
     * Se asigna en createModel(), es decir, la primera vez que se pide el modelo.
     */
    private string $catalogoActivo;

    /**
     * Fabrica el modelo del catálogo activo (gancho de BaseController::getModel()).
     *
     * Aprovecho este punto para fijar $catalogoActivo, así el resto de los
     * métodos lo pueden leer sin volver a resolverlo.
     *
     * @param PDO $pdo Conexión abierta por getModel().
     */
    protected function createModel(PDO $pdo): object
    {
        $this->catalogoActivo = $this->resolveTipoActivo();

        return $this->instantiateModel($this->catalogoActivo, $pdo);
    }

    /**
     * Determina qué catálogo se está pidiendo.
     *
     * Lee `tipo` de GET y, si no está, de POST. Si el valor no es una clave
     * válida de CATALOGOS, cae al primer catálogo de la lista, así una URL
     * manipulada nunca instancia una clase arbitraria.
     *
     * @return string Clave válida de self::CATALOGOS.
     */
    private function resolveTipoActivo(): string
    {
        $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? '';
        return array_key_exists($tipo, self::CATALOGOS) ? $tipo : array_key_first(self::CATALOGOS);
    }

    /**
     * Crea el modelo de un catálogo a partir de su clave.
     *
     * Arma el nombre completo de la clase (App\Models\XxxModel) con la
     * definición de CATALOGOS. Si no se pasa $pdo, usa la conexión ya abierta
     * en $this->pdo, que solo existe después de la primera llamada a getModel().
     *
     * @param string   $tipo Clave de self::CATALOGOS.
     * @param PDO|null $pdo  Conexión a usar; opcional.
     */
    private function instantiateModel(string $tipo, ?PDO $pdo = null): object
    {
        $fqcn = 'App\\Models\\' . self::CATALOGOS[$tipo]['modelClass'];

        return new $fqcn($pdo ?? $this->pdo);
    }

    /**
     * Listado del catálogo activo.
     * Ruta: catalogo/index (GET).
     *
     * Se sobrescribe en vez de usar getExtraIndexData(): acá los
     * elementos necesitan normalizarse (cada tabla tiene su propia PK,
     * la vista/JS siempre esperan 'id') y hay que instanciar TODOS los
     * catálogos para armar el selector de tipos — dos cosas que el
     * hook del padre no resuelve porque solo agrega variables extra,
     * no transforma $items ni cambia de modelo.
     *
     * Variables que le paso a la vista: $elementos, $total, $paginacion,
     * $filters, $catalogoActivo y $tiposCatalogo.
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
     * Formulario de edición de un registro del catálogo activo.
     * Ruta: catalogo/edit/{id}?tipo=... (GET).
     *
     * edit() se sobrescribe por lo mismo que en index(): normalización
     * de PK, y preservar ?tipo= al redirigir si el id no existe (el
     * edit() del padre redirige a index sin ese query param y manda al
     * admin al primer catálogo en vez de quedarse donde estaba).
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

    /**
     * Agrega la clave 'id' a cada elemento, tomada de la PK real de la tabla.
     *
     * Cada catálogo tiene su propia clave primaria (id_grado, id_turno, ...),
     * pero la vista y catalogo.js siempre trabajan con 'id'. Mantengo la PK
     * original y solo añado 'id' como alias.
     *
     * @param array $elementos Filas tal como las devuelve el modelo.
     * @return array Las mismas filas con la clave 'id' añadida (null si falta la PK).
     */
    private function normalizarPrimaryKey(array $elementos): array
    {
        $pk = $this->getModel()->getPrimaryKey();

        foreach ($elementos as &$el) {
            $el['id'] = $el[$pk] ?? null;
        }
        unset($el);

        return $elementos;
    }

    /**
     * Arma los datos del selector de catálogos (pestañas o menú lateral).
     *
     * Para cada catálogo devuelve su clave, nombre, icono y el total de
     * registros. Reutilizo el modelo activo (ya instanciado) y creo uno nuevo
     * solo para los demás.
     *
     * @return array<int, array{clave: string, nombre: string, icono: string, total: int}>
     */
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

    /**
     * Lee los filtros del listado desde la URL (gancho de CrudController).
     *
     * Acepto `q` o `search` para el texto de búsqueda, y `estado` para
     * filtrar por activo/inactivo. Los devuelvo con las mismas claves que
     * espera el modelo.
     *
     * @return array{search: string, estado: string}
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'search' => trim($_GET['q'] ?? $_GET['search'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
        ];
    }

    /**
     * Extrae y normaliza los datos del formulario (gancho de CrudController).
     *
     * El nombre se capitaliza acá (capitalizarTitulo(), heredado de
     * TextoTrait) antes de validar y guardar, así que tanto store()
     * como update() —ambos parten de extractData()— quedan cubiertos
     * sin duplicar la lógica. "licenciado en informatica" se guarda
     * como "Licenciado en Informática".
     *
     * @param array $source Normalmente $_POST.
     * @return array{nombre: string}
     */
    protected function extractData(array $source): array
    {
        return [
            'nombre' => $this->capitalizarTitulo(trim($source['nombre'] ?? '')),
        ];
    }

    /**
     * Valida el nombre del elemento (gancho de CrudController).
     *
     * Reglas, en orden (devuelve el primer error que encuentre):
     *  1. Obligatorio.
     *  2. Longitud mínima: 1 carácter para 'seccion' (las secciones son
     *     letras sueltas como "A" o "B") y 2 para el resto.
     *  3. Longitud máxima de 50 caracteres.
     *  4. No repetido dentro del catálogo activo. En edición, $excludeId
     *     excluye el propio registro para que no se marque como duplicado.
     *
     * @param array    $data      Datos ya normalizados por extractData().
     * @param int|null $excludeId ID del registro que se está editando, o null al crear.
     * @return array<string, string> Errores por campo; vacío si todo está bien.
     */
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
     * Actualiza un registro del catálogo activo.
     * Ruta: catalogo/update (POST, responde JSON).
     *
     * Se sobrescribe (no queda heredado) porque CrudController::update()
     * llama a validate($data) con un solo argumento, y acá hace falta
     * pasar $id como $excludeId para la validación de nombre duplicado.
     *
     * Respuestas:
     *  - 405 si el modelo del catálogo no implementa Updatable.
     *  - 400 si el ID es inválido.
     *  - 422 con los errores por campo si la validación falla.
     *  - 500 (vía jsonError) si el modelo lanza una excepción.
     *  - 200 con el nombre normalizado si todo sale bien.
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