<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Matriculas;
use App\Exceptions\CapacidadInsuficienteException;
use App\Exceptions\CupoAgotadoException;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Updatable;

/**
 * Estructura académica: combinación de año escolar + grado + sección + turno con capacidad máxima (estructura_academica).
 * El conteo de alumnos sale de `matricula` (no de `inscripcion`, que guarda el año de origen y mostraría 0 en una sección llena).
 * Cuenta solo INSCRIPCION y RATIFICACION, para coincidir con conteoDeOcupados.
*/
class AcademicStructureModel extends BaseModel implements Listable, Creatable, Updatable
{
    protected string $table = 'estructura_academica';
    protected string $primaryKey = 'id_estructura_academica';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT ea.id_estructura_academica AS id, ea.id_anio_escolar,
                        ea.id_grado, ea.id_seccion, ea.id_turno, ea.capacidad_maxima,
                        a.nombre_periodo AS anio,
                        g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno,
                        (SELECT COUNT(DISTINCT m.id_estudiante) FROM matricula m
                          WHERE m.id_estructura_academica = ea.id_estructura_academica
                            AND " . Matriculas::condicionCursando('m') . ") AS estudiantes
                 FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.anio_inicio DESC, g.nombre, s.nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['capacidad_maxima'] = (int) $row['capacidad_maxima'];
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM estructura_academica ea
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
 * Tarjetas de la pantalla: cuántas combinaciones hay, de cuántos grados y secciones,
 * y la capacidad total.
 *
 * $idAnio acota a un período. Sin él (null) cuenta el año ACTIVO, que es lo que se
 * quiere mostrar por defecto. Hace falta el parámetro porque con el filtro de año la
 * lista puede estar mostrando un período cerrado y las tarjetas seguían dando los
 * totales del activo: una lista de 2025-2026 con los números de 2026-2027 se lee bien
 * y no quiere decir nada.
*/
public function getStats(?int $idAnio = null): array
{
    if ($idAnio !== null && $idAnio > 0) {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(ea.id_estructura_academica) AS total,
                    COUNT(DISTINCT ea.id_grado) AS grados,
                    COUNT(DISTINCT ea.id_seccion) AS secciones,
                    COALESCE(SUM(ea.capacidad_maxima), 0) AS capacidad
               FROM estructura_academica ea
              WHERE ea.id_anio_escolar = :anio'
        );
        $stmt->execute(['anio' => $idAnio]);
    } else {
        $stmt = $this->pdo->query(
            "SELECT COUNT(ea.id_estructura_academica) AS total,
                    COUNT(DISTINCT ea.id_grado) AS grados,
                    COUNT(DISTINCT ea.id_seccion) AS secciones,
                    COALESCE(SUM(ea.capacidad_maxima), 0) AS capacidad
               FROM anio_escolar a
               LEFT JOIN estructura_academica ea ON ea.id_anio_escolar = a.id_anio_escolar
              WHERE a.estado = 'ACTIVO'"
        );
    }

    $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [
        'total'     => (int) $stats['total'],
        'grados'    => (int) $stats['grados'],
        'secciones' => (int) $stats['secciones'],
        'capacidad' => (int) $stats['capacidad'],
    ];
    }

/**
 * Años escolares que se pueden SELECCIONAR en el filtro del listado.
 *
 * No se reutiliza getOptions('anio_escolar') porque ese filtra `estado = 'ACTIVO'` y
 * por definición jamás devolvería un año cerrado, que es justo lo que el filtro
 * necesita ofrecer. Aquí entran todos los períodos con su estado.
 *
 * `es_editable` viaja con cada año para que la vista no tenga que deducirlo
 * comparando estados: la misma regla que aplican validar() en el alta y update() en la
 * edición (solo se toca el ACTIVO) queda escrita una sola vez, aquí.
 *
 * `sufijo_estado` es el rótulo que se muestra junto al nombre. Antes la vista imprimía
 * "(cerrado)" en cuanto el año no era editable, con lo que un período PLANIFICADO
 * salía rotulado como cerrado pese a estar abierto: se decía una cosa falsa sobre un
 * período que ni siquiera había empezado. El rótulo lo decide el estado real, no la
 * ausencia de la condición de edición.
 *
 * Se ordena por fecha de inicio descendente y no por id: los ids son de inserción y no
 * siguen el orden cronológico, así que el filtro podría salir desordenado.
 *
 * @return array<int, array{id:int,nombre:string,estado:string,es_editable:bool,sufijo_estado:string}>
*/
public function getAniosParaConsultar(): array
{
    $filas = $this->pdo->query(
        'SELECT id_anio_escolar AS id, nombre_periodo AS nombre, estado
           FROM anio_escolar
          ORDER BY anio_inicio DESC, id_anio_escolar DESC'
    )->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(static function (array $fila): array {
        $estado = strtoupper((string) $fila['estado']);
        return [
            'id'          => (int) $fila['id'],
            'nombre'      => (string) $fila['nombre'],
            'estado'      => $estado,
            'es_editable' => $estado === 'ACTIVO',
            'sufijo_estado' => match ($estado) {
                'CERRADO' => ' (cerrado)',
                'PLANIFICADO' => ' (planificado)',
                default => '',
            },
        ];
    }, $filas);
}

