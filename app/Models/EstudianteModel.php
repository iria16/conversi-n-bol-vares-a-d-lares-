<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Listable;

/**
 * Consulta de expedientes (estudiante ↔ persona).
 * Resuelve el grado y la sección vigentes de cada estudiante a partir de su matrícula más reciente (inscripción o ratificación).
*/
class EstudianteModel extends BaseModel implements Listable
{
    protected string $table = 'estudiante';
    protected string $primaryKey = 'id_estudiante';

    /* Estado del CICLO del estudiante, en una sola expresión SQL. Se lee de la última fila de
 `matricula`, no de `persona.estado`: ese campo es de la persona, se comparte con empleados y
 familiares, y el retiro también lo apaga.*/
    private const EXPRESION_ESTADO = "CASE
            WHEN ciclo.tipo = 'RETIRO' THEN 'retirado'
            WHEN ciclo.tipo = 'EGRESO' THEN 'egresado'
            ELSE 'activo'
        END";

    /* La última matrícula de cada estudiante, como tabla derivada. "Última" se decide por la FECHA
 del año escolar y no por id_anio_escolar: los ids son de inserción y comparar por ellos
 puede devolver el año equivocado.*/
    private function joinCiclo(): string
    {
        return "LEFT JOIN (
                    SELECT m.id_estudiante, m.tipo
                      FROM matricula m
                      INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                     WHERE NOT EXISTS (
                           SELECT 1 FROM matricula m2
                             INNER JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                            WHERE m2.id_estudiante = m.id_estudiante
                              AND (a2.anio_inicio > a.anio_inicio
                                   OR (a2.anio_inicio = a.anio_inicio AND m2.id_matricula > m.id_matricula))
                     )
                ) ciclo ON ciclo.id_estudiante = est.id_estudiante";
    }

    /**
 * Joins each student's latest enrollment within the requested year/turn,
 * or their most recent school year when no year was selected.
*/
    private function joinActual(?int $anioId = null, ?int $idTurno = null): array
    {
        $params = [];
        if ($anioId !== null) {
            if ($anioId <= 0) {
                return [
                    'sql' => "INNER JOIN matricula m ON 1 = 0
                              LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                              LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                              LEFT JOIN grado g ON g.id_grado = ea.id_grado
                              LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion",
                    'params' => [],
                ];
            }
            $params['matricula_anio'] = $anioId;
            $turnoCondition = '';
            if ($idTurno !== null) {
                $turnoCondition = ' AND ea_filtro.id_turno = :matricula_turno';
                $params['matricula_turno'] = $idTurno;
            }

            $join = "INNER JOIN (
                        SELECT m_filtro.id_estudiante, MAX(m_filtro.id_matricula) AS id_matricula
                          FROM matricula m_filtro
                          LEFT JOIN estructura_academica ea_filtro
                                 ON ea_filtro.id_estructura_academica = m_filtro.id_estructura_academica
                         WHERE m_filtro.id_anio_escolar = :matricula_anio{$turnoCondition}
                         GROUP BY m_filtro.id_estudiante
                    ) ult ON ult.id_estudiante = est.id_estudiante
                    INNER JOIN matricula m ON m.id_matricula = ult.id_matricula";
        } else {
            $turnoJoin = '';
            $turnoCondition = '';
            if ($idTurno !== null) {
                $turnoJoin = ' LEFT JOIN estructura_academica ea_filtro ON ea_filtro.id_estructura_academica = m_filtro.id_estructura_academica';
                $turnoCondition = ' WHERE ea_filtro.id_turno = :matricula_turno';
                $params['matricula_turno'] = $idTurno;
            }
/* Sin año seleccionado se muestra la matrícula más reciente del alumno.
 ROW_NUMBER y no MAX(id_anio_escolar): los ids de año son de inserción y no
 siguen el orden, así que comparar por id invertía "último" y "primero" cuando
 se cargaba un año viejo, que es justo lo que se hace al abrir un período.
 El desempate por id_matricula además deja UNA fila por estudiante: con
 MAX() el alumno que tiene dos filas en el mismo año (inscripción y retiro)
 salía duplicado en el listado.*/
            $join = "INNER JOIN (
                        SELECT id_estudiante, id_anio_escolar FROM (
                            SELECT m_filtro.id_estudiante, m_filtro.id_matricula, m_filtro.id_anio_escolar,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY m_filtro.id_estudiante
                                       ORDER BY a_filtro.anio_inicio DESC, m_filtro.id_matricula DESC
                                   ) AS puesto
                              FROM matricula m_filtro
                              INNER JOIN anio_escolar a_filtro ON a_filtro.id_anio_escolar = m_filtro.id_anio_escolar{$turnoJoin}
                              {$turnoCondition}
                        ) UltimaFila
                         WHERE UltimaFila.puesto = 1
                     ) ult ON ult.id_estudiante = est.id_estudiante
                     INNER JOIN matricula m ON m.id_matricula = ult.id_matricula";
        }

        return [
            'sql' => $join . "
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion",
            'params' => $params,
        ];
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
        $params = array_merge($matricula['params'], $params);

