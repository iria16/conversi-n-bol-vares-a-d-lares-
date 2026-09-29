<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Matriculas
 * ---------------------------------------------------------------
 * Las reglas de qué es una matrícula y qué se puede hacer con una.
 *
 * UNA FILA ES UN TIPO, NUNCA DOS A LA VEZ. Cada fila de `matricula` dice una sola
 * cosa, y eso es lo que hace que el historial sea legible: el alumno que entró en
 * primer grado y se graduó de sexto tiene siete filas, cada una de un tipo, y se
 * lee como una línea de tiempo sin interrogantes.
 *
 *   (nada)        → INSCRIPCION
 *   INSCRIPCION   → RATIFICACION | RETIRO | EGRESO*
 *   RATIFICACION  → RATIFICACION | RETIRO | EGRESO*
 *   RETIRO        → (nada)      estado final
 *   EGRESO        → (nada)      estado final
 *   * EGRESO solo si culminó 6to grado.
 *
 * El detalle de por qué se retiró o egresó sigue en `retiro` y en `egreso`, que
 * apuntan a la fila de RETIRO o de EGRESO. El tipo dice QUE pasó; la tabla
 * adjacent dice POR QUÉ.
 *
 * ── Por qué RETIRO y EGRESO son filas propias y no un cambio de estado ────────
 * Antes un retiro o un egreso cambiaban el `estado` de la fila del año, y esa fila
 * era a la vez la ratificación de ese año y el retiro. Una fila diciendo dos cosas.
 * Con el tipo, el alumno que se va en tercero tiene su RATIFICACION de tercero (que
 * de verdad ocurrió: entró a tercero) y aparte su RETIRO. El historial cuenta lo
 * que pasó, no solo cómo terminó.
 *
 * ── Por qué el estado "TRASLADADO" no existe ────────────────────────────────
 * mudarse a otra institución es exactamente el caso RETIRO: se va del plantel sin
 * haber culminado sexto. No es un quinto desenlace, es uno de los dos.
 *
 * Esta clase es el ÚNICO lugar donde vive la tabla de transiciones. Se pide
 * `Matriculas::puedeDespuesDe()` desde los cuatro modelos que crean filas
 * (inscripción, ratificación, retiro y egreso). Si cada uno tuviera su propia
 * versión, el día que una se olvide de un caso el sistema permite una transición
 * prohibida y el error aparece en el historial, meses después.
 */
final class Matriculas
{
    /** Primer ingreso. Una sola vez en toda la vida escolar del alumno. */
    public const INSCRIPCION = 'INSCRIPCION';

    /** Continuidad. Una vez por año escolar, mientras el alumno siga aquí. */
    public const RATIFICACION = 'RATIFICACION';

    /** Se va antes de culminar sexto. Estado final. */
    public const RETIRO = 'RETIRO';

    /** Culminó sexto. Estado final. */
    public const EGRESO = 'EGRESO';

    /**
     * Los cuatro tipos, y nada más.
     *
     * La lista es cerrada a propósito. Un valor que ninguna regla puede alcanzar
     * es una trampa: queda disponible, alguien lo usa, y después nadie sabe qué
     * pasó. Por eso el `TRASLADADO` que había antes no está.
     *
     * @return array<int, string>
     */
    public static function tipos(): array
    {
        return [self::INSCRIPCION, self::RATIFICACION, self::RETIRO, self::EGRESO];
    }

    /**
     * Los tipos que representan al alumno CURSANDO, o sea los que ocupan cupo.
     *
     * @return array<int, string>
     */
    public static function tiposQueCursan(): array
    {
        return [self::INSCRIPCION, self::RATIFICACION];
    }

    /**
     * Los tipos que CIERRAN el ciclo: después de uno de estos no hay más matrículas.
     *
     * @return array<int, string>
     */
    public static function tiposFinales(): array
    {
        return [self::RETIRO, self::EGRESO];
    }

    /**
     * ¿Este tipo cierra el ciclo?
     */
    public static function esFinal(?string $tipo): bool
    {
        return $tipo !== null && in_array($tipo, self::tiposFinales(), true);
    }

    /**
     * ¿La transición de un tipo a otro está permitida?
     *
     * `null` como tipo actual quiere decir "no tiene ninguna matrícula todavía", y
     * de ahí solo se puede pasar a una inscripción.
     */
    public static function puedeDespuesDe(?string $actual, string $nuevo): bool
    {
        $nuevo = strtoupper(trim($nuevo));
        if (!in_array($nuevo, self::tipos(), true)) {
            return false;
        }

        // Sin historial: el único camino es la inscripción. Nadie entra al
        // plantel por la ratificación, que es la que continúa.
        if ($actual === null) {
            return $nuevo === self::INSCRIPCION;
        }

        $actual = strtoupper(trim($actual));
        if (!in_array($actual, self::tipos(), true)) {
            return false;
        }

        // Un estado final no admite nada más: ni otra ratificación, ni un egreso
        // "por las dudas". Se cierra y se queda cerrado.
        if (self::esFinal($actual)) {
            return false;
        }

        // INSCRIPCION y RATIFICACION comparten lo que puede seguirles: continuar,
        // o cerrarse. Lo único que no puede ser es otra inscripción, porque la
        // inscripción es única de por vida.
        return $nuevo !== self::INSCRIPCION;
    }

