<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Updatable;
use App\Models\EmpleadoModel;
use PDO;
use Throwable;

/**
 * EmpleadoController
 *
 * Gestión del personal (Personal > Empleados): listado con filtros y
 * estadísticas, registro y edición por modales, detalle y activación/
 * desactivación.
 *
 * Extiende CrudController y aprovecha lo heredado:
 *  - index() viene de ListableIndexTrait; aquí solo aporto los filtros
 *    (getFiltersFromRequest) y las variables extra de la vista
 *    (getExtraIndexData).
 *  - store() y toggleStatus() los uso tal cual: el modelo implementa
 *    Creatable y Toggleable, y no implementa Deletable a propósito (al
 *    personal se le desactiva, no se le borra), así que delete() responde 405.
 *  - Sobrescribo create() y edit() porque este módulo trabaja con modales,
 *    y update() para validar la cédula excluyendo al propio empleado (el
 *    update() del padre llama a validate() sin el ID).
 *
 * Vistas: Views/empleados/ (index.php) y sus modales en Views/empleados/modals/.
 * La URL pública es `empleados` (la del menú), pero la clase se llama
 * EmpleadoController: el front controller (public/index.php) las une con
 * el mapa `$aliasControladores`.
 *
 * Rutas: `empleados/{accion}`. Los roles con acceso a cada acción (index,
 * create, show, edit, store, update, toggleStatus) se definen en la matriz
 * `$rutasPermitidas` del front controller, con la clave `empleados`, no aquí.
 *
 * @author Logística
 * @package App\Controllers
 */
class EmpleadoController extends CrudController
{
    /** Carpeta de vistas del módulo (Views/empleados). */
    protected string $viewPath  = 'empleados';

    /** Segmento de ruta del controlador; lo usa redirect() del BaseController. */
    protected string $routeName = 'empleados';

    /**
     * Crea la instancia concreta del modelo cuando sea requerida.
     *
     * @param PDO $pdo Conexión abierta por BaseController::getModel().
     */
    protected function createModel(PDO $pdo): object
    {
        return new EmpleadoModel($pdo);
    }

    /**
     * Lee los filtros del listado desde la URL (gancho de ListableIndexTrait).
     *
     * Uso las mismas claves que el formulario de la vista (`q`, `cargo`,
     * `estado`) para reutilizar el arreglo tanto en la consulta como para
     * repoblar los <select>, sin traducir nombres. Solo dejo pasar lo que la
     * vista puede generar: cargo numérico y estado activo/inactivo.
     *
     * @return array{q: string, cargo: string, estado: string}
     */
    protected function getFiltersFromRequest(): array
    {
        $cargo  = (string) ($_GET['cargo'] ?? '');
        $estado = (string) ($_GET['estado'] ?? '');

        return [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'cargo'  => ctype_digit($cargo) ? $cargo : '',
            'estado' => in_array($estado, ['activo', 'inactivo'], true) ? $estado : '',
        ];
    }

    /**
     * Variables extra que necesita la vista de listado.
     *
     * Se conserva la clave 'empleados' (en vez de solo 'items') para que
     * empleados/index.php no tenga que tocarse.
     *
     * Variables que entrega:
     *  - empleados:        filas de la página actual.
     *  - stats:            contadores para las tarjetas de resumen.
     *  - cargos:           cargos para el filtro.
     *  - gradosAcademicos, titulos, instituciones, tiposInstitucion, estados:
     *                      catálogos de los modales (wizard, título e institución).
     *
     * @param array $items   Filas de la página actual.
     * @param array $filters Filtros aplicados.
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model     = $this->getModel();
        $catalogos = $model->getCatalogosFormulario();

        return [
            'empleados'        => $items,
            'stats'            => $model->getEstadisticas(),
            'cargos'           => $model->getCargos(),
            'gradosAcademicos' => $catalogos['grados_academicos'] ?? [],
            'titulos'          => $catalogos['titulos'] ?? [],
            'instituciones'    => $catalogos['instituciones'] ?? [],
            'tiposInstitucion' => $catalogos['tipos_institucion'] ?? [],
            'estados'          => $catalogos['estados'] ?? [],
        ];
    }

    /**
     * Bloquea la página de creación independiente.
     * Ruta: empleados/create (GET).
     *
     * El registro se hace desde el wizard modal del listado (ver store()),
     * así que aquí anulo el create() del padre y vuelvo al listado.
     */
    public function create(): void
    {
        $this->redirect('index');
    }

    /**
     * Devuelve los datos de un empleado en JSON (modales "Ver" y "Editar").
     * Ruta: empleados/show?id=... (GET).
     *
     * Respuestas: 400 si el ID es inválido, 404 si no existe, 500 si el
     * modelo falla y 200 con los datos si todo sale bien.
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $empleado = $this->getModel()->getById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$empleado) {
            $this->jsonResponse(false, null, 'El empleado solicitado no existe.', 404);
        }

        $this->jsonResponse(true, $empleado);
    }

    /**
     * El modal de edición carga los mismos datos que el de detalle, así que
     * reutilizo show() en vez de duplicar la consulta.
     * Ruta: empleados/edit?id=... (GET).
     */
    public function edit(): void
    {
        $this->show();
    }

