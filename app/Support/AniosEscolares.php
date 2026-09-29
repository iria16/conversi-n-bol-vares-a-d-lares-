<?php

namespace App\Support;

use PDO;

/**
 * El año escolar en curso, en un solo lugar.
 *
 * Existía esta misma consulta escrita a mano en seis puntos del código, y cada
 * copia era una occasion de que una se desactualice. No es hipotético: comparar
 * años por `id_anio_escolar` en vez de por `anio_inicio` apareció tres veces en
 * el mismo cambio, porque los ids se dan en orden de inserccion y un ano cargado
 * antes puede tener id mayor que uno de despues.
 *
 * Que quede aqui escrito una vez, y con el `ORDER BY anio_inicio DESC LIMIT 1`
 * siempre puesto, es la garantia de que todas las pantallas coincidan en cual es
 * el ano en curso. Sin ese `LIMIT 1` y si por lo que sea hubiera dos anos
 * activos, cada consulta devolveria uno distinto y el sistema "funcionaria" con
 * cada pantalla diciendo una cosa diferente.
 */
final class AniosEscolares
{
    private function __construct()
    {
    }

    /**
     * Id del ano escolar en curso, o 0 si no hay ninguno abierto.
     *
     * El 0 es a proposito: las consultas que arman con el van a filtrar
     * `id_anio_escolar = 0`, que no trae a nadie. Es preferible una lista vacia
     * a una lista con los alumnos de cualquier ano, y deja a la vista el
     * problema real, que es que no hay ano abierto.
     */
    public static function idEnCurso(PDO $pdo): int
    {
        return (int) self::columna($pdo, 'id_anio_escolar');
    }

    /**
     * Fecha de inicio del ano en curso, o '9999-12-31' si no hay ninguno.
     *
     * El 9999-12-31 es una fecha que ningun ano real va a alcanzar, y sirve para
     * que las comparaciones de "empieza antes que el ano en curso" se lleven
     * bien: sin ano abierto, todo lo que exista se considera anterior, que es
     * como se lee un ano que todavia no empieza.
     */
    public static function inicioEnCurso(PDO $pdo): string
    {
        return (string) (self::columna($pdo, 'anio_inicio') ?: '9999-12-31');
    }

    /**
     * Nombre del ano en curso, o '' si no hay ninguno abierto. Para mostrar.
     */
    public static function nombreEnCurso(PDO $pdo): string
    {
        return (string) self::columna($pdo, 'nombre_periodo');
    }

    private static function columna(PDO $pdo, string $columna): string|false
    {
        /* La columna viene de las dos llamadas de arriba, no de entrada del
           usuario, pero se filtra igual: una interpolacion de SQL sin filtro es
           justo el tipo de cosa que despues se copia mal. */
        if (!in_array($columna, ['id_anio_escolar', 'anio_inicio', 'nombre_periodo'], true)) {
            throw new \InvalidArgumentException("Columna no permitida: {$columna}");
        }

        return $pdo->query(
            "SELECT {$columna} FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        )->fetchColumn();
    }
}
