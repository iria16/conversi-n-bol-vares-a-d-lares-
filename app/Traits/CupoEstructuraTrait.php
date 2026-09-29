<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\CupoAgotadoException;
use App\Support\Matriculas;

/**
 * CupoEstructuraTrait
 *
 * La fuente de verdad de qué combinaciones existen y cuántas quedan libres es
 * `estructura_academica` (grado + sección + turno + año escolar, con
 * `capacidad_maxima`). Este trait es el único lugar donde se cruza esa
 * configuración con los estudiantes que realmente la ocupan, y lo usan los dos
 * módulos que necesitan el dato: InscripcionesModel (el paso académico) y
 * RatificacionModel (que proyecta al periodo siguiente).
 *
 * ── El modelo de matrícula ──────────────────────────────────────────────────
 * La idea de negocio es que la matrícula es HISTORIAL: un registro por estudiante
 * por año escolar. Un alumno que sigue en el plantel tiene una fila por cada año
 * que cursó, y NO se sobrescribe ni se borra la anterior.
 *
 * La situación del alumno para ese año es una PROPIEDAD de la fila:
 *
 *   matricula  id_matricula, id_estudiante, fecha_matricula,
 *              id_anio_escolar, id_estructura_academica,
 *              tipo ENUM('INSCRIPCION','RATIFICACION','RETIRO','EGRESO')
 *              → el año y la estructura viven ACÁ, no en la inscripción.
 *   retiro     tabla aparte, con el motivo y la fecha. Apunta a la fila RETIRO.
 *   egreso     tabla aparte, con el detalle. Apunta a la fila EGRESO.
 *   inscripcion  la procedencia del alumno: de qué colegio viene, qué año cursó
 *              allá. Es un dato de origen, una sola vez por estudiante
 *              (uk_inscripcion_estudiante), y NO es lo mismo que el tipo
 *              INSCRIPCION: un alumno que viene de otro colegio tiene su
 *              inscripción con es_primer_ingreso = 0 y aun así su primera fila de
 *              matrícula es INSCRIPCION, porque es su primer ingreso AQUÍ.
 *
 * El tipo dice QUÉ pasó; `retiro` y `egreso` dicen POR QUÉ. Por eso el conteo de
 * abajo puede leer `tipo` y no tiene que deducir nada buscando en tres tablas.
 *
 * Un RETIRO también libera el puesto: se va del plantel. Mudarse a otra
 * institución es el mismo caso, no uno aparte.
 *
 * ── Qué cuenta como ocupado ───────────────────────────────────────────────
 * Cuenta las matrículas `ACTIVO` de la combinación. Nada más: la fila de un
 * alumno cuenta en la sección en la que está, porque la estructura ahora está en
 * la propia matrícula y no hay que resolver con cuál inscripción se queda.
 *
 * Cuando se aprueba un retiro o se registra un egreso, el cupo se libera solo: se
 * cambia el `estado` de esa fila, y el siguiente que pregunta lo cuenta y lo ve.
 *
 * Todo se resuelve con DOS consultas (los nombres de los niveles y el conteo),
 * no una por combinación.
 *
 * @author Logística
 * @package App\Traits
 */
