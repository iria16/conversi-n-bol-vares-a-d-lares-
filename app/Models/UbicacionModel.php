<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Hijos de la cascada de dirección (estado -> municipio -> parroquia), compartidos por empleados e inscripciones.
 * `direccion` solo guarda id_parroquia: estado y municipio se deducen de ella.
 */
class UbicacionModel extends Model
{
    /** Niveles que sirve el endpoint (el estado no: es corto y se imprime con el HTML). Los declara el modelo para no desincronizar validación y consulta. */
    public const TIPOS = ['municipio', 'parroquia'];

    /**
     * Nivel completo con el id de su padre en cada fila; el navegador filtra (SIGDEUI.filtrarOpciones)
     * y así los tres <select> quedan habilitados desde el inicio. Cada opción trae `data-id-estado` o `data-id-municipio`.
     *
     * @param string $tipo Uno de self::TIPOS.
     * @return array<int, array{id_ubicacion: int, nombre: string, data-id-*?: int}>
     */
    public function nivelCompleto(string $tipo): array
    {
        $catalogo = match ($tipo) {
            'municipio' => ['tabla' => 'municipio', 'pk' => 'id_municipio', 'padre' => 'id_estado', 'attr' => 'data-id-estado'],
            'parroquia' => ['tabla' => 'parroquia', 'pk' => 'id_parroquia', 'padre' => 'id_municipio', 'attr' => 'data-id-municipio'],
            default     => null,
        };

        if ($catalogo === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT {$catalogo['pk']} AS id_ubicacion, nombre, {$catalogo['padre']} AS id_padre
             FROM {$catalogo['tabla']}
             WHERE estado = 'ACTIVO'
             ORDER BY nombre"
        );
        $stmt->execute();

        return array_map(
            static fn (array $fila): array => [
                'id_ubicacion'      => (int) $fila['id_ubicacion'],
                'nombre'            => (string) $fila['nombre'],
                $catalogo['attr']   => (int) $fila['id_padre'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Estado y municipio de una parroquia, para dejar la cascada con los tres niveles elegidos.
     *
     * @return array{estado: int, municipio: int}|null
     */
    public function ubicacionDeParroquia(int $idParroquia): ?array
    {
        if ($idParroquia <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.id_municipio, m.id_estado
             FROM parroquia p
             INNER JOIN municipio m ON m.id_municipio = p.id_municipio
             WHERE p.id_parroquia = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $idParroquia]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        return ['estado' => (int) $fila['id_estado'], 'municipio' => (int) $fila['id_municipio']];
    }
}