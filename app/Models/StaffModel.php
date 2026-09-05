<?php

require_once __DIR__ . '/BaseModel.php';

class StaffModel extends BaseModel
{
    protected string $table = 'empleado';
    protected string $primaryKey = 'id_empleado';

    /**
     * Nombres de tipo_institucion que no aplican como tipo de institución
     * de formación académica del docente (comparación case-insensitive).
     */
    private const TIPOS_INSTITUCION_EXCLUIDOS_FORMACION = ['preescolar', 'escuela'];

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento', 'c.nombre'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT e.id_empleado AS id, p.id_persona, p.primer_nombre, p.segundo_nombre,
                       p.primer_apellido, p.segundo_apellido, p.numero_documento,
                       p.tipo_documento, p.sexo, p.foto, e.fecha_ingreso, c.nombre AS cargo,
                       p.estado
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);
        $sql = 'SELECT COUNT(*) FROM empleado e INNER JOIN persona p ON p.id_persona = e.id_persona LEFT JOIN cargo c ON c.id_cargo = e.id_cargo';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM empleado')->fetchColumn();
        $activos = (int) $this->pdo->query("SELECT COUNT(*) FROM empleado e INNER JOIN persona p ON p.id_persona = e.id_persona WHERE p.estado = 'ACTIVO' AND e.fecha_egreso IS NULL")->fetchColumn();
        $cargos = (int) $this->pdo->query('SELECT COUNT(DISTINCT id_cargo) FROM empleado WHERE id_cargo IS NOT NULL')->fetchColumn();
        return ['total' => $total, 'activos' => $activos, 'inactivos' => max(0, $total - $activos), 'cargos' => $cargos];
    }

    public function getEstados(): array
    {
        return $this->pdo->query('SELECT id_estado, nombre FROM estado ORDER BY nombre')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMunicipiosPorEstado(int $idEstado): array
    {
        $stmt = $this->pdo->prepare('SELECT id_municipio, nombre FROM municipio WHERE id_estado = :id ORDER BY nombre');
        $stmt->execute([':id' => $idEstado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParroquiasPorMunicipio(int $idMunicipio): array
    {
        $stmt = $this->pdo->prepare('SELECT id_parroquia, nombre FROM parroquia WHERE id_municipio = :id ORDER BY nombre');
        $stmt->execute([':id' => $idMunicipio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOptions(string $table, string $id, string $name): array
    {
        $allowed = ['cargo', 'tipo_documento', 'grado_academico', 'titulo', 'institucion', 'estado', 'municipio', 'parroquia', 'tipo_institucion'];
        if (!in_array($table, $allowed, true)) return [];

        // tipo_institucion e institucion tienen una regla de negocio propia
        // (excluir preescolar/escuela), así que se delegan a su propio
        // método en vez de mezclar la excepción en la consulta genérica.
        if ($table === 'tipo_institucion') {
            return $this->getTiposInstitucionFormacion();
        }
        if ($table === 'institucion') {
            return $this->getInstitucionesFormacion();
        }

        $where = $table === 'parroquia' && isset($_GET['municipio']) ? ' WHERE id_municipio = ' . (int) $_GET['municipio'] : '';
        if ($table !== 'municipio' && $table !== 'parroquia') $where = " WHERE estado = 'ACTIVO'";

        return $this->pdo->query("SELECT {$id} AS id, {$name} AS nombre FROM {$table}{$where} ORDER BY {$name}")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tipos de institución disponibles para el formulario de formación
     * académica del docente. Excluye los tipos definidos en
     * TIPOS_INSTITUCION_EXCLUIDOS_FORMACION (preescolar, escuela),
     * ya que esos niveles no aplican como institución de formación.
     */
    public function getTiposInstitucionFormacion(): array
    {
        [$clausula, $params] = $this->clausulaExclusionTiposInstitucion('nombre');

        $sql = "SELECT id_tipo_institucion AS id, nombre
                FROM tipo_institucion
                WHERE estado = 'ACTIVO'
                  AND {$clausula}
                ORDER BY nombre";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Instituciones disponibles para el formulario de formación académica
     * del docente. Excluye las instituciones cuyo tipo esté en
     * TIPOS_INSTITUCION_EXCLUIDOS_FORMACION (preescolar, escuela),
     * vía JOIN con tipo_institucion — no por el nombre de la institución.
     */
    public function getInstitucionesFormacion(): array
    {
        [$clausula, $params] = $this->clausulaExclusionTiposInstitucion('ti.nombre');

        $sql = "SELECT i.id_institucion AS id, i.nombre
                FROM institucion i
                INNER JOIN tipo_institucion ti ON ti.id_tipo_institucion = i.id_tipo_institucion
                WHERE i.estado = 'ACTIVO'
                  AND {$clausula}
                ORDER BY i.nombre";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Construye la cláusula "LOWER(TRIM(campo)) NOT IN (...)" y sus
     * parámetros a partir de TIPOS_INSTITUCION_EXCLUIDOS_FORMACION.
     * $campoNombre es la columna de tipo_institucion.nombre a comparar
     * (con o sin alias, según la consulta que la use).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function clausulaExclusionTiposInstitucion(string $campoNombre): array
    {
        $placeholders = [];
        $params = [];

        foreach (self::TIPOS_INSTITUCION_EXCLUIDOS_FORMACION as $i => $nombreExcluido) {
            $clave = ":excluido{$i}";
            $placeholders[] = $clave;
            $params[$clave] = $nombreExcluido;
        }

        $clausula = "LOWER(TRIM({$campoNombre})) NOT IN (" . implode(', ', $placeholders) . ")";
        return [$clausula, $params];
    }

    public function createStaff(array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Validar que la cédula/documento no exista previamente en persona
            $stmtCheck = $this->pdo->prepare('SELECT COUNT(*) FROM persona WHERE tipo_documento = :tipo AND numero_documento = :num');
            $stmtCheck->execute([
                'tipo' => $data['persona']['tipo_documento'],
                'num'  => $data['persona']['numero_documento'],
            ]);
            if ((int) $stmtCheck->fetchColumn() > 0) {
                $this->pdo->rollBack();
                return [
                    'ok' => false,
                    'mensaje' => "Ya existe una persona registrada con el documento {$data['persona']['tipo_documento']}-{$data['persona']['numero_documento']}."
                ];
            }

            // 2. Insertar persona
            $stmt = $this->pdo->prepare('INSERT INTO persona (tipo_documento, numero_documento, fecha_nacimiento, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, estado, nacionalidad, foto) VALUES (:tipo_documento, :numero_documento, :fecha_nacimiento, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :sexo, \'ACTIVO\', :nacionalidad, :foto)');
            $stmt->execute($data['persona']);
            $personaId = (int) $this->pdo->lastInsertId();

            // 3. Insertar empleado
            $stmt = $this->pdo->prepare('INSERT INTO empleado (id_persona, fecha_ingreso, id_cargo) VALUES (:persona, :ingreso, :cargo)');
            $stmt->execute(['persona' => $personaId, 'ingreso' => $data['fecha_ingreso'], 'cargo' => $data['id_cargo'] ?: null]);
            $empleadoId = (int) $this->pdo->lastInsertId();

            // 4. Insertar contacto
            if (!empty($data['contacto']['numero_principal']) || !empty($data['contacto']['correo'])) {
                $data['contacto']['persona'] = $personaId;
                $this->pdo->prepare('INSERT INTO contacto (id_persona, codigo_telefono_principal, numero_telefono_principal, codigo_telefono_alternativo, numero_telefono_alternativo, correo_electronico) VALUES (:persona, :codigo_principal, :numero_principal, :codigo_alternativo, :numero_alternativo, :correo)')->execute($data['contacto']);
            }

            // 5. Insertar dirección
            if (!empty($data['direccion']['parroquia']) && ($data['direccion']['sector'] !== '' || $data['direccion']['calle'] !== '')) {
                $data['direccion']['persona'] = $personaId;
                $this->pdo->prepare('INSERT INTO direccion (id_persona, sector_urbanizacion, calle_avenida, nro_casa_apto, punto_referencia, id_parroquia) VALUES (:persona, :sector, :calle, :casa, :referencia, :parroquia)')->execute($data['direccion']);
            }

            // 6. Insertar formación académica (títulos recolectados en el wizard o únicos)
            $formaciones = $data['formaciones'] ?? [];
            if (empty($formaciones) && !empty($data['formacion']['titulo'])) {
                $formaciones = [$data['formacion']];
            }

            if (!empty($formaciones)) {
                $stmtFa = $this->pdo->prepare('INSERT INTO formacion_academica (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion, estado) VALUES (:empleado, :titulo, :grado, :institucion, :obtencion, \'ACTIVO\')');
                foreach ($formaciones as $fa) {
                    $idTitulo = (int) ($fa['id_titulo'] ?? $fa['titulo'] ?? 0);
                    $idGrado  = (int) ($fa['id_grado_academico'] ?? $fa['grado'] ?? 0);
                    $idInst   = (int) ($fa['id_institucion'] ?? $fa['institucion'] ?? 0);
                    $fechaObt = trim($fa['fecha_obtencion'] ?? $fa['obtencion'] ?? '');

                    if ($idTitulo > 0 && $idGrado > 0 && $idInst > 0 && $fechaObt !== '') {
                        $stmtFa->execute([
                            'empleado'    => $empleadoId,
                            'titulo'      => $idTitulo,
                            'grado'       => $idGrado,
                            'institucion' => $idInst,
                            'obtencion'   => $fechaObt,
                        ]);
                    }
                }
            }

            $this->pdo->commit();
            return ['ok' => true, 'id' => $empleadoId, 'mensaje' => 'Empleado registrado correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::createStaff(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el empleado: ' . $e->getMessage()];
        }
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT e.id_empleado, e.id_persona, e.id_cargo, e.fecha_ingreso,
                       p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       p.sexo, p.nacionalidad, p.estado, p.foto,
                       ct.codigo_telefono_principal, ct.numero_telefono_principal,
                       ct.codigo_telefono_alternativo, ct.numero_telefono_alternativo,
                       ct.correo_electronico,
                       d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto,
                       d.punto_referencia, d.id_parroquia,
                       c.nombre AS cargo
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT  JOIN cargo c    ON c.id_cargo   = e.id_cargo
                LEFT  JOIN contacto ct ON ct.id_persona = p.id_persona
                LEFT  JOIN direccion d  ON d.id_persona  = p.id_persona
                WHERE e.id_empleado = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Cargar todo el historial de formación académica del empleado
        $stmtFa = $this->pdo->prepare(
            "SELECT fa.id_formacion_academica, fa.id_titulo, fa.id_grado_academico, fa.id_institucion, fa.fecha_obtencion,
                    t.nombre AS titulo_nombre,
                    i.nombre AS institucion_nombre,
                    ti.nombre AS tipo_institucion_nombre,
                    ga.nombre AS grado_academico_nombre
             FROM formacion_academica fa
             LEFT JOIN titulo t ON t.id_titulo = fa.id_titulo
             LEFT JOIN institucion i ON i.id_institucion = fa.id_institucion
             LEFT JOIN tipo_institucion ti ON ti.id_tipo_institucion = i.id_tipo_institucion
             LEFT JOIN grado_academico ga ON ga.id_grado_academico = fa.id_grado_academico
             WHERE fa.id_empleado = :id AND fa.estado = 'ACTIVO'
             ORDER BY fa.fecha_obtencion DESC"
        );
        $stmtFa->execute(['id' => $id]);
        $formaciones = $stmtFa->fetchAll(PDO::FETCH_ASSOC);
        $row['formaciones'] = $formaciones;

        if (!empty($formaciones)) {
            $row['id_titulo'] = (int) $formaciones[0]['id_titulo'];
            $row['id_grado_academico'] = (int) $formaciones[0]['id_grado_academico'];
            $row['id_institucion'] = (int) $formaciones[0]['id_institucion'];
            $row['fecha_obtencion'] = $formaciones[0]['fecha_obtencion'];
            $row['titulo_nombre'] = $formaciones[0]['titulo_nombre'];
            $row['institucion_nombre'] = $formaciones[0]['institucion_nombre'];
            $row['tipo_institucion_nombre'] = $formaciones[0]['tipo_institucion_nombre'];
            $row['grado_academico_nombre'] = $formaciones[0]['grado_academico_nombre'];
        } else {
            $row['id_titulo'] = null;
            $row['id_grado_academico'] = null;
            $row['id_institucion'] = null;
            $row['fecha_obtencion'] = null;
            $row['titulo_nombre'] = null;
            $row['institucion_nombre'] = null;
            $row['tipo_institucion_nombre'] = null;
            $row['grado_academico_nombre'] = null;
        }

        return $row;
    }

    public function updateStaff(int $id, array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT id_persona FROM empleado WHERE id_empleado = :id');
            $stmt->execute(['id' => $id]);
            $personaId = (int) $stmt->fetchColumn();
            if (!$personaId) throw new \RuntimeException('Empleado no encontrado.');

            // La foto es opcional en la edición: solo se actualiza si viene
            // un valor nuevo (base64 o ruta). Si no se envía, se conserva la
            // que ya existe en la BD para no perderla al guardar el resto
            // del formulario.
            $datosPersona = $data['persona'];
            // Nota: NO se hace unset() cuando foto === null; ese valor nulo es
            // intencional (el usuario quitó la foto) y debe escribirse en la BD.
            // Solo se omite la columna foto cuando la clave no existe en absoluto
            // (caso "no se tocó la foto en el formulario").

            if (array_key_exists('foto', $datosPersona)) {
                $this->pdo->prepare(
                    'UPDATE persona SET tipo_documento = :tipo_documento, numero_documento = :numero_documento,
                     fecha_nacimiento = :fecha_nacimiento, primer_nombre = :primer_nombre,
                     segundo_nombre = :segundo_nombre, primer_apellido = :primer_apellido,
                     segundo_apellido = :segundo_apellido, sexo = :sexo, nacionalidad = :nacionalidad,
                     foto = :foto
                     WHERE id_persona = :id_persona'
                )->execute(array_merge($datosPersona, ['id_persona' => $personaId]));
            } else {
                $this->pdo->prepare(
                    'UPDATE persona SET tipo_documento = :tipo_documento, numero_documento = :numero_documento,
                     fecha_nacimiento = :fecha_nacimiento, primer_nombre = :primer_nombre,
                     segundo_nombre = :segundo_nombre, primer_apellido = :primer_apellido,
                     segundo_apellido = :segundo_apellido, sexo = :sexo, nacionalidad = :nacionalidad
                     WHERE id_persona = :id_persona'
                )->execute(array_merge($datosPersona, ['id_persona' => $personaId]));
            }

            $this->pdo->prepare(
                'UPDATE empleado SET fecha_ingreso = :ingreso, id_cargo = :cargo WHERE id_empleado = :id'
            )->execute(['ingreso' => $data['fecha_ingreso'], 'cargo' => $data['id_cargo'] ?: null, 'id' => $id]);

            $hayContacto = $data['contacto']['numero_principal'] !== '' || $data['contacto']['correo'] !== '';
            if ($hayContacto) {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM contacto WHERE id_persona = :p');
                $stmt->execute(['p' => $personaId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->pdo->prepare(
                        'UPDATE contacto SET codigo_telefono_principal = :codigo_principal,
                         numero_telefono_principal = :numero_principal,
                         codigo_telefono_alternativo = :codigo_alternativo,
                         numero_telefono_alternativo = :numero_alternativo,
                         correo_electronico = :correo
                         WHERE id_persona = :persona'
                    )->execute(array_merge($data['contacto'], ['persona' => $personaId]));
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO contacto (id_persona, codigo_telefono_principal, numero_telefono_principal,
                         codigo_telefono_alternativo, numero_telefono_alternativo, correo_electronico)
                         VALUES (:persona, :codigo_principal, :numero_principal, :codigo_alternativo, :numero_alternativo, :correo)'
                    )->execute(array_merge($data['contacto'], ['persona' => $personaId]));
                }
            }

            $hayDireccion = $data['direccion']['sector'] !== '' || $data['direccion']['calle'] !== '';
            if ($hayDireccion) {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM direccion WHERE id_persona = :p');
                $stmt->execute(['p' => $personaId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->pdo->prepare(
                        'UPDATE direccion SET sector_urbanizacion = :sector, calle_avenida = :calle,
                         nro_casa_apto = :casa, punto_referencia = :referencia, id_parroquia = :parroquia
                         WHERE id_persona = :persona'
                    )->execute(array_merge($data['direccion'], ['persona' => $personaId]));
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO direccion (id_persona, sector_urbanizacion, calle_avenida, nro_casa_apto, punto_referencia, id_parroquia)
                         VALUES (:persona, :sector, :calle, :casa, :referencia, :parroquia)'
                    )->execute(array_merge($data['direccion'], ['persona' => $personaId]));
                }
            }

            if ($data['formacion']['titulo'] > 0) {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM formacion_academica WHERE id_empleado = :e');
                $stmt->execute(['e' => $id]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->pdo->prepare(
                        'UPDATE formacion_academica SET id_titulo = :titulo, id_grado_academico = :grado,
                         id_institucion = :institucion, fecha_obtencion = :obtencion
                         WHERE id_empleado = :empleado'
                    )->execute(array_merge($data['formacion'], ['empleado' => $id]));
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO formacion_academica (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion)
                         VALUES (:empleado, :titulo, :grado, :institucion, :obtencion)'
                    )->execute(array_merge($data['formacion'], ['empleado' => $id]));
                }
            }

            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Empleado actualizado correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::update(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo actualizar el empleado. No se guardaron cambios.'];
        }
    }

    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        return $this->pdo->prepare("UPDATE persona p INNER JOIN empleado e ON e.id_persona = p.id_persona SET p.estado = IF(p.estado = 'ACTIVO', 'INACTIVO', 'ACTIVO') WHERE e.id_empleado = :id")->execute(['id' => $id]);
    }

    private function filters(array $filters): array
    {
        $where = []; $params = [];
        if (($filters['cargo'] ?? '') !== '') { $where[] = 'e.id_cargo = :cargo'; $params['cargo'] = (int) $filters['cargo']; }
        if (($filters['estado'] ?? '') !== '') { $where[] = 'p.estado = :estado'; $params['estado'] = strtoupper($filters['estado']); }
        if (!empty($filters['q'])) {
            // Cada ocurrencia de LIKE necesita su propio placeholder:
            // con PDO::ATTR_EMULATE_PREPARES en false, MySQL no permite
            // bindear un mismo nombre de parámetro repetido varias veces
            // en la misma consulta (causaba "Invalid parameter number").
            $where[] = '(p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2 OR p.numero_documento LIKE :q3 OR c.nombre LIKE :q4)';
            $q = $filters['q'] . '%';
            $params['q1'] = $q;
            $params['q2'] = $q;
            $params['q3'] = $q;
            $params['q4'] = $q;
        }
        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $name = trim($row['primer_nombre'] . ' ' . $row['primer_apellido']);
        return ['id' => (int) $row['id'], 'nombre' => $name, 'cedula' => $row['tipo_documento'] . '-' . $row['numero_documento'], 'iniciales' => mb_strtoupper(mb_substr($row['primer_nombre'], 0, 1) . mb_substr($row['primer_apellido'], 0, 1)), 'foto' => $row['foto'] ?? null, 'cargo' => $row['cargo'] ?? 'Sin cargo', 'fecha_ingreso' => $row['fecha_ingreso'], 'estado' => strtolower($row['estado'])];
    }

    public function createTitulo(string $nombre): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'El nombre del título es obligatorio.'];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id_titulo, nombre FROM titulo WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmt->execute(['nombre' => $nombre]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $this->pdo->commit();
                return [
                    'ok' => true,
                    'titulo' => [
                        'id_titulo' => (int) $existente['id_titulo'],
                        'nombre'    => $existente['nombre']
                    ],
                    'mensaje' => 'El título ya existe en el catálogo y ha sido seleccionado.'
                ];
            }

            $stmt = $this->pdo->prepare("INSERT INTO titulo (nombre, estado) VALUES (:nombre, 'ACTIVO')");
            $stmt->execute(['nombre' => $nombre]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return [
                'ok' => true,
                'titulo' => ['id_titulo' => $id, 'nombre' => $nombre],
                'mensaje' => 'Título registrado correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::createTitulo(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el título. Verifique que no exista ya.'];
        }
    }

    public function createTipoInstitucion(string $nombre): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'mensaje' => 'El nombre del tipo de institución es obligatorio.'];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id_tipo_institucion, nombre FROM tipo_institucion WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmt->execute(['nombre' => $nombre]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $this->pdo->commit();
                return [
                    'ok' => true,
                    'tipo_institucion' => [
                        'id_tipo_institucion' => (int) $existente['id_tipo_institucion'],
                        'nombre'              => $existente['nombre']
                    ],
                    'mensaje' => 'El tipo de institución ya existe en el catálogo y ha sido seleccionado.'
                ];
            }

            $stmt = $this->pdo->prepare("INSERT INTO tipo_institucion (nombre, estado) VALUES (:nombre, 'ACTIVO')");
            $stmt->execute(['nombre' => $nombre]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return [
                'ok' => true,
                'tipo_institucion' => ['id_tipo_institucion' => $id, 'nombre' => $nombre],
                'mensaje' => 'Tipo de institución registrado correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::createTipoInstitucion(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el tipo de institución.'];
        }
    }

    public function createInstitucion(string $nombre, int $idTipo): array
    {
        $nombre = trim($nombre);
        if ($nombre === '' || $idTipo <= 0) {
            return ['ok' => false, 'mensaje' => 'Nombre y tipo de institución son obligatorios.'];
        }

        try {
            $this->pdo->beginTransaction();

            // Verificar que el tipo de institución exista
            $stmtTipo = $this->pdo->prepare("SELECT id_tipo_institucion FROM tipo_institucion WHERE id_tipo_institucion = :tipo");
            $stmtTipo->execute(['tipo' => $idTipo]);
            if (!$stmtTipo->fetchColumn()) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El tipo de institución seleccionado no es válido.'];
            }

            // Verificar si ya existe la institución con el mismo nombre
            $stmt = $this->pdo->prepare("SELECT id_institucion, nombre FROM institucion WHERE LOWER(nombre) = LOWER(:nombre) LIMIT 1");
            $stmt->execute(['nombre' => $nombre]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                $this->pdo->commit();
                return [
                    'ok' => true,
                    'institucion' => [
                        'id_institucion' => (int) $existente['id_institucion'],
                        'nombre'         => $existente['nombre']
                    ],
                    'mensaje' => 'La institución ya existía en el catálogo y ha sido seleccionada.'
                ];
            }

            $stmt = $this->pdo->prepare("INSERT INTO institucion (nombre, estado, id_tipo_institucion) VALUES (:nombre, 'ACTIVO', :tipo)");
            $stmt->execute(['nombre' => $nombre, 'tipo' => $idTipo]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return [
                'ok' => true,
                'institucion' => ['id_institucion' => $id, 'nombre' => $nombre],
                'mensaje' => 'Institución registrada correctamente.'
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::createInstitucion(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la institución. Verifique que no exista ya.'];
        }
    }

    /**
     * Pone en NULL la columna foto de la persona asociada al empleado dado.
     * El archivo físico debe haberse eliminado previamente desde el controlador.
     */
    public function clearFoto(int $idEmpleado): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE persona p
             INNER JOIN empleado e ON e.id_persona = p.id_persona
             SET p.foto = NULL
             WHERE e.id_empleado = :id'
        );
        return $stmt->execute(['id' => $idEmpleado]);
    }
}