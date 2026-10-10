<?php
declare(strict_types=1);

namespace App\Models;

class DireccionModel extends BaseModel
{
    protected string $table = 'direccion';
    protected string $primaryKey = 'id_direccion';

    private const COLUMNAS_DIRECCION = [
        'id_parroquia',
        'sector_urbanizacion',
        'calle_avenida',
        'nro_casa_apto',
        'punto_referencia'
    ];
    private const NULABLES_DIRECCION = ['punto_referencia'];

    public function upsert(int $idPersona, array $data): bool
    {
        $columnas = array_values(array_intersect(array_keys($data), self::COLUMNAS_DIRECCION));
        if ($columnas === []) {
            return true;
        }

        $params = ['id_persona' => $idPersona];
        $updates = [];

        foreach ($columnas as $col) {
            $val = $data[$col] ?? null;
            $val = is_string($val) ? trim($val) : $val;
            if (($val === '' || $val === null) && in_array($col, self::NULABLES_DIRECCION, true)) {
                $val = null;
            }
            $params[$col] = $val;
            $updates[] = "{$col} = VALUES({$col})";
        }

        $sql = 'INSERT INTO direccion (id_persona, ' . implode(', ', $columnas) . ')
                VALUES (:id_persona, ' . implode(', ', array_map(fn($c) => ":{$c}", $columnas)) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        return $this->pdo->prepare($sql)->execute($params);
    }
}
