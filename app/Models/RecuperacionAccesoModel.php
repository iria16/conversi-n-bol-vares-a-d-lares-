<?php
declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;
use App\Traits\AvatarTrait; // AJUSTA esta ruta si tu AvatarTrait vive en otro namespace
use PDO;
use RuntimeException;
use Throwable;

class RecuperacionAccesoModel extends BaseModel implements Listable
{
    use AvatarTrait;

    protected string $table      = 'recuperacion_acceso';
    protected string $primaryKey = 'id_recuperacion';

    // Confirmado con `SHOW COLUMNS FROM recuperacion_acceso LIKE 'estado_recuperacion'`:
    // el ENUM real es PENDIENTE / APROVADO (con V, no B) / RECHAZADO.
    // No "corregir" a APROBADO sin antes migrar el ENUM de la base de datos.
    private const MAP_ESTADO_TO_DB = [
        'pendiente' => 'PENDIENTE',
        'aprobado'  => 'APROVADO',
        'rechazado' => 'RECHAZADO',
    ];

    private const MAP_ESTADO_FROM_DB = [
        'PENDIENTE' => 'pendiente',
        'APROVADO'  => 'aprobado',
        'RECHAZADO' => 'rechazado',
    ];

    // Distingue si la fila nació de una solicitud real del usuario o de un
    // restablecimiento directo del admin sin solicitud previa.
    private const MAP_ORIGEN_FROM_DB = [
        'SOLICITUD' => 'solicitud',
        'ADMIN'     => 'admin',
    ];

    private const JOIN_BASE = "FROM recuperacion_acceso ra
                INNER JOIN usuario u  ON u.id_usuario = ra.id_usuario
                INNER JOIN persona p  ON p.id_persona = u.id_persona
                LEFT JOIN rol r       ON r.id_rol = u.id_rol
                LEFT JOIN empleado e  ON e.id_persona = p.id_persona
                LEFT JOIN cargo c     ON c.id_cargo = e.id_cargo";

    // --- Listado (sobrescribe BaseModel::getAll/countAll por los JOIN) ---

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = "SELECT
                    ra.id_recuperacion,
                    ra.id_usuario,
                    ra.fecha_solicitud,
                    ra.estado_recuperacion,
                    ra.fecha_atencion,
                    ra.id_admin_atendio,
                    ra.origen,
                    u.nombre_usuario,
                    p.foto,
                    p.primer_nombre,
                    p.primer_apellido,
                    c.nombre AS cargo,
                    r.nombre AS rol
                {$this->joinBase()}";

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY ra.fecha_solicitud DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'normalizarFila'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = "SELECT COUNT(*) {$this->joinBase()}";

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function joinBase(): string
    {
        return self::JOIN_BASE;
    }

