<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/StaffModel.php';

class StaffController extends BaseController
{
    protected string $viewPath = 'staff';
    protected string $routeName = 'staff';
    protected array $allowedRoles = ['directivo'];
    protected int $perPage = 10;

    protected function createModel(PDO $pdo): StaffModel { return new StaffModel($pdo); }

    public function index(): void
    {
        try {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $filters = ['q' => trim($_GET['q'] ?? ''), 'cargo' => $_GET['cargo'] ?? '', 'estado' => $_GET['estado'] ?? ''];
            $empleados = $this->model->getAll($filters, $page, $this->perPage);
            $total = $this->model->countAll($filters);
            $stats = $this->model->getStats();
            $cargos = $this->model->getOptions('cargo', 'id_cargo', 'nombre');
            $tiposDocumento = $this->model->getOptions('tipo_documento', 'id_tipo_documento', 'nombre');
            $gradosAcademicos = $this->model->getOptions('grado_academico', 'id_grado_academico', 'nombre');
            $titulos = $this->model->getOptions('titulo', 'id_titulo', 'nombre');
            $instituciones = $this->model->getOptions('institucion', 'id_institucion', 'nombre');
            $estados = $this->model->getEstados();
            $tiposInstitucion = $this->model->getOptions('tipo_institucion', 'id_tipo_institucion', 'nombre');
            $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
            require APP_PATH . "/Views/{$this->viewPath}/index.php";
        } catch (Throwable $e) { $this->handleError($e, 'Gestión de empleados'); }
    }

