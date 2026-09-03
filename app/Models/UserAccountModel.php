<?php

require_once __DIR__ . '/BaseModel.php';

class UserAccountModel extends BaseModel
{
    protected string $table = 'usuario';
    protected string $primaryKey = 'id_usuario';

    protected function searchableFields(): array
    {
        // NOTA: getAll()/countAll() de este modelo usan su propio
        // buildJoinFilters() en vez de basarse en esta lista, porque
        // la búsqueda cruza varias tablas (persona, usuario). Se deja
        // declarado por si el BaseModel genérico lo exige como
        // contrato, pero no gobierna el filtrado real aquí.
        return ['p.primer_nombre', 'p.primer_apellido', 'u.nombre_usuario', 'u.rol'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = "SELECT
                    u.id_usuario, u.nombre_usuario, u.estado,
                    p.primer_nombre, p.primer_apellido,
                    c.nombre AS cargo,
                    r.id_rol, r.nombre AS rol
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";

        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY u.id_usuario DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        // Transformamos cada fila al shape EXACTO que la vista (tabla/listado) espera
        return array_map([$this, 'mapRowToVista'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Shape "delgado" para el listado / tabla de usuarios
    public function mapRowToVista(array $row): array
    {
        $nombreCompleto = trim(($row['primer_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? ''));
        if ($nombreCompleto === '') {
            $nombreCompleto = $row['nombre_usuario'] ?? 'Usuario';
        }

        return [
            'id'             => (int) $row['id_usuario'],
            // Columna USUARIO: el nombre de usuario con el que inicia sesión.
            'nombre'         => $row['nombre_usuario'] ?? '',
            'nombre_usuario' => $row['nombre_usuario'] ?? '',
            'iniciales'      => $this->generarIniciales($row),
            'avatar_color'   => $this->colorPorRol($row['rol'] ?? ''),
            // Columna EMPLEADO: el nombre completo de la persona (antes se
            // mostraba por error el cargo, que ahora va aparte en 'cargo').
            'empleado'       => $nombreCompleto,
            'cargo'          => $row['cargo'] ?? '—',
            'id_rol'         => isset($row['id_rol']) ? (int) $row['id_rol'] : null,
            'rol'            => $row['rol'] ?? '—',
            'estado'         => strtolower($row['estado'] ?? 'activo'), // ACTIVO -> activo
        ];
    }

    // Shape "completo" para el modal de Detalle de Usuario.
    // Incluye documento, fecha de alta y último acceso, que la tabla no necesita.
    public function mapRowToDetalle(array $row): array
    {
        $base = $this->mapRowToVista($row);

        $documento = null;
        if (!empty($row['numero_documento'])) {
            $documento = ($row['tipo_documento'] ?? '') . '-' . $row['numero_documento'];
        }

        $partes = array_filter([
            $row['primer_nombre']    ?? '',
            $row['segundo_nombre']   ?? '',
            $row['primer_apellido']  ?? '',
            $row['segundo_apellido'] ?? '',
        ]);
        $nombreCompleto = implode(' ', $partes) ?: ($row['nombre_usuario'] ?? '—');

        return $base + [
            'documento'      => $documento ?? '—',
            'nombre_completo'=> $nombreCompleto,
            'miembro_desde'  => $row['fecha_creacion'] ?? null,
            'ultimo_acceso'  => $row['ultimo_acceso']  ?? null,
        ];
    }

    private function generarIniciales(array $row): string
    {
        $n = mb_substr($row['primer_nombre'] ?? '', 0, 1);
        $a = mb_substr($row['primer_apellido'] ?? '', 0, 1);
        $iniciales = mb_strtoupper($n . $a);
        return $iniciales !== '' ? $iniciales : mb_strtoupper(mb_substr($row['nombre_usuario'] ?? 'U', 0, 1));
    }

    private function colorPorRol(string $rol): string
    {
        return match (mb_strtolower($rol)) {
            'admin', 'administrador' => 'dark',    // navy, igual que la imagen de referencia
            'docente'                => 'primary', // azul info
            default                  => 'success',
        };
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildJoinFilters($filters);
        $sql = "SELECT COUNT(*)
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildJoinFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['rol'])) {
            $where[] = "r.nombre = :rol";
            $params['rol'] = $filters['rol'];
        }

        if (!empty($filters['estado'])) {
            $where[] = "u.estado = :estado";
            $params['estado'] = strtoupper($filters['estado']); // activo -> ACTIVO
        }

        if (!empty($filters['q'])) {
            $where[] = "(p.primer_nombre LIKE :s1 OR p.primer_apellido LIKE :s2 OR u.nombre_usuario LIKE :s3)";
            $like = '%' . $filters['q'] . '%';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }

        return [$where, $params];
    }

    public function getStats(): array
    {
        $total   = (int) $this->pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn();
        $activas = (int) $this->pdo->query("SELECT COUNT(*) FROM usuario WHERE estado = 'ACTIVO'")->fetchColumn();

        return [
            'total'     => $total,
            'activas'   => $activas,
            'inactivas' => $total - $activas,
            // TODO: dato fijo, no calculado. Reemplazar por una tendencia
            // real (ej. altas de los últimos 30 días vs. los 30 anteriores)
            // o quitar la tarjeta del dashboard antes de entregar.
            'tendencia' => '+12%',
        ];
    }

    public function getRolesDisponibles(): array
    {
        $stmt = $this->pdo->query("SELECT nombre FROM rol ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // NOTA: la tabla `rol` en el esquema sidge NO tiene columna `estado`
    // (solo id_rol, nombre, descripcion), así que aquí ya no se filtra
    // por eso. Si en el futuro quieres poder desactivar roles, habría
    // que añadir esa columna con un ALTER TABLE primero.
    public function getRolesSistema(): array
    {
        $stmt = $this->pdo->query("SELECT id_rol AS id, nombre FROM rol ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Devuelve empleados sin cuenta de usuario todavía. El "id" que se
    // expone aquí es el id_persona del empleado (no el id_empleado):
    // usuario.id_persona es el campo que realmente se inserta al crear
    // la cuenta, ya que `usuario` no tiene una FK directa a `empleado`.
    public function getEmpleadosDisponibles(): array
    {
        $sql = "SELECT
                    e.id_persona AS id,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido, IF(c.nombre IS NOT NULL, CONCAT(' - ', c.nombre), '')) AS nombre
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                LEFT JOIN usuario u ON u.id_persona = e.id_persona
                WHERE p.estado = 'ACTIVO'
                  AND e.fecha_egreso IS NULL
                  AND u.id_usuario IS NULL
                ORDER BY p.primer_nombre, p.primer_apellido";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeNombreUsuario(string $nombreUsuario, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM usuario WHERE LOWER(nombre_usuario) = LOWER(:nombre)";
        $params = ['nombre' => $nombreUsuario];
        if ($excludeId !== null) {
            $sql .= " AND id_usuario != :excludeId";
            $params['excludeId'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function personaTieneUsuario(int $personaId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuario WHERE id_persona = :persona_id");
        $stmt->execute(['persona_id' => $personaId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function crearUsuario(int $personaId, int $rolId, string $nombreUsuario): array
    {
        if ($this->existeNombreUsuario($nombreUsuario)) {
            return ['ok' => false, 'mensaje' => 'El nombre de usuario "' . $nombreUsuario . '" ya está en uso. Por favor elija otro.'];
        }

        if ($this->personaTieneUsuario($personaId)) {
            return ['ok' => false, 'mensaje' => 'La persona seleccionada ya posee una cuenta de usuario asignada.'];
        }

        $passwordProvisional = $this->generarPasswordProvisional();
        $passwordHash = password_hash($passwordProvisional, PASSWORD_DEFAULT);

        try {
            $sql = "INSERT INTO usuario (id_persona, id_rol, nombre_usuario, password_hash, password_provisional, estado)
                    VALUES (:persona_id, :rol_id, :nombre_usuario, :password_hash, 1, 'ACTIVO')";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'persona_id'     => $personaId,
                'rol_id'         => $rolId,
                'nombre_usuario' => $nombreUsuario,
                'password_hash'  => $passwordHash,
            ]);

            $nuevoId = (int) $this->pdo->lastInsertId();
            $usuarioData = $this->getDetalleById($nuevoId);

            return [
                'ok'                   => true,
                'id'                   => $nuevoId,
                'usuario'              => $nombreUsuario,
                'password_provisional' => $passwordProvisional,
                'datos'                => $usuarioData,
                'mensaje'              => 'Usuario creado exitosamente.',
            ];
        } catch (Throwable $e) {
            error_log('UserAccountModel::crearUsuario(): ' . $e->getMessage());

            // Red de seguridad: si dos solicitudes casi simultáneas pasan
            // ambas la validación de existeNombreUsuario() antes de que
            // cualquiera inserte, la segunda choca acá contra la UNIQUE
            // de la base de datos. Se distingue ese caso (SQLSTATE 23000)
            // para no mostrar un mensaje genérico cuando en realidad se
            // sabe exactamente qué pasó.
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                return ['ok' => false, 'mensaje' => 'El nombre de usuario "' . $nombreUsuario . '" ya está en uso. Por favor elija otro.'];
            }

            return ['ok' => false, 'mensaje' => 'Ocurrió un error al registrar el usuario en el sistema.'];
        }
    }

    // Actualiza nombre de usuario y rol. Usado por update()/updateAjax()
    // del controlador (modal de edición).
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE usuario
                SET nombre_usuario = :nombre_usuario, id_rol = :rol_id
                WHERE id_usuario = :id";
        try {
            return $this->pdo->prepare($sql)->execute([
                'nombre_usuario' => $data['nombre_usuario'],
                'rol_id'         => $data['rol_id'],
                'id'             => $id,
            ]);
        } catch (Throwable $e) {
            error_log('UserAccountModel::update(): ' . $e->getMessage());
            return false;
        }
    }

    private function generarPasswordProvisional(int $length = 10): string
    {
        $caracteres = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        $max = strlen($caracteres) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $caracteres[random_int(0, $max)];
        }
        return $password;
    }

    // Usado por el listado / creación (shape delgado).
    public function getDetalleById(int $id): ?array
    {
        $row = $this->fetchUsuarioRow($id);
        return $row ? $this->mapRowToVista($row) : null;
    }

    // Usado por el modal "Ver Detalle de Usuario" (shape completo,
    // incluye documento, fecha de alta y último acceso).
    public function getDetalleCompletoById(int $id): ?array
    {
        $row = $this->fetchUsuarioRow($id, incluirDetalleExtendido: true);
        return $row ? $this->mapRowToDetalle($row) : null;
    }

    private function fetchUsuarioRow(int $id, bool $incluirDetalleExtendido = false): ?array
    {
        $camposExtra = $incluirDetalleExtendido
            ? ", p.tipo_documento, p.numero_documento, p.segundo_nombre, p.segundo_apellido, u.fecha_creacion,
                (SELECT CONCAT(b.fecha, ' ', b.hora)
                   FROM bitacora b
                  WHERE b.id_usuario = u.id_usuario AND b.accion = 'LOGIN'
                  ORDER BY b.fecha DESC, b.hora DESC
                  LIMIT 1) AS ultimo_acceso"
            : "";

        $sql = "SELECT
                    u.id_usuario, u.nombre_usuario, u.estado,
                    p.primer_nombre, p.primer_apellido,
                    c.nombre AS cargo,
                    r.id_rol, r.nombre AS rol
                    {$camposExtra}
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                WHERE u.id_usuario = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // $field permite reutilizar este método si en el futuro alguna
    // pantalla necesita alternar una columna de estado distinta a
    // 'estado' (ej. otra tabla con la misma forma de toggle). Con el
    // valor por defecto el comportamiento es idéntico al de siempre.
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field); // evita inyectar el nombre de columna

        $sql = "UPDATE usuario
                SET {$field} = CASE WHEN {$field} = 'ACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END
                WHERE id_usuario = :id";
        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }
}