$sql = "SELECT est.id_estudiante AS id, est.id_estudiante,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       p.numero_documento AS cedula_escolar,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       " . self::EXPRESION_ESTADO . " AS estado_ciclo,
                       g.nombre AS grado, s.nombre AS seccion,
                       a.nombre_periodo AS anio_escolar
                 FROM estudiante est
                 INNER JOIN persona p ON p.id_persona = est.id_persona
                 " . $matricula['sql'] . '
                 ' . $this->joinCiclo();
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
        foreach ($rows as &$estudiante) {
            $estudiante['id']        = (int) $estudiante['id'];
            $estudiante['estado'] = $estudiante['estado_ciclo'];
            $estudiante['avatar_color'] = 'primary';
            $estudiante['iniciales'] = $estudiante['iniciales'] ?: 'ES';
            unset($estudiante['estado_ciclo']);
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
        $params = array_merge($matricula['params'], $params);

        $sql = "SELECT COUNT(*) FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                " . $matricula['sql'] . '
                ' . $this->joinCiclo();
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(array $filters = []): array
    {
        $matricula = $this->joinActual(
            isset($filters['anio']) ? (int) $filters['anio'] : null,
            isset($filters['id_turno']) && $filters['id_turno'] !== '' ? (int) $filters['id_turno'] : null
        );
$sql = "SELECT COUNT(DISTINCT est.id_estudiante) AS total,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'activo'
                                           THEN est.id_estudiante END) AS activos,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'retirado'
                                           THEN est.id_estudiante END) AS retirados,
                       COUNT(DISTINCT CASE WHEN " . self::EXPRESION_ESTADO . " = 'egresado'
                                           THEN est.id_estudiante END) AS egresados
                 FROM estudiante est
                 INNER JOIN persona p ON p.id_persona = est.id_persona";
        $sql .= ' ' . $matricula['sql'];
        $sql .= ' ' . $this->joinCiclo();

        /* El docente ve el conteo de lo suyo, no el de toda la institución: las tarjetas
 deben cuadrar con la tabla que tiene delante, que ya viene filtrada.*/
        $params = $matricula['params'];
        [$whereDocente, $paramsDocente] = $this->restriccionDocente(
            isset($filters['id_docente']) && $filters['id_docente'] !== '' ? (int) $filters['id_docente'] : null
        );
        if ($whereDocente !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $whereDocente);
            $params = array_merge($params, $paramsDocente);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $retirados = (int) ($row['retirados'] ?? 0);
        $egresados = (int) ($row['egresados'] ?? 0);
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'activos'   => (int) ($row['activos'] ?? 0),
            'retirados' => $retirados,
            'egresados' => $egresados,
            // Quien no está activo es cualquiera de los dos desenlaces, que es lo que la
            // tarjeta "Inactivos" resume; el desglose va en el filtro por estado.
            'inactivos' => $retirados + $egresados,
        ];
    }

    /** Detalle completo para la ficha del estudiante.*/
    public function getById(int $id, ?int $anioId = null, ?int $idTurno = null, ?int $idDocente = null): ?array
    {
        $matricula = $this->joinActual($anioId, $idTurno);
        $sql = "SELECT est.id_estudiante AS id,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_corto,
                       p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                       p.id_estado_nacimiento,
                       p.id_nacionalidad, nac.nombre AS nacionalidad,
                       p.id_parroquia_nacimiento, p.sexo, p.id_nacionalidad,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       p.foto AS avatar_img,
                       " . self::EXPRESION_ESTADO . " AS estado_ciclo,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       g.nombre AS grado, s.nombre AS seccion, a.nombre_periodo AS anio_escolar,
                       t.nombre AS turno,
                       c.correo_electronico,
                       CONCAT(c.codigo_telefono_principal, '-', c.numero_telefono_principal) AS telefono,
                       CONCAT(c.codigo_telefono_alternativo, '-', c.numero_telefono_alternativo) AS telefono_alternativo,
                        esN.nombre AS estado_nacimiento,
                        pn.nombre AS parroquia_nacimiento,
                        mn.nombre AS municipio_nacimiento,
                       d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto, d.punto_referencia
                FROM estudiante est
                INNER JOIN persona p ON p.id_persona = est.id_persona
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
LEFT JOIN direccion d          ON d.id_persona            = p.id_persona
                 /* El lugar de nacimiento solo guarda estado y parroquia; los nombres de
                    los tres niveles salen de esos dos, igual que la dirección deduce el
                    suyo de id_parroquia. */
                 /* La nacionalidad se trae con JOIN porque `persona` guarda el id del
                    catálogo, no el texto: sin este JOIN la ficha mostraría el número. */
                 LEFT JOIN nacionalidad nac       ON nac.id_nacionalidad    = p.id_nacionalidad
                 LEFT JOIN estado     esN        ON esN.id_estado          = p.id_estado_nacimiento
                 LEFT JOIN parroquia  pn         ON pn.id_parroquia        = p.id_parroquia_nacimiento
                 LEFT JOIN municipio  mn         ON mn.id_municipio        = pn.id_municipio
                " . $matricula['sql'] . '
                ' . $this->joinCiclo() . "
                LEFT JOIN turno t ON t.id_turno = ea.id_turno
                WHERE est.id_estudiante = :id";
        $params = array_merge($matricula['params'], ['id' => $id]);

        /* Un docente solo abre el expediente de quien tiene en sus estructuras asignadas.*/
        [$whereDocente, $paramsDocente] = $this->restriccionDocente($idDocente);
        if ($whereDocente !== []) {
            $sql .= ' AND ' . implode(' AND ', $whereDocente);
            $params = array_merge($params, $paramsDocente);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['id']          = (int) $row['id'];
        $row['estado']      = $row['estado_ciclo'];
        $row['avatar_color'] = 'primary';
        $row['iniciales']   = $row['iniciales'] ?: 'ES';
        unset($row['estado_ciclo']);
        return $row;
    }

    public function getHistorialAcademico(int $idEstudiante, ?int $idTurno = null): array
    {
        $sql = "SELECT a.nombre_periodo AS anio_escolar, a.estado AS estado_anio,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                       m.tipo AS tipo_matricula, m.fecha_matricula, m.motivo_anticipo
                  FROM matricula m
                  INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  LEFT JOIN turno t ON t.id_turno = ea.id_turno
                 WHERE m.id_estudiante = :estudiante";
        $params = ['estudiante' => $idEstudiante];
        if ($idTurno !== null) {
            $sql .= ' AND ea.id_turno = :turno';
            $params['turno'] = $idTurno;
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, m.fecha_matricula DESC, m.id_matricula DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAniosEscolares(?int $idTurno = null): array
    {
        $sql = "SELECT DISTINCT a.id_anio_escolar AS id, a.nombre_periodo AS nombre, a.estado
                  FROM anio_escolar a
                  INNER JOIN matricula m ON m.id_anio_escolar = a.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica";
        if ($idTurno !== null) {
            $sql .= ' WHERE ea.id_turno = :turno';
        }
        $sql .= ' ORDER BY a.anio_inicio DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($idTurno !== null ? ['turno' => $idTurno] : []);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAnioEscolarActivoId(): ?int
    {
        $id = $this->pdo->query(
            "SELECT id_anio_escolar FROM anio_escolar WHERE estado = 'ACTIVO' LIMIT 1"
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function getGrados(): array
    {
        return $this->pdo->query("SELECT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getSecciones(): array
    {
        return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre ASC")
                         ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
 * Restricción de un docente a las estructuras que tiene asignadas.
 *
 * Sin esto el docente veía TODOS los expedientes de la institución, incluidos los de
 * secciones en las que no da clase. La asignación docente es la única fuente de "qué
 * le toca a cada cual", así que se toma de ahí y no de un turno ni de un grado.
 *
 * Un docente sin asignaciones no ve a nadie: antes del filtro esto tampoco sefiltraba
 * y por eso la pantalla queda vacía a propósito, con un aviso que lo explica.
 *
 * @return array{0:array<int,string>,1:array<string,int>}
*/
    private function restriccionDocente(?int $idDocente): array
    {
        if ($idDocente === null) {
            return [[], []];
        }

        return [
            ['ea.id_estructura_academica IN (
                SELECT ad.id_estructura_academica
                  FROM asignacion_docente ad
                  JOIN estructura_academica asignada ON asignada.id_estructura_academica = ad.id_estructura_academica
                  JOIN anio_escolar asignada_anio ON asignada_anio.id_anio_escolar = asignada.id_anio_escolar
                 WHERE ad.id_docente = :docente
                   AND asignada_anio.estado = \'ACTIVO\'
            )'],
            ['docente' => $idDocente],
        ];
    }

    /**
 * Estructuras asignadas al docente en el año en curso; [] si no tiene ninguna.
 * Existe para que la interfaz pueda explicar una tabla vacía en lugar de dejar
 * al usuario pensando que la consulta falló.
 *
 * @return array<int, int>
*/
    public function getEstructurasDocente(int $idDocente): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ad.id_estructura_academica
               FROM asignacion_docente ad
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
               INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
              WHERE ad.id_docente = :docente AND a.estado = \'ACTIVO\'
              ORDER BY ad.id_estructura_academica'
        );
        $stmt->execute(['docente' => $idDocente]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        [$whereDocente, $paramsDocente] = $this->restriccionDocente(
            isset($filters['id_docente']) && $filters['id_docente'] !== '' ? (int) $filters['id_docente'] : null
        );
        foreach ($whereDocente as $condicion) {
            $where[] = $condicion;
        }
        $params = array_merge($params, $paramsDocente);

        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = "(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)";
        }
        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        /* El filtro trabaja sobre el estado del CICLO (la misma expresión que la columna), no
 sobre `persona.estado`: un egresado no tiene por qué estar inactivo como persona,
 y con el filtro anterior se podía filtrar "activos" y recibirlos como si lo
 fueran. Los tres valores son excluyentes, así que `inactivo` agrupa a los dos
 desenlaces finales para no romper los enlaces ya compartidos.*/
        if (!empty($filters['estado'])) {
            $estado = strtolower(trim((string) $filters['estado']));
            if ($estado === 'inactivo') {
                $where[] = "(" . self::EXPRESION_ESTADO . " IN ('retirado', 'egresado'))";
            } elseif (in_array($estado, ['activo', 'retirado', 'egresado'], true)) {
                $where[] = self::EXPRESION_ESTADO . " = :estado_ciclo";
                $params['estado_ciclo'] = $estado;
            }
        }
        return [$where, $params];
    }
}