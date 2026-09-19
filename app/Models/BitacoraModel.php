<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;
use App\Traits\AvatarTrait;
use DateTime;
use PDO;
use Throwable;

/**
 * Modelo de la Bitácora del Sistema.
 *
 * Combina dos responsabilidades relacionadas sobre la misma tabla:
 *  - registrar(): usado por otros controladores para auditar sus
 *    propias acciones (INSERT). Deliberadamente tolerante a fallos —
 *    un error al auditar nunca debe tumbar la acción del usuario,
 *    solo queda en error_log.
 *  - getAll()/countAll()/getAllForExport()/stats: usado por
 *    BitacoraController para la vista de auditoría (solo lectura
 *    sobre este segundo grupo).
 *
 * Extiende Model directamente (no BaseModel): no necesita ni debe
 * exponer create()/update()/delete()/toggleStatus() genéricos sobre
 * esta tabla — registrar() ya es la única vía de escritura, y es
 * intencionalmente distinta a un create() genérico (nunca lanza,
 * siempre retorna bool).
 *
 * Tabla real (bitacora), según sidge.sql:
 *   id_bitacora   INT PK
 *   id_usuario    INT FK -> usuario.id_usuario
 *   fecha         DATE
 *   hora          TIME
 *   accion        VARCHAR(100)  -- texto libre (ej. 'LOGIN'), NO es un ENUM
 *   modulo        VARCHAR(50)
 *   direccion_ip  VARCHAR(45)
 *
 * usuario: id_usuario, id_persona, id_rol, nombre_usuario, ...
 * persona: id_persona, primer_nombre, primer_apellido, ...
 */
class BitacoraModel extends Model implements Listable
{
    use AvatarTrait;

    protected string $table = 'bitacora';
    protected string $primaryKey = 'id_bitacora';

    // ------------------------------------------------------------------
    // Escritura: registrar una acción de auditoría
    // ------------------------------------------------------------------

    public function registrar(int $idUsuario, string $accion, string $modulo, ?string $ip = null): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO bitacora (id_usuario, fecha, hora, accion, modulo, direccion_ip)
                 VALUES (:id, CURDATE(), CURTIME(), :accion, :modulo, :ip)'
            );

            return $stmt->execute([
                'id'     => $idUsuario,
                'accion' => $accion,
                'modulo' => $modulo,
                'ip'     => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            ]);
        } catch (Throwable $e) {
            error_log('BitacoraModel: no se pudo registrar la acción: ' . $e->getMessage());
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Lectura: listado/exportación para la vista de auditoría
    // ------------------------------------------------------------------

    private const TIPOS_CONOCIDOS = [
        'LOGIN'         => 'login',
        'LOGOUT'        => 'login',
        'CREAR'         => 'create',
        'CREATE'        => 'create',
        'CREACION'      => 'create',
        'CREACIÓN'      => 'create',
        'ACTUALIZAR'    => 'update',
        'EDITAR'        => 'update',
        'UPDATE'        => 'update',
        'ACTUALIZACION' => 'update',
        'ELIMINAR'      => 'delete',
        'DELETE'        => 'delete',
        'ELIMINACION'   => 'delete',
        'CONSULTAR'     => 'query',
        'QUERY'         => 'query',
        'CONSULTA'      => 'query',
        'EXPORTAR'      => 'export',
        'EXPORT'        => 'export',
        'EXPORTACION'   => 'export',
    ];

    private const TIPO_LABELS = [
        'login'  => 'Inicio de sesión',
        'create' => 'Creación',
        'update' => 'Actualización',
        'delete' => 'Eliminación',
        'query'  => 'Consulta',
        'export' => 'Exportación',
    ];

    private function baseSelect(): string
    {
        return "SELECT
                    b.id_bitacora,
                    b.id_usuario,
                    b.accion,
                    b.modulo,
                    b.direccion_ip,
                    b.fecha,
                    b.hora,
                    u.nombre_usuario AS usuario_login,
                    p.primer_nombre,
                    p.primer_apellido,
                    p.foto,
                    r.nombre AS rol_nombre
                FROM {$this->table} b
                LEFT JOIN usuario u ON u.id_usuario = b.id_usuario
                LEFT JOIN persona p ON p.id_persona = u.id_persona
                LEFT JOIN rol r ON r.id_rol = u.id_rol";
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = $this->baseSelect();
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY b.fecha DESC, b.hora DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = "SELECT COUNT(*) FROM {$this->table} b
                LEFT JOIN usuario u ON u.id_usuario = b.id_usuario
                LEFT JOIN persona p ON p.id_persona = u.id_persona";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function getAllForExport(array $filters = []): array
    {
        [$where, $params] = $this->buildJoinFilters($filters);

        $sql = $this->baseSelect();
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY b.fecha DESC, b.hora DESC";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAccionesDisponibles(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT accion FROM {$this->table} ORDER BY accion");
        $valores = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $acciones = [];
        foreach ($valores as $valor) {
            $acciones[$valor] = self::TIPO_LABELS[$this->normalizarTipo($valor)] ?? ucfirst(strtolower($valor));
        }
        return $acciones;
    }

    private function buildJoinFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['desde'])) {
            $where[] = "b.fecha >= :desde";
            $params['desde'] = $filters['desde'];
        }

        if (!empty($filters['hasta'])) {
            $where[] = "b.fecha <= :hasta";
            $params['hasta'] = $filters['hasta'];
        }

        if (!empty($filters['accion'])) {
            $where[] = "b.accion = :accion";
            $params['accion'] = $filters['accion'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(u.nombre_usuario LIKE :search
                         OR p.primer_nombre LIKE :search
                         OR p.primer_apellido LIKE :search
                         OR b.modulo LIKE :search
                         OR b.direccion_ip LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $nombreCompleto = trim(($row['primer_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? ''));
        $nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : 'Sistema';

        $fecha  = new DateTime($row['fecha'] . ' ' . $row['hora']);
        $tipo   = $this->normalizarTipo($row['accion']);
        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            $row['rol_nombre'] ?? null,
            $row['usuario_login'] ?? 'U'
        );

        return [
            'fecha'         => $fecha->format('d/m/Y'),
            'hora'          => $fecha->format('h:i A'),
            'usuario'       => $nombreCompleto,
            'usuario_login' => $row['usuario_login'] ?? '—',
            'iniciales'     => $avatar['iniciales'],
            'avatar_color'  => $avatar['avatar_color'],
            'accion'        => $tipo,
            'accion_label'  => self::TIPO_LABELS[$tipo] ?? ucfirst(strtolower($row['accion'])),
            'modulo'        => $row['modulo'],
            'ip'            => $row['direccion_ip'],
        ];
    }

    private function normalizarTipo(string $accion): string
    {
        $clave = strtoupper(trim($accion));
        return self::TIPOS_CONOCIDOS[$clave] ?? 'query';
    }

    public function countHoy(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE fecha = CURDATE()");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countUsuariosActivosHoy(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT id_usuario) FROM {$this->table} WHERE fecha = CURDATE()"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countAlertasSeguridad(): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM usuario
             WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}