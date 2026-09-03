<?php

require_once __DIR__ . '/BaseModel.php';

class AccessRecoveryModel extends BaseModel
{
    protected string $table = 'recuperacion_acceso';
    protected string $primaryKey = 'id_recuperacion';

    private const MESES_ES = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    protected function searchableFields(): array
    {
        return ['p.primer_nombre', 'p.primer_apellido', 'u.nombre_usuario'];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildJoinFilters($filters);
        $sql = "SELECT ra.id_recuperacion AS id, ra.id_usuario AS usuario_id,
                   ra.fecha_solicitud, ra.estado_recuperacion AS estado,
                   u.nombre_usuario,
                       p.primer_nombre, p.primer_apellido, c.nombre AS cargo,
                       r.nombre AS rol
                FROM recuperacion_acceso ra
                INNER JOIN usuario u ON ra.id_usuario = u.id_usuario
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";

        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY ra.fecha_solicitud DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) $stmt->bindValue($key, $value);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRowToVista'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function mapRowToVista(array $row): array
    {
        $nombre = trim(($row['primer_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? ''));
        $nombre = $nombre !== '' ? $nombre : ($row['nombre_usuario'] ?? 'Usuario');
        [$fecha, $hora] = $this->formatFechaHora($row['fecha_solicitud']);

        return [
            'id'           => (int) $row['id'],
            'usuario_id'   => (int) ($row['usuario_id'] ?? 0),
            'nombre'       => $nombre,
            'iniciales'    => $this->generarIniciales($row),
            'avatar_color' => $this->colorPorRol($row['rol'] ?? ''),
            'cargo'        => $row['cargo'] ?? '—',
            'usuario'      => $row['nombre_usuario'] ?? '',
            'fecha'        => $fecha,
            'hora'         => $hora,
            'estado'       => $this->mapearEstado($row['estado'] ?? ''),
        ];
    }

    private function formatFechaHora(string $datetime): array
    {
        $timestamp = strtotime($datetime);
        if ($timestamp === false) return ['—', '—'];

        return [
            sprintf('%02d %s, %s', (int) date('d', $timestamp), self::MESES_ES[(int) date('n', $timestamp)], date('Y', $timestamp)),
            date('h:i a', $timestamp),
        ];
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildJoinFilters($filters);
        $sql = "SELECT COUNT(*)
                FROM recuperacion_acceso ra
                INNER JOIN usuario u ON ra.id_usuario = u.id_usuario
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function buildJoinFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $estado = match (strtolower($filters['estado'])) {
                'aprobada' => 'COMPLETADO',
                'rechazada' => 'CANCELADO',
                default => strtoupper($filters['estado']),
            };
            $where[] = 'ra.estado_recuperacion = :estado';
            $params['estado'] = $estado;
        }

        if (!empty($filters['q'])) {
            $where[] = '(p.primer_nombre LIKE :s1 OR p.primer_apellido LIKE :s2 OR u.nombre_usuario LIKE :s3)';
            $like = '%' . $filters['q'] . '%';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }

        return [$where, $params];
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query('SELECT estado_recuperacion, COUNT(*) AS total FROM recuperacion_acceso GROUP BY estado_recuperacion');
        $counts = ['PENDIENTE' => 0, 'COMPLETADO' => 0, 'CANCELADO' => 0];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['estado_recuperacion'], $counts)) {
                $counts[$row['estado_recuperacion']] = (int) $row['total'];
            }
        }

