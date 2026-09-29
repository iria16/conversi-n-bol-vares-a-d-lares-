<?php

declare(strict_types=1);

namespace App\Controllers;


use App\Exceptions\CupoAgotadoException;
use App\Models\InscripcionesModel;
use App\Support\Instituciones;
use App\Support\CedulaEscolar;
use App\Support\Documento;
use App\Traits\InstitucionTrait;
use App\Traits\UbicacionTrait;
use App\Traits\ContactoValidacionTrait;
use App\Traits\FotoSubidaTrait;
use App\Traits\PersonaValidacionTrait;
use App\Traits\ValidacionFechaTrait;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Inscripciones (gestion_estudiante/inscripcion) con asistente wizard.
 * Endpoints: index, store, buscarPersona, ver, continuar, getUbicacion, guardarInstitucion.
 * Los campos de extractData() los manda public/assets/js/enrollments.js.
 */
class InscripcionesController extends CrudController
{
    use FotoSubidaTrait;
    use PersonaValidacionTrait;
    use ContactoValidacionTrait;
    use ValidacionFechaTrait;
    use InstitucionTrait;
    use UbicacionTrait;

    /** Opciones de FotoSubidaTrait para la foto del estudiante. */
    private const FOTO_ESTUDIANTE = [
        'campo'   => 'foto',
        'carpeta' => 'estudiantes',
        'prefijo' => 'estudiante_',
    ];

    protected string $viewPath  = 'gestion_estudiante/inscripcion';
    protected string $routeName = 'inscripciones';

