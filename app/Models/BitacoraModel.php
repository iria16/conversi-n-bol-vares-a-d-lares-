<?php
    namespace App\Models;

require_once __DIR__ . '/BaseModel.php';

class BitacoraModel extends BaseModel
{
    protected string $table = 'Bitacora';
    protected string $primaryKey = 'id';

    protected function searchableFields(): array
    {
        return [];
    }

    protected function buildFilters(array $filters): array
    {
        $where = [];
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

        return [$where, $params];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT
                    b.id,
                    b.fecha,
                    b.hora,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS usuario,
                    u.nombre_usuario AS usuario_login,
                    UPPER(LEFT(p.primer_nombre, 1)) AS iniciales,
                    b.accion,
                    b.modulo,
                    b.direccion_ip AS ip
                FROM Bitacora b
                INNER JOIN Usuario u ON u.id = b.usuario_id
                INNER JOIN Persona p ON p.id = u.persona_id";

        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY b.fecha DESC, b.hora DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($registros as &$r) {
            $r['accion_label'] = $this->accionLabel($r['accion']);
            $r['avatar_color'] = 'primary'; // visual, sin lógica real todavía
        }

        return $registros;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = "SELECT COUNT(*) FROM Bitacora b";
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getAllSinPaginar(array $filters = []): array
    {
        return $this->getAll($filters, 1, PHP_INT_MAX);
    }

    public function getStats(): array
    {
        $hoy = date('Y-m-d');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Bitacora WHERE fecha = :hoy");
        $stmt->execute(['hoy' => $hoy]);
        $actividadHoy = (int) $stmt->fetchColumn();

        // Sin columna de "alerta" en el esquema: por ahora, DELETE de hoy como proxy.
        // Ajusta esta definición cuando tengas la regla de negocio real.
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM Bitacora WHERE accion = 'DELETE' AND fecha = :hoy");
        $stmt->execute(['hoy' => $hoy]);
        $alertas = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT usuario_id) FROM Bitacora WHERE fecha = :hoy");
        $stmt->execute(['hoy' => $hoy]);
        $usuariosActivos = (int) $stmt->fetchColumn();

        return [
            'actividad_hoy'    => $actividadHoy,
            'alertas'          => $alertas,
            'usuarios_activos' => $usuariosActivos,
        ];
    }

    public function getAcciones(): array
    {
        // El comentario de la columna sugiere estos valores en MAYÚSCULAS
        return [
            'LOGIN'  => 'Inicio de sesión',
            'LOGOUT' => 'Cierre de sesión',
            'CREATE' => 'Creación',
            'UPDATE' => 'Actualización',
            'DELETE' => 'Eliminación',
        ];
    }

    private function accionLabel(string $accion): string
    {
        return $this->getAcciones()[$accion] ?? ucfirst(strtolower($accion));
    }
}