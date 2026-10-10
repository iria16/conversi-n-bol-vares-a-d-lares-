<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AnioEscolarCerradoException;
use App\Exceptions\CupoAgotadoException;
use App\Models\InscripcionesModel;
use App\Support\Instituciones;
use App\Support\CedulaEscolar;
use App\Support\Documento;
use App\Traits\InstitucionTrait;
use App\Traits\UbicacionTrait;
use App\Traits\ContactoValidacionTrait;
use App\Traits\FamiliaresValidacionTrait;
use App\Traits\FotoSubidaTrait;
use App\Traits\PersonaValidacionTrait;
use App\Traits\ValidacionFechaTrait;
use App\Traits\TextoTrait;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Inscripciones (gestion_estudiante/inscripcion).
 *
 * Endpoints:
 * - index GET listado con filtros
 * - ver GET detalle de una inscripción
 * - buscar POST busca al estudiante por cédula para el alta
 * - store POST registra la inscripción (JSON si es AJAX)
 * - updateEstudiante POST guarda los datos del estudiante desde el modal
 *
 * Lo que toca una matrícula pasa por el modelo, que es donde vive el bloqueo de cupo. Este
 * controlador valida y traduce las excepciones de dominio: 409 para un conflicto de estado y
 * 422 para un error de campo.
*/
class InscripcionesController extends CrudController
{
    use FotoSubidaTrait;
    use PersonaValidacionTrait;
    use ContactoValidacionTrait;
    /* Los familiares se leen y se validan igual en inscripción y en ratificación;
 ver FamiliaresValidacionTrait para por qué no pueden ser dos copias.
 Ese trait usa errorTipoDocumento() y errorFormatoDocumento() de
 PersonaValidacionTrait, así que ambos tienen que estar en la clase.*/
    use FamiliaresValidacionTrait;
    use ValidacionFechaTrait;
    use InstitucionTrait;
    use UbicacionTrait;
    use TextoTrait;

    /** Opciones de FotoSubidaTrait para la foto del estudiante.*/
    private const FOTO_ESTUDIANTE = [
        'campo'   => 'foto',
        'carpeta' => 'estudiantes',
        'prefijo' => 'estudiante_',
    ];

    protected string $viewPath  = 'gestion_estudiante/inscripcion';
    protected string $routeName = 'inscripciones';

