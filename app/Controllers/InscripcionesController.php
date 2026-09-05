<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/InscripcionesModel.php';

class InscripcionesController extends BaseController
{
    protected string $viewPath = 'students';
    protected string $routeName = 'inscripciones';
    protected array $allowedRoles = ['secretaria'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): InscripcionesModel
    {
        return new InscripcionesModel($pdo);
    }

    /** Datos comunes para el modal wizard — usa el modelo principal, sin PDO directo. */
    private function getModalData(): array
    {
        $grados          = $this->model->getOptionsForModal('grado');
        $secciones       = $this->model->getOptionsForModal('seccion');
        $turnos          = $this->model->getOptionsForModal('turno');
        $anioEscolarActivo = $this->model->getAnioActivo();
        return compact('grados', 'secciones', 'turnos', 'anioEscolarActivo');
    }

    public function index(): void
    {
        try {
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $inscripciones  = $this->model->getAll($filters, $page, $this->perPage);
            $total          = $this->model->countAll($filters);
            $stats          = $this->model->getStats();

            // Opciones para los filtros de la tabla
            $aniosEscolares = $this->model->getOptions('anios');
            $grados         = $this->model->getOptions('grados');
            $secciones      = $this->model->getOptions('secciones');

            $paginacion = [
                'desde'         => $total ? (($page - 1) * $this->perPage) + 1 : 0,
                'hasta'         => min($page * $this->perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            // Variables para el modal wizard (enrollment-modal.php)
            extract($this->getModalData());

            require APP_PATH . "/Views/{$this->viewPath}/enrollments.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Inscripciones');
        }
    }

    public function create(): void
    {
        try {
            extract($this->getModalData());
            require APP_PATH . "/Views/{$this->viewPath}/enrollment-create.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Nueva Inscripción');
        }
    }

    public function storeAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $data   = $this->extractData($_POST);
            $errors = $this->validate($data);

            if (!empty($errors)) {
                echo json_encode(['ok' => false, 'mensaje' => implode(' ', $errors)]);
                return;
            }

            // Usar el nuevo método con transacciones
            $resultado = $this->model->createInscripcion($data);
            
            if ($resultado['ok']) {
                echo json_encode($resultado);
            } else {
                http_response_code(400);
                echo json_encode($resultado);
            }
        } catch (Throwable $e) {
            error_log('InscripcionesController::storeAjax(): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error interno del servidor.']);
        }
    }

    public function listAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $page    = max(1, (int) ($_GET['page'] ?? 1));
            $filters = $this->getFiltersFromRequest();

            $inscripciones  = $this->model->getAll($filters, $page, $this->perPage);
            $total          = $this->model->countAll($filters);

            $paginacion = [
                'desde'         => $total ? (($page - 1) * $this->perPage) + 1 : 0,
                'hasta'         => min($page * $this->perPage, $total),
                'total'         => $total,
                'pagina_actual' => $page,
                'total_paginas' => max(1, (int) ceil($total / $this->perPage)),
            ];

            echo json_encode(['ok' => true, 'inscripciones' => $inscripciones, 'paginacion' => $paginacion]);
        } catch (Throwable $e) {
            error_log('InscripcionesController::listAjax(): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error interno del servidor.']);
        }
    }

    public function store(): void
    {
        try {
            $data = $this->extractData($_POST);
            $errors = $this->validate($data);
            
            if (empty($errors)) {
                // Usar el nuevo método con transacciones
                $resultado = $this->model->createInscripcion($data);
                
                if ($resultado['ok']) {
                    $this->setFlash('success', $resultado['mensaje']);
                    $this->redirect('index');
                } else {
                    $this->setFlash('error', $resultado['mensaje']);
                    $_SESSION['old'] = $_POST;
                    $this->redirect('create');
                }
            } else {
                $_SESSION['errors'] = $errors;
                $_SESSION['old'] = $_POST;
                $this->redirect('create');
            }
        } catch (Throwable $e) {
            $this->handleError($e, 'Crear Inscripción');
        }
    }

    public function ver(int $id): void
    {
        try {
            // Aquí obtendrías los detalles de una inscripción específica
            // Por ahora mostramos una vista simple
            require APP_PATH . "/Views/{$this->viewPath}/enrollment-view.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Ver Inscripción');
        }
    }

    public function continuar(int $id): void
    {
        try {
            // Aquí cargarías una inscripción incompleta para continuar
            require APP_PATH . "/Views/{$this->viewPath}/enrollment-continue.php";
        } catch (Throwable $e) {
            $this->handleError($e, 'Continuar Inscripción');
        }
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q' => trim($_GET['q'] ?? ''),
            'anio' => $_GET['anio'] ?? '',
            'grado' => $_GET['grado'] ?? '',
            'seccion' => $_GET['seccion'] ?? ''
        ];
    }

    protected function extractData(array $source): array
    {
        // Primero necesitamos obtener el id_estructura_academica a partir de los valores del wizard
        $idEstructuraAcademica = $this->obtenerIdEstructuraAcademica($source);
        
        $data = [
            // Datos básicos de inscripción
            'id_estructura_academica' => $idEstructuraAcademica,
            'fecha_inscripcion' => trim($source['fecha_inscripcion'] ?? date('Y-m-d')),
            'fecha_matricula' => trim($source['fecha_matricula'] ?? date('Y-m-d')),
            'es_primer_ingreso' => isset($source['es_primer_ingreso']) ? (int) $source['es_primer_ingreso'] : 1,
            'id_estudiante_existente' => (int) ($source['id_estudiante_existente'] ?? 0),
            
            // Datos académicos adicionales
            'ult_grado_cursado' => trim($source['ult_grado_cursado'] ?? ''),
            'motivo_egreso' => trim($source['motivo_egreso'] ?? ''),
            'anio_escolar_pre' => trim($source['anio_escolar_pre'] ?? ''),
            'id_institucion' => (int) ($source['id_institucion'] ?? 0),
        ];

        // Si es un estudiante nuevo (no existe), extraer datos de persona
        if (!isset($source['id_estudiante_existente']) || $source['id_estudiante_existente'] <= 0) {
            $data['persona'] = [
                'tipo_documento' => strtoupper(trim($source['tipo_documento'] ?? 'V')),
                'numero_documento' => trim($source['numero_documento'] ?? ''),
                'fecha_nacimiento' => trim($source['fecha_nacimiento'] ?? ''),
                'primer_nombre' => trim($source['primer_nombre'] ?? ''),
                'segundo_nombre' => trim($source['segundo_nombre'] ?? ''),
                'primer_apellido' => trim($source['primer_apellido'] ?? ''),
                'segundo_apellido' => trim($source['segundo_apellido'] ?? ''),
                'sexo' => strtoupper(trim($source['sexo'] ?? 'M')),
                'nacionalidad' => trim($source['nacionalidad'] ?? 'Venezolano'),
                'foto' => $source['foto'] ?? null
            ];
        }

        // Contacto
        if (!empty($source['codigo_telefono_principal']) || !empty($source['correo_electronico'])) {
            $data['contacto'] = [
                'codigo_principal' => trim($source['codigo_telefono_principal'] ?? ''),
                'numero_principal' => trim($source['numero_telefono_principal'] ?? ''),
                'codigo_alternativo' => trim($source['codigo_telefono_alternativo'] ?? ''),
                'numero_alternativo' => trim($source['numero_telefono_alternativo'] ?? ''),
                'correo' => trim($source['correo_electronico'] ?? '')
            ];
        }

        // Dirección
        if (!empty($source['sector_urbanizacion']) || !empty($source['calle_avenida'])) {
            $data['direccion'] = [
                'sector' => trim($source['sector_urbanizacion'] ?? ''),
                'calle' => trim($source['calle_avenida'] ?? ''),
                'casa' => trim($source['nro_casa_apto'] ?? ''),
                'referencia' => trim($source['punto_referencia'] ?? ''),
                'parroquia' => (int) ($source['id_parroquia'] ?? 0)
            ];
        }

        // Familiares (del JSON)
        if (!empty($source['familiares_json'])) {
            $familiares = json_decode($source['familiares_json'], true);
            if (is_array($familiares)) {
                // Nota: En una implementación completa, aquí se procesarían los familiares
                // y se extraerían sus datos de persona y relaciones
                $data['familiares'] = $familiares;
            }
        }

        // Recaudos (para estudiante_documento)
        $data['recaudos'] = [];
        if (!empty($source['recaudos'])) {
            // Procesar recaudos según la estructura del wizard
            // Esto debería adaptarse según cómo se envían los recaudos
        }

        // Ficha médica (si existe)
        if (!empty($source['condiciones_especiales']) || !empty($source['alergias'])) {
            $data['ficha_medica'] = [
                'condiciones_especiales' => trim($source['condiciones_especiales'] ?? ''),
                'medicamentos' => trim($source['medicamentos'] ?? ''),
                'observaciones' => trim($source['observaciones_medicas'] ?? ''),
                'alergias' => trim($source['alergias'] ?? ''),
                'enfermedades' => trim($source['enfermedades'] ?? ''),
                'tipo_sangre' => strtoupper(trim($source['tipo_sangre'] ?? ''))
            ];
        }

        return $data;
    }
    
    /**
     * Obtiene el ID de estructura académica a partir de los valores del wizard
     */
    private function obtenerIdEstructuraAcademica(array $source): int
    {
        // Si ya viene el ID directamente, lo usamos
        if (isset($source['id_estructura_academica']) && $source['id_estructura_academica'] > 0) {
            return (int) $source['id_estructura_academica'];
        }
        
        // Obtener valores del wizard
        $gradoNombre = trim($source['grado_cursar'] ?? '');
        $seccionNombre = trim($source['seccion'] ?? '');
        $turnoNombre = trim($source['turno'] ?? '');
        
        if (empty($gradoNombre) || empty($seccionNombre) || empty($turnoNombre)) {
            return 0;
        }
        
        try {
            // Obtener año escolar activo
            $anioActivo = $this->model->getAnioActivo();
            if (empty($anioActivo['id'])) {
                return 0;
            }
            
            // Obtener IDs de las tablas correspondientes
            $idGrado = $this->obtenerIdPorNombre('grado', $gradoNombre);
            $idSeccion = $this->obtenerIdPorNombre('seccion', $seccionNombre);
            $idTurno = $this->obtenerIdPorNombre('turno', $turnoNombre);
            
            if (!$idGrado || !$idSeccion || !$idTurno) {
                return 0;
            }
            
            // Usar el método del modelo para obtener la estructura académica
            return $this->model->getEstructuraAcademicaId($idGrado, $idSeccion, $idTurno, $anioActivo['id']) ?? 0;
            
        } catch (Throwable $e) {
            error_log('Error al obtener estructura académica: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtiene el ID de un registro por su nombre en una tabla
     */
    private function obtenerIdPorNombre(string $tabla, string $nombre): ?int
    {
        try {
            $stmt = $this->model->pdo->prepare(
                "SELECT id_{$tabla} FROM {$tabla} WHERE nombre = :nombre AND estado = 'ACTIVO' LIMIT 1"
            );
            $stmt->execute(['nombre' => $nombre]);
            $result = $stmt->fetchColumn();
            return $result ? (int) $result : null;
        } catch (Throwable $e) {
            error_log("Error al obtener ID de {$tabla}: " . $e->getMessage());
            return null;
        }
    }

    protected function validate(array $data): array
    {
        $errors = [];
        
        // Validar estructura académica
        if ($data['id_estructura_academica'] <= 0) {
            $errors[] = 'Debe seleccionar una estructura académica (grado, sección y turno).';
        }
        
        // Validar fecha de inscripción
        if (empty($data['fecha_inscripcion']) || !strtotime($data['fecha_inscripcion'])) {
            $errors[] = 'La fecha de inscripción no es válida.';
        }
        
        // Validar fecha de matrícula
        if (empty($data['fecha_matricula']) || !strtotime($data['fecha_matricula'])) {
            $errors[] = 'La fecha de matrícula no es válida.';
        }
        
        // Si es estudiante nuevo, validar datos de persona
        if (isset($data['persona']) && !empty($data['persona'])) {
            $persona = $data['persona'];
            
            if (empty($persona['primer_nombre'])) {
                $errors[] = 'El primer nombre es obligatorio.';
            }
            
            if (empty($persona['primer_apellido'])) {
                $errors[] = 'El primer apellido es obligatorio.';
            }
            
            if (empty($persona['numero_documento'])) {
                $errors[] = 'El número de documento es obligatorio.';
            }
            
            if (empty($persona['fecha_nacimiento']) || !strtotime($persona['fecha_nacimiento'])) {
                $errors[] = 'La fecha de nacimiento no es válida.';
            }
            
            if (!in_array(strtoupper($persona['sexo']), ['M', 'F'])) {
                $errors[] = 'El sexo debe ser Masculino (M) o Femenino (F).';
            }
        }
        // Si es estudiante existente, validar que exista
        elseif ($data['id_estudiante_existente'] <= 0) {
            $errors[] = 'Debe seleccionar un estudiante existente o proporcionar datos de un nuevo estudiante.';
        }
        
        // Validar que al menos haya un representante legal si se proporcionan familiares
        if (isset($data['familiares']) && is_array($data['familiares']) && count($data['familiares']) > 0) {
            $tieneRepresentante = false;
            foreach ($data['familiares'] as $familiar) {
                if (isset($familiar['es_representante_legal']) && $familiar['es_representante_legal']) {
                    $tieneRepresentante = true;
                    break;
                }
            }
            
            if (!$tieneRepresentante) {
                $errors[] = 'Debe designar al menos un representante legal entre los familiares registrados.';
            }
        }
        
        return $errors;
    }
}