<?php

require_once __DIR__ . '/BaseModel.php';

class InscripcionesModel extends BaseModel
{
    protected string $table = 'inscripcion';
    protected string $primaryKey = 'id_inscripcion';

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'p.cedula'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        try {
            [$where, $params] = $this->filters($filters);
            
            $sql = "SELECT i.id_inscripcion AS id,
                           COALESCE(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido), 'Estudiante') AS nombre,
                           COALESCE(p.cedula, 'Sin cédula') AS cedula,
                           COALESCE(CONCAT(UPPER(SUBSTRING(p.primer_nombre FROM 1 FOR 1)), 
                                   UPPER(SUBSTRING(p.primer_apellido FROM 1 FOR 1))), 'ES') AS iniciales,
                           'primary' AS avatar_color,
                           NULL AS avatar_img,
                           COALESCE(g.nombre, 'Sin grado') AS grado,
                           COALESCE(s.nombre, 'Sin sección') AS seccion,
                           DATE_FORMAT(COALESCE(i.fecha_inscripcion, NOW()), '%d/%m/%Y') AS fecha_inscripcion,
                           CASE 
                               WHEN i.estado = 'COMPLETO' THEN 'completa'
                               ELSE 'incompleta'
                           END AS estado,
                           COALESCE(a.nombre_periodo, '2024-2025') AS anio_escolar
                    FROM inscripcion i
                    LEFT JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                    LEFT JOIN persona p ON p.id_persona = e.id_persona
                    LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                    LEFT JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar";
            
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            
            $sql .= ' ORDER BY i.fecha_inscripcion DESC, p.primer_apellido, p.primer_nombre LIMIT :limit OFFSET :offset';
            
            $stmt = $this->pdo->prepare($sql);
            