    /** Tipos de sangre admitidos; los mismos del ENUM de ficha_medica.tipo_sangre.*/
    private const TIPOS_SANGRE = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /**
 * Modelo con su tipo real (InscripcionesModel), para que el editor
 * resuelva sus métodos en vez de verlos sobre `object`.
*/
    private function modelo(): InscripcionesModel
    {
        /** @var InscripcionesModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new InscripcionesModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        $filters = [
            'q'       => trim($_GET['q'] ?? ''),
            /* Solo el NOMBRE del filtro (q/grado/seccion) se usa como texto. El año
 NO se pasa crudo: si fuera un id que no existe, la lista saldría
 vacía sin explicación. getAnioSeleccionado() lo contrasta con los años
 reales y, si no está, cae en el que está en curso.*/
            'anio'    => ctype_digit(trim((string) ($_GET['anio'] ?? ''))) ? (int) $_GET['anio'] : 0,
            'grado'   => trim($_GET['grado'] ?? ''),
            'seccion' => trim($_GET['seccion'] ?? ''),
            /* Estado del alumno: 'activos' (por defecto, oculta a los retirados) o
 'todos'. Se valida contra la lista en vez de pasar cualquier valor de
 la URL al modelo.*/
            'estado'  => in_array($_GET['estado'] ?? '', ['activos', 'todos'], true)
                            ? (string) $_GET['estado']
                            : 'activos',
        ];
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null) {
            $filters['id_turno'] = $turno;
        }
        return $filters;
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model            = $this->modelo();
        $anios            = $model->getAniosEscolares();
        $anioSeleccionado = $model->getAnioSeleccionado($filters);

        /* Los filtros de grado y sección son los que ese AÑO tuvo, no el catálogo
 completo: el catálogo es global y ofrecería combinaciones que en ese período
 no existieron. Si el año no tiene estructuras registradas se cae al
 catálogo, para que el filtro no quede vacío sin motivo.*/
        $gradosAnio    = $model->getGradosSeccionesDelAnio($anioSeleccionado['id'], 'grado');
        $seccionesAnio = $model->getGradosSeccionesDelAnio($anioSeleccionado['id'], 'seccion');

        return [
            /* El año se resuelve UNA vez y alimenta todo lo de abajo: la tabla, las
 tarjetas y los filtros. Si cada pieza lo sacara por su cuenta, con un año
 inválido en la URL se vería una lista vacía con tarjetas de otro año.*/
            'anios'           => $anios,
            'anioSeleccionado' => $anioSeleccionado,
            'anioEsLectura'   => $anioSeleccionado['es_lectura'],

            'stats'           => $model->getStats($filters['id_turno'] ?? null, $anioSeleccionado['id']),
            'inscripciones'   => $items,

            'grados'          => $gradosAnio ?: $model->getOptions('grados'),
            'secciones'       => $seccionesAnio ?: $model->getOptions('secciones'),

            /* Las combinaciones del wizard son SIEMPRE del año en curso, nunca del año
 que se está consultando: el wizard sirve para inscribir, y un año cerrado
 no admite inscripciones. Con el botón de nueva inscripción oculto en años
 cerrados, el wizard no llega a mostrarse en ese caso.*/
            'academico'       => $model->opcionesPasoAcademico($filters['id_turno'] ?? null),

            // Grados sueltos para el paso 2: el grado del colegio anterior no
            // tiene que estar en la estructura de este período.
            'gradosModal'     => $model->getOptionsForModal('grado'),
            'instituciones'   => $model->getInstituciones(),
            // Solo tipos de etapa del estudiante (preescolar o primaria).
            'tiposInstitucionEstudiante' => $model->getTiposInstitucionEstudiante(),
            'catFamiliares'   => $model->getFamiliarCatalogs(),
            /* Nacionalidad del estudiante: catálogo propio, no el del sub-modal de
               familiares. Va suelto y no dentro de catFamiliares porque lo pinta otro
               formulario. */
            'nacionalidades'  => $model->getNacionalidades(),
            // Recaudos del paso 5, desde el catálogo: al agregar uno allí, aparece aquí.
            'recaudos'        => $model->getRecaudos(),
            'parroquias'      => $model->getParroquias(),
            // Estados para la cascada del familiar; municipio y parroquia llegan por AJAX.
            'estados'         => $model->getEstados(),
            'anioActivo'      => $model->getAnioActivo(),
            'anioEnCurso'     => \App\Support\AniosEscolares::nombreEnCurso($this->conexion()),
        ];
    }

    /**
 * Arma los datos del alta a partir del wizard.
 * Se aplica limpieza profunda (XSS, mayúsculas, formato) a todo el arreglo.
 * Nota: La foto ya no se procesa aquí para evitar archivos basura si falla la validación.
*/
    protected function extractData(array $source): array
    {
        $raw = static fn(string $clave): string => (string) ($source[$clave] ?? '');

        $idGrado   = (int) ($source['id_grado'] ?? 0);
        $idSeccion = (int) ($source['id_seccion'] ?? 0);
        $idTurno   = (int) ($source['id_turno'] ?? 0);
        $anio      = $this->modelo()->getAnioActivo();

        $erroresCombo = [];
        $combinacion  = null;
        $estructuraId = 0;

        /* Sin año escolar activo no hay estructura donde buscar, y el mensaje genérico de
 abajo decía "no registrada en la Estructura Académica de ." con el año en
 blanco: el problema real es que no hay período abierto.*/
        if ((int) ($anio['id'] ?? 0) <= 0) {
            $erroresCombo['id_grado'] = 'No hay un año escolar activo. '
                . 'La directora o el administrador deben abrir un período en '
                . 'Configuración → Años escolares antes de inscribir a un estudiante.';
        } elseif ($idGrado > 0 && $idSeccion > 0 && $idTurno > 0) {
            $combinacion = $this->modelo()->combinacionDeGradoSeccionTurno(
                $idGrado,
                $idSeccion,
                $idTurno,
                (int) ($anio['id'] ?? 0)
            );

            if ($combinacion === null) {
                $erroresCombo['seccion'] = 'Esa combinación de grado, sección y turno no está '
                    . 'registrada en la Estructura Académica de '
                    . (string) ($anio['nombre'] ?? 'este año') . '.';
            } else {
                $estructuraId = (int) $combinacion['id_estructura_academica'];

                if ($combinacion['lleno']) {
                    $erroresCombo['turno'] = 'La sección está llena: '
                        . (int) $combinacion['ocupados'] . ' de '
                        . (int) $combinacion['capacidad_maxima'] . ' cupos ocupados.';
                }
            }
        }

        $familiares = $this->decodificarFamiliares($source['familiares_json'] ?? '[]');
        $documento  = $this->resolverDocumentoEstudiante($source, $familiares);

        return [
            'persona' => [
                'tipo_documento'   => $documento['tipo_documento'],
                'numero_documento' => $documento['numero_documento'],
                'fecha_nacimiento' => $this->normalizarEspacios($raw('fecha_nacimiento')),
                'primer_nombre'    => $this->capitalizarTitulo($raw('primer_nombre')),
                'segundo_nombre'   => $this->capitalizarTitulo($raw('segundo_nombre')),
                'primer_apellido'  => $this->capitalizarTitulo($raw('primer_apellido')),
                'segundo_apellido' => $this->capitalizarTitulo($raw('segundo_apellido')),
                'sexo'             => $this->normalizarEspacios($raw('sexo')) === 'F' ? 'F' : 'M',
                /* El estudiante es el único caso en que se guardan los tres niveles del
                   lugar de nacimiento. El municipio se deduce de la parroquia, así que no
                   se persiste: su name en el formulario lleva el sufijo _ui. */
                'id_estado_nacimiento'    => (int) ($raw('id_estado_nacimiento') ?? '0'),
                /* Se guarda tal cual la escribe la secretaria: el campo tiene una lista de
                   países que sugiere al teclear, pero no es un catálogo cerrado. Se
                   capitalizan las palabras para que "colombia" y "Colombia" queden
                   iguales de leer en el expediente. */
                'id_nacionalidad'         => (int) ($raw('id_nacionalidad') ?? '0'),
                'id_parroquia_nacimiento' => (int) ($raw('id_parroquia_nacimiento') ?? '0'),
            ],
            // La foto inicializa en null; se procesa en store() si la validación es exitosa.
            'foto'                    => null,
            'id_grado'                => $idGrado,
            'ult_grado_cursado'       => $this->capitalizarTitulo($raw('ultimo_grado_cursado')),
            'es_primer_ingreso'       => isset($source['ingreso_primera_vez']) ? 1 : 0,
            'anio_escolar_pre'        => $this->normalizarEspacios($raw('anio_escolar_procedencia')),
            'id_institucion'          => (int) ($source['id_institucion'] ?? 0),
            'id_estructura_academica' => $estructuraId,
            'errores_combinacion'     => $erroresCombo,

            'talla_uniforme' => [
                'camisa'        => $this->vacioComoNull($this->aMayusculas($raw('talla_camisa'))),
                'pantalon'      => $this->vacioComoNull($this->aMayusculas($raw('talla_pantalon'))),
                'zapato'        => $this->vacioComoNull($this->aMayusculas($raw('talla_zapato'))),
                'observaciones' => $this->vacioComoNull($this->capitalizarOracion($raw('talla_observaciones'))),
            ],

            'ficha_medica' => [
                'condiciones_especiales' => $this->vacioComoNull($this->capitalizarOracion($raw('condiciones_especiales'))),
                'medicamentos'           => $this->vacioComoNull($this->capitalizarOracion($raw('medicamentos'))),
                'observaciones'          => $this->vacioComoNull($this->capitalizarOracion($raw('observaciones_medicas'))),
                'alergias'               => $this->vacioComoNull($this->capitalizarOracion($raw('alergias'))),
                'enfermedades'           => $this->vacioComoNull($this->capitalizarOracion($raw('enfermedades'))),
                'tipo_sangre'            => $this->vacioComoNull($this->aMayusculas($raw('tipo_sangre'))),
            ],

            'familiares'           => $familiares,
            'recaudos'             => $this->decodificarRecaudos($source['recaudos'] ?? []),
            'cedula_escolar_error' => $documento['error'],
        ];
    }

    /**
 * Normaliza el bloque de recaudos del paso 5.
 *
 * Llega como recaudos[<id_tipo_documento>][consignado|observacion]. Se limpian aquí los
 * tipos de dato, pero NO se decide cuáles son válidos: eso lo hace el modelo contra el
 * catálogo, que es el único que puede saber si un id existe y está activo. Aceptar el id
 * tal como viene del formulario dejaría la puerta abierta a guardar un recaudo inventado.
 *
 * @return array<int, array{consignado: int, observacion: string}>
*/
    protected function decodificarRecaudos($crudo): array
    {
        if (!is_array($crudo)) {
            return [];
        }

        $recaudos = [];
        foreach ($crudo as $id => $estado) {
            $id = (int) $id;
            if ($id <= 0 || !is_array($estado)) {
                continue;
            }
            $recaudos[$id] = [
                'consignado'  => !empty($estado['consignado']) ? 1 : 0,
                /* normalizarEspacios quita HTML y colapsa espacios, igual que el resto de
 textos libres del alta: la observación es un campo de texto que escribe la
 secretaria y se vuelve a mostrar en la ficha. Sin limpiar, un "<script>"
 quedaría guardado y se ejecutaría al abrir la ficha del estudiante.
 El mb_substr va después porque normalizarEspacios no acota la longitud.*/
                'observacion' => mb_substr(
                    $this->normalizarEspacios((string) ($estado['observacion'] ?? '')),
                    0,
                    255
                ),
            ];
        }

        return $recaudos;
    }

    /**
 * Decide el documento del estudiante y lo sanitiza con TextoTrait.
 *
 * Para una cédula escolar (CE) el número se genera a partir del representante
 * legal, que tiene que traer una cédula V o E válida: la letra de la cédula escolar
 * sale de la suya, y un representante con tipo CE (o sin número) no sirve de base.
*/
    private function resolverDocumentoEstudiante(array $source, array $familiares): array
    {
        $resultado = [
            'tipo_documento'   => $this->normalizarEspacios((string) ($source['tipo_documento'] ?? '')),
            'numero_documento' => Documento::normalizar($this->normalizarEspacios((string) ($source['numero_documento'] ?? ''))),
            'error'            => null,
        ];

        if ($resultado['tipo_documento'] !== 'CE') {
            return $resultado;
        }

        $resultado['numero_documento'] = '';
        $representante = $this->representanteLegal($familiares);

        if ($representante === null) {
            $resultado['error'] = 'Para generar la cédula escolar debe registrar un representante legal '
                . 'con su número de documento (paso 6, representante legal).';
            return $resultado;
        }

        $cedulaRepresentante = (string) ($representante['numero_documento'] ?? '');
        $tipoRepresentante   = (string) ($representante['tipo_documento'] ?? '');

        if ($cedulaRepresentante === '' || $this->errorTipoDocumento($tipoRepresentante, ['V', 'E']) !== null) {
            $resultado['error'] = 'Para generar la cédula escolar, el representante legal debe tener '
                . 'una cédula V o E válida (paso 6, representante legal).';
            return $resultado;
        }

        $fechaNacimiento = trim((string) ($source['fecha_nacimiento'] ?? ''));

        try {
            $resultado['numero_documento'] = CedulaEscolar::generar(
                $this->modelo()->proximoOrdenHermano($cedulaRepresentante, $fechaNacimiento),
                $fechaNacimiento,
                $cedulaRepresentante,
                $tipoRepresentante
            );
        } catch (InvalidArgumentException $e) {
            $resultado['error'] = $e->getMessage();
        }

        return $resultado;
    }

    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Inscripciones');
    }

    /**
 * Guarda la inscripción.
 * Retrasa la subida de la foto hasta que todo sea válido. Si la BD o
 * los cupos fallan durante el try/catch, borra la foto del disco.
*/
    public function store(): void
    {
        $model = $this->getModel();
        $data  = $this->extractData($_POST);
        // Quién inscribe: lo necesita la bitácora del proceso, no se guarda en la matrícula.
        $data['id_usuario'] = (int) ($this->idUsuarioActual() ?? 0);

        $errors = $this->validate($data);
        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        // Subimos la foto SOLO si el formulario pasó todas las reglas.
        $data['foto'] = $this->procesarFotoSubida(self::FOTO_ESTUDIANTE);

        try {
            $model->create($data);
        } catch (AnioEscolarCerradoException $e) {
            /* El año de la estructura elegida ya no admite inscripciones.
 Es un 403 y no un 422: el formulario está bien, lo que no se permite es la
 operación sobre ese período. Un 422 dejaría a la secretaria corrigiendo
 campos de un formulario que no tiene nada de malo.*/
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }
            $this->jsonResponse(false, null, $e->getMessage(), 403);
        } catch (CupoAgotadoException | Throwable $e) {

            // Revertir la foto subida en caso de excepción
            if ($data['foto'] !== null) {
                $this->borrarFoto($data['foto']);
            }

            if ($e instanceof CupoAgotadoException) {
                $this->jsonResponse(
                    false,
                    $e->campo() !== null ? [$e->campo() => $e->getMessage()] : null,
                    $e->getMessage(),
                    422
                );
            }

            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro creado correctamente.');
    }

    public function guardarInstitucion(): void
    {
        $this->guardarInstitucionComun(
            static fn(string $tipo): ?string => Instituciones::esDeEstudiante($tipo)
                ? null
                : 'Solo se registran instituciones de preescolar o de escuela.'
        );
    }

    protected function validate(array $data, ?int $ignorarId = null): array
    {
        $errores = [];

        foreach (($data['errores_combinacion'] ?? []) as $campo => $mensaje) {
            $errores[$campo] = $mensaje;
        }

        $p = $data['persona'];

        if ($data['cedula_escolar_error'] !== null) {
            $errores['numero_documento'] = $data['cedula_escolar_error'];
        } else {
            // El mensaje lo pone el modelo: el mismo "documento repetido" quiere decir tres
            // cosas distintas según quién lo tiene registrado, y quien inscribe tiene que
            // saber a qué módulo ir en cada caso.
            $this->validarDocumento(
                ['tipo_documento' => $p['tipo_documento'], 'numero_documento' => $p['numero_documento']],
                $errores,
                ['V', 'E', 'CE'],
                null,
                $this->modelo()->descripcionDocumentoExistente($p['numero_documento'])
                    ?? 'Ese número de documento ya está registrado en el sistema.'
            );
        }

        $this->validarFechaNacimiento($p, $errores, 'fecha_nacimiento', 5, 14);
        $this->validarLugarNacimiento($p, $errores, true);
        $this->validarNacionalidad($p, $errores);

        if ($p['primer_nombre'] === '') {
            $errores['primer_nombre'] = 'Indique el primer nombre.';
        }
        if ($p['primer_apellido'] === '') {
            $errores['primer_apellido'] = 'Indique el primer apellido.';
        }

        /* El tipo de sangre es un ENUM en la base. Sin esta comprobación, un valor fuera de la
 lista llega al INSERT y MariaDB solo avisa ("Data truncated") y guarda un valor
 vacío: la secretaria ve un error 500 sin explicación cuando
 el problema es un dato mal escrito. El <select> de la pantalla solo ofrece las
 opciones correctas, pero el servidor no puede depender del formulario.*/
        if ($data['ficha_medica']['tipo_sangre'] !== null
            && !in_array($data['ficha_medica']['tipo_sangre'], self::TIPOS_SANGRE, true)) {
            $errores['tipo_sangre'] = 'Seleccione un tipo de sangre de la lista.';
        }

        $this->validarFamiliares($data['familiares'], $errores);

        /* Solo si no hay un motivo mejor ya calculado. extractData() ya sabe decir por qué no
 hay estructura (por ejemplo que no hay año escolar activo), y este mensaje de
 abajo es el último recurso: aplicarlo siempre pisaba el motivo real y la
 secretaria veía "cree la estructura" cuando lo que faltaba era abrir el período.*/
        if ($data['id_estructura_academica'] <= 0 && !isset($errores['id_grado'])) {
            $errores['id_grado'] = 'La sección no existe para esa combinación en el período activo. '
                . 'Cree primero la estructura académica.';
        }
        if ($data['id_estructura_academica'] > 0
            && !$this->secretariaTieneAccesoAEstructura((int) $data['id_estructura_academica'])) {
            $errores['id_turno'] = 'Solo puede registrar estudiantes en su turno asignado.';
        }

        $this->validarGradoProcedencia($data, $errores);

        return $errores;
    }

    private function validarGradoProcedencia(array $data, array &$errores): void
    {
        if ($data['id_institucion'] <= 0) {
            return;
        }

        $error = Instituciones::validarGradoProcedencia(
            (string) $this->modelo()->getTipoInstitucion($data['id_institucion']),
            $data['ult_grado_cursado'],
            $data['id_grado'] > 0
                ? Instituciones::ordinalGrado($this->modelo()->getNombreGrado($data['id_grado']))
                : null
        );

        if ($error !== null) {
            $errores['ultimo_grado_cursado'] = $error;
        }
    }

    /** Helper interno que pasa los valores nulos o vacíos limpios.*/
    private function vacioComoNull(mixed $valor): ?string
    {
        $texto = $this->normalizarEspacios((string) $valor);
        return $texto !== '' ? $texto : null;
    }

    /**
 * Busca una persona por cédula para precargar al familiar en el sub-modal.
 * Ruta: inscripciones/buscarPersona?documento= (GET, JSON).
 * Responde 200 (data null si no existe) o 422 si la cédula es inválida.
 *
 * Este método es una envoltura de una línea a propósito: la consulta y el
 * formato de la respuesta son los mismos que ratificación necesita, y la
 * lógica está en el modelo (buscarPersonaPorDocumento), que es donde debe
 * estar. RatificacionController::buscarPersona es igual de corto.
*/
    public function buscarPersona(): void
    {
        $documento = Documento::normalizar(trim((string) ($_GET['documento'] ?? '')));

        if ($documento === '') {
            $this->jsonResponse(false, null, 'Indique la cédula a buscar.', 422);

            return;
        }

        if (!Documento::esCedula($documento)) {
            $this->jsonResponse(false, null, 'La cédula debe tener entre 7 y 8 dígitos.', 422);

            return;
        }

        try {
            /* En el alta todavía no hay estudiante: se busca sin contexto y el
 sub-modal usa la relación más reciente de esa persona. En ratificación
 sí se envía, y ahí sí importa (ver PersonaModel::nivelYOcupacionDe).*/
            $idEstudiante = (int) ($_GET['id_estudiante'] ?? 0);
            $this->jsonResponse(true, $this->modelo()->buscarPersonaPorDocumento(
                $documento,
                $idEstudiante > 0 ? $idEstudiante : null
            ));
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    public function ver(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $item = $id > 0 ? $this->modelo()->getById($id) : null;
            $turno = $this->turnoSecretariaActual();
            if ($item && $turno !== null && (int) ($item['id_turno'] ?? 0) !== $turno) {
                $item = null;
            }
            if (!$item) {
                $this->setFlash('error', 'La inscripción solicitada no existe.');
                $this->redirect('index');
            }
            require __DIR__ . "/../Views/{$this->viewPath}/ver.php";
        } catch (Throwable $e) {
            $this->handleError($e, static::class);
        }
    }

    public function continuar(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->setFlash('error', 'Inscripción no válida.');
            $this->redirect('index');
        }
        $this->setFlash('success', 'La inscripción ya fue completada correctamente.');
        $this->redirect('ver', ['id' => $id]);
    }
}