    /**
     * Actualiza un empleado (AJAX Modal).
     * Ruta: empleados/update (POST, responde JSON).
     *
     * Reemplaza al update() del padre por una sola razón: llamo a validate()
     * con el ID del registro, para que la comprobación de cédula duplicada
     * ignore al propio empleado.
     *
     * Respuestas: 405 si el modelo no es Updatable, 400 si el ID es
     * inválido, 422 por datos vacíos o errores de validación, 500 si falla.
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

        $data   = $this->extractData($_POST);
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->update($id, $data);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, null, 'Empleado actualizado correctamente.');
    }

    // ---------- Ganchos de CrudController (store() y update()) ----------

    /**
     * Extrae y normaliza los campos del formulario (wizard y modal de edición).
     *
     * Cubre lo obligatorio de `persona` y `empleado`: datos personales,
     * fecha de nacimiento, cargo y fecha de ingreso. La nacionalidad solo
     * la incluyo si viene con valor, para que la BD aplique su default
     * ('Venezolano'). Contacto, dirección, formación académica y foto se
     * suman aquí con los mismos `name` que tengan los inputs de los modales.
     *
     * @param array $source Normalmente $_POST.
     */
    protected function extractData(array $source): array
    {
        $texto = static fn (string $clave): string => trim((string) ($source[$clave] ?? ''));

        $data = [
            'tipo_documento'   => $texto('tipo_documento'),
            'numero_documento' => $texto('numero_documento'),
            'fecha_nacimiento' => $texto('fecha_nacimiento'),
            'primer_nombre'    => $texto('primer_nombre'),
            'segundo_nombre'   => $texto('segundo_nombre'),
            'primer_apellido'  => $texto('primer_apellido'),
            'segundo_apellido' => $texto('segundo_apellido'),
            'sexo'             => $texto('sexo'),
            'id_cargo'         => (int) ($source['id_cargo'] ?? 0),
            'fecha_ingreso'    => $texto('fecha_ingreso'),
        ];

        if ($texto('nacionalidad') !== '') {
            $data['nacionalidad'] = $texto('nacionalidad');
        }

        return $data;
    }

    /**
     * Valida los datos extraídos y devuelve los errores por campo.
     *
     * El segundo parámetro es opcional para no romper la firma del padre:
     * store() llama a validate($data) y mi update() le pasa el ID para
     * excluir al propio registro de la búsqueda de cédulas repetidas.
     *
     * Los valores permitidos de tipo de documento y sexo son los ENUM de la
     * tabla persona.
     *
     * @param array    $data      Datos devueltos por extractData().
     * @param int|null $ignorarId ID del empleado que se está editando, si aplica.
     * @return array<string, string> Errores por campo (vacío si todo está bien).
     */
    protected function validate(array $data, ?int $ignorarId = null): array
    {
        $errores = [];
        $hoy     = date('Y-m-d');

        if (!in_array($data['tipo_documento'], ['V', 'E', 'CE'], true)) {
            $errores['tipo_documento'] = 'Selecciona el tipo de documento.';
        }

        if ($data['numero_documento'] === '') {
            $errores['numero_documento'] = 'La cédula es obligatoria.';
        } elseif (!preg_match('/^[A-Za-z0-9-]{5,20}$/', $data['numero_documento'])) {
            $errores['numero_documento'] = 'La cédula solo admite letras, números y guiones (5 a 20 caracteres).';
        } elseif ($this->getModel()->existeDocumento($data['numero_documento'], $ignorarId)) {
            $errores['numero_documento'] = 'Ya existe un empleado registrado con esa cédula.';
        }

        if ($data['primer_nombre'] === '') {
            $errores['primer_nombre'] = 'El primer nombre es obligatorio.';
        }

        if ($data['primer_apellido'] === '') {
            $errores['primer_apellido'] = 'El primer apellido es obligatorio.';
        }

        if (!in_array($data['sexo'], ['F', 'M'], true)) {
            $errores['sexo'] = 'Selecciona el sexo.';
        }

        if (!$this->fechaValida($data['fecha_nacimiento'])) {
            $errores['fecha_nacimiento'] = 'Indica una fecha de nacimiento válida.';
        } elseif ($data['fecha_nacimiento'] > $hoy) {
            $errores['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser futura.';
        }

        if ($data['id_cargo'] <= 0) {
            $errores['id_cargo'] = 'Selecciona un cargo.';
        }

        if (!$this->fechaValida($data['fecha_ingreso'])) {
            $errores['fecha_ingreso'] = 'Indica una fecha de ingreso válida.';
        }

        return $errores;
    }

    /**
     * Comprueba que $fecha tenga formato Y-m-d y sea una fecha real
     * (rechaza, por ejemplo, 2024-02-31).
     */
    private function fechaValida(string $fecha): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}