/**
 * Catálogos activos (id + nombre) para filtros y modales: anio_escolar, grado, seccion y turno.
 * Cada tabla declara su columna de etiqueta (anio_escolar usa nombre_periodo) y siempre se devuelve como 'nombre'.
*/
    public function getOptions(string $table): array
    {
        $catalogos = [
            'anio_escolar' => ['etiqueta' => 'nombre_periodo', 'orden' => 'anio_inicio DESC'],
            'grado'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'seccion'      => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
            'turno'        => ['etiqueta' => 'nombre', 'orden' => 'nombre'],
        ];
        if (!isset($catalogos[$table])) {
            return [];
        }
        $etiqueta = $catalogos[$table]['etiqueta'];
        $orden    = $catalogos[$table]['orden'];
        $stmt = $this->pdo->query(
            "SELECT id_{$table} AS id, {$etiqueta} AS nombre
             FROM {$table} WHERE estado = 'ACTIVO' ORDER BY {$orden}"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getActiveSchoolYearId(): ?int
    {
        $stmt = $this->pdo->query(
            "SELECT id_anio_escolar
               FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        );
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
 * Qué falta configurar en la estructura del año activo.
 *
 * Existe porque la secretaria no puede crear ni editar la estructura (esa ruta es
 * del directivo): si le falta una combinación, se entera al final, cuando ya
 * llenó el formulario entero y el servidor le responde "la sección no existe para
 * esa combinación en el período activo". Ese mensaje dice la mitad de la verdad:
 * no parece un dato mal escrito, sino una configuración que le falta a ella.
 * Anticiparlo en su panel convierte un error en una tarea.
 *
 * LA REGLA: se espera al menos una combinación grado × sección para cada grado
 * activo y cada turno activo. Con 6 grados y 2 turnos el mínimo razonable son 12
 * combinaciones; las que falten son las pendientes. La cuenta y su supuesto se
 * devuelven separados (`esperadas`, `registradas`, `detalle`) para que quien lo
 * lea pueda discutir la aritmética en vez de tener que creerla.
 *
 * Un supuesto abierto y declarado: que todo grado activo deba existir en todo
 * turno activo. Si una escuela quisiera un turno solo de primaria, esta cuenta
 * daría un número más alto del real, y por eso se muestra el desglose y no solo
 * el total.
 *
 * @return array{
 * hay_anio_activo:bool, anio:string,
 * grados:int, turnos:int, esperadas:int, registradas:int, pendientes:int,
 * grados_sin_seccion:array<int,string>
 * }
*/
    public function pendientesDeConfiguracion(): array
    {
        $idAnio  = $this->getActiveSchoolYearId();
        $nombre  = $this->getActiveSchoolYearName() ?? '';

        $grados = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM grado WHERE estado = 'ACTIVO'")
            ->fetchColumn();
        $turnos = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM turno WHERE estado = 'ACTIVO'")
            ->fetchColumn();

        /* Sin año abierto no hay nada que contar: el mensaje tiene que decir eso y no
 "faltan 12 combinaciones", que empuja a arreglar algo que no es el
 problema real.*/
        if ($idAnio === null) {
            return [
                'hay_anio_activo' => false,
                'anio'            => $nombre,
                'grados'           => $grados,
                'turnos'           => $turnos,
                'esperadas'        => 0,
                'registradas'      => 0,
                'pendientes'       => 0,
                'grados_sin_seccion' => [],
            ];
        }

        $registradas = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM estructura_academica WHERE id_anio_escolar = ' . (int) $idAnio
        )->fetchColumn();

        /* Los grados que no tienen ni una sección son los que más duelen: cualquier
 alumno que venga de un grado anterior y promueva a uno de estos no se puede
 ratificar, y el error aparece en el modal de ratificación, no antes.*/
        $stmt = $this->pdo->prepare(
            'SELECT g.nombre
               FROM grado g
              WHERE g.estado = \'ACTIVO\'
                AND NOT EXISTS (
                    SELECT 1 FROM estructura_academica ea
                     WHERE ea.id_grado = g.id_grado AND ea.id_anio_escolar = :anio
                )
              ORDER BY CAST(SUBSTRING_INDEX(g.nombre, \' \', 1) AS UNSIGNED), g.nombre'
        );
        $stmt->execute(['anio' => $idAnio]);

        $esperadas = $grados * $turnos;

        return [
            'hay_anio_activo'  => true,
            'anio'             => $nombre,
            'grados'           => $grados,
            'turnos'           => $turnos,
            'esperadas'        => $esperadas,
            'registradas'      => $registradas,
            'pendientes'       => max(0, $esperadas - $registradas),
            'grados_sin_seccion' => $stmt->fetchAll(\PDO::FETCH_COLUMN),
        ];
    }

    public function getActiveSchoolYearName(): ?string
    {
        $stmt = $this->pdo->query(
            "SELECT nombre_periodo
               FROM anio_escolar
              WHERE estado = 'ACTIVO'
              ORDER BY anio_inicio DESC
              LIMIT 1"
        );
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    public function esAnioActivo(int $idAnioEscolar): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM anio_escolar
              WHERE id_anio_escolar = :id AND estado = 'ACTIVO'
              LIMIT 1"
        );
        $stmt->execute(['id' => $idAnioEscolar]);

        return $stmt->fetchColumn() !== false;
    }

    /**
 * Alta de una combinación académica (contrato Creatable).
 * El duplicado (UNIQUE año, grado, sección, turno) se valida en el controlador antes de llegar aquí.
*/
    public function create(array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->validarAnioActivo((int) $data['id_anio_escolar']);
            $ok = $this->pdo->prepare(
                'INSERT INTO estructura_academica (id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima)
                 VALUES (:anio, :grado, :seccion, :turno, :capacidad)'
            )->execute([
                'anio'      => (int) $data['id_anio_escolar'],
                'grado'     => (int) $data['id_grado'],
                'seccion'   => (int) $data['id_seccion'],
                'turno'     => (int) $data['id_turno'],
                'capacidad' => (int) $data['capacidad_maxima'],
            ]);
            $this->pdo->commit();

            return $ok;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Indica si ya existe esa combinación (año + grado + sección + turno).
 * Con $exceptoId se deja fuera la fila que se está editando.
*/
    public function existeCombination(array $data, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM estructura_academica
                WHERE id_anio_escolar = :anio AND id_grado = :grado
                  AND id_seccion = :seccion AND id_turno = :turno';
        $params = [
            'anio'    => (int) $data['id_anio_escolar'],
            'grado'   => (int) $data['id_grado'],
            'seccion' => (int) $data['id_seccion'],
            'turno'   => (int) $data['id_turno'],
        ];
        if ($exceptoId !== null) {
            $sql .= ' AND id_estructura_academica != :id';
            $params['id'] = $exceptoId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /* Cuántos estudiantes ocupan una fila de estructura concreta, por su id. Es el gemelo de
 ocupadosDeCombinacion() pero por fila y no por combinación, y esa es la diferencia que
 importa al editar: la combinación del formulario puede no ser la que se está tocando.*/
    public function ocupadosDeEstructura(int $idEstructura): int
    {
        if ($idEstructura <= 0) {
            return 0;
        }

        return (int) ($this->conteoDeOcupados($idEstructura)[$idEstructura] ?? 0);
    }

    /**
 * Última fila de la estructura con su año, grado, sección, turno y capacidad.
 *
 * Se usa para comparar lo que hay en la BD contra lo que viene del formulario y
 * poder decir si la edición mueve la combinación o solo cambia la capacidad.
 *
 * @return array{id_anio_escolar:int,id_grado:int,id_seccion:int,id_turno:int,capacidad_maxima:int}|null
*/
    public function estructuraActual(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_anio_escolar, id_grado, id_seccion, id_turno, capacidad_maxima
               FROM estructura_academica
              WHERE id_estructura_academica = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $fila ? [
            'id_anio_escolar'  => (int) $fila['id_anio_escolar'],
            'id_grado'         => (int) $fila['id_grado'],
            'id_seccion'       => (int) $fila['id_seccion'],
            'id_turno'         => (int) $fila['id_turno'],
            'capacidad_maxima' => (int) $fila['capacidad_maxima'],
        ] : null;
    }

    /**
 * ¿La edición cambia la combinación (grado, sección o turno) o solo la capacidad?
 *
 * El año se excluye a propósito: mover una fila de un período a otro no es un
 * cambio de combinación que arrastre alumnos, porque sin Combinar no se puede
 * pasar a un año que ya tenga esa combinación y el modelo impide el duplicado.
*/
    public function cambiaCombinacion(array $actual, array $nuevo): bool
    {
        return (int) $actual['id_grado'] !== (int) $nuevo['id_grado']
            || (int) $actual['id_seccion'] !== (int) $nuevo['id_seccion']
            || (int) $actual['id_turno'] !== (int) $nuevo['id_turno'];
    }

    /* Edición de la combinación académica (contrato Updatable). Recibe el id y los mismos campos
 que create(). La ocupación se comprueba SOBRE LA FILA QUE SE ESTÁ EDITANDO, no sobre la
 combinación que llega en el formulario, que pueden no ser la misma.*/
    public function update(int $id, array $data): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ea.id_estructura_academica, ea.id_grado, ea.id_seccion, ea.id_turno
               FROM estructura_academica ea
               INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
              WHERE ea.id_estructura_academica = :id AND a.estado = 'ACTIVO'
              FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($actual === false) {
                throw new \RuntimeException('No se pueden modificar estructuras de años escolares cerrados o inexistentes.');
            }

            $this->validarAnioActivo((int) $data['id_anio_escolar']);

            $ocupados = $this->ocupadosDeEstructura($id);
            if ($ocupados > 0 && (int) $data['capacidad_maxima'] < $ocupados) {
                throw new CapacidadInsuficienteException(
                    'La sección tiene ' . $ocupados . ' estudiante(s) matriculado(s): '
                    . 'la capacidad no puede quedar por debajo de esa cantidad. '
                    . 'Reduce el número de alumnos o aumenta la capacidad.',
                    $ocupados
                );
            }

            $ok = $this->pdo->prepare(
                'UPDATE estructura_academica
                 SET id_anio_escolar = :anio, id_grado = :grado, id_seccion = :seccion,
                     id_turno = :turno, capacidad_maxima = :capacidad
                 WHERE id_estructura_academica = :id'
            )->execute([
                'anio'      => (int) $data['id_anio_escolar'],
                'grado'     => (int) $data['id_grado'],
                'seccion'   => (int) $data['id_seccion'],
                'turno'     => (int) $data['id_turno'],
                'capacidad' => (int) $data['capacidad_maxima'],
                'id'        => $id,
            ]);
            $this->pdo->commit();

            return $ok;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
 * Cuántos estudiantes ocupan HOY la combinación año + grado + sección + turno.
 *
 * Cuenta los que cursan de verdad (INSCRIPCION y RATIFICACION, y solo su última fila),
 * que es la misma regla de Matriculas::condicionCursando que usa el conteo de cupos: si
 * esta cuenta y la del cupo no hicieran lo mismo, la validación de capacidad y el
 * bloqueo por sección llena se contradirían entre sí.
*/
    public function ocupadosDeCombinacion(int $idAnioEscolar, int $idGrado, int $idSeccion, int $idTurno): int
    {
        if ($idAnioEscolar <= 0 || $idGrado <= 0 || $idSeccion <= 0 || $idTurno <= 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ea.id_estructura_academica
               FROM estructura_academica ea
              WHERE ea.id_anio_escolar = :anio AND ea.id_grado = :grado
                AND ea.id_seccion = :seccion AND ea.id_turno = :turno
              LIMIT 1'
        );
        $stmt->execute([
            'anio'    => $idAnioEscolar,
            'grado'   => $idGrado,
            'seccion' => $idSeccion,
            'turno'   => $idTurno,
        ]);
        $idEstructura = $stmt->fetchColumn();
        if ($idEstructura === false) {
            return 0;
        }

        return (int) ($this->conteoDeOcupados()[(int) $idEstructura] ?? 0);
    }

    private function validarAnioActivo(int $idAnioEscolar): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_anio_escolar FROM anio_escolar
              WHERE id_anio_escolar = :id AND estado = 'ACTIVO'
              FOR UPDATE"
        );
        $stmt->execute(['id' => $idAnioEscolar]);
        if ($stmt->fetchColumn() === false) {
            throw new \RuntimeException('Las nuevas estructuras académicas deben pertenecer al año escolar activo.');
        }
    }

    // --- MÉTODOS MIGRADOS DE CupoEstructuraTrait ---

    public function estructurasConCupo(?int $idAnioEscolar = null): array
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
 * Alumnos por estructura, o solo los de UNA estructura.
 *
 * Con $soloEstructura se acota el GROUP BY a esa fila. Es lo que hace el camino
 * caliente de la inscripción y de la ratificación, que preguntan el cupo de una
 * sección concreta una y otra vez: sin el filtro, cada consulta arrastraba todas
 * las matrículas de todos los años y de todas las secciones para descartar casi
 * todas después. Con el filtro, MySQL usa idx_matricula_estructura y lee solo lo
 * de esa sección.
 *
 * El NOT EXISTS de condicionCursando() (descartar al alumno que tiene una fila
 * posterior, como un retiro) se mantiene igual en los dos casos: el número tiene
 * que salir igual se pida una fila o el mapa entero, o la validación de capacidad
 * y el bloqueo por sección llena se contradirían entre sí.
 *
 * @param int|null $soloEstructura Limita el conteo a esa estructura.
 * @return array<int, int> estructura => ocupados
*/
    public function conteoDeOcupados(?int $soloEstructura = null): array
    {
        $filtro = '';
        $params = [];

        if ($soloEstructura !== null && $soloEstructura > 0) {
            $filtro = ' AND m.id_estructura_academica = :estructura';
            $params['estructura'] = $soloEstructura;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_estructura_academica AS combo, COUNT(DISTINCT id_estudiante) AS ocupados
               FROM matricula m
              WHERE ' . Matriculas::condicionCursando('m') . '
                AND m.id_estructura_academica IS NOT NULL'
            . $filtro . '
              GROUP BY m.id_estructura_academica'
        );
        $stmt->execute($params);

        $mapa = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $combo => $ocupados) {
            $mapa[(int) $combo] = (int) $ocupados;
        }

        return $mapa;
    }

    public function combinacionConCupo(int $idGrado, int $idSeccion, int $idTurno, int $idAnioEscolar): ?array
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
 * La disponibilidad de UNA estructura: capacidad, ocupados, libres y si está llena.
 *
 * Devuelve la misma forma que una fila de estructurasConCupo() porque reservarCupo()
 * la consume igual, pero ya no la busca: antes cargaba todas las estructuras de todos
 * los años (y con ellas el conteo de matrículas completo) para devolver una sola y
 * descartarla en un bucle de PHP. Ahora son dos consultas con índice: la fila por su
 * PRIMARY KEY y el conteo acotado por idx_matricula_estructura.
 *
 * @return array<string, mixed>|null null si la estructura no existe.
*/
    public function disponibilidadDe(int $idEstructura): ?array
    {
        if ($idEstructura <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ea.id_estructura_academica,
                    ea.id_grado, ea.id_seccion, ea.id_turno, ea.id_anio_escolar,
                    ea.capacidad_maxima,
                    g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno
               FROM estructura_academica ea
               LEFT JOIN grado g   ON g.id_grado   = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t   ON t.id_turno   = ea.id_turno
              WHERE ea.id_estructura_academica = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idEstructura]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fila) {
            return null;
        }

        $capacidad = (int) $fila['capacidad_maxima'];
        /* Un solo valor: el conteo acotado a esta estructura. Antes venía del mapa
 completo que construye estructurasConCupo().*/
        $ocupados = (int) ($this->conteoDeOcupados($idEstructura)[$idEstructura] ?? 0);
        $libres   = $capacidad - $ocupados;

        return [
            'id_estructura_academica' => (int) $fila['id_estructura_academica'],
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

    public function opcionesAcademicas(int $idAnioEscolar): array
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

    public function reservarCupo(int $idEstructura): array
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

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        foreach (['anio' => 'ea.id_anio_escolar', 'grado' => 'ea.id_grado', 'seccion' => 'ea.id_seccion', 'turno' => 'ea.id_turno'] as $key => $column) {
            if (($filters[$key] ?? '') !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = (int) $filters[$key];
            }
        }
        if (!empty($filters['q'])) {
            foreach (['g.nombre', 's.nombre', 't.nombre'] as $i => $col) {
                $params["q$i"] = $filters['q'] . '%';
            }
            $where[] = '(g.nombre LIKE :q0 OR s.nombre LIKE :q1 OR t.nombre LIKE :q2)';
        }
        return [$where, $params];
    }
}
