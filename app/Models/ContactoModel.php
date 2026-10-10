<?php
declare(strict_types=1);

namespace App\Models;

class ContactoModel extends BaseModel
{
    protected string $table = 'contacto';
    protected string $primaryKey = 'id_contacto';

    private const COLUMNAS_CONTACTO = [
        'codigo_telefono_principal',
        'numero_telefono_principal',
        'codigo_telefono_alternativo',
        'numero_telefono_alternativo',
        'correo_electronico',
        'telefono_trabajo'
    ];
    private const NULABLES_CONTACTO = ['codigo_telefono_alternativo', 'numero_telefono_alternativo', 'correo_electronico', 'telefono_trabajo'];

    public function upsert(int $idPersona, array $data): bool
    {
        $columnas = array_values(array_intersect(array_keys($data), self::COLUMNAS_CONTACTO));
        if ($columnas === []) {
            return true;
        }

        $params = ['id_persona' => $idPersona];
        $updates = [];

        foreach ($columnas as $col) {
            $val = $data[$col] ?? null;
            $val = is_string($val) ? trim($val) : $val;
            if (($val === '' || $val === null) && in_array($col, self::NULABLES_CONTACTO, true)) {
                $val = null;
            }
            $params[$col] = $val;
            $updates[] = "{$col} = VALUES({$col})";
        }

        $sql = 'INSERT INTO contacto (id_persona, ' . implode(', ', $columnas) . ')
                VALUES (:id_persona, ' . implode(', ', array_map(fn($c) => ":{$c}", $columnas)) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

        return $this->pdo->prepare($sql)->execute($params);
    }
}