        return [
            'total'      => array_sum($counts),
            'pendientes' => $counts['PENDIENTE'],
            'aprobadas'  => $counts['COMPLETADO'],
            'rechazadas' => $counts['CANCELADO'],
        ];
    }

    public function getDetalleById(int $id): ?array
    {
        $sql = "SELECT ra.id_recuperacion AS id, ra.fecha_solicitud, ra.fecha_atencion,
                       ra.estado_recuperacion AS estado, u.nombre_usuario,
                       u.id_usuario AS usuario_id, p.primer_nombre, p.primer_apellido,
                       c.nombre AS cargo, r.nombre AS rol
                FROM recuperacion_acceso ra
                INNER JOIN usuario u ON ra.id_usuario = u.id_usuario
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                WHERE ra.id_recuperacion = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $detalle = $this->mapRowToVista($row);
        $detalle['usuario_id'] = (int) $row['usuario_id'];
        $detalle['fecha_resolucion'] = $row['fecha_atencion'];
        return $detalle;
    }

    public function aprobar(int $id): array
    {
        $usuarioId = $this->getUsuarioIdBySolicitud($id);
        if (!$usuarioId) return ['ok' => false];

        $password = $this->generarPasswordProvisional();
        try {
            $this->pdo->beginTransaction();
            $this->actualizarPassword($usuarioId, $password);
            $this->marcarSolicitud($id, 'COMPLETADO');
            $this->pdo->commit();
            return ['ok' => true, 'password_temporal' => $password];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('AccessRecoveryModel::aprobar(): ' . $e->getMessage());
            return ['ok' => false];
        }
    }

    public function rechazar(int $id): bool
    {
        return $this->marcarSolicitud($id, 'CANCELADO');
    }

    public function reenviarTemporal(int $id): array
    {
        $solicitud = $this->getSolicitud($id);
        if (!$solicitud) {
            return ['ok' => false, 'mensaje' => 'La solicitud de recuperación no existe.'];
        }
        if (!in_array($solicitud['estado_recuperacion'], ['COMPLETADO', 'APROBADA'], true)) {
            return ['ok' => false, 'mensaje' => 'Solo se puede reenviar una solicitud aprobada.'];
        }

        $usuarioId = (int) $solicitud['id_usuario'];

        $password = $this->generarPasswordProvisional();
        try {
            $this->pdo->beginTransaction();
            $this->actualizarPassword($usuarioId, $password);
            $this->marcarSolicitud($id, 'COMPLETADO');
            $this->pdo->commit();
            return ['ok' => true, 'password_temporal' => $password];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('AccessRecoveryModel::reenviarTemporal(): ' . $e->getMessage());
            return ['ok' => false];
        }
    }

    public function resetManual(int $usuarioId): array
    {
        $password = $this->generarPasswordProvisional();
        try {
            $this->pdo->beginTransaction();
            $this->actualizarPassword($usuarioId, $password);
            $this->pdo->prepare('INSERT INTO recuperacion_acceso (id_usuario) VALUES (:usuario_id)')
                ->execute(['usuario_id' => $usuarioId]);
            $this->pdo->commit();
            return ['ok' => true, 'password_temporal' => $password];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('AccessRecoveryModel::resetManual(): ' . $e->getMessage());
            return ['ok' => false];
        }
    }

    public function getUsuariosDisponibles(): array
    {
        $sql = "SELECT u.id_usuario AS id, u.nombre_usuario,
                       p.primer_nombre, p.primer_apellido, c.nombre AS cargo
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                WHERE u.estado = 'ACTIVO'
                ORDER BY p.primer_nombre, p.primer_apellido";

        $stmt = $this->pdo->query($sql);
        return array_map(static function (array $row): array {
            return [
                'id'      => (int) $row['id'],
                'nombre'  => trim($row['primer_nombre'] . ' ' . $row['primer_apellido']),
                'usuario' => $row['nombre_usuario'],
                'cargo'   => $row['cargo'] ?? '—',
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getSolicitud(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id_usuario, estado_recuperacion FROM recuperacion_acceso WHERE id_recuperacion = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getUsuarioIdBySolicitud(int $id): ?int
    {
        $solicitud = $this->getSolicitud($id);
        return $solicitud ? (int) $solicitud['id_usuario'] : null;
    }

    private function actualizarPassword(int $usuarioId, string $password): void
    {
        $this->pdo->prepare('UPDATE usuario SET password_hash = :hash, password_provisional = 1 WHERE id_usuario = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $usuarioId]);
    }

    private function marcarSolicitud(int $id, string $estado): bool
    {
        return $this->pdo->prepare('UPDATE recuperacion_acceso SET estado_recuperacion = :estado, fecha_atencion = NOW() WHERE id_recuperacion = :id')
            ->execute(['estado' => $estado, 'id' => $id]);
    }

    private function mapearEstado(string $estado): string
    {
        return match (strtoupper($estado)) {
            'COMPLETADO', 'APROBADA' => 'aprobada',
            'CANCELADO' => 'rechazada',
            default => 'pendiente',
        };
    }

    private function generarIniciales(array $row): string
    {
        $initials = mb_strtoupper(
            mb_substr($row['primer_nombre'] ?? '', 0, 1) .
            mb_substr($row['primer_apellido'] ?? '', 0, 1)
        );
        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($row['nombre_usuario'] ?? 'U', 0, 1));
    }

    private function colorPorRol(string $rol): string
    {
        return in_array(mb_strtolower($rol), ['admin', 'administrador'], true) ? 'dark' : 'success';
    }

    private function generarPasswordProvisional(int $length = 10): string
    {
        $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        $max = strlen($characters) - 1;

        for ($index = 0; $index < $length; $index++) {
            $password .= $characters[random_int(0, $max)];
        }

        return $password;
    }

    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        return false;
    }
}