    /**
     * Modelo con su tipo real (InscripcionesModel), para que el editor
     * resuelva sus métodos en vez de verlos sobre `object`.
     */
    private function modelo(): InscripcionesModel
    {
        /** @var InscripcionesModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new InscripcionesModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'       => trim($_GET['q'] ?? ''),
            'anio'    => trim($_GET['anio'] ?? ''),
            'grado'   => trim($_GET['grado'] ?? ''),
            'seccion' => trim($_GET['seccion'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        return [
            'stats'           => $model->getStats(),
            'inscripciones'   => $items,
            // El año en curso se pasa solo para mostrarlo (el listado es de este año).
            'anioEnCurso'     => \App\Support\AniosEscolares::nombreEnCurso($this->conexion()),
            'grados'          => $model->getOptions('grados'),
            'secciones'       => $model->getOptions('secciones'),
            // Combinaciones registradas en Estructura Académica, con su disponibilidad.
            'academico'       => $model->opcionesPasoAcademico(),
            // Grados sueltos para el paso 2: el grado del colegio anterior no
            // tiene que estar en la estructura de este período.
            'gradosModal'     => $model->getOptionsForModal('grado'),
            'instituciones'   => $model->getInstituciones(),
            // Solo tipos de etapa del estudiante (preescolar o primaria).
            'tiposInstitucionEstudiante' => $model->getTiposInstitucionEstudiante(),
            'catFamiliares'   => $model->getFamiliarCatalogs(),
            'parroquias'      => $model->getParroquias(),
            // Estados para la cascada del familiar; municipio y parroquia llegan por AJAX.
            'estados'         => $model->getEstados(),
            'anioActivo'      => $model->getAnioActivo(),
        ];
    }

    /**
     * Arma los datos del alta a partir del wizard (contrato de CrudController::store()).
     * También sube la foto de $_FILES: si es inválida, procesarFotoSubida()
     * responde el error y corta antes de validate() y create().
     */
    protected function extractData(array $source): array
    {
        $idGrado   = (int) ($source['id_grado'] ?? 0);
        $idSeccion = (int) ($source['id_seccion'] ?? 0);
        $idTurno   = (int) ($source['id_turno'] ?? 0);
        $anio      = $this->modelo()->getAnioActivo();

        // La combinación grado + sección + turno debe estar registrada para el
        // año activo y tener cupo. Se revisa otra vez aquí porque desde que se
        // abrió el wizard pudo llenarse o desregistrarse.
        $erroresCombo = [];
        $combinacion  = null;
        $estructuraId = 0;
        if ($idGrado > 0 && $idSeccion > 0 && $idTurno > 0) {
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
                // El id se deja aunque la sección esté llena: así validate() no
                // suma un segundo aviso que contradiga el del cupo.
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
                'fecha_nacimiento' => trim($source['fecha_nacimiento'] ?? ''),
                // Mismo criterio que EmpleadoController: nombres capitalizados y recortados.
                'primer_nombre'    => $this->capitalizarTitulo(trim($source['primer_nombre'] ?? '')),
                'segundo_nombre'   => $this->capitalizarTitulo(trim($source['segundo_nombre'] ?? '')),
                'primer_apellido'  => $this->capitalizarTitulo(trim($source['primer_apellido'] ?? '')),
                'segundo_apellido' => $this->capitalizarTitulo(trim($source['segundo_apellido'] ?? '')),
                'sexo'             => ($source['sexo'] ?? 'M') === 'F' ? 'F' : 'M',
            ],
            'foto'               => $this->procesarFotoSubida(self::FOTO_ESTUDIANTE),
            // El tipo de la institución de procedencia se resuelve en validate(),
            // donde se compara el grado. El grado se pasa por id porque su
            // número se deduce del nombre del catálogo.
            'id_grado'           => $idGrado,
            'ult_grado_cursado' => $this->capitalizarTitulo(trim($source['ultimo_grado_cursado'] ?? '')),
            'es_primer_ingreso' => isset($source['ingreso_primera_vez']) ? 1 : 0,
            'anio_escolar_pre'  => trim($source['anio_escolar_procedencia'] ?? ''),
            'id_institucion'    => (int) ($source['id_institucion'] ?? 0),
            'id_estructura_academica' => $estructuraId,
            // Errores de la combinación: validate() los suma a los suyos (mismo 422).
            'errores_combinacion'  => $erroresCombo,
            // El menor no lleva contacto ni dirección: son los de su
            // representante y viajan dentro de cada familiar.
            'talla_uniforme' => [
                // Tallas en mayúsculas y sin espacios (" m " -> "M").
                'camisa'       => mb_strtoupper(trim($source['talla_camisa'] ?? ''), 'UTF-8') ?: null,
                'pantalon'     => mb_strtoupper(trim($source['talla_pantalon'] ?? ''), 'UTF-8') ?: null,
                'zapato'       => mb_strtoupper(trim($source['talla_zapato'] ?? ''), 'UTF-8') ?: null,
                'observaciones' => $this->vacioComoNull($source['talla_observaciones'] ?? ''),
            ],
            'ficha_medica' => [
                'condiciones_especiales' => $this->vacioComoNull($source['condiciones_especiales'] ?? ''),
                'medicamentos'           => $this->vacioComoNull($source['medicamentos'] ?? ''),
                'observaciones'          => $this->vacioComoNull($source['observaciones_medicas'] ?? ''),
                'alergias'               => $this->vacioComoNull($source['alergias'] ?? ''),
                'enfermedades'           => $this->vacioComoNull($source['enfermedades'] ?? ''),
                'tipo_sangre'            => $this->vacioComoNull($source['tipo_sangre'] ?? ''),
            ],
            'familiares' => $familiares,
            // Si la cédula escolar no se pudo calcular, validate() reporta este motivo.
            'cedula_escolar_error' => $documento['error'],
        ];
    }

