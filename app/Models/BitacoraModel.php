<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;
use App\Traits\AvatarTrait;
use DateTime;
use PDO;
use Throwable;

/**
 * Bitácora del sistema: registrar() audita acciones (tolerante a fallos, solo deja error_log) y el resto es lectura para la vista de auditoría.
 * Extiende Model y no BaseModel a propósito: registrar() es la única vía de escritura y no expone create/update/delete genéricos.
 * Tabla bitacora: id_bitacora, id_usuario, fecha, hora, accion (texto libre, no ENUM), modulo, direccion_ip.
*/
class BitacoraModel extends Model implements Listable
{
    use AvatarTrait;

    protected string $table = 'bitacora';
    protected string $primaryKey = 'id_bitacora';

    // Escritura: registrar una acción de auditoría

    public function registrar(int $idUsuario, string $accion, string $modulo, ?string $ip = null): bool
    {
        try {
            /* Recorte a la medida de las columnas. `accion` y `modulo` son varchar(100) y
 varchar(50) y el modo es STRICT_TRANS_TABLES, así que un texto más largo no
 se recorta: revienta la consulta y la acción que se quería auditar se queda
 sin registro. Los mensajes se arman con nombres de alumno y de grado, que es
 justo donde se pasan de largo.*/
            $stmt = $this->pdo->prepare(
                'INSERT INTO bitacora (id_usuario, fecha, hora, accion, modulo, direccion_ip)
                 VALUES (:id, CURDATE(), CURTIME(), :accion, :modulo, :ip)'
            );

            return $stmt->execute([
                'id'     => $idUsuario,
                'accion' => mb_substr($accion, 0, 100),
                'modulo' => mb_substr($modulo, 0, 50),
                'ip'     => mb_substr((string) ($ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45),
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
        /* Faltaba la variante CON tilde. Sin ella, una acción que empieza por
 "ACTUALIZACIÓN ..." caía en el último recurso de normalizarTipo() y la
 auditoría la mostraba como "Consulta": cambiar un dato de un alumno es
 justamente lo que esa pantalla tiene que dejar a la vista, y se escondeía
 detrás de la etiqueta de solo leer. CREACIÓN, INSCRIPCIÓN y EXPORTACIÓN ya
 tenían su variante acentuada; esta se había quedado sin ella.*/
        'ACTUALIZACIÓN' => 'update',
        'ELIMINAR'      => 'delete',
        'DELETE'        => 'delete',
        'ELIMINACION'   => 'delete',
        'CONSULTAR'     => 'query',
        'QUERY'         => 'query',
        'CONSULTA'      => 'query',
        'EXPORTAR'      => 'export',
        'EXPORT'        => 'export',
        'EXPORTACION'   => 'export',
        'EXPORTACIÓN'   => 'export',

        /* Procesos académicos. El nombre canónico va al principio del texto de `accion`
 y el detalle detrás, así que lo que clasifica es el prefijo (normalizarTipo()).*/
        'INSCRIPCIÓN'     => 'inscripcion',
        'RATIFICACIÓN'    => 'ratificacion',
        'SOLICITUD'       => 'retiro',
        'RETIRO'          => 'retiro',
        'EGRESO'          => 'egreso',
        'INICIO'          => 'anio',
        'CIERRE'          => 'anio',
        /* Una reapertura del ciclo es del mismo tipo que abrirlo o cerrarlo, pero usaba una palabra que no estaba en esta lista y caía en el último recurso
 ('query'): en la auditoría una reapertura aparecía etiquetada como "Consulta", igual que si nadie hubiera hecho nada. Y es de las acciones más
 delicadas que hace un directivo, porque devuelve el período a un estado anterior y deshace un cierre: era justo la que más necesitaba quedar a la
 vista.*/
        'REAPERTURA'      => 'anio',
        'CAMBIO_PASSWORD' => 'password',
    ];

    /**
 * Prefijos de `accion` que pertenecen a cada tipo, para poder FILTRAR por tipo.
 *
 * El filtro no puede ser `accion = 'x'`: el texto lleva detrás el detalle del
 * proceso. Con la igualdad, filtrar por "Inscripción" no encontraba nada y el
 * desplegable acababa ofreciendo una opción por cada acción registrada.
*/
    private const PREFIJOS_POR_TIPO = [
        'login'        => ["'LOGIN'", "'LOGOUT'"],
        'password'     => ["'CAMBIO_PASSWORD'"],
        'create'       => ["'CREAR%'", "'CREACI%'"],
        'update'       => ["'ACTUALIZ%'", "'EDITAR%'", "'UPDATE%'"],
        'delete'       => ["'ELIMIN%'", "'DELETE%'"],
        'export'       => ["'EXPORT%'"],
        'inscripcion'  => ["'INSCRIPCIÓN%'"],
        'ratificacion' => ["'RATIFICACIÓN%'"],
        'retiro'       => ["'SOLICITUD%'", "'RETIRO%'"],
        'egreso'       => ["'EGRESO%'"],
        'anio'         => ["'INICIO%'", "'CIERRE%'", "'REAPERTURA%'"],
        // Sin prefijo conocido: cualquier otra cosa es una consulta.
        'query'        => ["'CONSULT%'", "'QUERY%'"],
    ];

    private const TIPO_LABELS = [
        'login'       => 'Inicio de sesión',
        'password'    => 'Cambio de clave',
        'create'      => 'Creación',
        'update'      => 'Actualización',
        'delete'      => 'Eliminación',
        'query'       => 'Consulta',
'export'      => 'Exportación',
        'inscripcion' => 'Inscripción',
        'ratificacion' => 'Ratificación',
        'retiro'      => 'Retiro',
        'egreso'      => 'Egreso',
        'anio'        => 'Ciclo escolar',
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
        /* Los tipos que HAY en la tabla, no los textos crudos: el desplegable lista
 "Inscripción", "Retiro", "Egreso"… y no una línea por cada inscripción
 registrada, que es lo que pasaba al devolver los `accion` sin transformar.*/
        $acciones = [];
        $filas = $this->pdo->query("SELECT DISTINCT accion FROM {$this->table}")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($filas as $valor) {
            $tipo = $this->normalizarTipo((string) $valor);
            $acciones[$tipo] = self::TIPO_LABELS[$tipo] ?? 'Acción';
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

        /* Por tipo, no por texto exacto: `accion` lleva el detalle detrás del nombre canónico,
 así que la igualdad dejaría el filtro sin resultados. Los valores vienen del
 desplegable, que solo ofrece claves de PREFIJOS_POR_TIPO.*/
        if (!empty($filters['accion'])) {
            $tipo = (string) $filters['accion'];
            $prefijos = self::PREFIJOS_POR_TIPO[$tipo] ?? null;
            if ($prefijos === null) {
                $where[] = '1 = 0';   // tipo desconocido: no se filtra a la ligera, se muestra vacío
            } else {
                $where[] = '(b.accion LIKE ' . implode(' OR b.accion LIKE ', $prefijos) . ')';
            }
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
            'accion_label'  => self::TIPO_LABELS[$tipo] ?? 'Acción',
            // El texto completo de la acción: el badge muestra el tipo y este lleva el
            // detalle (a quién se inscribió o retiró, a qué grado pasó), que es
            // lo que hace útil la auditoría y lo único que queda tras el badge.
            'detalle'       => $row['accion'] ?? '',
            'modulo'        => $row['modulo'],
            'ip'            => $row['direccion_ip'],
        ];
    }

    private function normalizarTipo(string $accion): string
    {
        /* Solo la primera palabra decide el tipo. `accion` es texto libre y los procesos
 académicos escriben el detalle detrás del nombre canónico ("INSCRIPCIÓN Ana
 Pérez (V-1) · 1er Grado · A · Tarde"); con la comparación exacta de antes, todo
 eso caía en 'query' y la auditoría mostraba "Consulta" encima de una inscripción.*/
        $primera = strtok(mb_strtoupper(trim($accion), 'UTF-8'), " \t\n\r");
        return self::TIPOS_CONOCIDOS[(string) $primera] ?? 'query';
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