<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Support\Documento;
use App\Interfaces\Creatable;
use App\Support\Matriculas;
use App\Traits\RetiradoTrait;
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
    /* Estado "retirado" derivado y compartido con InscripcionesModel, para que los dos
 módulos consulten el mismo registro y no tengan cada uno su idea de si un alumno
 está retirado. Ver RetiradoTrait.*/
    use RetiradoTrait;
    protected string $table = 'retiro';
    protected string $primaryKey = 'id_retiro';

    /**
 * Por qué falló la última actualización, ya traducida a un mensaje para la secretaria.
 * Vive en el modelo porque el throw original es una excepción de SQL y el texto que
 * sale de ahí es incomprensible para quien opera la pantalla.
*/
    private string $ultimoError = '';

public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        /* La fecha que se muestra es la de la SOLICITUD, no la de la matrícula: antes se
 leía m.fecha_matricula, así que una solicitud registrada hoy aparecía con la
 fecha en que el alumno se matriculó, que es un dato distinto y más antiguo.
 Los registros anteriores a la migración no tienen fecha y salen como '—', que es
 lo honesto: no se inventa una fecha que nadie registró.*/
        $sql = "SELECT r.id_retiro AS id, r.estado_solicitud AS estado,
                       r.observaciones AS observaciones, r.fecha_solicitud AS fecha_solicitud,
                       CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS estudiante,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       g.nombre AS grado, s.nombre AS seccion, mr.nombre AS motivo,
                       p.foto AS avatar_img,
                       /* NULLIF alrededor del CONCAT_WS: si el usuario no existe, los dos
 nombres vienen NULL y CONCAT_WS devuelve '' (cadena vacía), NO NULL.
 Por eso el COALESCE de fuera no se activaba nunca y `registrado_por`
 llegaba vacío al mapRow, que solo reemplaza los NULL por
 'Sin registro': las solicitudes sin autor salían con un hueco en
 vez de decir que no hay registro. NULLIF convierte el '' en NULL y el
 COALESCE vuelve a hacer su trabajo.*/
                       COALESCE(NULLIF(CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido), ''), 'Sin registro') AS registrado_por,
                       COALESCE(NULLIF(CONCAT_WS(' ', ru.primer_nombre, ru.primer_apellido), ''), '') AS resuelto_por,
                       r.fecha_resolucion AS fecha_resolucion,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), LEFT(p.primer_apellido, 1))) AS iniciales
                 FROM retiro r
                 INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                 INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                 INNER JOIN persona p ON p.id_persona = e.id_persona
                   LEFT JOIN usuario u_reg ON u_reg.id_usuario = r.id_usuario_registro
                   LEFT JOIN persona pu ON pu.id_persona = u_reg.id_persona
                   LEFT JOIN usuario u_res ON u_res.id_usuario = r.id_usuario_resolucion
                   LEFT JOIN persona ru ON ru.id_persona = u_res.id_persona
                   LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                   LEFT JOIN grado g ON g.id_grado = ea.id_grado
                   LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                   INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY COALESCE(r.fecha_solicitud, m.fecha_matricula) DESC, r.id_retiro DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRow'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
 * Una solicitud de retiro por su id, para el modal de detalle.
 *
 * Devuelve exactamente los mismos campos que getAll(), porque pasa por el mismo
 * mapRow: así el modal y la fila del listado no pueden mostrar dos versiones de
 * un mismo dato (por ejemplo, una fecha de solicitud en un formato y otra en el
 * otro lado).
 *
 * Existe porque el botón de la fila ya no lleva los datos en atributos data-:
 * duplicar el modelo en el markup obliga a mantenerlo en dos lugares y se
 * desincroniza solo. Con el id alcanza y el detalle se pide al servidor, que es
 * quien tiene la fuente de verdad.
 *
 * @return array<string, mixed>|null Null si no existe (el controller responde 404).