trait CupoEstructuraTrait
{
    /**
     * Las combinaciones de `estructura_academica` con lo ocupado y lo que queda.
     *
     * @param  int|null $idAnioEscolar null = todos los años. La inscripción
     *         filtra por el año activo; la ratificación necesita el que sea,
     *         porque su estructura de destino es del periodo SIGUIENTE, que
     *         todavía no está activo, y filtrar por el activo le daría "no
     *         existe" siempre.
     * @return array<int, array<string, mixed>> con id_estructura_academica,
     *         id_grado, id_seccion, id_turno, id_anio_escolar, grado, seccion,
     *         turno, capacidad_maxima, ocupados, disponibles, lleno y nombre.
     */
    protected function estructurasConCupo(?int $idAnioEscolar = null): array
    {
        $donde = $idAnioEscolar !== null ? 'WHERE ea.id_anio_escolar = :anio' : '';
        $stmt  = $this->pdo->prepare(
            "SELECT ea.id_estructura_academica,
                    ea.id_grado, ea.id_seccion, ea.id_turno, ea.id_anio_escolar,
                    ea.capacidad_maxima,
                    g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno
               FROM estructura_academica ea
               LEFT JOIN grado g   ON g.id_grado   = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t   ON t.id_turno   = ea.id_turno
               {$donde}
              ORDER BY g.nombre, s.nombre, t.nombre"
        );
        $stmt->execute($idAnioEscolar !== null ? ['anio' => $idAnioEscolar] : []);

        $ocupadosPor = $this->conteoDeOcupados();

        $filas = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            $id        = (int) $fila['id_estructura_academica'];
            $capacidad = (int) $fila['capacidad_maxima'];
            $ocupados  = (int) ($ocupadosPor[$id] ?? 0);
            $libres    = $capacidad - $ocupados;

            $filas[] = [
                'id_estructura_academica' => $id,
                'id_grado'                 => (int) $fila['id_grado'],
                'id_seccion'               => (int) $fila['id_seccion'],
                'id_turno'                 => (int) $fila['id_turno'],
                'id_anio_escolar'          => (int) $fila['id_anio_escolar'],
                'grado'                    => (string) $fila['grado'],
                'seccion'                  => (string) $fila['seccion'],
                'turno'                    => (string) $fila['turno'],
                'capacidad_maxima'         => $capacidad,
                'ocupados'                 => $ocupados,
                'disponibles'              => $libres,
                'lleno'                    => $libres <= 0,
                'nombre'                   => trim($fila['grado'] . ' · ' . $fila['seccion'] . ' · ' . $fila['turno']),
            ];
        }