    /**
     * Decide el documento del estudiante. Con tipo V o E se usa el que escribió
     * el wizard; con CE se genera la cédula escolar a partir del representante
     * legal (paso 6) y del orden del hijo, que cuenta el modelo.
     *
     * @param array $source     $_POST del wizard.
     * @param array $familiares Ya normalizado por decodificarFamiliares().
     *
     * @return array{tipo_documento: string, numero_documento: string, error: ?string}
     */
    private function resolverDocumentoEstudiante(array $source, array $familiares): array
    {
        $resultado = [
            'tipo_documento'   => trim($source['tipo_documento'] ?? ''),
            // Acepta prefijo V/E, pero se guarda solo con los dígitos.
            'numero_documento' => Documento::normalizar(trim($source['numero_documento'] ?? '')),
            'error'            => null,
        ];

        // El tipo de documento es la decisión: solo CE genera la cédula escolar.
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

        try {
            // El orden del hijo se deduce de los hermanos ya registrados con
            // ese año de nacimiento (InscripcionesModel::proximoOrdenHermano()).
            $resultado['numero_documento'] = CedulaEscolar::generar(
                $this->modelo()->proximoOrdenHermano($cedulaRepresentante, trim($source['fecha_nacimiento'] ?? '')),
                trim($source['fecha_nacimiento'] ?? ''),
                $cedulaRepresentante,
                (string) ($representante['tipo_documento'] ?? '')
            );
        } catch (InvalidArgumentException $e) {
            $resultado['error'] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Un nivel de la cascada de ubicación para la dirección del familiar.
     * Mismo flujo que EmpleadoController::getUbicacion(), vía
     * UbicacionTrait::getUbicacionComun().
     */
    public function getUbicacion(): void
    {
        $this->getUbicacionComun('Inscripciones');
    }

    /**
     * Guarda la inscripción y responde 422 si el cupo se agotó.
     * El cupo se revisa en validate() y otra vez dentro de la transacción
     * (reservarCupo()), que es la que decide ante peticiones simultáneas.
     */
    public function store(): void
    {
        $model = $this->getModel();
        $data  = $this->extractData($_POST);

        $errors = $this->validate($data);
        if (!empty($errors)) {
            $this->jsonResponse(false, $errors, 'Hay errores en el formulario.', 422);
        }

        try {
            $model->create($data);
        } catch (CupoAgotadoException $e) {
            $this->jsonResponse(
                false,
                $e->campo() !== null ? [$e->campo() => $e->getMessage()] : null,
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->jsonResponse(true, null, 'Registro creado correctamente.');
    }

    /**
     * Alta rápida de la institución de procedencia (paso 2 del wizard).
     * Solo acepta tipos de estudiante (preescolar o escuela); el servidor
     * no confía en el <select>. El límite de grado se valida al guardar.
     */
    public function guardarInstitucion(): void
    {
        $this->guardarInstitucionComun(
            static fn(string $tipo): ?string => Instituciones::esDeEstudiante($tipo)
                ? null
                : 'Solo se registran instituciones de preescolar o de escuela.'
        );
    }

    /**
     * El familiar marcado como representante legal, o null si no hay.
     *
     * @param array $familiares Ya normalizado por decodificarFamiliares().
     */
    private function representanteLegal(array $familiares): ?array
    {
        foreach ($familiares as $familiar) {
            if ((int) ($familiar['representante_principal'] ?? 0) === 1) {
                return $familiar;
            }
        }

        return null;
    }

    /**
     * Errores por campo del alta: combinación grado/sección/turno, documento,
     * datos del estudiante, familiares y grado de procedencia.
     */
    protected function validate(array $data): array
    {
        $errores = [];

        // Los errores de la combinación ya los resolvió extractData().
        foreach (($data['errores_combinacion'] ?? []) as $campo => $mensaje) {
            $errores[$campo] = $mensaje;
        }

        $p = $data['persona'];

        // El formato del número depende del tipo de documento. Si la cédula
        // escolar no se pudo generar, su motivo va antes que el de formato.
        if ($data['cedula_escolar_error'] !== null) {
            $errores['numero_documento'] = $data['cedula_escolar_error'];
        } else {
            $this->validarDocumento(
                ['tipo_documento' => $p['tipo_documento'], 'numero_documento' => $p['numero_documento']],
                $errores,
                ['V', 'E', 'CE'],
                null,
                'Ya existe un estudiante con ese número de documento.'
            );
        }

        // Edad del estudiante: 5 a 14 años (preescolar y primaria).
        $this->validarFechaNacimiento($p, $errores, 'fecha_nacimiento', 5, 14);
        if ($p['primer_nombre'] === '') {
            $errores['primer_nombre'] = 'Indique el primer nombre.';
        }
        if ($p['primer_apellido'] === '') {
            $errores['primer_apellido'] = 'Indique el primer apellido.';
        }
        // Contacto y domicilio se validan en los familiares, no en el menor.
        $this->validarRepresentanteLegal($data['familiares'], $errores);
        if ($data['id_estructura_academica'] <= 0) {
            $errores['id_grado'] = 'La sección no existe para esa combinación en el período activo. '
                . 'Cree primero la estructura académica.';
        }
        $this->validarGradoProcedencia($data, $errores);

        return $errores;
    }

    /**
     * Valida el grado de procedencia según el tipo de institución
     * (Instituciones::validarGradoProcedencia()). Va al final del wizard
     * porque el grado a cursar se pide después, en el paso 3.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errores
     */
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

    /**
     * Caché de nombres de parentesco activos: se consulta una sola vez por guardado.
     *
     * @var array<string, true>|null
     */
    private ?array $nombresParentesco = null;

    /**
     * ¿El parentesco es padre o madre? Compara por nombre (no por id) para
     * no depender del catálogo; misma regla que esPadreOMadreSegun en enrollments.js.
     */
    private function esPadreOMadre(string $parentesco): bool
    {
        $nombre = mb_strtolower(trim($parentesco));

        return $nombre === 'madre' || $nombre === 'padre';
    }

    /** ¿Ese nombre de parentesco está en el catálogo activo? */
    private function existeParentesco(string $nombre): bool
    {
        if ($this->nombresParentesco === null) {
            $stmt = $this->conexion()->query("SELECT nombre FROM parentesco WHERE estado = 'ACTIVO'");
            $this->nombresParentesco = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $activo) {
                $this->nombresParentesco[(string) $activo] = true;
            }
        }

        return isset($this->nombresParentesco[$nombre]);
    }


    /**
     * Reglas de familiares y representante legal: uno y solo un representante;
     * todos con parentesco válido (salvo "no es familiar") y teléfono principal;
     * el representante además con domicilio completo y fecha de nacimiento (18-100).
     *
     * @param array $familiares Ya normalizado por decodificarFamiliares().
     * @param array $errores    Errores por campo, por referencia.
     */
    private function validarRepresentanteLegal(array $familiares, array &$errores): void
    {
        if ($familiares === []) {
            $errores['familiares'] = 'Debe registrar al menos un familiar.';

            return;
        }

        $representantes = array_values(array_filter(
            $familiares,
            fn($familiar) => (int) ($familiar['representante_principal'] ?? 0) === 1
        ));

        if ($representantes === []) {
            $errores['familiares'] = 'Debe marcar un familiar como representante legal.';

            return;
        }

        if (count($representantes) > 1) {
            $errores['familiares'] = 'Solo puede haber un representante legal por estudiante; '
                . 'deje marcado uno solo.';

            return;
        }

        $erroresRol = [];

        foreach ($familiares as $indice => $familiar) {
            $esRepresentante  = (int) ($familiar['representante_principal'] ?? 0) === 1;
            $soloAutorizado   = (bool) ($familiar['solo_autorizado'] ?? false);
            $parentesco       = trim((string) ($familiar['parentesco'] ?? ''));
            $contacto  = $familiar['contacto']  ?? [];
            $faltantes = [];

            // Quien no es familiar solo puede estar autorizado para retirar:
            // no puede ser además el representante legal.
            if ($soloAutorizado && $esRepresentante) {
                $erroresRol[] = 'Una persona sin parentesco no puede ser el representante '
                    . 'legal: márquela como autorizada para retiros o deja de marcar '
                    . 'el representante.';

                continue;
            }

            // El parentesco es obligatorio, salvo con el interruptor "no es familiar".
            if (!$soloAutorizado && $parentesco === '') {
                $erroresRol[] = 'Falta el parentesco con el estudiante: márquelo en el '
                    . 'campo de parentesco, o active "no es familiar" si no tiene '
                    . 'ningún vínculo.';

                continue;
            }

            // Debe existir en el catálogo: si no, el modelo lo guardaría como
            // NULL sin avisar (catálogo cambiado o JSON armado a mano).
            if ($parentesco !== '' && !$this->existeParentesco($parentesco)) {
                $erroresRol[] = 'El parentesco "' . $parentesco . '" no existe en el '
                    . 'catálogo. Revise la lista de parentescos.';

                continue;
            }

            // Teléfono y correo con las mismas reglas que empleados: se
            // remapean las claves cortas del familiar a las de
            // ContactoValidacionTrait. El 3.er parámetro es $esObligatorio (bool).
            $erroresContacto = [];
            $this->validarContacto(
                [
                    'contacto' => [
                        'codigo_telefono_principal'   => $contacto['codigo'] ?? '',
                        'numero_telefono_principal'   => $contacto['numero'] ?? '',
                        'codigo_telefono_alternativo' => $contacto['codigo_alt'] ?? '',
                        'numero_telefono_alternativo' => $contacto['numero_alt'] ?? '',
                        'correo_electronico'          => $contacto['correo'] ?? '',
                    ],
                ],
                $erroresContacto,
                true
            );

            if (isset($erroresContacto['numero_telefono_principal'], $erroresContacto['codigo_telefono_principal'])) {
                $faltantes[] = 'el teléfono principal (prefijo y número válidos)';
            } elseif (isset($erroresContacto['numero_telefono_principal'])) {
                $faltantes[] = 'un teléfono principal con 7 dígitos';
            } elseif (isset($erroresContacto['codigo_telefono_principal'])) {
                $faltantes[] = 'un prefijo válido para el teléfono principal';
            }

            if (isset($erroresContacto['numero_telefono_alternativo']) || isset($erroresContacto['codigo_telefono_alternativo'])) {
                $faltantes[] = 'un teléfono alternativo válido (o dejarlo vacío)';
            }

            if (isset($erroresContacto['correo_electronico'])) {
                $faltantes[] = 'un correo electrónico válido';
            }

            // Domicilio y fecha de nacimiento: solo los exige el representante.
            if ($esRepresentante) {
                $direccion = $familiar['direccion'] ?? [];

                foreach (['sector' => 'el sector', 'calle' => 'la calle', 'casa' => 'el número de casa'] as $campo => $etiqueta) {
                    if (trim((string) ($direccion[$campo] ?? '')) === '') {
                        $faltantes[] = $etiqueta;
                    }
                }

                // Solo se guarda id_parroquia; estado y municipio son pasos de la cascada.
                if ((int) ($direccion['id_parroquia'] ?? 0) <= 0) {
                    $faltantes[] = 'la parroquia (seleccione estado, municipio y parroquia)';
                }

                // Array aparte: la clave 'fecha_nacimiento' pisaría la del estudiante.
                $erroresFecha = [];
                $this->validarFechaNacimiento($familiar, $erroresFecha, 'fecha_nacimiento', 18, 100);
                if (isset($erroresFecha['fecha_nacimiento'])) {
                    $faltantes[] = 'una fecha de nacimiento válida (mayor de edad)';
                }
            }

            if ($faltantes !== []) {
                $quien = $esRepresentante ? 'El representante legal' : 'El familiar';
                $erroresRol[] = $quien . ' necesita ' . implode(', ', $faltantes) . '.';
            }

            unset($indice);
        }

        if ($erroresRol !== []) {
            $errores['familiares'] = implode(' ', $erroresRol);
        }
    }

    /** Cadena vacía (o solo espacios) a null; el resto, recortado. */
    private function vacioComoNull(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }

    /**
     * Decodifica y normaliza los familiares de `familiares_json`.
     * Cada uno trae `contacto` y `direccion`; solo el representante los
     * usa, porque el menor no tiene datos propios (paso 6 del wizard).
     */
    private function decodificarFamiliares(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $familiares = json_decode($raw, true);

        if (!is_array($familiares)) {
            return [];
        }

        foreach ($familiares as &$familiar) {
            if (!is_array($familiar)) {
                $familiar = [];
                continue;
            }

            // "No es familiar": solo autorizado para retirar. Si viene marcado
            // se descarta el parentesco (no se puede tener ambos).
            $familiar['solo_autorizado'] = (int) ($familiar['solo_autorizado'] ?? 0) === 1;

            // Si vive con el menor: siempre 0 o 1, y por defecto 0 (no se da por
            // hecho). Para el representante lo fuerza el navegador.
            $familiar['vive_con_estudiante'] = (int) ($familiar['vive_con_estudiante'] ?? 0) === 1 ? 1 : 0;
            if ($familiar['solo_autorizado']) {
                $familiar['parentesco']         = '';
                $familiar['autorizado_retiros'] = 1;
            }

            $familiar['contacto'] = [
                'codigo'     => trim((string) ($familiar['contacto']['codigo'] ?? '')),
                'numero'     => trim((string) ($familiar['contacto']['numero'] ?? '')),
                'codigo_alt' => $this->vacioComoNull($familiar['contacto']['codigo_alt'] ?? ''),
                'numero_alt' => $this->vacioComoNull($familiar['contacto']['numero_alt'] ?? ''),
                'correo'     => $this->vacioComoNull($familiar['contacto']['correo'] ?? ''),
            ];

            // Nivel de instrucción y ocupación (por nombre de catálogo) solo se
            // piden al representante o al padre/madre; en los demás se vacían.
            // Se compara por nombre; el modelo aplica la misma regla.
            $pideDatosPersonales = ((int) ($familiar['representante_principal'] ?? 0) === 1)
                || $this->esPadreOMadre($familiar['parentesco'] ?? '');

            if ($pideDatosPersonales) {
                $familiar['nivel_instruccion'] = trim((string) ($familiar['nivel_instruccion'] ?? ''));
                $familiar['ocupacion']         = trim((string) ($familiar['ocupacion'] ?? ''));
            } else {
                $familiar['nivel_instruccion'] = '';
                $familiar['ocupacion'] = '';
            }

            // El domicilio se normaliza a las claves del modelo y de la tabla
            // `direccion`. El sub-modal manda `parroquia`; se aceptan ambos
            // nombres y se emite `id_parroquia`. Solo se guarda para el
            // representante (lo aplica InscripcionesModel::insertFamiliares).
            $direccionRecibida = $familiar['direccion'] ?? [];
            $familiar['direccion'] = [
                'id_parroquia' => (int) ($direccionRecibida['id_parroquia'] ?? $direccionRecibida['parroquia'] ?? 0),
                'sector'       => trim((string) ($direccionRecibida['sector'] ?? '')),
                'calle'        => trim((string) ($direccionRecibida['calle'] ?? '')),
                'casa'         => trim((string) ($direccionRecibida['casa'] ?? '')),
                'referencia'   => $this->vacioComoNull($direccionRecibida['referencia'] ?? ''),
            ];

            // La letra (V/E) se lee del valor original, antes de normalizar,
            // para que "e-87654321" deduzca E. Sirve para la cédula escolar.
            $documentoCrudo = trim((string) ($familiar['numero_documento'] ?? ''));
            $familiar['tipo_documento']  = mb_strtoupper(trim((string) ($familiar['tipo_documento'] ?? '')), 'UTF-8');

            // El representante lo decide el interruptor del sub-modal; se
            // normaliza aquí porque el JSON viene del cliente. Siempre puede
            // retirar al estudiante, así que su permiso se marca solo.
            $familiar['representante_principal'] = (int) ($familiar['representante_principal'] ?? 0) === 1 ? 1 : 0;

            if ($familiar['representante_principal'] === 1) {
                $familiar['autorizado_retiros'] = 1;
            } else {
                $familiar['autorizado_retiros'] = (int) ($familiar['autorizado_retiros'] ?? 0) === 1 ? 1 : 0;
            }
            $familiar['numero_documento'] = Documento::normalizar($documentoCrudo);

            if (
                !in_array($familiar['tipo_documento'], Documento::TIPOS_CEDULA, true)
                && Documento::esCedula($familiar['numero_documento'])
            ) {
                $familiar['tipo_documento'] = Documento::letraDe($documentoCrudo, Documento::LETRA_POR_DEFECTO);
            }
            $familiar['sexo']             = ($familiar['sexo'] ?? 'M') === 'F' ? 'F' : 'M';

            // Solo se recorta; el formato y el rango (18-100 años) se validan en
            // validarRepresentanteLegal().
            $familiar['fecha_nacimiento'] = trim((string) ($familiar['fecha_nacimiento'] ?? ''));
        }
        unset($familiar);

        return $familiares;
    }

    /**
     * Busca una persona por cédula para precargar al familiar en el sub-modal.
     * Ruta: inscripciones/buscarPersona?documento= (GET, JSON).
     * Responde 200 (data null si no existe) o 422 si la cédula es inválida.
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
            $this->jsonResponse(true, $this->modelo()->buscarPersonaPorDocumento($documento));
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    public function ver(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $item = $id > 0 ? $this->modelo()->getById($id) : null;
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