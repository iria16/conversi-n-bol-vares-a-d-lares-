<?php

require_once __DIR__ . '/BaseModel.php';

class StaffModel extends BaseModel
{
    protected string $table = 'empleado';
    protected string $primaryKey = 'id_empleado';

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento', 'c.nombre'];
    }

    /**
     * Construye las cláusulas WHERE y parámetros para los filtros
     * de búsqueda de empleados: q (texto libre), cargo, estado.
     */

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $sql = "SELECT e.id_empleado AS id, p.id_persona, p.primer_nombre, p.segundo_nombre,
                       p.primer_apellido, p.segundo_apellido, p.numero_documento,
                       p.tipo_documento, p.sexo, e.fecha_ingreso, c.nombre AS cargo,
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
        $where = $table === 'parroquia' && isset($_GET['municipio']) ? ' WHERE id_municipio = ' . (int) $_GET['municipio'] : '';
        if ($table !== 'municipio' && $table !== 'parroquia') $where = ' WHERE estado = \'ACTIVO\'';
        return $this->pdo->query("SELECT {$id} AS id, {$name} AS nombre FROM {$table}{$where} ORDER BY {$name}")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createStaff(array $data): array
    {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('INSERT INTO persona (tipo_documento, numero_documento, fecha_nacimiento, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, estado, nacionalidad) VALUES (:tipo_documento, :numero_documento, :fecha_nacimiento, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :sexo, \'ACTIVO\', :nacionalidad)');
            $stmt->execute($data['persona']);
            $personaId = (int) $this->pdo->lastInsertId();
            $stmt = $this->pdo->prepare('INSERT INTO empleado (id_persona, fecha_ingreso, id_cargo) VALUES (:persona, :ingreso, :cargo)');
            $stmt->execute(['persona' => $personaId, 'ingreso' => $data['fecha_ingreso'], 'cargo' => $data['id_cargo'] ?: null]);
            $empleadoId = (int) $this->pdo->lastInsertId();
            if ($data['contacto']['numero_telefono_principal'] !== '' || $data['contacto']['correo_electronico'] !== '') {
                $data['contacto']['persona'] = $personaId;
                $this->pdo->prepare('INSERT INTO contacto (id_persona, codigo_telefono_principal, numero_telefono_principal, codigo_telefono_alternativo, numero_telefono_alternativo, correo_electronico) VALUES (:persona, :codigo_principal, :numero_principal, :codigo_alternativo, :numero_alternativo, :correo)')->execute($data['contacto']);
            }
            if ($data['direccion']['sector_urbanizacion'] !== '' || $data['direccion']['calle_avenida'] !== '') {
                $data['direccion']['persona'] = $personaId;
                $this->pdo->prepare('INSERT INTO direccion (id_persona, sector_urbanizacion, calle_avenida, nro_casa_apto, punto_referencia, id_parroquia) VALUES (:persona, :sector, :calle, :casa, :referencia, :parroquia)')->execute($data['direccion']);
            }
            if ($data['formacion']['id_titulo'] > 0) {
                $data['formacion']['empleado'] = $empleadoId;
                $this->pdo->prepare('INSERT INTO formacion_academica (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion) VALUES (:empleado, :titulo, :grado, :institucion, :obtencion)')->execute($data['formacion']);
            }
            $this->pdo->commit();
            return ['ok' => true, 'id' => $empleadoId, 'mensaje' => 'Empleado registrado correctamente.'];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('StaffModel::create(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar el empleado. No se guardaron cambios.'];
        }
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT e.id_empleado, e.id_persona, e.id_cargo, e.fecha_ingreso,
                       p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       p.sexo, p.nacionalidad, p.estado,
                       ct.codigo_telefono_principal, ct.numero_telefono_principal,
                       ct.codigo_telefono_alternativo, ct.numero_telefono_alternativo,
                       ct.correo_electronico,
                       d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto,
                       d.punto_referencia, d.id_parroquia,
                       fa.id_titulo, fa.id_grado_academico, fa.id_institucion, fa.fecha_obtencion
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT  JOIN contacto ct ON ct.id_persona = p.id_persona
                LEFT  JOIN direccion d  ON d.id_persona  = p.id_persona
                LEFT  JOIN formacion_academica fa ON fa.id_empleado = e.id_empleado
                WHERE e.id_empleado = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStaff(int $id, array $data): array
    {
        try {
            $this->pdo->beginTransaction();

            // Obtener id_persona del empleado
            $stmt = $this->pdo->prepare('SELECT id_persona FROM empleado WHERE id_empleado = :id');
            $stmt->execute(['id' => $id]);
            $personaId = (int) $stmt->fetchColumn();
            if (!$personaId) throw new \RuntimeException('Empleado no encontrado.');

            // Actualizar tabla persona
            $this->pdo->prepare(
                'UPDATE persona SET tipo_documento = :tipo_documento, numero_documento = :numero_documento,
                 fecha_nacimiento = :fecha_nacimiento, primer_nombre = :primer_nombre,
                 segundo_nombre = :segundo_nombre, primer_apellido = :primer_apellido,
                 segundo_apellido = :segundo_apellido, sexo = :sexo, nacionalidad = :nacionalidad
                 WHERE id_persona = :id_persona'
            )->execute(array_merge($data['persona'], ['id_persona' => $personaId]));

            // Actualizar tabla empleado
            $this->pdo->prepare(
                'UPDATE empleado SET fecha_ingreso = :ingreso, id_cargo = :cargo WHERE id_empleado = :id'
            )->execute(['ingreso' => $data['fecha_ingreso'], 'cargo' => $data['id_cargo'] ?: null, 'id' => $id]);

            // Upsert contacto
            $hayContacto = $data['contacto']['numero_principal'] !== '' || $data['contacto']['correo'] !== '';
            if ($hayContacto) {
                $exists = (int) $this->pdo->prepare('SELECT COUNT(*) FROM contacto WHERE id_persona = :p')->execute(['p' => $personaId]);
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

            // Upsert formación académica
            if ($data['formacion']['id_titulo'] > 0) {
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
        if (!empty($filters['q'])) { $where[] = '(p.primer_nombre LIKE :q OR p.primer_apellido LIKE :q OR p.numero_documento LIKE :q OR c.nombre LIKE :q)'; $params['q'] = $filters['q'] . '%'; }
        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $name = trim($row['primer_nombre'] . ' ' . $row['primer_apellido']);
        return ['id' => (int) $row['id'], 'nombre' => $name, 'cedula' => $row['tipo_documento'] . '-' . $row['numero_documento'], 'iniciales' => mb_strtoupper(mb_substr($row['primer_nombre'], 0, 1) . mb_substr($row['primer_apellido'], 0, 1)), 'cargo' => $row['cargo'] ?? 'Sin cargo', 'fecha_ingreso' => $row['fecha_ingreso'], 'estado' => strtolower($row['estado'])];
    }
}