        return $filas;
    }

    /**
     * Estructura -> cuántos estudiantes la ocupan, en una sola consulta.
     *
     * Esto es "cuántos están CURSANDO", y ahora se lee directo:
     *
     *   1. Cada fila de `matricula` es un alumno cursando UN año en UNA
     *      combinación, porque el año y la estructura están en la propia fila.
     *      Un alumno que viene del año anterior tiene dos filas, pero solo una
     *      cuenta: la del año que se está contando.
     *   2. Solo cuentan las que son INSCRIPCION o RATIFICACION, que son las que
     *      significan "cursando". RETIRO y EGRESO son filas de cierre: el alumno
     *      ya no está, y liberaron el puesto.
     *
     * Ojo con el detalle de por qué hay dos filas en el mismo año y no es un
     * error: un alumno que entra a tercero y se retira ese mismo año tiene su
     * RATIFICACION de tercero y su RETIRO de tercero. La de RATIFICACION cuenta
     * (estuvo cursando) y la de RETIRO no. Por eso el filtro es por tipo y no por
     * "la última fila del alumno": la última fila de ese alumno es el retiro, y
     * contar solo la última daría 0 donde debería dar 1.
     *
     * El COUNT es DISTINCT por estudiante a propósito: con el UNIQUE
     * (estudiante, año) es imposible que un mismo alumno tenga dos filas en la
     * misma combinación, pero mantenerlo hace que el número no dependa de que esa
     * restricción siga puesta.
     *
     * @return array<int, int>
     */
    private function conteoDeOcupados(): array
    {
        $filas = $this->pdo->query(
            'SELECT id_estructura_academica AS combo, COUNT(DISTINCT id_estudiante) AS ocupados
               FROM matricula m
              WHERE ' . Matriculas::condicionCursando('m') . '
                AND m.id_estructura_academica IS NOT NULL
              GROUP BY m.id_estructura_academica'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $mapa = [];
        foreach ($filas as $combo => $ocupados) {
            $mapa[(int) $combo] = (int) $ocupados;
        }

        return $mapa;
    }

    /**
     * La combinación grado + sección + turno de un año, con su disponibilidad.
     *
     * null cuando no está registrada: ese es el caso que el formulario no debe
     * permitir, porque significa que esa sección no existe para ese periodo.
     *
     * @return array<string, mixed>|null
     */
    protected function combinacionConCupo(int $idGrado, int $idSeccion, int $idTurno, int $idAnioEscolar): ?array
    {
        if ($idAnioEscolar <= 0) {
            return null;
        }

        foreach ($this->estructurasConCupo($idAnioEscolar) as $combinacion) {
            if ($combinacion['id_grado'] === $idGrado
                && $combinacion['id_seccion'] === $idSeccion
                && $combinacion['id_turno'] === $idTurno) {
                return $combinacion;
            }
        }

        return null;
    }

    /**
     * La disponibilidad de UNA combinación, por su id de estructura.
     *
     * Es la comprobación puntual para cuando ya se sabe cuál es: la ratificación
     * resuelve sola la de destino, y la inscripción llega con el id ya resuelto.
     * null si ese id no existe.
     *
     * @return array<string, mixed>|null
     */
    protected function disponibilidadDe(int $idEstructura): ?array
    {
        foreach ($this->estructurasConCupo() as $combinacion) {
            if ($combinacion['id_estructura_academica'] === $idEstructura) {
                return $combinacion;
            }
        }

        return null;
    }

    /**
     * Los niveles que se ofrecen en el paso académico, con lo que queda libre en
     * cada uno, y las combinaciones con su número.
     *
     * @param  int $idAnioEscolar
     * @return array{grados:array<int,array<string,mixed>>,secciones:array<int,array<string,mixed>>,
     *               turnos:array<int,array<string,mixed>>,combinaciones:array<int,array<string,mixed>>}
     */
    protected function opcionesAcademicas(int $idAnioEscolar): array
    {
        $combos = $this->estructurasConCupo($idAnioEscolar);

        $grados    = [];
        $secciones = [];
        $turnos    = [];

        foreach ($combos as $combo) {
            $gr = (int) $combo['id_grado'];
            $grados[$gr] ??= ['id' => $gr, 'nombre' => $combo['grado'], 'libres' => 0, 'total' => 0];
            $grados[$gr]['total']++;
            $grados[$gr]['libres'] += max(0, (int) $combo['disponibles']);

            $se = (int) $combo['id_seccion'];
            $secciones[$se] ??= ['id' => $se, 'nombre' => $combo['seccion'], 'libres' => 0, 'total' => 0];
            $secciones[$se]['total']++;
            $secciones[$se]['libres'] += max(0, (int) $combo['disponibles']);

            $tu = (int) $combo['id_turno'];
            $turnos[$tu] ??= ['id' => $tu, 'nombre' => $combo['turno']];
        }

        return [
            'grados'        => array_values($grados),
            'secciones'     => array_values($secciones),
            'turnos'        => array_values($turnos),
            'combinaciones' => $combos,
        ];
    }

    /**
     * Revisa el cupo de una combinación DENTRO de la transacción que va a
     * escribir, y bloquea la fila mientras la revisa.
     *
     * Existe porque comprobar el cupo antes de abrir la transacción no alcanza:
     * entre la comprobación y el guardado cabe otro guardado, y los dos
     * pasarían la misma revisión con el mismo conteo. El FOR UPDATE sobre la
     * fila de estructura_academica hace que el segundo guardado espere, y al
     * despertar vuelve a contar con el primero ya confirmado.
     *
     * @throws CupoAgotadoException si la combinación no existe o ya está llena.
     */
    protected function reservarCupo(int $idEstructura): array
    {
        $this->pdo->prepare(
            'SELECT id_estructura_academica FROM estructura_academica
              WHERE id_estructura_academica = :id FOR UPDATE'
        )->execute(['id' => $idEstructura]);

        $disponibilidad = $this->disponibilidadDe($idEstructura);
        if ($disponibilidad === null) {
            throw new CupoAgotadoException(
                'La sección seleccionada no está registrada en la Estructura Académica.',
                'id_seccion'
            );
        }
        if ($disponibilidad['lleno']) {
            throw new CupoAgotadoException(
                'La sección está llena: ' . (int) $disponibilidad['ocupados'] . ' de '
                . (int) $disponibilidad['capacidad_maxima'] . ' cupos ocupados ('
                . $disponibilidad['nombre'] . ').'
            );
        }

        return $disponibilidad;
    }
}
