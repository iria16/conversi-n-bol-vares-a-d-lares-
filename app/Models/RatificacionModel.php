<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Interfaces\Listable;
use App\Support\Matriculas;
use App\Traits\CupoEstructuraTrait;

use Throwable;
use RuntimeException;

/**
 * Ratificación de matrícula: proyecta al estudiante al siguiente grado (misma sección y turno) en el año en curso.
 * INSCRIPCIÓN es el primer ingreso y ocurre una sola vez; RATIFICACIÓN es cada año siguiente, y se decide con flujoPara().
 * Ratificar crea una MATRÍCULA NUEVA de tipo RATIFICACION; la inscripción es única y no se vuelve a crear.
 */
class RatificacionModel extends BaseModel implements Listable
{
    // Misma fuente de disponibilidad que la inscripción.
    use CupoEstructuraTrait;

    protected string $table = 'ratificacion';
    protected string $primaryKey = 'id_ratificacion';

    /**
     * Une cada estudiante con su matrícula del año más reciente (una sola fila por alumno) y con la del año en curso (`sig`).
     * Se usa el año y no la fecha porque `fecha_matricula` es DATE y dos matrículas del mismo día duplicaban al estudiante.
     * La estructura sale de la propia matrícula, y la situación de su tipo (ver App\Support\Matriculas).
     */
    private function joinActual(): string
    {
        // Se mira el año en curso para que la fila sea estable: pendiente primero, ratificado después, sin volver a pendiente.
        $anio = $this->anioEnCurso();
        return "INNER JOIN (
                    SELECT m2.id_estudiante, MAX(m2.id_anio_escolar) AS anio_ultimo
                      FROM matricula m2
                     GROUP BY m2.id_estudiante
                ) ult ON ult.id_estudiante = est.id_estudiante
                LEFT JOIN (
                    SELECT m3.id_estudiante, MAX(m3.id_matricula) AS id_matricula
                      FROM matricula m3
                      INNER JOIN (
                          SELECT id_estudiante, MAX(id_anio_escolar) AS anio
                            FROM matricula GROUP BY id_estudiante
                      ) y3 ON y3.id_estudiante = m3.id_estudiante
                                    AND y3.anio = m3.id_anio_escolar
                     GROUP BY m3.id_estudiante
                ) ult2 ON ult2.id_estudiante = est.id_estudiante
                LEFT JOIN matricula m ON m.id_matricula = ult2.id_matricula
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                LEFT JOIN matricula sig ON sig.id_estudiante = est.id_estudiante
                                     AND sig.id_anio_escolar = $anio";
    }

    /**
     * Situación del alumno, de más a menos terminal: egresado, retirado, ratificado (tiene fila en el año en curso) o pendiente.
     * Se lee del tipo de la fila del año en curso y de si existe una posterior.
     */
    private const EXPRESION_ESTADO = "CASE
            WHEN m.tipo = 'EGRESO' THEN 'egresado'
            WHEN m.tipo = 'RETIRO' THEN 'retirado'
            WHEN sig.id_matricula IS NOT NULL THEN 'ratificado'
            ELSE 'pendiente'
        END";

    /**
     * Proceso que le corresponde a cada estudiante (misma pregunta que el guardado: ¿tiene matrícula previa?).
     * Se evalúa sobre toda la historia del alumno y no sobre la matrícula vigente.
     */
    private const EXPRESION_FLUJO = "CASE WHEN EXISTS (
                SELECT 1 FROM matricula mi WHERE mi.id_estudiante = est.id_estudiante
             ) THEN 'RATIFICACION' ELSE 'INSCRIPCION' END";

    /** Tipos con el ciclo cerrado (no es un error del dato): se muestran para que se vea por qué quedan fuera. */
    private const ESTADOS_CERRADOS = ['EGRESO', 'RETIRO'];

    /** Columna de comparación del año anterior; compartida por listado, conteo y estadísticas para que cuadren. */
    private const CONDICION_ANIO_ANTERIOR = 'a.anio_inicio';

    /** Procesos de ingreso al plantel. La inscripción es única y la primera. */
    public const FLUJO_INSCRIPCION  = 'INSCRIPCION';
    public const FLUJO_RATIFICACION = 'RATIFICACION';

    /**
     * EL CRITERIO: ¿tiene el estudiante alguna matrícula previa? No → INSCRIPCIÓN; sí → RATIFICACIÓN.
     * Cuenta cualquier matrícula (también RATIFICACION, RETIRO y EGRESO), no solo el tipo de la última.
     *
     * @return self::FLUJO_*
     */
    public function flujoPara(int $estudianteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula WHERE id_estudiante = :estudiante'
        );
        $stmt->execute(['estudiante' => $estudianteId]);
        return ((int) $stmt->fetchColumn() > 0)
            ? self::FLUJO_RATIFICACION
            : self::FLUJO_INSCRIPCION;
    }

    /**
     * Lista de la ratificación: quién está pendiente y por qué.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params, $gradoActual] = $this->filters($filters);
        $grados = $this->gradosOrdenados();

        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       COALESCE(g.nombre, 'Sin grado') AS grado_actual,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       a.nombre_periodo AS anio_actual,
                       " . self::EXPRESION_ESTADO . " AS estado,
                       " . self::EXPRESION_FLUJO . " AS flujo
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.primer_nombre, p.primer_apellido LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id']           = (int) $row['id'];
            $row['avatar_color'] = 'primary';
            $row['iniciales']    = $row['iniciales'] ?: 'ES';
            $row['proximo_grado'] = $gradoActual
                ? $this->siguienteGrado($grados, $row['grado_actual'])
                : ($gradoActual !== null ? $this->siguienteGrado($grados, $gradoActual) : '');
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Números del panel, sobre el mismo criterio que el listado para que cuadren con la tabla.
     * "Por ratificar" son solo los pendientes con matrícula previa; retirados y egresados no cuentan, y los alumnos para inscribir son otro proceso.
     */
    public function getStats(): array
    {
        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(" . self::EXPRESION_ESTADO . " = 'ratificado'), 0) AS ya_ratificados,
                       COALESCE(SUM(" . self::EXPRESION_ESTADO . " = 'pendiente'
                                    AND " . self::EXPRESION_FLUJO . " = 'RATIFICACION'), 0) AS por_ratificar,
                       COALESCE(SUM(m.tipo = 'EGRESO'), 0) AS egresados,
                       COALESCE(SUM(m.tipo = 'RETIRO'), 0) AS retirados
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $this->joinActual() . "
                WHERE " . implode(' AND ', $this->condicionesBase());
        $row = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);

        $yaRatificados = (int) ($row['ya_ratificados'] ?? 0);
        $porRatificar  = (int) ($row['por_ratificar'] ?? 0);
        $egresados     = (int) ($row['egresados'] ?? 0);
        $retirados     = (int) ($row['retirados'] ?? 0);

        // Denominador: pendientes más ya hechos; retirados y egresados quedan fuera porque su año se cerró.
        $ratificables = $porRatificar + $yaRatificados;

        return [
            'porRatificar' => $porRatificar,
            'yaRatificados' => $yaRatificados,
            'egresados'    => $egresados,
            'retirados'    => $retirados,
            // El avance se mide sobre los ratificables; si no, una tanda de graduados inflaría el porcentaje.
            'avance'       => $ratificables > 0
                ? (int) round(($yaRatificados / $ratificables) * 100)
                : 0,
        ];
    }

    public function getGrados(): array
    {
        return array_column($this->gradosOrdenados(), 'nombre');
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Proyecta al siguiente grado e inserta la ratificación. */
    public function ratificar(int $estudianteId): array
    {
        /* El criterio va primero: un alumno sin matrícula previa no entra por aquí sino por inscripción,
           y preguntar por estructura o cupo antes daría un mensaje equivocado. */
        if ($this->flujoPara($estudianteId) === self::FLUJO_INSCRIPCION) {
            return ['ok' => false, 'mensaje' => 'Este estudiante no tiene una inscripción previa en el plantel, '
                . 'así que no se ratifica: corresponde inscribirlo. La ratificación es solo para alumnos '
                . 'que ya estaban inscriptos de un año anterior.'];
        }

        $matriculaVigente = $this->matriculaConEstructura($estudianteId);
        if (!$matriculaVigente) {
            // Dos casos: ya cursa el año en curso (nada que ratificar aún) o nunca tuvo matrícula anterior.
            $enCurso = (int) $this->pdo->query(
                'SELECT COUNT(*) FROM matricula
                  WHERE id_estudiante = ' . (int) $estudianteId
                    . ' AND id_anio_escolar = ' . $this->anioEnCurso()
            )->fetchColumn();

            return ['ok' => false, 'mensaje' => $enCurso > 0
                ? 'El estudiante ya tiene matrícula en el año escolar en curso: está cursando. '
                  . 'La ratificación corresponde al cierre del año, cuando se abre el siguiente periodo.'
                : 'El estudiante no tiene ninguna matrícula de un año anterior, así que no hay nada que ratificar.'];
        }

        /* Primero lo que pasó con el estudiante y después la configuración: si no, a un egresado le dirían
           "no existe estructura académica" y se escondería que ya terminó su ciclo. Son casos válidos, no errores. */
        $cerrado = $this->estadoCerrado($matriculaVigente['id_matricula']);
        if ($cerrado !== null) {
            $mensajes = [
                'egresado' => 'El estudiante ya egresó de la institución: su ciclo está cerrado y no corresponde ratificarlo.',
                'retirado' => 'El estudiante se retiró antes de culminar el año: su ciclo está cerrado y no corresponde ratificarlo.',
            ];
            return ['ok' => false, 'mensaje' => $mensajes[$cerrado] ?? 'El año de este estudiante ya está cerrado.'];
        }

        /* Un retiro EN CURSO también bloquea: es una decisión pendiente, no un hecho consumado.
           Sin esta guarda, con la página abierta o por URL el alumno quedaba con retiro en marcha y matrícula del año siguiente. */
        if ($this->tieneRetiroPendiente((int) $matriculaVigente['id_matricula'])) {
            return ['ok' => false, 'mensaje' => 'Este estudiante tiene una solicitud de retiro en curso. '
                . 'Mientras no se resuelva no se puede ratificar: si se aprueba, se va; si se rechaza, sigue y '
                . 'entonces corresponde ratificarlo.'];
        }

        $estructura = $this->estructuraDestino($matriculaVigente);
        if (!$estructura) {
            return ['ok' => false, 'mensaje' => 'No existe una sección para el siguiente grado en el año escolar en curso. '
                . 'Hay que crearla en la Estructura Académica antes de ratificar a este alumno.'];
        }

        if ($this->yaTieneMatriculaEn($estudianteId, (int) $estructura['anio'])) {
            return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula para ese año escolar, así que no hay nada que ratificar.'];
        }

        // "¿Ya fue ratificado?" antes que el cupo; al revés se decía "sección llena" a quien ya estaba ratificado.
        if ($this->yaRatificado($matriculaVigente['id_matricula'])) {
            return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula en el año escolar en curso: ya está ratificado.'];
        }

        /* Lo que decide si entra va dentro de la transacción con el cupo bloqueado (reservarCupo hace SELECT ... FOR UPDATE),
           porque otra secretaria podría ratificar al último que cabía entre el aviso y el INSERT. */
        try {
            $this->pdo->beginTransaction();

            if ($this->yaRatificado($matriculaVigente['id_matricula'])) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El estudiante ya tiene matrícula en el año escolar en curso: ya está ratificado.'];
            }

            // El destino debe tener cupo (misma regla que la inscripción, ver CupoEstructuraTrait).
            $this->reservarCupo((int) $estructura['id']);

            /* La ratificación es una matrícula nueva de tipo RATIFICACION en la estructura destino.
               No se toca la matrícula anterior (el historial queda entero) ni se crea otra inscripción,
               que es única para siempre (uk_inscripcion_estudiante). */
            $this->pdo->prepare(
                'INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo)
                 VALUES (:estudiante, CURDATE(), :anio, :estructura, :tipo)'
            )->execute([
                'estudiante' => $estudianteId,
                'anio'       => (int) $estructura['anio'],
                'estructura' => (int) $estructura['id'],
                'tipo'       => Matriculas::RATIFICACION,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Ratificación registrada correctamente.'];
        } catch (RuntimeException $e) {
            // reservarCupo() lanza con un texto ya apto para la secretaria: es un aviso, no una falla.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RatificacionModel::ratificar(): ' . $e->getMessage());
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la ratificación.'];
        }
    }

    /** Matrícula del último año del alumno (anterior al año en curso) con la estructura que cursó; no depende de la inscripción. */
    private function matriculaConEstructura(int $estudianteId): ?array
    {
        /* El filtro de año anterior impide ratificar a quien ya cursa el año activo.
           Se compara por `anio_inicio` y no por id, porque los ids son de inserción y no siguen el orden. */
        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula, m.tipo, m.id_anio_escolar,
                    ea.id_grado, ea.id_seccion, ea.id_turno
               FROM matricula m
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               INNER JOIN anio_escolar ay          ON ay.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_estudiante = :estudiante
                AND ay.anio_inicio < {$this->inicioDelAnioEnCurso()}
              ORDER BY ay.anio_inicio DESC, m.id_matricula DESC LIMIT 1"
        );
        $stmt->execute(['estudiante' => $estudianteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Estructura destino: grado siguiente, misma sección y turno, en el año en curso. */
    private function estructuraDestino(array $matricula): ?array
    {
        $grados = $this->gradosOrdenados();
        $siguiente = $this->siguienteGradoConId($grados, $matricula['id_grado']);
        if (!$siguiente) {
            return null;
        }

        // El destino es el año en curso, no "el próximo que exista": si no, el alumno se matricularía años antes de tiempo.
        $stmt = $this->pdo->prepare(
            'SELECT id_estructura_academica AS id, id_anio_escolar AS anio
               FROM estructura_academica
              WHERE id_grado = :grado AND id_seccion = :seccion AND id_turno = :turno
                AND id_anio_escolar = :anio
              LIMIT 1'
        );
        $stmt->execute([
            'grado'   => $siguiente['id'],
            'seccion' => $matricula['id_seccion'],
            'turno'   => $matricula['id_turno'],
            'anio'    => $this->anioEnCurso(),
        ]);
        $id = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $id ? ['id' => (int) $id['id'], 'anio' => (int) $id['anio']] : null;
    }

    /** ¿Ya tiene matrícula en ese año? El UNIQUE (estudiante, año) lo rechazaría igual; se pregunta antes para dar un mensaje claro. */
    private function yaTieneMatriculaEn(int $estudianteId, int $anio): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula WHERE id_estudiante = :estudiante AND id_anio_escolar = :anio'
        );
        $stmt->execute(['estudiante' => $estudianteId, 'anio' => $anio]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Estado con el que cerró el año esa matrícula (el tipo de la fila), o null si sigue cursando.
     *
     * @return 'egresado'|'retirado'|null
     */
    private function estadoCerrado(int $matriculaId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM matricula WHERE id_matricula = :m');
        $stmt->execute(['m' => $matriculaId]);
        $tipo = (string) ($stmt->fetchColumn() ?: Matriculas::INSCRIPCION);

        return in_array($tipo, self::ESTADOS_CERRADOS, true)
            ? mb_strtolower($tipo)
            : null;
    }

    /** ¿El alumno ya tiene matrícula en el año en curso? Es la misma pregunta y el mismo año que el listado, para que el botón no contradiga la pantalla. */
    private function yaRatificado(int $matriculaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM matricula
              WHERE id_estudiante = (SELECT id_estudiante FROM matricula WHERE id_matricula = :m)
                AND id_anio_escolar = :anio'
        );
        $stmt->execute(['m' => $matriculaId, 'anio' => $this->anioEnCurso()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Año escolar en curso (el ACTIVO); sin año devuelve 0 y las consultas no traen a nadie. Sin caché: cachearlo hace que las pantallas se contradigan. */
    private function anioEnCurso(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    /** Fecha de inicio del año en curso, para comparar años por fecha y no por id (los ids son de inserción y no siguen el orden). */
    private function inicioDelAnioEnCurso(): string
    {
        /* Con comillas: sin ellas MySQL lee `2026-12-10` como aritmética y la pantalla sale vacía sin error.
           Si no hay año en curso el helper devuelve 9999-12-31, y todo cuenta como anterior. */
        return "'" . AniosEscolares::inicioEnCurso($this->pdo) . "'";
    }

    /**
     * A quién le corresponde esta pantalla (la definición, no un filtro). Vive en un método para que listado y tarjetas cuadren.
     * (1) Su última matrícula es de un año anterior al en curso; (2) es de un tipo con continuación (INSCRIPCION o RATIFICACION);
     * (3) no tiene un retiro PENDIENTE sobre esa matrícula (uno rechazado no bloquea).
     */
    private function condicionesBase(): array
    {
        return [
            // (1) Año anterior, por FECHA y no por id del año (ver inicioDelAnioEnCurso()).
            self::CONDICION_ANIO_ANTERIOR . ' < ' . $this->inicioDelAnioEnCurso(),

            // (2) Último tipo con continuación; RETIRO y EGRESO son finales.
            "m.tipo IN ('" . self::FLUJO_INSCRIPCION . "', '" . self::FLUJO_RATIFICACION . "')",

            // (3) Sin retiro pendiente, sobre la matrícula concreta y no sobre el estudiante.
            'NOT EXISTS (
                 SELECT 1 FROM retiro r
                  WHERE r.id_matricula = m.id_matricula
                    AND r.estado_solicitud = \'PENDIENTE\'
             )',
        ];
    }

    /** ¿La matrícula tiene un retiro PENDIENTE? Solo ese bloquea; UNIQUE(id_matricula) garantiza a lo sumo una solicitud. */
    private function tieneRetiroPendiente(int $matriculaId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM retiro
              WHERE id_matricula = :m AND estado_solicitud = 'PENDIENTE'"
        );
        $stmt->execute(['m' => $matriculaId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Listas de grados ACTIVO ordenados por el ordinal de su nombre. */
    private function gradosOrdenados(): array
    {
        $rows = $this->pdo->query("SELECT id_grado AS id, nombre FROM grado WHERE estado = 'ACTIVO'")->fetchAll(\PDO::FETCH_ASSOC);
        usort($rows, function ($a, $b) {
            $ordA = (int) (preg_match('/^(\d+)/', $a['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            $ordB = (int) (preg_match('/^(\d+)/', $b['nombre'], $m) ? $m[1] : PHP_INT_MAX);
            return $ordA <=> $ordB;
        });
        return $rows;
    }

    private function siguienteGrado(array $grados, string $nombreActual): string
    {
        $siguiente = $this->siguienteGradoConId($grados, null, $nombreActual);
        return $siguiente['nombre'] ?? '—';
    }

    private function siguienteGradoConId(array $grados, ?int $idActual = null, ?string $nombreActual = null): ?array
    {
        $ordinalActual = PHP_INT_MAX;
        foreach ($grados as $grado) {
            if ($idActual !== null && $grado['id'] === $idActual) {
                $ordinalActual = $this->ordinal($grado['nombre']);
                break;
            }
            if ($nombreActual !== null && $grado['nombre'] === $nombreActual) {
                $ordinalActual = $this->ordinal($grado['nombre']);
                break;
            }
        }
        foreach ($grados as $grado) {
            if ($this->ordinal($grado['nombre']) > $ordinalActual) {
                return $grado;
            }
        }
        return null;
    }

    private function ordinal(string $nombre): int
    {
        return (int) (preg_match('/^(\d+)/', $nombre, $m) ? $m[1] : PHP_INT_MAX);
    }

    private function filters(array $filters): array
    {
        // Las condiciones base están en condicionesBase() (compartidas con las tarjetas); aquí solo los filtros del usuario.
        $where = $this->condicionesBase();
        $params = [];
        $gradoActual = null;

        if (!empty($filters['grado'])) {
            $gradoActual = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
        }
        return [$where, $params, $gradoActual];
    }
}