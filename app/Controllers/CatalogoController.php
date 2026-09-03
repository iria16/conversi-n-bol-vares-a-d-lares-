<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/CatalogoModel.php';
require_once __DIR__ . '/../Models/SchoolYearModel.php';
require_once __DIR__ . '/../Models/GradeModel.php';
require_once __DIR__ . '/../Models/SectionModel.php';
require_once __DIR__ . '/../Models/ShiftModel.php';

class CatalogoController extends BaseController
{
    protected string $viewPath  = 'settings';
    protected string $routeName = 'catalogo';
    protected array $allowedRoles = ['admin'];
    protected int $perPage = 10;

    // Copia propia del PDO, guardada dentro de createModel(). No reutilizamos
    // una posible propiedad $pdo de BaseController para evitar choques de
    // visibilidad/tipo con la clase padre.
    private PDO $pdoCatalogo;

    // Registro de tipos de catálogo soportados: clave => [nombre visible, ícono, clase del Model]
    private const TIPOS = [
        'anioEscolar' => ['nombre' => 'Año Escolar', 'icono' => 'bi-calendar3',     'model' => SchoolYearModel::class],
        'grado'       => ['nombre' => 'Grado',       'icono' => 'bi-mortarboard',   'model' => GradeModel::class],
        'seccion'     => ['nombre' => 'Sección',     'icono' => 'bi-diagram-3',     'model' => SectionModel::class],
        'turno'       => ['nombre' => 'Turno',       'icono' => 'bi-clock-history', 'model' => ShiftModel::class],
    ];

    private string $tipoActivo;

    // El tipo puede venir por GET (index) o por POST (los AJAX); $_REQUEST cubre ambos
    // porque el controlador se instancia una sola vez por petición.
    protected function createModel(PDO $pdo)
    {
        $this->pdoCatalogo = $pdo;

        $tipo = $_REQUEST['tipo'] ?? array_key_first(self::TIPOS);

        if (!isset(self::TIPOS[$tipo])) {
            $tipo = array_key_first(self::TIPOS);
        }

        $this->tipoActivo = $tipo;
        $claseModel = self::TIPOS[$tipo]['model'];

        return new $claseModel($pdo);
    }

    // -------------------------------------------------------------
    // LISTADO (selector de catálogo + filtros + tabla)
    // -------------------------------------------------------------
    public function index(): void
    {
        try {
            $paginaActual = max(1, (int)($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $elementosRaw = $this->model->getAll($filters, $paginaActual, $this->perPage);
            $total        = $this->model->countAll($filters);

            $elementos = array_map(static function (array $row): array {
                return [
                    'id'     => (int) $row['id'],
                    'nombre' => $row['nombre'],
                    'estado' => strtolower($row['estado']), // ACTIVO -> activo, INACTIVO -> inactivo
                ];
            }, $elementosRaw);

            $tiposCatalogo  = $this->buildTiposCatalogo();
            $catalogoActivo = $this->tipoActivo;

            $paginacion = [
                'desde'         => $total > 0 ? (($paginaActual - 1) * $this->perPage) + 1 : 0,
                'hasta'         => min($paginaActual * $this->perPage, $total),
                'total'         => $total,
                'pagina_actual' => $paginaActual,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            require APP_PATH . "/Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Catálogos');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim($_GET['q'] ?? ''),
            'estado' => $_GET['estado'] ?? '',
        ];
    }

    // Arma la lista para el <select> de tipos, con el conteo total de cada catálogo
    private function buildTiposCatalogo(): array
    {
        $tipos = [];

        foreach (self::TIPOS as $clave => $meta) {
            // Reutiliza $this->model si ya es del tipo activo, para no reconectar de más
            $model = ($clave === $this->tipoActivo) ? $this->model : new $meta['model']($this->pdoCatalogo);

            $tipos[] = [
                'clave'  => $clave,
                'nombre' => $meta['nombre'],
                'icono'  => $meta['icono'],
                'total'  => $model->countAll(),
            ];
        }

        return $tipos;
    }

    // -------------------------------------------------------------
    // NUEVO ELEMENTO (AJAX) — modal "+ Nuevo Elemento"
    // -------------------------------------------------------------
    public function storeAjax(): void
    {
        header('Content-Type: application/json');

        $data   = $this->extractData($_POST);
        $errors = $this->validate($data);

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => implode(' ', $errors)]);
            exit;
        }

        try {
            $resultado = $this->model->crearElemento($data['nombre']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Catálogos');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
        }
        echo json_encode($resultado);
        exit;
    }

    // -------------------------------------------------------------
    // ACTIVAR / DESACTIVAR ELEMENTO (AJAX) — switch en cada fila
    // -------------------------------------------------------------
    public function toggleEstadoAjax(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $resultado = $this->model->toggleEstado($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Catálogos');
            return;
        }

        if (!$resultado['ok']) {
            http_response_code(400);
            $resultado['mensaje'] = 'No se pudo actualizar el estado.';
        }
        echo json_encode($resultado);
        exit;
    }

    // -------------------------------------------------------------
    // EDITAR NOMBRE (AJAX) — ícono lápiz en cada fila
    // -------------------------------------------------------------
    public function updateAjax(): void
    {
        header('Content-Type: application/json');

        $id     = (int)($_POST['id'] ?? 0);
        $data   = $this->extractData($_POST);
        $errors = $this->validate($data);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'ID inválido']);
            exit;
        }

        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => implode(' ', $errors)]);
            exit;
        }

        try {
            $ok = $this->model->updateNombre($id, $data['nombre']);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Catálogos');
            return;
        }

        if (!$ok) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo actualizar el elemento.']);
            exit;
        }

        echo json_encode(['ok' => true, 'mensaje' => 'Elemento actualizado correctamente.']);
        exit;
    }

    // -------------------------------------------------------------
    // Requeridos por BaseController (usados en storeAjax()/updateAjax())
    // -------------------------------------------------------------
    protected function extractData(array $source): array
    {
        return [
            'nombre' => trim($source['nombre'] ?? ''),
        ];
    }

    protected function validate(array $data): array
    {
        $errores = [];

        if ($data['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($data['nombre']) > 100) {
            $errores[] = 'El nombre no puede superar los 100 caracteres.';
        }

        return $errores;
    }
}