    /**
     * La razón por la que no se puede pasar de un tipo a otro, para poder
     * mostrársela a quien lo intenta en vez de un error seco.
     *
     * @return string Vacío cuando la transición sí está permitida.
     */
    public static function motivoBloqueo(?string $actual, string $nuevo): string
    {
        if (self::puedeDespuesDe($actual, $nuevo)) {
            return '';
        }
        $nuevo = strtoupper(trim($nuevo));

        if ($actual === null) {
            return "Un estudiante sin inscripción previa no se ratifica: corresponde inscribirlo. "
                . "La ratificación es solo para alumnos que ya estaban.";
        }
        if (self::esFinal($actual)) {
            $cierre = $actual === self::EGRESO ? 'egresó' : 'se retiró';
            return "El ciclo de este estudiante ya se cerró ($cierre). No admite más matrículas.";
        }
        if ($nuevo === self::INSCRIPCION) {
            return 'La inscripción ocurre una sola vez en toda la vida escolar del alumno: '
                . 'los que ya están inscriptos se ratifican.';
        }
        if (!in_array($nuevo, self::tipos(), true)) {
            return "'$nuevo' no es un tipo de matrícula válido.";
        }
        return "No se puede pasar de $actual a $nuevo.";
    }

    /**
     * El egreso exige haber culminado el último grado del ciclo (6to) y el retiro
     * no. Se comprueba aparte porque es la única condición que depende del grado y
     * no del tipo anterior.
     *
     * Se compara contra `Instituciones::GRADO_MAYOR` y no contra el número 6, para
     * que el "último grado" sea una sola cosa en el sistema. Tener el 6 escrito en
     * varios lados es cómo un día queda el egreso en un sitio y la pantalla de
     * grados en otro.
     */
    public static function egresoPermitido(?int $ordinalGrado): bool
    {
        return $ordinalGrado === Instituciones::GRADO_MAYOR;
    }

    /**
     * Condición SQL de "este alumno cursa esta fila", para contar cupos.
     *
     * Son dos cosas y por eso van las dos:
     *
     *  1. La fila es de tipo INSCRIPCION o RATIFICACION. Las de RETIRO y EGRESO
     *     son cierres, no cursada.
     *
     *  2. Y es la ÚLTIMA fila del alumno. Esto es lo que no se ve: cuando alguien
     *     se retira, su fila del año sigue ahí, con su tipo de cursada, porque no
     *     se toca el historial. Si el conteo se quedara con "tiene alguna fila
     *     activa", ese alumno seguiría ocupando el puesto para siempre y la
     *     sección nunca liberaría el lugar. Lo mismo con el que se ratifica al año
     *     siguiente: ya no está en la sección de antes.
     *
     * Está como texto y no como método que arme la consulta entera porque la usan
     *     dos sitios con_SELECT distintos (el conteo de cupos y el conteo de
     *     alumnos de la Estructura Académica), y duplicar la regla entre ellos es
     *     como la institución termina mostrando dos cifras distintas para lo
     *     mismo. La condición del último va con la comparación de año primero
     *     porque `id_matricula` solo desempata dentro del mismo año.
     */
    public static function condicionCursando(string $alias = 'm'): string
    {
        /* La comparación de años es por `anio_inicio`, NO por `id_anio_escolar`.

           Los ids de año escolar son de inserción, así que no siguen el orden
           cronológico: un año cargado antes puede tener id mayor que uno cargado
           después, y comparar por id invierte "anterior" y "posterior" sin que
           nada parezca roto.

           Se intentó tres veces con el id, y las tres fallaron de la misma manera,
           que es justamente la señal de que el problema no era el sitio sino la
           idea: hay que comparar años por su FECHA. Pasó con un año de prueba de
           2024 con id 316 contra el año en curso de 2025 con id 175. */
        $otro = $alias === 'm' ? 'u' : $alias . '_u';
        $yo   = $alias === 'm' ? 'c' : $alias . '_c';

        return $alias . ".tipo IN ('INSCRIPCION', 'RATIFICACION') AND NOT EXISTS (
                    SELECT 1 FROM matricula {$otro}
                      INNER JOIN anio_escolar anu ON anu.id_anio_escolar = {$otro}.id_anio_escolar
                      INNER JOIN anio_escolar anc ON anc.id_anio_escolar = {$alias}.id_anio_escolar
                     WHERE {$otro}.id_estudiante = {$alias}.id_estudiante
                       AND (anu.anio_inicio > anc.anio_inicio
                            OR (anu.anio_inicio = anc.anio_inicio
                                AND {$otro}.id_matricula > {$alias}.id_matricula))
                )";
    }
}