            // Bind de parámetros de filtros
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
            
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si no hay resultados, retornar array vacío
            return $result ?: [];
            
        } catch (PDOException $e) {
            error_log("Error en getAll: " . $e->getMessage());
            // Retornar array vacío en caso de error
            return [];
        }
    }

    public function countAll(array $filters = []): int
    {
        try {
            [$where, $params] = $this->filters($filters);
            
            $sql = "SELECT COUNT(*) 
                    FROM inscripcion i
                    LEFT JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                    LEFT JOIN persona p ON p.id_persona = e.id_persona
                    LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                    LEFT JOIN grado g ON g.id_grado = ea.id_grado
                    LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                    LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar";
            
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Error en countAll: " . $e->getMessage());
            return 0; // Retornar 0 en caso de error
        }
    }

    public function getStats(): array
    {
        try {
            // 1. Inscritos este año escolar (año actual)
            $inscritosAnio = 0;
            try {
                $sql = "SELECT COUNT(*) as total 
                        FROM inscripcion i
                        INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                        INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                        WHERE a.estado = 'ACTIVO'";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $inscritosAnio = (int) ($result['total'] ?? 0);
            } catch (Exception $e) {
                error_log("Error en getStats-inscritosAnio: " . $e->getMessage());
            }
            
            // 2. Tendencia vs mes anterior (simplificada)
            $trendInscritos = 0;
            try {
                $sql = "SELECT 
                    COUNT(CASE WHEN MONTH(fecha_inscripcion) = MONTH(CURDATE()) THEN 1 END) as mes_actual,
                    COUNT(CASE WHEN MONTH(fecha_inscripcion) = MONTH(CURDATE()) - 1 THEN 1 END) as mes_anterior
                    FROM inscripcion 
                    WHERE YEAR(fecha_inscripcion) = YEAR(CURDATE())";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $mes_actual = (int) ($result['mes_actual'] ?? 0);
                $mes_anterior = (int) ($result['mes_anterior'] ?? 0);
                
                if ($mes_anterior > 0) {
                    $trendInscritos = round((($mes_actual - $mes_anterior) / $mes_anterior) * 100);
                }
            } catch (Exception $e) {
                error_log("Error en getStats-trendInscritos: " . $e->getMessage());
            }
            
            // 3. Cupos disponibles totales
            $cuposDisponibles = 0;
            try {
                // Primero obtener capacidad total
                $sql_capacidad = "SELECT COALESCE(SUM(capacidad_maxima), 0) as capacidad_total 
                                 FROM estructura_academica 
                                 WHERE id_estructura_academica IN (SELECT id_estructura_academica FROM inscripcion)";
                $stmt = $this->pdo->prepare($sql_capacidad);
                $stmt->execute();
                $capacidad = $stmt->fetch(PDO::FETCH_ASSOC);
                $capacidad_total = (int) ($capacidad['capacidad_total'] ?? 0);
                
                // Obtener inscritos totales
                $sql_inscritos = "SELECT COUNT(*) as inscritos_total FROM inscripcion WHERE estado = 'COMPLETO'";
                $stmt = $this->pdo->prepare($sql_inscritos);
                $stmt->execute();
                $inscritos = $stmt->fetch(PDO::FETCH_ASSOC);
                $inscritos_total = (int) ($inscritos['inscritos_total'] ?? 0);
                
                $cuposDisponibles = max(0, $capacidad_total - $inscritos_total);
            } catch (Exception $e) {
                error_log("Error en getStats-cuposDisponibles: " . $e->getMessage());
            }
            
            // 4. Inscripciones este mes
            $inscripcionesMes = 0;
            try {
                $sql = "SELECT COUNT(*) as total 
                        FROM inscripcion 
                        WHERE MONTH(fecha_inscripcion) = MONTH(CURDATE()) 
                        AND YEAR(fecha_inscripcion) = YEAR(CURDATE())";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $inscripcionesMes = (int) ($result['total'] ?? 0);
            } catch (Exception $e) {
                error_log("Error en getStats-inscripcionesMes: " . $e->getMessage());
            }
            
            return [
                'inscritosAnio' => $inscritosAnio,
                'trendInscritos' => $trendInscritos,
                'cuposDisponibles' => $cuposDisponibles,
                'inscripcionesMes' => $inscripcionesMes
            ];
        } catch (PDOException $e) {
            error_log("Error en getStats: " . $e->getMessage());
            return [
                'inscritosAnio' => 0,
                'trendInscritos' => 0,
                'cuposDisponibles' => 0,
                'inscripcionesMes' => 0
            ];
        }
    }

    public function getOptions(string $type): array
    {
        try {
            switch ($type) {
                case 'anios':
                    $sql = "SELECT DISTINCT nombre_periodo FROM anio_escolar WHERE estado = 'ACTIVO' ORDER BY fecha_inicio DESC";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                case 'grados':
                    $sql = "SELECT DISTINCT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY orden";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                case 'secciones':
                    $sql = "SELECT DISTINCT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute();
                    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                default:
                    return [];
            }
        } catch (PDOException $e) {
            error_log("Error en getOptions({$type}): " . $e->getMessage());
            switch ($type) {
                case 'anios':    return ['2024-2025', '2023-2024'];
                case 'grados':   return ['1er Grado', '2do Grado', '3er Grado', '4to Grado', '5to Grado', '6to Grado'];
                case 'secciones': return ['A', 'B', 'C'];
                default:         return [];
            }
        }
    }

    /**
     * Opciones con id+nombre para los selects del modal wizard.
     * @param string $table  grado | seccion | turno
     */
    public function getOptionsForModal(string $table): array
    {
        $allowed = [
            'grado'   => ['id_grado',   'nombre'],
            'seccion' => ['id_seccion', 'nombre'],
            'turno'   => ['id_turno',   'nombre'],
        ];
        if (!isset($allowed[$table])) return [];

        [$id, $name] = $allowed[$table];
        try {
            $stmt = $this->pdo->query(
                "SELECT {$id} AS id, {$name} AS nombre FROM {$table} WHERE estado = 'ACTIVO' ORDER BY {$name}"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("InscripcionesModel::getOptionsForModal({$table}): " . $e->getMessage());
            return [];
        }
    }

    /** Año escolar activo para el paso 3 del wizard (readonly). */
    public function getAnioActivo(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id_anio_escolar AS id, nombre_periodo AS nombre
                   FROM anio_escolar WHERE estado = 'ACTIVO'
                   ORDER BY fecha_inicio DESC LIMIT 1"
            );
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => '', 'nombre' => ''];
        } catch (PDOException $e) {
            error_log("InscripcionesModel::getAnioActivo(): " . $e->getMessage());
            return ['id' => '', 'nombre' => ''];
        }
    }

    private function filters(array $filters): array
    {
        $params = [];

        // Filtro de búsqueda por nombre o cédula
        if (!empty($filters['q'])) {
            $where[] = "(p.primer_nombre LIKE :q OR p.primer_apellido LIKE :q OR p.cedula LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        // Filtro por año escolar
        if (!empty($filters['anio'])) {
            $where[] = "a.nombre_periodo = :anio";
            $params['anio'] = $filters['anio'];
        }

        // Filtro por grado
        if (!empty($filters['grado'])) {
            $where[] = "g.nombre = :grado";
            $params['grado'] = $filters['grado'];
        }

        // Filtro por sección
        if (!empty($filters['seccion'])) {
            $where[] = "s.nombre = :seccion";
            $params['seccion'] = $filters['seccion'];
        }

        return [$where, $params];
    }
}


    /**
     * Crea una inscripción completa con transacciones.
     * Maneja múltiples tablas: persona, estudiante, matricula, inscripcion, contacto, direccion, familiares.
     * 
     * @param array $data Datos del formulario wizard
     * @return array Resultado de la operación
     */
    public function createInscripcion(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Paso 1: Crear persona (si es nuevo estudiante)
            $personaId = null;
            if (isset($data['persona']) && !empty($data['persona'])) {
                $personaData = $data['persona'];
                $stmt = $this->pdo->prepare(
                    'INSERT INTO persona (tipo_documento, numero_documento, fecha_nacimiento, 
                    primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, 
                    estado, nacionalidad, foto) 
                    VALUES (:tipo_documento, :numero_documento, :fecha_nacimiento, 
                    :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, 
                    :sexo, \'ACTIVO\', :nacionalidad, :foto)'
                );
                $stmt->execute($personaData);
                $personaId = (int) $this->pdo->lastInsertId();
            } elseif (isset($data['id_estudiante_existente']) && $data['id_estudiante_existente'] > 0) {
                // Obtener personaId del estudiante existente
                $stmt = $this->pdo->prepare('SELECT id_persona FROM estudiante WHERE id_estudiante = :id');
                $stmt->execute(['id' => $data['id_estudiante_existente']]);
                $personaId = (int) $stmt->fetchColumn();
            }

            // Paso 2: Crear estudiante (si es nuevo)
            $estudianteId = null;
            if ($personaId && isset($data['persona'])) {
                $stmt = $this->pdo->prepare('INSERT INTO estudiante (id_persona) VALUES (:persona)');
                $stmt->execute(['persona' => $personaId]);
                $estudianteId = (int) $this->pdo->lastInsertId();
            } else {
                $estudianteId = $data['id_estudiante_existente'];
            }

            // Paso 3: Crear matrícula
            $stmt = $this->pdo->prepare(
                'INSERT INTO matricula (id_estudiante, fecha_matricula) 
                VALUES (:estudiante, :fecha_matricula)'
            );
            $stmt->execute([
                'estudiante' => $estudianteId,
                'fecha_matricula' => $data['fecha_matricula'] ?? date('Y-m-d')
            ]);
            $matriculaId = (int) $this->pdo->lastInsertId();

            // Paso 4: Crear inscripción
            $stmt = $this->pdo->prepare(
                'INSERT INTO inscripcion (id_matricula, id_estructura_academica, 
                ult_grado_cursado, es_primer_ingreso, motivo_egreso, anio_escolar_pre, 
                id_institucion, fecha_inscripcion, estado) 
                VALUES (:matricula, :estructura_academica, :ult_grado_cursado, 
                :es_primer_ingreso, :motivo_egreso, :anio_escolar_pre, :id_institucion, 
                :fecha_inscripcion, \'COMPLETO\')'
            );
            $inscripcionData = [
                'matricula' => $matriculaId,
                'estructura_academica' => $data['id_estructura_academica'],
                'ult_grado_cursado' => $data['ult_grado_cursado'] ?? null,
                'es_primer_ingreso' => $data['es_primer_ingreso'] ?? 1,
                'motivo_egreso' => $data['motivo_egreso'] ?? null,
                'anio_escolar_pre' => $data['anio_escolar_pre'] ?? null,
                'id_institucion' => $data['id_institucion'] ?? null,
                'fecha_inscripcion' => $data['fecha_inscripcion'] ?? date('Y-m-d')
            ];
            $stmt->execute($inscripcionData);
            $inscripcionId = (int) $this->pdo->lastInsertId();

            // Paso 5: Crear contacto (si se proporcionó)
            if ($personaId && isset($data['contacto']) && !empty($data['contacto'])) {
                $contactoData = $data['contacto'];
                $contactoData['persona'] = $personaId;
                $stmt = $this->pdo->prepare(
                    'INSERT INTO contacto (id_persona, codigo_telefono_principal, 
                    numero_telefono_principal, codigo_telefono_alternativo, 
                    numero_telefono_alternativo, correo_electronico) 
                    VALUES (:persona, :codigo_principal, :numero_principal, 
                    :codigo_alternativo, :numero_alternativo, :correo)'
                );
                $stmt->execute($contactoData);
            }

            // Paso 6: Crear dirección (si se proporcionó)
            if ($personaId && isset($data['direccion']) && !empty($data['direccion'])) {
                $direccionData = $data['direccion'];
                $direccionData['persona'] = $personaId;
                $stmt = $this->pdo->prepare(
                    'INSERT INTO direccion (id_persona, sector_urbanizacion, calle_avenida, 
                    nro_casa_apto, punto_referencia, id_parroquia) 
                    VALUES (:persona, :sector, :calle, :casa, :referencia, :parroquia)'
                );
                $stmt->execute($direccionData);
            }

            // Paso 7: Crear familiares/representantes
            if ($estudianteId && isset($data['familiares']) && is_array($data['familiares'])) {
                foreach ($data['familiares'] as $familiar) {
                    if (!isset($familiar['id_persona']) || !$familiar['id_persona']) continue;
                    
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO persona_estudiante (id_persona, id_estudiante, 
                        es_representante_legal, es_autorizado_retiro, estado_vital, 
                        estado_relacion, id_parentesco, id_nivel_instruccion, id_ocupacion) 
                        VALUES (:persona, :estudiante, :representante_legal, 
                        :autorizado_retiro, \'VIVO\', \'ACTIVO\', :parentesco, 
                        :nivel_instruccion, :ocupacion)'
                    );
                    $stmt->execute([
                        'persona' => $familiar['id_persona'],
                        'estudiante' => $estudianteId,
                        'representante_legal' => $familiar['es_representante_legal'] ?? 0,
                        'autorizado_retiro' => $familiar['es_autorizado_retiro'] ?? 0,
                        'parentesco' => $familiar['id_parentesco'] ?? null,
                        'nivel_instruccion' => $familiar['id_nivel_instruccion'] ?? null,
                        'ocupacion' => $familiar['id_ocupacion'] ?? null
                    ]);
                }
            }

            // Paso 8: Crear recaudos/documentos
            if ($estudianteId && isset($data['recaudos']) && is_array($data['recaudos'])) {
                foreach ($data['recaudos'] as $recaudo) {
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO estudiante_documento (id_estudiante, id_tipo_documento, 
                        observacion, consignado) 
                        VALUES (:estudiante, :tipo_documento, :observacion, :consignado)'
                    );
                    $stmt->execute([
                        'estudiante' => $estudianteId,
                        'tipo_documento' => $recaudo['id_tipo_documento'],
                        'observacion' => $recaudo['observacion'] ?? null,
                        'consignado' => $recaudo['consignado'] ?? 0
                    ]);
                }
            }

            // Paso 9: Crear ficha médica (si se proporcionó)
            if ($estudianteId && isset($data['ficha_medica']) && !empty($data['ficha_medica'])) {
                $fichaData = $data['ficha_medica'];
                $fichaData['estudiante'] = $estudianteId;
                $fichaData['fecha_actualizacion'] = date('Y-m-d');
                
                $stmt = $this->pdo->prepare(
                    'INSERT INTO ficha_medica (id_estudiante, condiciones_especiales, 
                    medicamentos, observaciones, alergias, enfermedades, tipo_sangre, 
                    fecha_actualizacion) 
                    VALUES (:estudiante, :condiciones_especiales, :medicamentos, 
                    :observaciones, :alergias, :enfermedades, :tipo_sangre, :fecha_actualizacion)'
                );
                $stmt->execute($fichaData);
            }

            // Confirmar transacción
            $this->pdo->commit();

            return [
                'ok' => true,
                'id' => $inscripcionId,
                'id_matricula' => $matriculaId,
                'id_estudiante' => $estudianteId,
                'mensaje' => 'Inscripción registrada correctamente.'
            ];

        } catch (Throwable $e) {
            // Revertir transacción en caso de error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            error_log('InscripcionesModel::createInscripcion(): ' . $e->getMessage());
            return [
                'ok' => false,
                'mensaje' => 'No se pudo registrar la inscripción. Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene una estructura académica por grado, sección, turno y año escolar
     */
    public function getEstructuraAcademicaId(int $id_grado, int $id_seccion, int $id_turno, int $id_anio_escolar): ?int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id_estructura_academica FROM estructura_academica 
                WHERE id_grado = :grado AND id_seccion = :seccion 
                AND id_turno = :turno AND id_anio_escolar = :anio'
            );
            $stmt->execute([
                'grado' => $id_grado,
                'seccion' => $id_seccion,
                'turno' => $id_turno,
                'anio' => $id_anio_escolar
            ]);
            return $stmt->fetchColumn() ? (int) $stmt->fetchColumn() : null;
        } catch (PDOException $e) {
            error_log("Error al obtener estructura académica: " . $e->getMessage());
            return null;
        }
    }
}