    public function listAjax(): void
    {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $filters = ['q' => trim($_GET['q'] ?? ''), 'cargo' => $_GET['cargo'] ?? '', 'estado' => $_GET['estado'] ?? ''];
        try {
            $empleados  = $this->model->getAll($filters, $page, $this->perPage);
            $total      = $this->model->countAll($filters);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $paginacion = ['desde' => $total ? (($page - 1) * $this->perPage) + 1 : 0, 'hasta' => min($page * $this->perPage, $total), 'total' => $total, 'pagina_actual' => $page, 'total_paginas' => max(1, (int) ceil($total / $this->perPage))];
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'empleados' => $empleados, 'paginacion' => $paginacion]);
        exit;
    }

    public function municipiosPorEstadoAjax(): void
    {
        $id = (int) ($_GET['id_estado'] ?? 0);
        if ($id <= 0) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'municipios' => []]); exit; }
        try {
            $municipios = $this->model->getMunicipiosPorEstado($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'municipios' => $municipios]);
        exit;
    }

    public function parroquiasPorMunicipioAjax(): void
    {
        $id = (int) ($_GET['id_municipio'] ?? 0);
        if ($id <= 0) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'parroquias' => []]); exit; }
        try {
            $parroquias = $this->model->getParroquiasPorMunicipio($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'parroquias' => $parroquias]);
        exit;
    }

    public function storeAjax(): void
    {
        $data = $this->data($_POST);

        // Procesar foto nueva en creación
        if (!empty($_FILES['foto']['tmp_name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $rutaFoto = $this->handleFotoUpload($_FILES['foto'], null);
            if ($rutaFoto !== null) {
                $data['persona']['foto'] = $rutaFoto;
            }
        } else {
            error_log('storeAjax: $_FILES[foto] no llegó o tiene error. FILES=' . json_encode(array_map(fn($f) => ['error' => $f['error'] ?? '?', 'size' => $f['size'] ?? 0, 'tmp' => $f['tmp_name'] ?? ''], $_FILES)));
        }

        try { $result = $this->model->createStaff($data); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    public function toggleStatusAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        try { $ok = $this->model->toggleStatus($id); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond(['ok' => $ok, 'mensaje' => $ok ? 'Estado actualizado correctamente.' : 'No se pudo actualizar el estado.']);
    }

    public function getByIdAjax(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        try {
            $row = $this->model->getById($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        if (!$row) $this->message('Empleado no encontrado.', 404);
        $this->respond(['ok' => true, 'datos' => $row]);
    }

    public function updateAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);
        $data = $this->data($_POST);

        $fotoActual = trim($_POST['foto_actual'] ?? '');

        if (!empty($_FILES['foto']['tmp_name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            // Subieron una foto nueva: guardarla y borrar la anterior
            $rutaFoto = $this->handleFotoUpload($_FILES['foto'], $fotoActual !== '' ? $fotoActual : null);
            if ($rutaFoto !== null) {
                $data['persona']['foto'] = $rutaFoto;
            }
        } elseif ($fotoActual === '') {
            // El usuario quitó la foto explícitamente
            $data['persona']['foto'] = null;
        } else {
            // Sin cambio de foto: quitar la clave para que updateStaff preserve lo que hay en BD
            unset($data['persona']['foto']);
        }

        try { $result = $this->model->updateStaff($id, $data); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    /**
     * Elimina físicamente la foto de un empleado y borra el campo en la BD.
     * Llamado desde el JS cuando el usuario hace click en "Quitar foto" (antes de guardar el form).
     */
    public function removePhotoAjax(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) $this->message('ID de empleado inválido.', 400);

        try {
            $row = $this->model->getById($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }

        if (!$row) $this->message('Empleado no encontrado.', 404);

        $fotoActual = $row['foto'] ?? '';

        // Borrar el archivo del disco
        if ($fotoActual !== '') {
            $rutaAbsoluta = __DIR__ . '/../../public' . $fotoActual;
            if (is_file($rutaAbsoluta)) {
                @unlink($rutaAbsoluta);
            }
        }

        // Limpiar la columna foto en la BD
        try {
            $this->model->clearFoto($id);
        } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }

        $this->respond(['ok' => true, 'mensaje' => 'Foto eliminada correctamente.']);
    }

    /**
     * Normaliza y valida los datos crudos de $_POST hacia la estructura
     * que espera StaffModel. Las claves de cada sub-array coinciden
     * exactamente con los placeholders nombrados (:xxx) usados en los
     * INSERT/UPDATE de StaffModel — no renombrar sin actualizar ambos lados.
     */
    private function data(array $source): array
    {
        $data = [
            'persona' => [
                'tipo_documento'    => trim($source['tipo_documento'] ?? ''),
                'numero_documento'  => trim($source['numero_documento'] ?? ''),
                'fecha_nacimiento'  => trim($source['fecha_nacimiento'] ?? ''),
                'primer_nombre'     => trim($source['primer_nombre'] ?? ''),
                'segundo_nombre'    => trim($source['segundo_nombre'] ?? ''),
                'primer_apellido'   => trim($source['primer_apellido'] ?? ''),
                'segundo_apellido'  => trim($source['segundo_apellido'] ?? ''),
                'sexo'              => trim($source['sexo'] ?? ''),
                'nacionalidad'      => trim($source['nacionalidad'] ?? 'Venezolano'),
                'foto'              => null, // se sobreescribe en storeAjax/updateAjax si viene archivo
            ],
            'fecha_ingreso' => trim($source['fecha_ingreso'] ?? ''),
            'id_cargo'      => (int) ($source['id_cargo'] ?? 0),
            'contacto' => [
                'codigo_principal'    => trim($source['codigo_telefono_principal'] ?? '0412'),
                'numero_principal'    => trim($source['numero_telefono_principal'] ?? ''),
                'codigo_alternativo'  => trim($source['codigo_telefono_alternativo'] ?? '') ?: null,
                'numero_alternativo'  => trim($source['numero_telefono_alternativo'] ?? '') ?: null,
                'correo'              => trim($source['correo_electronico'] ?? ''),
            ],
            'direccion' => [
                'sector'      => trim($source['sector_urbanizacion'] ?? ''),
                'calle'       => trim($source['calle_avenida'] ?? ''),
                'casa'        => trim($source['nro_casa_apto'] ?? ''),
                'referencia'  => trim($source['punto_referencia'] ?? ''),
                'parroquia'   => (int) ($source['id_parroquia'] ?? 0),
            ],
            'formacion' => [
                'titulo'      => (int) ($source['id_titulo'] ?? 0),
                'grado'       => (int) ($source['id_grado_academico'] ?? 0),
                'institucion' => (int) ($source['id_institucion'] ?? 0),
                'obtencion'   => trim($source['fecha_obtencion'] ?? ''),
            ],
        ];

        // Procesar array de títulos académicos agregados desde el wizard
        $formaciones = [];
        if (!empty($source['titulos_json'])) {
            $decoded = json_decode($source['titulos_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    $idTitulo = (int) ($item['id_titulo'] ?? 0);
                    $idGrado  = (int) ($item['id_grado_academico'] ?? 0);
                    $idInst   = (int) ($item['id_institucion'] ?? 0);
                    $fechaObt = trim($item['fecha_obtencion'] ?? '');
                    if ($idTitulo > 0 && $idGrado > 0 && $idInst > 0 && $fechaObt !== '') {
                        $formaciones[] = [
                            'id_titulo'          => $idTitulo,
                            'id_grado_academico' => $idGrado,
                            'id_institucion'     => $idInst,
                            'fecha_obtencion'    => $fechaObt,
                        ];
                    }
                }
            }
        }

        // Compatibilidad: si no vino JSON pero sí campos escalares directos
        if (empty($formaciones) && !empty($source['id_titulo'])) {
            $formaciones[] = [
                'id_titulo'          => (int) $source['id_titulo'],
                'id_grado_academico' => (int) ($source['id_grado_academico'] ?? 0),
                'id_institucion'     => (int) ($source['id_institucion'] ?? 0),
                'fecha_obtencion'    => trim($source['fecha_obtencion'] ?? ''),
            ];
        }

        $data['formaciones'] = $formaciones;

        $errors = [];
        foreach (['tipo_documento' => 'tipo de documento', 'numero_documento' => 'número de documento', 'fecha_nacimiento' => 'fecha de nacimiento', 'primer_nombre' => 'primer nombre', 'primer_apellido' => 'primer apellido', 'sexo' => 'sexo'] as $key => $label) {
            if ($data['persona'][$key] === '') $errors[] = "El {$label} es obligatorio.";
        }
        if ($data['fecha_ingreso'] === '') $errors[] = 'La fecha de ingreso es obligatoria.';
        if ($data['id_cargo'] <= 0) $errors[] = 'El cargo es obligatorio.';
        if ($data['contacto']['numero_principal'] === '') $errors[] = 'El teléfono principal es obligatorio.';
        if ($errors) $this->message(implode(' ', $errors), 422);

        return $data;
    }

    /**
     * Mueve un archivo de foto subido al directorio de empleados y elimina
     * la foto anterior del disco si se proporciona su ruta.
     *
     * @param array       $fileInfo  Elemento de $_FILES['foto']
     * @param string|null $oldPath   Ruta relativa actual (p.ej. /uploads/empleados/x.webp) o null
     * @return string|null           Ruta relativa guardada, o null si falló la subida
     */
    private function handleFotoUpload(array $fileInfo, ?string $oldPath): ?string
    {
        // Validar tipo MIME real del archivo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($fileInfo['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            error_log("handleFotoUpload: MIME rechazado [{$mime}]");
            return null;
        }

        // Validar tamaño (máx 2 MB)
        if ($fileInfo['size'] > 2 * 1024 * 1024) {
            error_log("handleFotoUpload: archivo demasiado grande [{$fileInfo['size']} bytes]");
            return null;
        }

        // Determinar extensión a partir del MIME
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => 'jpg',
        };

        $uploadDir = __DIR__ . '/../../public/uploads/empleados/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'empleado_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destino  = $uploadDir . $filename;

        if (!move_uploaded_file($fileInfo['tmp_name'], $destino)) {
            // Fallback para entornos donde move_uploaded_file falla (ej. php -S)
            if (!copy($fileInfo['tmp_name'], $destino)) {
                error_log("handleFotoUpload: move_uploaded_file y copy fallaron. tmp=[{$fileInfo['tmp_name']}] destino=[{$destino}] writable=[" . (is_writable($uploadDir) ? 'si' : 'no') . "]");
                return null;
            }
            @unlink($fileInfo['tmp_name']);
        }

        // Borrar la foto anterior del disco
        if ($oldPath !== null && $oldPath !== '') {
            $rutaVieja = __DIR__ . '/../../public' . $oldPath;
            if (is_file($rutaVieja)) {
                @unlink($rutaVieja);
            }
        }

        return '/uploads/empleados/' . $filename;
    }

    private function respond(array $result): never { header('Content-Type: application/json'); if (!$result['ok']) http_response_code(400); echo json_encode($result); exit; }
    private function message(string $message, int $code): never { header('Content-Type: application/json'); http_response_code($code); echo json_encode(['ok' => false, 'mensaje' => $message]); exit; }

    public function storeTituloAjax(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') $this->message('El nombre del título es obligatorio.', 422);
        try { $result = $this->model->createTitulo($nombre); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    public function storeTipoInstitucionAjax(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') $this->message('El nombre del tipo de institución es obligatorio.', 422);
        try { $result = $this->model->createTipoInstitucion($nombre); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }

    public function storeInstitucionAjax(): void
    {
        $nombre = trim($_POST['nombre'] ?? '');
        $idTipo = (int) ($_POST['id_tipo_institucion'] ?? 0);
        if ($nombre === '' || $idTipo <= 0) $this->message('Nombre y tipo de institución son obligatorios.', 422);
        try { $result = $this->model->createInstitucion($nombre, $idTipo); } catch (Throwable $e) { $this->jsonError($e, 'Gestión de empleados'); return; }
        $this->respond($result);
    }
}