    /**
     * IMPORTANTE: con PDO en modo nativo (ATTR_EMULATE_PREPARES = false) no
     * se puede reutilizar el mismo nombre de placeholder más de una vez,
     * aunque el valor sea idéntico — por eso :q1/:q2/:q3/:q4 en vez de :q repetido.
     */
    private function buildJoinFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        $estado = $filters['estado'] ?? '';
        if ($estado !== '' && isset(self::MAP_ESTADO_TO_DB[$estado])) {
            $where[]          = 'ra.estado_recuperacion = :estado';
            $params['estado'] = self::MAP_ESTADO_TO_DB[$estado];
        }

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            // Columna real en persona es numero_documento (confirmado con
            // SHOW COLUMNS FROM persona; antes decía erróneamente
            // nro_documento y rompía tanto el filtro de búsqueda como el
            // detalle de la solicitud con SQLSTATE[42S22] Column not found).
            $where[]      = "(u.nombre_usuario LIKE :q1 OR p.primer_nombre LIKE :q2
                              OR p.primer_apellido LIKE :q3 OR p.numero_documento LIKE :q4)";
            $like         = "%$q%";
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        return [$where, $params];
    }

    /**
     * Traduce una fila cruda del JOIN al contrato que espera la vista
     * (ver el docblock de usuarios/recuperacion/index.php):
     * id, usuario_id, fecha, hora, nombre, cargo, usuario, avatar_tipo,
     * avatar_foto, avatar_color, iniciales, estado, origen.
     */
    private function normalizarFila(array $row): array
    {
        $ts = strtotime((string) $row['fecha_solicitud']);

        $nombre = trim(($row['primer_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? ''));

        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            $row['rol'] ?? null,
            $row['nombre_usuario'] ?? 'U'
        );

        return [
            'id'           => (int) $row['id_recuperacion'],
            'usuario_id'   => (int) $row['id_usuario'],
            'fecha'        => $ts ? date('d/m/Y', $ts) : '',
            'hora'         => $ts ? date('h:i a', $ts) : '',
            'nombre'       => $nombre,
            'cargo'        => $row['cargo'] ?? null,
            'usuario'      => $row['nombre_usuario'] ?? '',
            'avatar_tipo'  => $avatar['tipo'],
            'avatar_foto'  => $avatar['foto'],
            'avatar_color' => $avatar['avatar_color'],
            'iniciales'    => $avatar['iniciales'],
            'estado'       => self::MAP_ESTADO_FROM_DB[$row['estado_recuperacion']] ?? 'pendiente',
            'origen'       => self::MAP_ORIGEN_FROM_DB[$row['origen']] ?? 'solicitud',
        ];
    }

    // --- Datos extra que pide el controlador en getExtraIndexData() ---

    public function getStats(): array
    {
        $rows = $this->pdo
            ->query("SELECT estado_recuperacion, COUNT(*) AS total
                      FROM {$this->table}
                      GROUP BY estado_recuperacion")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'pendientes' => (int) ($rows['PENDIENTE'] ?? 0),
            'aprobadas'  => (int) ($rows['APROVADO']  ?? 0),
            'rechazadas' => (int) ($rows['RECHAZADO'] ?? 0),
            'total'      => array_sum(array_map('intval', $rows)),
        ];
    }

    public function getUsuariosDisponibles(): array
    {
        $sql = "SELECT
                    u.id_usuario AS id,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS nombre,
                    u.nombre_usuario AS usuario
                FROM usuario u
                INNER JOIN persona p ON p.id_persona = u.id_persona
                WHERE u.estado = 'ACTIVO'
                ORDER BY p.primer_apellido, p.primer_nombre";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * A diferencia de getAll(), aquí SÍ se conservan fecha_atencion y el
     * nombre del admin que atendió — antes se perdían porque
     * normalizarFila() no las incluía en su arreglo de salida.
     */
    public function getDetalleSolicitud(int $id): ?array
    {
        $sql = "SELECT
                    ra.id_recuperacion,
                    ra.id_usuario,
                    ra.fecha_solicitud,
                    ra.estado_recuperacion,
                    ra.fecha_atencion,
                    ra.id_admin_atendio,
                    ra.origen,
                    u.nombre_usuario,
                    p.foto,
                    p.primer_nombre,
                    p.primer_apellido,
                    p.numero_documento,
                    p.tipo_documento,
                    c.nombre AS cargo,
                    r.nombre AS rol,
                    admin_p.primer_nombre   AS admin_primer_nombre,
                    admin_p.primer_apellido AS admin_primer_apellido
                FROM {$this->table} ra
                INNER JOIN usuario u ON u.id_usuario = ra.id_usuario
                INNER JOIN persona p ON p.id_persona = u.id_persona
                LEFT JOIN rol r ON r.id_rol = u.id_rol
                LEFT JOIN empleado e ON e.id_persona = p.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                LEFT JOIN usuario admin_u ON admin_u.id_usuario = ra.id_admin_atendio
                LEFT JOIN persona admin_p ON admin_p.id_persona = admin_u.id_persona
                WHERE ra.id_recuperacion = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $detalle = $this->normalizarFila($row);

        $ts = strtotime((string) $row['fecha_atencion']);
        $adminNombre = trim(
            ($row['admin_primer_nombre'] ?? '') . ' ' . ($row['admin_primer_apellido'] ?? '')
        );

        $detalle['fecha_atencion'] = $ts ? date('d/m/Y h:i A', $ts) : null;
        $detalle['admin_atendio']  = $adminNombre !== '' ? $adminNombre : null;
        $detalle['numero_documento'] = $row['numero_documento'] ?? null;
        $detalle['tipo_documento']   = $row['tipo_documento'] ?? null;

        return $detalle;
    }

    // --- Transiciones de estado (usadas por updateEstado()) ---

    public function aprobarSolicitud(int $idSolicitud, int $idAdmin): ?array
    {
        $stmtBuscar = $this->pdo->prepare(
            "SELECT id_usuario FROM {$this->table}
             WHERE id_recuperacion = :id AND estado_recuperacion = 'PENDIENTE'"
        );
        $stmtBuscar->execute(['id' => $idSolicitud]);
        $idUsuario = $stmtBuscar->fetchColumn();

        if ($idUsuario === false) {
            return null;
        }

        $this->pdo->beginTransaction();

        try {
            $stmtUpd = $this->pdo->prepare(
                "UPDATE {$this->table}
                 SET estado_recuperacion = 'APROVADO',
                     fecha_atencion = NOW(),
                     id_admin_atendio = :idAdmin
                 WHERE id_recuperacion = :id
                   AND estado_recuperacion = 'PENDIENTE'"
            );
            $stmtUpd->execute(['idAdmin' => $idAdmin, 'id' => $idSolicitud]);

            if ($stmtUpd->rowCount() === 0) {
                $this->pdo->rollBack();
                return null;
            }

            $claveProvisional = $this->generarClaveProvisional();

            $this->pdo->prepare(
                "UPDATE usuario
                 SET password_hash = :hash, password_provisional = 1
                 WHERE id_usuario = :id_usuario"
            )->execute([
                'hash'       => password_hash($claveProvisional, PASSWORD_DEFAULT),
                'id_usuario' => $idUsuario,
            ]);

            $usuario = $this->getUsuarioBasico((int) $idUsuario);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'clave'   => $claveProvisional,
            'usuario' => $usuario['usuario'] ?? '',
            'nombre'  => $usuario['nombre']  ?? '',
        ];
    }

    public function rechazarSolicitud(int $idSolicitud, int $idAdmin): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table}
             SET estado_recuperacion = 'RECHAZADO',
                 fecha_atencion = NOW(),
                 id_admin_atendio = :idAdmin
             WHERE id_recuperacion = :id
               AND estado_recuperacion = 'PENDIENTE'"
        );
        $stmt->execute(['idAdmin' => $idAdmin, 'id' => $idSolicitud]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Restablecimiento manual: no hay solicitud previa, así que la fila en
     * recuperacion_acceso se crea ya resuelta, con origen ADMIN.
     * El estado insertado debe coincidir EXACTO con el ENUM real de la BD
     * (PENDIENTE/APROVADO/RECHAZADO — APROVADO con V, confirmado por
     * SHOW COLUMNS). Un valor fuera del ENUM causa SQLSTATE[01000]
     * "Data truncated" con el modo estricto activo, o se trunca
     * silenciosamente a '' sin él — cualquiera de los dos deja la fila
     * "atendida" pero mostrada como Pendiente en la vista.
     */
    public function resetPassword(int $usuarioId, int $idAdmin): string
    {
        $usuario = $this->getUsuarioBasico($usuarioId);

        if (!$usuario) {
            throw new RuntimeException('El usuario indicado no existe.');
        }

        $claveProvisional = $this->generarClaveProvisional();

        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                "UPDATE usuario
                 SET password_hash = :hash, password_provisional = 1
                 WHERE id_usuario = :id_usuario"
            )->execute([
                'hash'       => password_hash($claveProvisional, PASSWORD_DEFAULT),
                'id_usuario' => $usuarioId,
            ]);

            $this->pdo->prepare(
                "INSERT INTO {$this->table}
                    (id_usuario, fecha_solicitud, estado_recuperacion, fecha_atencion, id_admin_atendio, origen)
                 VALUES
                    (:id_usuario, NOW(), 'APROVADO', NOW(), :id_admin, 'ADMIN')"
            )->execute([
                'id_usuario' => $usuarioId,
                'id_admin'   => $idAdmin,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $claveProvisional;
    }

    public function getUsuarioBasico(int $usuarioId): ?array
    {
        $sql = "SELECT
                    u.id_usuario,
                    u.nombre_usuario AS usuario,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS nombre
                FROM usuario u
                INNER JOIN persona p ON p.id_persona = u.id_persona
                WHERE u.id_usuario = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $usuarioId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function generarClaveProvisional(int $longitud = 10): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $clave    = '';

        for ($i = 0; $i < $longitud; $i++) {
            $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $clave;
    }
}