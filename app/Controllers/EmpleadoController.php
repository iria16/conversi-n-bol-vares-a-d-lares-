<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\DocumentoDuplicadoException;
use App\Interfaces\Creatable;
use App\Interfaces\Updatable;
use App\Models\EmpleadoModel;
use App\Models\TituloModel;
use App\Support\Instituciones;
use App\Support\Documento;
use App\Traits\ContactoValidacionTrait;
use App\Traits\DireccionValidacionTrait;
use App\Traits\FotoSubidaTrait;
use App\Traits\InstitucionTrait;
use App\Traits\PersonaValidacionTrait;
use App\Traits\UbicacionTrait;
use App\Traits\TextoTrait;
use App\Traits\ValidacionFechaTrait;
use PDO;
use Throwable;

/**
 * Controlador de Personal > Empleados (todo el CRUD va por modales).
 * Hereda index() y toggleStatus(); no hay delete (405): se desactiva.
 * Sobrescribe store()/update() para distinguir la cédula duplicada.
 */
class EmpleadoController extends CrudController
{
    use PersonaValidacionTrait;
    use ValidacionFechaTrait;
    use ContactoValidacionTrait;
    use DireccionValidacionTrait;
    use FotoSubidaTrait;
    use TextoTrait;
    use InstitucionTrait;
    use UbicacionTrait;

    /** Opciones de FotoSubidaTrait para las fotos de empleados. */
    private const FOTO_EMPLEADO = [
        'campo'   => 'foto',
        'carpeta' => 'empleados',
        'prefijo' => 'empleado_',
    ];

    protected string $viewPath  = 'empleados';
    protected string $routeName = 'empleado';

    /** Crea el modelo del módulo. */
    protected function createModel(PDO $pdo): object
    {
        return new EmpleadoModel($pdo);
    }

    /**
     * Filtros del listado (q, cargo, estado) leídos de la URL.
     * Solo deja pasar cargo numérico y estado activo/inactivo.
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
     * Variables extra de la vista: filas como $empleados, stats, cargos
     * y catálogos de los modales.
     */
    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model     = $this->modelo();
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
     * Sin página de creación: el alta es el wizard del listado.
     * Ruta: empleados/create (GET).
     */
    public function create(): void
    {
        $this->redirect('index');
    }

    /**
     * Datos de un empleado en JSON (modales Ver y Editar).
     * Ruta: empleados/show?id=... (GET). Responde 400, 404 o 200.
     */
    public function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        try {
            $empleado = $this->modelo()->getById($id);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$empleado) {
            $this->jsonResponse(false, null, 'El empleado solicitado no existe.', 404);
        }

