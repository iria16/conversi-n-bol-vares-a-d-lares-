<?php

declare(strict_types=1);

namespace App\Traits;

use RuntimeException;

/**
 * ContactoDireccionTrait
 * -----------------------------------------------------------------
 * Escribe la fila de `contacto` y la de `direccion` de una persona, o las
 * actualiza si ya existían.
 *
 * Por qué un trait: `contacto` y `direccion` tienen una UNIQUE en id_persona,
 * así que guardar es siempre un upsert (INSERT ... ON DUPLICATE KEY UPDATE), y
 * ese SQL es idéntico en todos los módulos que registran personas con datos de
 * contacto: el empleado (EmpleadoModel) y los familiares del wizard de
 * inscripciones (InscripcionesModel). Los dos lo tenían escrito por separado.
 *
 * Lo que NO vive aquí, y es lo importante:
 *
 *  - Qué columnas escribe cada módulo y cómo se llaman en su formulario. Cada
 *    modelo traduce su entrada a nombres de columna y se la pasa ya limpia.
 *  - Cuándo hay algo que escribir. El empleado llama a guardarContacto() con
 *    todo lo que llegó del formulario; el familiar solo escribe si vino
 *    teléfono principal, porque esa es su regla. Esa decisión es del modelo.
 *  - La validación de lo que se guarda, que vive en el controlador
 *    (ContactoValidacionTrait, DireccionValidacionTrait) porque sus reglas
 *    cambian según el módulo.
 *
 * Uso típico desde un modelo:
 *
 *     $this->guardarContacto($idPersona, [
 *         'codigo_telefono_principal'   => $codigo,
 *         'numero_telefono_principal'   => $numero,
 *         'codigo_telefono_alternativo' => $alternativo,   // '' -> NULL
 *         'correo_electronico'          => $correo,        // '' -> NULL
 *     ]);
 *
 * @author Logística
 * @package App\Traits
 */
trait ContactoDireccionTrait
{
    /**
     * Columnas de `contacto` que un módulo puede escribir.
     *
     * Los prefijos y números de teléfono son ENUM y VARCHAR NOT NULL, así que
     * un vacío en el principal es un error de negocio (lo avisa el
     * controlador), no algo que se pueda guardar.
     */
    private const COLUMNAS_CONTACTO = [
        'codigo_telefono_principal',
        'numero_telefono_principal',
        'codigo_telefono_alternativo',
        'numero_telefono_alternativo',
        'correo_electronico',
    ];

    /**
     * Columnas de `contacto` que aceptan NULL. MySQL avisa (error 1265) si se
     * les manda '' a una columna ENUM, y un '' en un varchar no es lo mismo que
     * "no tiene": por eso el vacío se guarda como NULL.
     */
    private const NULABLES_CONTACTO = [
        'codigo_telefono_alternativo',
        'numero_telefono_alternativo',
        'correo_electronico',
    ];

    /** Columnas de `direccion` que un módulo puede escribir. */
    private const COLUMNAS_DIRECCION = [
        'id_parroquia',
        'sector_urbanizacion',
        'calle_avenida',
        'nro_casa_apto',
        'punto_referencia',
    ];

    /**
     * Columnas de `direccion` que aceptan NULL.
     *
     * `punto_referencia` es TEXT y admite ''; el resto son NOT NULL, porque una
     * dirección sin sector, sin calle o sin número no es una dirección.
     */
    private const NULABLES_DIRECCION = [
        'punto_referencia',
    ];

    /**
     * Guarda o actualiza el contacto de una persona.
     *
     * @param int                 $personaId id_persona dueño de la fila.
     * @param array<string,mixed> $valores   Columna => valor. Las columnas
     *                            que no estén en COLUMNAS_CONTACTO se ignoran
     *                            y se escribe solo lo que venga.
     */
    protected function guardarContacto(int $personaId, array $valores): void
    {
        $this->upsertDePersona(
            'contacto',
            self::COLUMNAS_CONTACTO,
            self::NULABLES_CONTACTO,
            $personaId,
            $valores
        );
    }

    /**
     * Guarda o actualiza la dirección de una persona.
     *
     * @param int                 $personaId id_persona dueño de la fila.
     * @param array<string,mixed> $valores   Columna => valor (id_parroquia
     *                            incluido; el estado y el municipio no se
     *                            guardan porque se deducen de la parroquia).
     */
    protected function guardarDireccion(int $personaId, array $valores): void
    {
        $this->upsertDePersona(
            'direccion',
            self::COLUMNAS_DIRECCION,
            self::NULABLES_DIRECCION,
            $personaId,
            $valores
        );
    }

    /**
     * INSERT ... ON DUPLICATE KEY UPDATE de una tabla que pertenece a una
     * persona, contra la UNIQUE de id_persona.
     *
     * No toca fecha_actualizacion a propósito: las dos tablas la tienen con
     * ON UPDATE CURRENT_TIMESTAMP(), así que MySQL la mueve sola cuando la
     * fila se actualiza de verdad. Ponerla en el SET la dejaría a medianoche
     * (CURDATE() no tiene hora), que es lo que pasaba en el modelo de
     * inscripciones antes de compartir este método.
     *
     * @param string              $tabla      'contacto' o 'direccion'.
     * @param array<int,string>   $columnas   Columnas que el módulo puede escribir.
     * @param array<int,string>   $nulos      De esas, las que aceptan NULL.
     * @param int                 $personaId  id_persona.
     * @param array<string,mixed> $valores    Columna => valor.
     */
    private function upsertDePersona(
        string $tabla,
        array $columnas,
        array $nulos,
        int $personaId,
        array $valores
    ): void {
        // Solo las columnas que este módulo puede escribir de la tabla: así un
        // nombre mal puesto en el modelo no termina en un INSERT que MySQL
        // rechaza con "columna desconocida".
        $aEscribir = array_values(array_intersect(array_keys($valores), $columnas));
        if ($aEscribir === []) {
            return;
        }

        $parametros = ['id_persona' => $personaId];
        $bind       = [];
        $actualizar = [];

        foreach ($aEscribir as $columna) {
            $valor = $valores[$columna] ?? null;
            $valor = is_string($valor) ? trim($valor) : $valor;

            if (($valor === '' || $valor === null) && in_array($columna, $nulos, true)) {
                $valor = null;
            }

            $parametros[$columna] = $valor;
            $bind[]               = ':' . $columna;
            $actualizar[]         = "{$columna} = VALUES({$columna})";
        }

        $sql = 'INSERT INTO ' . $tabla . ' (id_persona, ' . implode(', ', $aEscribir) . ')
                VALUES (:id_persona, ' . implode(', ', $bind) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', $actualizar);

        if (!$this->pdo->prepare($sql)->execute($parametros)) {
            throw new RuntimeException('No se pudo ejecutar la consulta de escritura.');
        }
    }
}