*/
    public function getDetail(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT r.id_retiro AS id, r.estado_solicitud AS estado,
                    r.observaciones AS observaciones, r.fecha_solicitud AS fecha_solicitud,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS estudiante,
                    CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                    g.nombre AS grado, s.nombre AS seccion, mr.nombre AS motivo,
                    p.foto AS avatar_img,
                    /* Ver getAll(): el NULLIF es necesario porque CONCAT_WS devuelve ''
 cuando los nombres son NULL, y entonces el COALESCE no se activaba.*/
                    COALESCE(NULLIF(CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido), ''), 'Sin registro') AS registrado_por,
                    COALESCE(NULLIF(CONCAT_WS(' ', ru.primer_nombre, ru.primer_apellido), ''), '') AS resuelto_por,
                    r.fecha_resolucion AS fecha_resolucion,
                    UPPER(CONCAT(LEFT(p.primer_nombre, 1), LEFT(p.primer_apellido, 1))) AS iniciales
               FROM retiro r
               INNER JOIN matricula m ON m.id_matricula = r.id_matricula
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
                 LEFT JOIN usuario u_reg ON u_reg.id_usuario = r.id_usuario_registro
                 LEFT JOIN persona pu ON pu.id_persona = u_reg.id_persona
                 LEFT JOIN usuario u_res ON u_res.id_usuario = r.id_usuario_resolucion
                 LEFT JOIN persona ru ON ru.id_persona = u_res.id_persona
                 LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                 LEFT JOIN grado g ON g.id_grado = ea.id_grado
                 LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro
              WHERE r.id_retiro = :id
              LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->mapRow($row) : null;
    }

    /**
 * Alumnos que YA están retirados: los que tienen fila `matricula.tipo = 'RETIRO'`.
 *
 * Es la lista de consulta del módulo de Retiro. NO es la lista de solicitudes
 * (esa es getAll(), y son cosas distintas: aquí ya se retiró, en la otra se pide
 * retirarlo). No tiene acciones y no se puede modificar desde aquí: el retiro no
 * se deshace, y por eso no hay botón de reactivar.
 *
 * Se apoya en la fila RETIRO, no en la de `inscripcion` ni en el `retiro` de la
 * solicitud: la fila de la inscripción sigue apuntando a su propia matrícula, que
 * es la que conserva el histórico. Ver RetiradoTrait.
 *
 * @param string|null $q Búsqueda por nombre o cédula.
 * @param int|null $idTurno Turno a acotar. `null` = sin filtro, que es lo que
 * recibe la directiva: el retiro es un hecho del alumno, no una tarea de un
 * turno, y la dirección ve el histórico completo. La secretaria recibe su
 * turno (BaseController::turnoSecretariaActual) y solo ve el suyo. El 0 que
 * devuelve esa rutina cuando la secretaria no tiene turno asignado deja la
 * lista vacía, que es lo mismo que hace el listado de solicitudes.
 * @return array<int, array<string, mixed>>
*/
    public function getRetirados(?string $q = null, ?int $idTurno = null, int $limite = 200): array
    {
        $where = ["m.tipo = 'RETIRO'"];
        $params = [];

        if ($idTurno !== null) {
            $where[] = 'ea.id_turno = :id_turno';
            /* La clave debe llamarse IGUAL que el marcador: con prepare emulado en
 no, MySQL lo rechazaba con "parameter was not defined".*/
            $params['id_turno'] = $idTurno;
        }

        if ($q !== null && trim($q) !== '') {
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
            $params['q0'] = '%' . trim($q) . '%';
            $params['q1'] = '%' . trim($q) . '%';
            $params['q2'] = '%' . trim($q) . '%';
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula, m.id_estudiante, m.fecha_matricula, m.motivo_anticipo,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre,
                    CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                    UPPER(CONCAT(LEFT(p.primer_nombre, 1), LEFT(p.primer_apellido, 1))) AS iniciales,
                    p.foto AS avatar_img,
                    COALESCE(g.nombre, 'Sin grado') AS grado,
                    COALESCE(s.nombre, 'Sin sección') AS seccion,
                    COALESCE(t.nombre, 'Sin turno') AS turno,
                    an.nombre_periodo AS anio_escolar,
                    mr.id_retiro, mtr.nombre AS motivo,
                    mr.observaciones, mr.estado_solicitud,
                    mr.fecha_solicitud, mr.fecha_resolucion,
                    COALESCE(CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido), '') AS resuelto_por
               FROM matricula m
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
               INNER JOIN anio_escolar an ON an.id_anio_escolar = m.id_anio_escolar
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               LEFT JOIN grado g ON g.id_grado = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN turno t ON t.id_turno = ea.id_turno
               /* El motivo NO está en `retiro`: la fila guarda id_motivo_retiro y el
 texto vive en el catálogo `motivo_retiro`. LEFT JOIN porque una fila
 RETIRO puede venir de una migración sin fila en `retiro`.*/
               LEFT JOIN retiro mr ON mr.id_matricula = m.id_matricula
               LEFT JOIN motivo_retiro mtr ON mtr.id_motivo_retiro = mr.id_motivo_retiro
               LEFT JOIN usuario u ON u.id_usuario = mr.id_usuario_resolucion
               LEFT JOIN persona pu ON pu.id_persona = u.id_persona
              WHERE " . implode(' AND ', $where) . "
              ORDER BY m.fecha_matricula DESC, p.primer_apellido, p.primer_nombre
              LIMIT :limite"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', max(1, $limite), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM retiro r
                INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                INNER JOIN motivo_retiro mr ON mr.id_motivo_retiro = r.id_motivo_retiro";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(?int $idTurno = null): array
    {
        $sql = 'SELECT r.estado_solicitud, COUNT(*) AS total
                  FROM retiro r
                  INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                  LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica';
        $params = [];
        if ($idTurno !== null) {
            $sql .= ' WHERE ea.id_turno = :turno';
            $params['turno'] = $idTurno;
        }
        $sql .= ' GROUP BY r.estado_solicitud';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
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
 * Además del estado guarda QUIÉN lo resolvió y CUÁNDO: sin eso la aprobación no deja
 * rastro y la bitácora sola no alcanza, porque una solicitud puede estar rechazada y
 * Approved sin que se sepa quién la tocó.
 *
 * Ojo con rowCount(): execute() devuelve true aunque no se haya
 * afectado ninguna fila, así que un id inexistente se reportaría como
 * éxito si solo se mirara el execute().
 *
 * @param int $idUsuario quién resuelve; 0 si no hay sesión (no se audita, pero no falla)
*/
    public function updateStatus(int $id, string $status, int $idUsuario = 0): bool
    {
        /* El año se comprueba antes de abrir la transacción: si el período se cerró después
 del alta, resolver ahora escribiría el cierre sobre un año ya histórico.*/
        $matricula = $this->matriculaDelRetiro($id);
        if ($matricula > 0 && !$this->anioActivoDeMatricula($matricula)) {
            $this->ultimoError = 'El año escolar de esa matrícula está cerrado. '
                . 'Reábralo con un motivo registrado para poder resolver la solicitud.';
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $ok = $this->pdo->prepare(
                'UPDATE retiro
                    SET estado_solicitud = :estado,
                        fecha_resolucion = NOW(),
                        id_usuario_resolucion = :usuario
                  WHERE id_retiro = :id'
            )
                ->execute([
                    'estado'  => $status,
                    'id'      => $id,
                    'usuario' => $idUsuario > 0 ? $idUsuario : null,
                ])
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
 dice POR QUÉ.*/
                $origen = $this->pdo->query(
                    'SELECT m.id_estudiante, m.id_anio_escolar, m.id_estructura_academica, m.tipo
                       FROM retiro r
                       INNER JOIN matricula m ON m.id_matricula = r.id_matricula
                      WHERE r.id_retiro = ' . (int) $id
                )->fetch(\PDO::FETCH_ASSOC);

                if (!$origen) {
                    throw new \RuntimeException('No se encontró la matrícula del retiro; no se puede liberar el cupo.');
                }

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
                if ($nueva <= 0) {
                    throw new \RuntimeException('No se pudo registrar la matrícula de retiro para liberar el cupo.');
                }

                $actualizado = $this->pdo->prepare('UPDATE retiro SET id_matricula = :nueva WHERE id_retiro = :id')
                    ->execute(['nueva' => $nueva, 'id' => $id]);
                if (!$actualizado || $this->pdo->query('SELECT ROW_COUNT()')->fetchColumn() <= 0) {
                    throw new \RuntimeException('No se pudo asociar la matrícula de retiro a la solicitud.');
                }

                $inactivado = $this->pdo->prepare(
                    "UPDATE persona p
                     INNER JOIN estudiante e ON e.id_persona = p.id_persona
                        SET p.estado = 'INACTIVO'
                      WHERE e.id_estudiante = :estudiante"
                )->execute(['estudiante' => (int) $origen['id_estudiante']]);
                if (!$inactivado) {
                    throw new \RuntimeException('No se pudo cambiar a inactivo el estado del estudiante retirado.');
                }

                $estadoEstudiante = $this->pdo->prepare(
                    'SELECT p.estado
                       FROM persona p
                       INNER JOIN estudiante e ON e.id_persona = p.id_persona
                      WHERE e.id_estudiante = :estudiante'
                );
                $estadoEstudiante->execute(['estudiante' => (int) $origen['id_estudiante']]);
                if ($estadoEstudiante->fetchColumn() !== 'INACTIVO') {
                    throw new \RuntimeException('El estado del estudiante retirado no quedó inactivo.');
                }
            }

            /* Se audita al final, ya con la transacción aún abierta: si algo de lo anterior
 falla y se revierte, la bitácora tampoco deja constancia de una aprobación que
 no ocurrió. Va en la misma conexión a propósito.*/
            $this->registrarBitacora(
                $idUsuario,
                ($status === 'APROBADO' ? 'RETIRO APROBADO ' : 'RETIRO RECHAZADO ')
                    . $this->descripcionEstudiante($this->matriculaDelRetiro($id)),
                'Retiros'
            );

            $this->pdo->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('WithdrawalModel::updateStatus(): ' . $e->getMessage());
            $this->ultimoError = $this->mensajeDeFallo($e);
            return false;
        }
    }

    /** Mensaje del último fallo de updateStatus(), o cadena vacía si no hubo ninguno.*/
    public function ultimoError(): string
    {
        return $this->ultimoError;
    }

    /**
 * Traduce el fallo de la base al mensaje que la secretaria puede entender.
 *
 * Lo que falla aquí casi nunca es el UPDATE: es la fila de RETIRO que se inserta después
 * y que el trigger de la base puede rechazar porque el alumno ya cerró su ciclo, o
 * porque el cierre no puede ir en un año anterior. Con "No se pudo actualizar la
 * solicitud" la.directiva se quedaba sin saber qué hacer, y la solicitud quedaba
 * PENDIENTE para siempre.
*/
    private function mensajeDeFallo(\Throwable $e): string
    {
        $sqlstate = (string) $e->getCode();
        $texto    = $e->getMessage();

        if ($sqlstate === '45000' && str_contains($texto, 'ciclo de este estudiante ya se cerro')) {
            return 'No se puede aprobar el retiro: el ciclo de este estudiante ya está cerrado '
                . '(se retiró o egresó). La solicitud queda como estaba.';
        }
        if ($sqlstate === '45000' && str_contains($texto, 'El cierre no puede ir en un anio anterior')) {
            return 'No se puede aprobar el retiro: el cierre no puede registrarse en un año '
                . 'escolar anterior al de la matrícula del estudiante.';
        }
        if ($sqlstate === '23000' || str_contains($texto, 'Duplicate entry')) {
            return 'Ya existe un registro de ese proceso para este estudiante.';
        }

        return 'No se pudo actualizar la solicitud.';
    }

    public function getYears(): array
    {
        return $this->pdo->query('SELECT id_anio_escolar AS id, nombre_periodo AS nombre FROM anio_escolar ORDER BY anio_inicio DESC')
                        ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
 * Busca estudiantes por cédula para el formulario de la secretaria.
 *
 * SOLO por documento, a propósito. Un retiro identifica a una persona, y el
 * documento es lo único que no se repite; buscar por nombre traía a homónimos
 * ("Leo Pinto" y otro "Pinto") y hacía elegir entre alumnos que en realidad
 * no se podían distinguir. Con el documento hay un solo resultado o ninguno.
 *
 * Trae TAMBIÉN a los que no se pueden retirar, con el motivo. Sin eso el
 * formulario responde "no se encontró" igual para un alumno que no existe
 * y para uno que ya egresó, y la secretaria no sabe si escribió mal la
 * cédula o si llegó tarde.
 *
 * La matrícula que se mira es la ÚLTIMA de cada alumno, y "última" se decide
 * por la FECHA del año, nunca por el id del año.
 *
 * Los ids de `anio_escolar` son de inserción: un año cargado antes puede
 * tener un id mayor que uno cargado después, así que comparar años por id
 * invierte el orden sin que nada parezca roto. Con 175 = 2025-2026 y
 * 238 = 2026-2027 los ids y las fechas coinciden y el error no aparece;
 * aparece en cuanto se carga un año viejo, que es justo lo que se hace al
 * abrir un período nuevo. Por eso el subquery ordena por `a2.anio_inicio`.
 *
 * @return array<int, array{id_matricula:?int,nombre:string,cedula:string,grado:string,
 * seccion:string,anio:string,elegible:bool,motivo:string}>
*/
    public function buscarPorDocumento(string $documento, int $limite = 10, ?int $idTurno = null): array
    {
        $digitos = Documento::normalizar($documento);

        // Sin dígitos no hay documento: un nombre, una letra suelta. No se
        // consulta nada, y el controlador avisa que falta el número.
        if ($digitos === '') {
            return [];
        }

        /* La comparación va sobre los dígitos de la columna y no sobre la columna
 tal cual: `numero_documento` guarda el prefijo DENTRO del valor en las
 cédulas escolares ("V1162586313"), mientras que en las de identidad
 guarda solo el número ("25863136"). Buscar "1162" contra el valor crudo
 no encontraría al alumno que lleva la cédula escolar. Se normaliza el
 lado de la columna para no depender de cómo quedó guardado cada
 documento, y así el prefijo es opcional al escribir.*/
        $inicioEnCurso = AniosEscolares::inicioEnCurso($this->pdo);

        $sql = "        SELECT m.id_matricula AS id_matricula,
               CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
               CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
               COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, '—') AS seccion,
                       COALESCE(a.nombre_periodo, '—') AS anio,
                       m.tipo AS tipo,
                       r.id_retiro AS id_retiro
                FROM estudiante e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN matricula m ON m.id_matricula = (
                      /* La última matrícula del alumno hasta el año en curso, por fecha.*/
                      SELECT m2.id_matricula
                        FROM matricula m2
                        JOIN anio_escolar a2 ON a2.id_anio_escolar = m2.id_anio_escolar
                       WHERE m2.id_estudiante = e.id_estudiante
                         AND a2.anio_inicio <= :inicio
                       ORDER BY a2.anio_inicio DESC, m2.id_matricula DESC
                       LIMIT 1
                )
                LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
                LEFT JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
                LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
                LEFT JOIN grado g ON g.id_grado = ea.id_grado
                LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
                WHERE REGEXP_REPLACE(p.numero_documento, '[^0-9]', '') LIKE :termino";
        if ($idTurno !== null) {
            $sql .= ' AND ea.id_turno = :turno';
        }
        $sql .= "
                ORDER BY p.primer_nombre, p.primer_apellido
                LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':inicio', $inicioEnCurso);
        $stmt->bindValue(':termino', $digitos . '%');
        if ($idTurno !== null) {
            $stmt->bindValue(':turno', $idTurno, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapBusqueda'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
 * Traduce una fila de buscarPorDocumento() y calcula si se puede registrar.
 *
 * La regla de "se puede" es la misma de siempre: una fila de matricula de
 * tipo INSCRIPCION o RATIFICACION que todavía no tenga retiro. El texto del
 * bloqueo sale de Matriculas para que el motivo se lea igual aquí que en el
 * resto del sistema.
*/
    private function mapBusqueda(array $fila): array
    {
        $tipo = $fila['tipo'] ?? null;

        if ($fila['id_matricula'] === null) {
            $motivo = 'Este estudiante no tiene matrícula registrada en el sistema.';
        } elseif ($fila['id_retiro'] !== null) {
            $motivo = 'Este estudiante ya tiene una solicitud de retiro registrada.';
        } else {
            $motivo = Matriculas::motivoBloqueo($tipo, Matriculas::RETIRO);
        }

        return [
            'id_matricula' => $fila['id_matricula'] !== null ? (int) $fila['id_matricula'] : null,
            'nombre'       => trim($fila['nombre'] ?? '') ?: 'Estudiante',
            'cedula'       => $fila['cedula'] ?? '—',
            'grado'        => $fila['grado'] ?? '—',
            'seccion'      => $fila['seccion'] ?? '—',
            'anio'         => $fila['anio'] ?? '—',
            'elegible'     => $motivo === '',
            'motivo'       => $motivo,
        ];
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
 * 'observaciones' => string, 'id_usuario' => ?int]
*/
    public function create(array $data): bool
    {
        $observaciones = trim((string) ($data['observaciones'] ?? ''));
        $idUsuario     = isset($data['id_usuario']) ? (int) $data['id_usuario'] : 0;

        $ok = $this->pdo->prepare(
            "INSERT INTO retiro (id_matricula, id_motivo_retiro, estado_solicitud, observaciones,
                                fecha_solicitud, id_usuario_registro)
             VALUES (:matricula, :motivo, 'PENDIENTE', :observaciones, NOW(), :usuario)"
        )->execute([
            'matricula'     => (int) $data['id_matricula'],
            'motivo'        => (int) $data['id_motivo_retiro'],
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'usuario'       => $idUsuario > 0 ? $idUsuario : null,
        ]);

        if ($ok) {
            $this->registrarBitacora(
                $idUsuario,
                'SOLICITUD DE RETIRO ' . $this->descripcionEstudiante((int) $data['id_matricula']),
                'Retiros'
            );
        }

        return $ok;
    }

    /**
 * Por qué esa matrícula no admite un retiro nuevo, o cadena vacía si sí lo admite.
 *
 * Vive en el modelo y no en el buscador para que la regla se aplique igual por la
 * pantalla y por un POST directo: antes `elegible=false` era solo una etiqueta del
 * formulario, y una petición hecha a mano se saltaba la regla entera.
*/
    public function bloqueoParaRetiro(int $idMatricula): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.tipo, r.id_retiro, r.estado_solicitud, a.estado AS estado_anio, a.nombre_periodo
               FROM matricula m
               INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
               LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
              WHERE m.id_matricula = :matricula
              LIMIT 1'
        );
        $stmt->execute(['matricula' => $idMatricula]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fila) {
            return 'La matrícula indicada no existe.';
        }

        /* Un año cerrado es histórico: un retiro que se registra ahora sobre una matrícula de
 un período ya cerrado reescribe el pasado del alumno, y la validación de elegibilidad
 no lo impedía porque solo miraba el tipo de matrícula. Se comprueba antes que la
 elegibilidad porque "el año está cerrado" explica mejor por qué no se puede que un
 "ya se retiró".*/
        if ((string) $fila['estado_anio'] !== 'ACTIVO') {
            return 'El año escolar ' . $fila['nombre_periodo'] . ' está cerrado. '
                . 'Los retiros solo se registran en el período activo; para uno de un período '
                . 'cerrado hay que reabrir el año con un motivo registrado.';
        }

        if ($fila['id_retiro'] !== null) {
            return $fila['estado_solicitud'] === 'RECHAZADO'
                ? 'Ese retiro ya fue rechazado. Para volver a retirar al estudiante debe '
                  . 'ratificar a un nuevo período; en su matrícula actual sí puede registrarse uno.'
                : 'Ese estudiante ya tiene una solicitud de retiro registrada o en proceso.';
        }

        return Matriculas::motivoBloqueo($fila['tipo'], Matriculas::RETIRO);
    }

    /**
 * ¿Esa matrícula pertenece a un año escolar activo? La usa updateStatus() para no
 * resolver un retiro cuyo año se cerró entre el alta y la aprobación.
*/
    private function anioActivoDeMatricula(int $idMatricula): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.estado
               FROM matricula m
               INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
              WHERE m.id_matricula = :matricula
              LIMIT 1'
        );
        $stmt->execute(['matricula' => $idMatricula]);
        $estado = $stmt->fetchColumn();

        return $estado === 'ACTIVO';
    }

    /** Matrícula de una solicitud de retiro; 0 si la solicitud no existe.*/
    private function matriculaDelRetiro(int $idRetiro): int
    {
        $stmt = $this->pdo->prepare('SELECT id_matricula FROM retiro WHERE id_retiro = :id');
        $stmt->execute(['id' => $idRetiro]);
        $id = $stmt->fetchColumn();
        return $id === false ? 0 : (int) $id;
    }

    /** Cédula del alumno de una matrícula, para que la bitácora se lea sin consultar.*/
    private function descripcionEstudiante(int $idMatricula): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.numero_documento, p.primer_nombre, p.primer_apellido
               FROM matricula m
               INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE m.id_matricula = :matricula
              LIMIT 1'
        );
        $stmt->execute(['matricula' => $idMatricula]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return "matrícula {$idMatricula}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }

    private function registrarBitacora(int $idUsuario, string $accion, string $modulo): void
    {
        if ($idUsuario <= 0) {
            return;
        }
        (new BitacoraModel($this->pdo))->registrar($idUsuario, $accion, $modulo);
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

    public function matriculaPerteneceTurno(int $idMatricula, int $idTurno): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM matricula m
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
              WHERE m.id_matricula = :matricula AND ea.id_turno = :turno'
        );
        $stmt->execute(['matricula' => $idMatricula, 'turno' => $idTurno]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** ¿El motivo existe y está activo?*/
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
        if (array_key_exists('id_turno', $filters)) {
            $where[] = 'ea.id_turno = :id_turno';
            $params['id_turno'] = (int) $filters['id_turno'];
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
            // '—' cuando la fila es anterior a la migración: no hay fecha registrada y
            // llenarla con la de la matrícula fue justo el dato falso que se corrigió.
            'fecha_solicitud' => !empty($row['fecha_solicitud'])
                ? date('d/m/Y', strtotime((string) $row['fecha_solicitud']))
                : '—',
            'registrado_por'  => $row['registrado_por'] ?? 'Sin registro',
            'resuelto_por'    => $row['resuelto_por'] !== '' ? $row['resuelto_por'] : null,
            'fecha_resolucion' => !empty($row['fecha_resolucion'])
                ? date('d/m/Y', strtotime((string) $row['fecha_resolucion']))
                : null,
            'motivo'          => $row['motivo'] ?? '—',
            'observaciones'   => $row['observaciones'] ?? '',
            'estado'          => strtolower($row['estado'] ?? 'pendiente'),
        ];
    }
}