        $this->jsonResponse(true, $empleado);
    }

    /**
     * Editar carga los mismos datos que Ver, así que reutiliza show().
     * Ruta: empleados/edit?id=... (GET).
     */
    public function edit(): void
    {
        $this->show();
    }

    /**
     * Opciones de la cascada estado > municipio > parroquia (paso 3).
     * Ruta: empleados/getUbicacion?tipo=&id= (GET, JSON).
     */
    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Empleados');
    }

    /**
     * Alta rápida de un título desde el paso 5 del wizard (catálogo `titulo`).
     * Ruta: empleados/guardarTitulo (POST, JSON).
     * Responde 422 si faltan datos, 409 si ya existe, 500 si falla.
     */
    public function guardarTitulo(): void
    {
        $nombre = $this->capitalizarTitulo((string) ($_POST['nombre'] ?? ''));
        $grado  = (int) ($_POST['id_grado_academico'] ?? 0);

        if ($nombre === '') {
            $this->jsonResponse(false, ['nombre' => 'Escribe el nombre del título.'], 'Faltan datos del título.', 422);
        }
        if ($grado <= 0) {
            $this->jsonResponse(false, ['id_grado_academico' => 'Selecciona el grado académico.'], 'Faltan datos del título.', 422);
        }
        if (!$this->modelo()->existeCatalogo('grado_academico', $grado)) {
            $this->jsonResponse(
                false,
                ['id_grado_academico' => 'El grado académico seleccionado no existe.'],
                'Datos inválidos.',
                422
            );
        }

        $model = new TituloModel($this->conexion());

        if ($model->existeTituloEnGrado($nombre, $grado)) {
            $this->jsonResponse(
                false,
                ['nombre' => 'Ya existe un título con ese nombre en el grado seleccionado.'],
                'Ese título ya está registrado.',
                409
            );
        }

        try {
            $ok = $model->create(['nombre' => $nombre, 'id_grado_academico' => $grado]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Empleados');
        }

        if (!$ok) {
            $this->jsonResponse(false, null, 'No se pudo guardar el título.', 500);
        }

        $this->jsonResponse(true, [
            'id'     => (int) $this->pdo->lastInsertId(),
            'nombre' => $nombre,
        ], 'Título registrado correctamente.');
    }

    /**
     * Alta rápida de una institución desde el paso 5 del wizard.
     * Ruta: empleados/guardarInstitucion (POST, JSON).
     * Solo se acepta el tipo TIPO_TITULO_ACADEMICO.
     */
    public function guardarInstitucion(): void
    {
        $this->guardarInstitucionComun(
            static fn(string $tipo): ?string => $tipo === Instituciones::TIPO_TITULO_ACADEMICO
                ? null
                : 'Solo se registran instituciones donde se obtuvo un título.'
        );
    }

    /**
     * Actualiza un empleado (modal de edición).
     * Ruta: empleados/update (POST, JSON).
     * Responde 400, 404, 405, 422 (validación o cédula duplicada) o 500.
     */
    public function update(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de registros.', 405);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $actual = $model->getById($id);
        if ($actual === null) {
            $this->jsonResponse(false, null, 'El empleado seleccionado ya no existe.', 404);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para actualizar.', 422);
        }

        // Se pasa el id para que la cédula duplicada ignore al propio empleado.
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        // Foto: solo se toca si suben una nueva o piden quitarla (eliminar_foto=1).
        // Va después de validate() para no mover archivos con datos inválidos.
        $fotoAnterior = $actual['foto'] ?? null;
        $fotoNueva    = null;
        $quitarFoto   = ($_POST['eliminar_foto'] ?? '') === '1';

        if (!empty($_FILES['foto']['name'])) {
            $fotoNueva = $this->procesarFotoSubida(self::FOTO_EMPLEADO);
            $data['foto'] = $fotoNueva;
        } elseif ($quitarFoto) {
            $data['foto'] = null;
        }

        try {
            $model->update($id, $data);
        } catch (DocumentoDuplicadoException $e) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            $this->jsonResponse(false, ['numero_documento' => $e->getMessage()], 'Hay errores en el formulario.', 422);
        } catch (Throwable $e) {
            if ($fotoNueva !== null) {
                $this->borrarFoto($fotoNueva);
            }
            $this->jsonError($e, 'Empleados');
        }

        // Borra del disco la foto anterior si se reemplazó o se quitó.
        if (
            $fotoAnterior !== null
            && $fotoNueva !== $fotoAnterior
            && ($fotoNueva !== null || $quitarFoto)
        ) {
            $this->borrarFoto($fotoAnterior);
        }

        $this->jsonResponse(true, null, 'Empleado actualizado correctamente.');
    }

    /**
     * Crea un empleado (wizard).
     * Ruta: empleados/store (POST, JSON).
     * Si create() falla, borra la foto subida; la cédula repetida da 422.
     */
    public function store(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Creatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite creación de registros.', 405);
        }

        $data = $this->extractData($_POST);

        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para guardar.', 422);
        }

        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        $data['foto'] = $this->procesarFotoSubida(self::FOTO_EMPLEADO);

        try {
            $model->create($data);
        } catch (DocumentoDuplicadoException $e) {
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }
            $this->jsonResponse(false, ['numero_documento' => $e->getMessage()], 'Hay errores en el formulario.', 422);
        } catch (Throwable $e) {
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }
            $this->jsonError($e, 'Empleados');
        }

        $this->jsonResponse(true, null, 'Empleado registrado correctamente.');
    }

    /**
     * Modelo con su tipo real (EmpleadoModel), para que el editor
     * resuelva sus métodos en vez de verlos sobre `object`.
     */
    private function modelo(): EmpleadoModel
    {
        /** @var EmpleadoModel $model */
        $model = $this->getModel();

        return $model;
    }

    /**
     * Convierte el $_POST del wizard o del modal de edición en el arreglo
     * de EmpleadoModel: persona, laboral, contacto, dirección y formación.
     * Los nombres se normalizan; el documento se guarda solo con dígitos.
     */
    protected function extractData(array $source): array
    {
        $texto = static fn(string $clave): string => preg_replace('/\s+/u', ' ', trim((string) ($source[$clave] ?? ''))) ?? '';

        $data = [
            'tipo_documento'   => $texto('tipo_documento'),
            'numero_documento' => Documento::normalizar($texto('numero_documento')),
            'fecha_nacimiento' => $texto('fecha_nacimiento'),
            'primer_nombre'    => $this->capitalizarTitulo($texto('primer_nombre')),
            'segundo_nombre'   => $this->capitalizarTitulo($texto('segundo_nombre')),
            'primer_apellido'  => $this->capitalizarTitulo($texto('primer_apellido')),
            'segundo_apellido' => $this->capitalizarTitulo($texto('segundo_apellido')),
            'sexo'             => $texto('sexo'),
            'id_cargo'         => (int) ($source['id_cargo'] ?? 0),
            'fecha_ingreso'    => $texto('fecha_ingreso'),

            'contacto' => [
                'codigo_telefono_principal'   => $texto('codigo_telefono_principal'),
                'numero_telefono_principal'   => $texto('numero_telefono_principal'),
                'codigo_telefono_alternativo' => $texto('codigo_telefono_alternativo'),
                'numero_telefono_alternativo' => $texto('numero_telefono_alternativo'),
                'correo_electronico'          => $texto('correo_electronico'),
            ],

            'direccion' => [
                'id_parroquia'        => (int) ($source['id_parroquia'] ?? 0),
                'sector_urbanizacion' => $texto('sector_urbanizacion'),
                'calle_avenida'       => $texto('calle_avenida'),
                'nro_casa_apto'       => $texto('nro_casa_apto'),
                'punto_referencia'    => $texto('punto_referencia'),
            ],

            'formacion' => $this->formacionDe($source),
        ];

        // La nacionalidad tiene default en la BD: solo se envía si viene con valor.
        if ($texto('nacionalidad') !== '') {
            $data['nacionalidad'] = $texto('nacionalidad');
        }

        return $data;
    }

    /**
     * Formación que llega al alta (JSON `titulos_json`) o a la edición
     * (una fila con campos planos). Solo devuelve filas completas.
     *
     * @return array<int, array{id_titulo:int,id_grado_academico:int,id_institucion:int,fecha_obtencion:?string}>
     */
    private function formacionDe(array $source): array
    {
        $json = $source['titulos_json'] ?? '[]';

        if (is_string($json) && trim($json) !== '') {
            $filas = json_decode($json, true);
            if (is_array($filas)) {
                $salida = [];
                foreach ($filas as $fila) {
                    $fila = $this->normalizarFormacion(is_array($fila) ? $fila : []);
                    if ($fila !== null) {
                        $salida[] = $fila;
                    }
                }
                return $salida;
            }
        }

        // Modal de edición: una fila con los inputs sueltos.
        $fila = $this->normalizarFormacion($source);

        return $fila !== null ? [$fila] : [];
    }

    /**
     * Fila de formación completa, o null si le falta algún id.
     * La fecha de obtención se conserva tal cual (vacía = null): su
     * validación se hace en validate() para poder avisar al usuario.
     */
    private function normalizarFormacion(array $fila): ?array
    {
        $idTitulo      = (int) ($fila['id_titulo'] ?? 0);
        $idGrado       = (int) ($fila['id_grado_academico'] ?? 0);
        $idInstitucion = (int) ($fila['id_institucion'] ?? 0);

        if ($idTitulo <= 0 || $idGrado <= 0 || $idInstitucion <= 0) {
            return null;
        }

        $fecha = trim((string) ($fila['fecha_obtencion'] ?? ''));

        return [
            'id_titulo'          => $idTitulo,
            'id_grado_academico' => $idGrado,
            'id_institucion'     => $idInstitucion,
            'fecha_obtencion'    => $fecha !== '' ? $fecha : null,
        ];
    }

    /**
     * Valida los datos de extractData() y devuelve los errores por campo.
     * $ignorarId excluye al propio empleado de la búsqueda de cédula repetida.
     * Las reglas de persona, contacto y dirección viven en los traits.
     *
     * @return array<string, string>
     */
    protected function validate(array $data, ?int $ignorarId = null): array
    {
        $errores = [];

        // ----- Persona -----
        $this->validarDocumento(
            $data,
            $errores,
            ['V', 'E'],
            $ignorarId,
            'Ya existe un empleado registrado con esa cédula.'
        );

        $this->validarNombresApellidos($data, $errores);

        if (!in_array($data['sexo'], ['F', 'M'], true)) {
            $errores['sexo'] = 'Selecciona el sexo.';
        }

        $this->validarFechaNacimiento($data, $errores, 'fecha_nacimiento', 18, 75);

        // ----- Laboral -----
        if ($data['id_cargo'] <= 0) {
            $errores['id_cargo'] = 'Selecciona un cargo.';
        }

        if (!$this->fechaValida($data['fecha_ingreso'])) {
            $errores['fecha_ingreso'] = 'Indica una fecha de ingreso válida.';
        }

        // ----- Formación -----
        // La fecha de obtención es opcional: solo se valida si viene con valor.
        foreach ($data['formacion'] as $fila) {
            $fecha = $fila['fecha_obtencion'];

            if ($fecha !== null && !$this->fechaEnRango($fecha)) {
                $errores['fecha_obtencion'] = 'Indica una fecha de obtención válida (no puede ser futura).';
                break;
            }
        }

        // ----- Contacto -----
        $this->validarContacto($data, $errores, true);

        // ----- Dirección -----
        $this->validarDireccion($data, $errores);

        return $errores;
    }
}