<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Interfaces\Creatable;
use App\Support\Matriculas;
use App\Interfaces\Listable;
use Throwable;

/**
 * WithdrawalModel
 * ---------------------------------------------------------------
 * Solicitudes de retiro (retiro): estado_solicitud
 * ENUM('PENDIENTE','APROBADO','RECHAZADO') y motivos de retiro.
 * La fecha de solicitud se toma de matricula.fecha_matricula.
 *
 * Listable: la secretaria y el directivo consultan el listado.
 * Creatable: la secretaria registra los retiros (CrudController::store()).
 * Resolverlos (aprobar/rechazar) es updateStatus(), del directivo.
 */
class WithdrawalModel extends BaseModel implements Listable, Creatable
{
    protected string $table = 'retiro';
    protected string $primaryKey = 'id_retiro';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT r.id_retiro AS id, r.estado_solicitud AS estado,
                       r.observaciones AS observaciones, m.fecha_matricula AS fecha_solicitud,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS estudiante,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       g.nombre AS grado, s.nombre AS seccion, mr.nombre AS motivo,
                       p.foto AS avatar_img,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), LEFT(p.primer_apellido, 1))) AS iniciales
                FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.id_anio_escolar DESC, m.fecha_matricula DESC, r.id_retiro DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query('SELECT estado_solicitud, COUNT(*) AS total FROM retiro GROUP BY estado_solicitud');
        $stats = ['PENDIENTE' => 0, 'APROBADO' => 0, 'RECHAZADO' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['estado_solicitud'], $stats)) {
                $stats[$row['estado_solicitud']] = (int) $row['total'];
            }
        }
        return ['pendientes' => $stats['PENDIENTE'], 'aprobados' => $stats['APROBADO'], 'rechazados' => $stats['RECHAZADO']];
    }

    /**
     * Aprueba o rechaza una solicitud.
     *
     * Ojo con rowCount(): execute() devuelve true aunque no se haya
     * afectado ninguna fila, así que un id inexistente se reportaría como
     * éxito si solo se mirara el execute().
     */
    public function updateStatus(int $id, string $status): bool
    {
        $this->pdo->beginTransaction();
        try {
            $ok = $this->pdo->prepare('UPDATE retiro SET estado_solicitud = :estado WHERE id_retiro = :id')
                      ->execute(['estado' => $status, 'id' => $id])
                && $this->pdo->query('SELECT ROW_COUNT()')->fetchColumn() > 0;

            if ($ok && $status === 'APROBADO') {
                /* El retiro aprobado crea una fila de tipo RETIRO, al lado de la
                   del año en que el alumno estaba. No le cambia el tipo a esa fila.

                   La fila del año dice "cursó este grado"; la de RETIRO dice "se
                   fue antes de terminar". Si se tocara la de arriba, el historial
                   perdería el hecho de que estuvo en ese grado, y un alumno que
                   se va en tercero dejaría de tener registro de que entró a
                   tercero. Es el mismo motivo por el que el egreso es fila propia.

                   El motivo del retiro sigue en la fila de `retiro`, que ahora
                   apunta a la de RETIRO: el tipo dice QUÉ y la tabla de al lado
                   dice POR QUÉ. */
                $origen = $this->pdo->query(
                    'SELECT m.id_estudiante, m.id_anio_escolar, m.id_estructura_academica, m.tipo
                       FROM retiro r
                       INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                      WHERE r.id_retiro = ' . (int) $id
                )->fetch(\PDO::FETCH_ASSOC);

                if ($origen) {
                    $motivo = Matriculas::motivoBloqueo($origen['tipo'], Matriculas::RETIRO);
                    if ($motivo !== '') {
                        throw new \RuntimeException($motivo);
                    }

                    $this->pdo->prepare(
                        'INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo)
                         VALUES (:estudiante, CURDATE(), :anio, :estructura, :tipo)'
                    )->execute([
                        'estudiante' => (int) $origen['id_estudiante'],
                        'anio'       => (int) $origen['id_anio_escolar'],
                        'estructura' => (int) $origen['id_estructura_academica'],
                        'tipo'       => Matriculas::RETIRO,
                    ]);
                    $nueva = (int) $this->pdo->lastInsertId();
                    $this->pdo->prepare('UPDATE retiro SET id_matricula = :nueva WHERE id_retiro = :id')
                              ->execute(['nueva' => $nueva, 'id' => $id]);
                }
            }

            $this->pdo->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('WithdrawalModel::updateStatus(): ' . $e->getMessage());
            return false;
        }
    }

    public function getYears(): array
    {
        return $this->pdo->query('SELECT id_anio_escolar AS id, nombre_periodo AS nombre FROM anio_escolar ORDER BY anio_inicio DESC')
                        ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Estudiantes que todavía no tienen retiro registrado, para el formulario
     * de la secretaria.
     *
     * Se toma su matrícula más reciente (misma regla que EstudianteModel) y
     * se excluyen los que ya tienen fila en retiro: la tabla tiene
     * UNIQUE(id_matricula), así que un segundo retiro para la misma matrícula
     * no podría insertarse.
     *
     * @return array<int, array{id_matricula:int,nombre:string,cedula:string,grado:string,seccion:string}>
     */
    public function getEstudiantesParaRetiro(): array
    {
        /* La matrícula vigente de cada alumno es su ÚLTIMA matrícula, y "última" se
           decide por la FECHA del año, nunca por el id del año.

           Los ids de `anio_escolar` son de inserción: un año cargado antes puede
           tener un id mayor que uno cargado después, así que comparar años por id
           invierte el orden sin que nada parezca roto. Con 175 = 2025-2026 y
           238 = 2026-2027 los ids y las fechas coinciden y el error no aparece;
           aparece en cuanto se carga un año viejo, que es justo lo que se hace al
           abrir un período nuevo.

           Por eso el subquery ordena por `a2.anio_inicio` y no por el id, y el
           filtro del año en curso compara contra su fecha. */
        $inicioEnCurso = "'" . AniosEscolares::inicioEnCurso($this->pdo) . "'";

        $sql = "SELECT m.id_matricula AS id_matricula,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, '—') AS seccion
                FROM matricula m
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                  LEFT JOIN retiro r ON r.id_retiro IS NULL AND r.id_matricula = m.id_matricula
                  LEFT JOIN (
                      /* La última matrícula de cada alumno hasta el año en curso, por fecha. */
                      SELECT m2.id_estudiante, MAX(a2.anio_inicio) AS inicio_actual
                        FROM matricula m2
                        JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                       WHERE a2.anio_inicio <= $inicioEnCurso
                       GROUP BY m2.id_estudiante
                  ) ult ON ult.id_estudiante = m.id_estudiante
                  LEFT JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                  LEFT JOIN grado g ON g.id_grado = ea.id_grado
                  LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                  WHERE a.anio_inicio = ult.inicio_actual
                    AND m.tipo IN ('INSCRIPCION', 'RATIFICACION')
                    AND r.id_retiro IS NULL
                ORDER BY p.primer_nombre, p.primer_apellido";

        $filas = $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $fila): array => [
            'id_matricula' => (int) $fila['id_matricula'],
            'nombre'       => $fila['nombre'] ?? 'Estudiante',
            'cedula'       => $fila['cedula'] ?? '—',
            'grado'        => $fila['grado'] ?? '—',
            'seccion'      => $fila['seccion'] ?? '—',
        ], $filas);
    }

    /**
     * Registra el retiro de un estudiante (contrato Creatable).
     *
     * La fila nace siempre en estado PENDIENTE: aprobar o rechazar es una
     * decisión posterior del directivo (updateStatus). Las validaciones de
     * negocio (motivo existente, retiro duplicado) las hace el controlador
     * en validate() antes de llegar acá.
     *
     * @param array $data ['id_matricula' => int, 'id_motivo_retiro' => int,
     *                     'observaciones' => string]
     */
    public function create(array $data): bool
    {
        $observaciones = trim((string) ($data['observaciones'] ?? ''));

        return $this->pdo->prepare(
            "INSERT INTO retiro (id_matricula, id_motivo_retiro, estado_solicitud, observaciones)
             VALUES (:matricula, :motivo, 'PENDIENTE', :observaciones)"
        )->execute([
            'matricula'     => (int) $data['id_matricula'],
            'motivo'        => (int) $data['id_motivo_retiro'],
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);
    }

    /**
     * ¿La matrícula ya tiene un retiro registrado? La tabla tiene
     * UNIQUE(id_matricula), así que el segundo intento siempre fallaría.
     */
    public function existeRetiro(int $idMatricula): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM retiro WHERE id_matricula = :id');
        $stmt->execute(['id' => $idMatricula]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** ¿El motivo existe y está activo? */
    public function existeMotivo(int $idMotivo): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM motivo_retiro WHERE id_motivo_retiro = :id AND estado = 'ACTIVO'");
        $stmt->execute(['id' => $idMotivo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getReasons(): array
    {
        return $this->pdo->query("SELECT id_motivo_retiro AS id, nombre FROM motivo_retiro WHERE estado = 'ACTIVO' ORDER BY nombre")
                        ->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function filters(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['estado'])) {
            $where[] = 'r.estado_solicitud = :estado';
            $params['estado'] = strtoupper($filters['estado']);
        }
        if (!empty($filters['anio'])) {
            $where[] = 'ea.id_anio_escolar = :anio';
            $params['anio'] = (int) $filters['anio'];
        }
        if (!empty($filters['motivo'])) {
            $where[] = 'r.id_motivo_retiro = :motivo';
            $params['motivo'] = (int) $filters['motivo'];
        }
        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento', 'mr.nombre'] as $i => $col) {
                $params["q$i"] = $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2 OR mr.nombre LIKE :q3)';
        }
        return [$where, $params];
    }

    private function mapRow(array $row): array
    {
        $nombre = trim($row['estudiante'] ?? 'Estudiante');
        return [
            'id'              => (int) $row['id'],
            'estudiante'      => $nombre,
            'cedula'          => $row['cedula'] ?? '—',
            'iniciales'       => $row['iniciales'] ?: mb_strtoupper(mb_substr($nombre, 0, 1)),
            'avatar_color'    => 'primary',
            'avatar_img'      => $row['avatar_img'] ?? '',
            'grado'           => $row['grado'] ?? '—',
            'seccion'         => $row['seccion'] ?? '—',
            'fecha_solicitud' => $row['fecha_solicitud'] ? date('d/m/Y', strtotime($row['fecha_solicitud'])) : '—',
            'motivo'          => $row['motivo'] ?? '—',
            'observaciones'   => $row['observaciones'] ?? '',
            'estado'          => strtolower($row['estado'] ?? 'pendiente'),
        ];
    }
}