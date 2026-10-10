<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\DocumentoDuplicadoException;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use App\Support\Instituciones;
use App\Traits\AvatarTrait;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

class EmpleadoModel extends BaseModel implements Listable, Creatable, Updatable, Toggleable
{
    use AvatarTrait;

    protected string $table = 'empleado';
    protected string $primaryKey = 'id_empleado';

    private const CAMPOS_EMPLEADO = ['id_cargo', 'fecha_ingreso', 'id_turno'];

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT e.id_empleado,
                       p.tipo_documento,
                       p.numero_documento,
                       p.primer_nombre,
                       p.segundo_nombre,
                       p.primer_apellido,
                       p.segundo_apellido,
                       p.foto,
                       p.estado,
                       c.nombre AS cargo,
                       t.nombre AS turno,
                        COALESCE(nac.nombre, '') AS nacionalidad
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                 LEFT JOIN nacionalidad nac ON nac.id_nacionalidad = p.id_nacionalidad
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo
                LEFT JOIN turno t ON t.id_turno = e.id_turno";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.primer_apellido, p.primer_nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'mapRowToVista'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = "SELECT COUNT(*)
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                LEFT JOIN cargo c ON c.id_cargo = e.id_cargo";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function mapRowToVista(array $row): array
    {
        $nombreCompleto = trim(
            (($row['primer_nombre'] ?? '') . ' ' . ($row['segundo_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? '') . ' ' . ($row['segundo_apellido'] ?? ''))
        );

        if ($nombreCompleto === '') {
            $nombreCompleto = 'Empleado';
        }

        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            'directivo',
            $nombreCompleto
        );

        return [
            'id_empleado'      => (int) ($row['id_empleado'] ?? 0),
            'nombre_completo'  => $nombreCompleto,
            'tipo_documento'   => (string) ($row['tipo_documento'] ?? ''),
            'numero_documento' => (string) ($row['numero_documento'] ?? ''),
            'cargo'            => $row['cargo'] ?? 'Sin cargo',
            'turno'            => $row['turno'] ?? 'Sin turno',
            /* Vacía y no "Sin nacionalidad": el listado es para leer de un vistazo y
               un texto de relleno por fila distrae de las que sí lo tienen. */
            'nacionalidad'     => (string) ($row['nacionalidad'] ?? ''),
            'estado'           => strtoupper((string) ($row['estado'] ?? 'INACTIVO')),
            'avatar_tipo'      => $avatar['tipo'],
            'avatar_foto'      => $avatar['foto'],
            'iniciales'        => $avatar['iniciales'],
            'avatar_color'     => $avatar['avatar_color'],
        ];
    }

    protected function buildFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(p.numero_documento LIKE :q0 OR p.primer_nombre LIKE :q1 OR p.primer_apellido LIKE :q2)';
            $search = '%' . $filters['q'] . '%';
            $params['q0'] = $search;
            $params['q1'] = $search;
            $params['q2'] = $search;
        }

        if (!empty($filters['cargo'])) {
            $where[]         = "e.id_cargo = :cargo";
            $params['cargo'] = (int) $filters['cargo'];
        }

        if (!empty($filters['estado'])) {
            $where[]          = "p.estado = :estado";
            $params['estado'] = strtoupper((string) $filters['estado']);
        }

        return [$where, $params];
    }

    public function getEstadisticas(): array
    {
        $total = (int) $this->pdo->query("SELECT COUNT(*) FROM empleado e INNER JOIN persona p ON p.id_persona = e.id_persona")->fetchColumn();
        $activos = (int) $this->pdo->query("SELECT COUNT(*) FROM empleado e INNER JOIN persona p ON p.id_persona = e.id_persona WHERE p.estado = 'ACTIVO'")->fetchColumn();

        return [
            'total'     => $total,
            'activos'   => $activos,
            'inactivos' => $total - $activos,
            'cargos'    => (int) $this->pdo->query('SELECT COUNT(DISTINCT id_cargo) FROM empleado WHERE id_cargo IS NOT NULL')->fetchColumn(),
        ];
    }

    /**
     * Nacionalidades para el <select> del paso 1.
     *
     * @return array{nacionalidades:array<int,array<string,mixed>>,id_por_defecto:int}
     */
    public function getNacionalidades(): array
    {
        return [
            'nacionalidades' => $this->pdo->query("SELECT id_nacionalidad, nombre FROM nacionalidad
                                                    WHERE estado = 'ACTIVO' ORDER BY nombre")
                                     ->fetchAll(PDO::FETCH_ASSOC),
            'id_por_defecto'  => (int) NacionalidadModel::ID_POR_DEFECTO,
        ];
    }

    public function getCargos(): array
    {
        $stmt = $this->pdo->query('SELECT id_cargo, nombre FROM cargo WHERE estado = "ACTIVO" ORDER BY nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existeTurno(int $idTurno): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM turno WHERE id_turno = :id AND estado = 'ACTIVO'");
        $stmt->execute(['id' => $idTurno]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
 * Un grado académico del catálogo existe.
 *
 * Se usa en empleados/guardarTitulo antes de crear el título. El <select> solo ofrece
 * los grados que hay, pero el id llega del formulario y el servidor no puede depender
 * de eso: sin esta comprobación, un id inventado llega al INSERT y MariaDB avisa por
 * log y guarda el 0, con el alta "exitosa" y el título sin grado.
 *
 * Antes se llamaba existeCatalogo('grado_academico', $grado) desde el controlador, y
 * ese método no existía en ningún modelo: era un error fatal que tumbaba la pantalla
 * con un 500 en blanco, sin mensaje ni aviso, y el título nunca se guardaba.
*/
    public function existeGradoAcademico(int $idGrado): bool
    {
        if ($idGrado <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM grado_academico WHERE id_grado_academico = :id');
        $stmt->execute(['id' => $idGrado]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function turnoCompatibleConAsignaciones(int $idEmpleado, ?int $idTurno): bool
    {
        $sql = "SELECT COUNT(*)
                  FROM docente d
                  INNER JOIN asignacion_docente ad ON ad.id_docente = d.id_docente
                  INNER JOIN estructura_academica ea ON ea.id_estructura_academica = ad.id_estructura_academica
                 WHERE d.id_empleado = :empleado
                   AND (ad.fecha_fin IS NULL OR ad.fecha_fin >= CURDATE())";
        $params = ['empleado' => $idEmpleado];

        if ($idTurno === null) {
            $sql .= ' AND ea.id_turno IS NOT NULL';
        } else {
            $sql .= ' AND ea.id_turno <> :turno';
            $params['turno'] = $idTurno;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }

    /**
 * Catálogos de los formularios de alta y edición.
 *
 * Cada uno devuelve su columna tal como se llama en la tabla (id_grado_academico,
 * id_titulo, id_institucion...) y no renombrada a "id". Estas consultas traían "AS id"
 * y las vistas piden la clave real, así que los <option> salían con value="0" en
 * todos los títulos, grados e instituciones de los dos modales: al elegir el grado no se
 * desosliegaba nada porque todos los títulos valían lo mismo, y al guardar el servidor
 * recibía id_titulo=0 y descartaba la formación.
 *
 * "estados" y "turnos" nunca se renombraron, y por eso esos dos sí funcionaban.
*/
    public function getCatalogosFormulario(): array
    {
        return [
            'grados_academicos' => $this->pdo->query('SELECT id_grado_academico, nombre FROM grado_academico ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'titulos'           => $this->pdo->query('SELECT id_titulo, nombre, id_grado_academico FROM titulo ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'instituciones'     => $this->pdo->query('SELECT id_institucion, nombre FROM institucion ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'tipos_institucion' => $this->pdo->query('SELECT id_tipo_institucion, nombre FROM tipo_institucion ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'estados'           => $this->pdo->query('SELECT id_estado, nombre FROM estado ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC),
            'turnos'            => $this->pdo->query("SELECT id_turno, nombre FROM turno WHERE estado = 'ACTIVO' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getById(int $id): ?array
    {
        $sql = "SELECT e.id_empleado,
                       e.id_cargo,
                       e.id_turno,
                       e.fecha_ingreso,
                       p.tipo_documento,
                       p.numero_documento,
                       p.fecha_nacimiento,
                       p.id_estado_nacimiento,
                       p.id_parroquia_nacimiento, p.id_nacionalidad,
                       p.sexo,
                       p.primer_nombre,
                       p.segundo_nombre,
                       p.primer_apellido,
                       p.segundo_apellido,
                       p.foto,
                       p.estado,
                       c.codigo_telefono_principal,
                       c.numero_telefono_principal,
                       c.codigo_telefono_alternativo,
                       c.numero_telefono_alternativo,
                       c.correo_electronico,
                       d.id_parroquia,
                       pq.nombre AS parroquia_nombre,
                       m.id_municipio,
                       m.nombre AS municipio_nombre,
                       m.id_estado,
                       est.nombre AS estado_nombre,
                       d.sector_urbanizacion,
                       d.calle_avenida,
                       d.nro_casa_apto,
                       d.punto_referencia,
                       car.nombre AS cargo,
                       t.nombre AS turno,
                        COALESCE(nac.nombre, '') AS nacionalidad
                FROM empleado e
                INNER JOIN persona p ON p.id_persona = e.id_persona
                 LEFT JOIN nacionalidad nac ON nac.id_nacionalidad = p.id_nacionalidad
                LEFT JOIN contacto c ON c.id_persona = p.id_persona
                LEFT JOIN direccion d ON d.id_persona = p.id_persona
                LEFT JOIN parroquia pq ON pq.id_parroquia = d.id_parroquia
                LEFT JOIN municipio m ON m.id_municipio = pq.id_municipio
                LEFT JOIN estado est ON est.id_estado = m.id_estado
                LEFT JOIN cargo car ON car.id_cargo = e.id_cargo
                LEFT JOIN turno t ON t.id_turno = e.id_turno
                WHERE e.id_empleado = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $nombreCompleto = trim(
            (($row['primer_nombre'] ?? '') . ' ' . ($row['segundo_nombre'] ?? '') . ' ' . ($row['primer_apellido'] ?? '') . ' ' . ($row['segundo_apellido'] ?? ''))
        );
        if ($nombreCompleto === '') {
            $nombreCompleto = 'Empleado';
        }

        $avatar = $this->resolverAvatar(
            $row['foto'] ?? null,
            $row['primer_nombre'] ?? null,
            $row['primer_apellido'] ?? null,
            'directivo',
            $nombreCompleto
        );

        $row['nombre_completo'] = $nombreCompleto;
        $row['avatar_tipo']     = $avatar['tipo'];
        $row['avatar_foto']     = $avatar['foto'];
        $row['iniciales']       = $avatar['iniciales'];
        $row['avatar_color']    = $avatar['avatar_color'];
        $row['telefono']        = trim(($row['codigo_telefono_principal'] ?? '') . '-' . ($row['numero_telefono_principal'] ?? ''));
        $row['telefono_alternativo'] = trim(($row['codigo_telefono_alternativo'] ?? '') . '-' . ($row['numero_telefono_alternativo'] ?? ''));
        $row['correo']          = $row['correo_electronico'] ?? '';

        $direccionPartes = array_filter([
            $row['calle_avenida'] ?? '',
            $row['nro_casa_apto'] ? 'Casa nro ' . $row['nro_casa_apto'] : '',
            $row['sector_urbanizacion'] ? 'Sector ' . $row['sector_urbanizacion'] : '',
            $row['parroquia_nombre'] ? 'Parroquia ' . $row['parroquia_nombre'] : '',
            $row['municipio_nombre'] ? 'Municipio ' . $row['municipio_nombre'] : '',
            $row['estado_nombre'] ? 'Estado ' . $row['estado_nombre'] : '',
            $row['punto_referencia'] ? 'Punto de referencia ' . $row['punto_referencia'] : '',
        ]);
        $row['direccion_completa'] = implode(', ', $direccionPartes);

        $stmtFormacion = $this->pdo->prepare('SELECT id_formacion_academica, id_titulo, id_grado_academico, id_institucion, fecha_obtencion FROM formacion_academica WHERE id_empleado = :id ORDER BY fecha_obtencion DESC');
        $stmtFormacion->execute(['id' => $id]);
        $row['formacion'] = $stmtFormacion->fetchAll(PDO::FETCH_ASSOC);

        return $row;
    }

    public function getMunicipiosPorEstado(int $id_estado): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_municipio, nombre FROM municipio
             WHERE id_estado = :id_estado AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_estado' => $id_estado]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParroquiasPorMunicipio(int $id_municipio): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_parroquia, nombre FROM parroquia
             WHERE id_municipio = :id_municipio AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_municipio' => $id_municipio]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): bool
    {
        $this->pdo->beginTransaction();

        try {
            $personaModel = new PersonaModel($this->pdo);
            $contactoModel = new ContactoModel($this->pdo);
            $direccionModel = new DireccionModel($this->pdo);

            $id_persona = $personaModel->buscarOCrear($data);

            $stmt = $this->pdo->prepare(
                    'INSERT INTO empleado (id_persona, fecha_ingreso, id_cargo, id_turno)
                     VALUES (:id_persona, :fecha_ingreso, :id_cargo, :id_turno)'
                );
            $stmt->execute(
                [
                    'id_persona'    => $id_persona,
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'id_cargo'      => (int) $data['id_cargo'],
                    'id_turno'      => $data['id_turno'] ?: null,
                ]
            );

            $id_empleado = (int) $this->pdo->lastInsertId();
            $this->sincronizarRegistroDocente($id_empleado, (int) $data['id_cargo']);

            if (!empty($data['contacto'])) {
                $contactoModel->upsert($id_persona, $data['contacto']);
            }

            if (!empty($data['direccion'])) {
                $direccionModel->upsert($id_persona, $data['direccion']);
            }

            $this->reemplazarFormacion($id_empleado, $data['formacion'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('SELECT id_persona FROM empleado WHERE id_empleado = :id');
        $stmt->execute(['id' => $id]);
        $id_persona = $stmt->fetchColumn();

        if ($id_persona === false) {
            throw new RuntimeException("El empleado {$id} no existe.");
        }

        $this->pdo->beginTransaction();

        try {
            $personaModel = new PersonaModel($this->pdo);
            $contactoModel = new ContactoModel($this->pdo);
            $direccionModel = new DireccionModel($this->pdo);

            $personaModel->updatePersona((int) $id_persona, $data);

            $empleadoData = array_intersect_key($data, array_flip(self::CAMPOS_EMPLEADO));
            if ($empleadoData) {
                $sets = array_map(fn (string $c) => "{$c} = :{$c}", array_keys($empleadoData));
                $empleadoData['id'] = $id;

                $stmt = $this->pdo->prepare('UPDATE empleado SET ' . implode(', ', $sets) . ' WHERE id_empleado = :id');
                $stmt->execute($empleadoData);
            }

            $stmt = $this->pdo->prepare('SELECT id_cargo FROM empleado WHERE id_empleado = :id');
            $stmt->execute(['id' => $id]);
            $idCargo = $stmt->fetchColumn();
            if ($idCargo !== false && $idCargo !== null) {
                $this->sincronizarRegistroDocente($id, (int) $idCargo);
            }

            if (!empty($data['contacto'])) {
                $contactoModel->upsert((int) $id_persona, $data['contacto']);
            }

            if (!empty($data['direccion'])) {
                $direccionModel->upsert((int) $id_persona, $data['direccion']);
            }

            /* La formación se actualiza fila a fila y no con "borro todo y reescribo".
 El modal de edición maneja una sola fila, pero un empleado puede tener
 varias: con el borrado completo, corregir el título de alguien borraba en
 silencio los demás. Ver actualizarFormacion().*/
            $this->actualizarFormacion($id, $data);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function sincronizarRegistroDocente(int $idEmpleado, int $idCargo): void
    {
        $cargo = $this->pdo->prepare(
            "SELECT COUNT(*)
               FROM cargo
              WHERE id_cargo = :cargo
                AND nombre IN ('Docente de Aula', 'Docente de Educación Especial')"
        );
        $cargo->execute(['cargo' => $idCargo]);
        if ((int) $cargo->fetchColumn() === 0) {
            return;
        }

        $docente = $this->pdo->prepare('SELECT 1 FROM docente WHERE id_empleado = :empleado LIMIT 1');
        $docente->execute(['empleado' => $idEmpleado]);
        if ($docente->fetchColumn() !== false) {
            return;
        }

        $this->pdo->prepare('INSERT INTO docente (id_empleado) VALUES (:empleado)')
            ->execute(['empleado' => $idEmpleado]);
    }

    public function toggleStatus(int $id, string $columna = 'estado'): bool
    {
        $sql = "UPDATE persona p
                INNER JOIN empleado e ON p.id_persona = e.id_persona
                SET p.estado = IF(p.estado = 'ACTIVO', 'INACTIVO', 'ACTIVO')
                WHERE e.id_empleado = :id";
        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }

    /**
 * Aplica lo que venga de la edición de la formación.
 *
 * Sustituye al "borro todo y reescribo" de la alta, y esa diferencia importa porque el
 * modal de edición solo maneja UNA fila: un empleado puede tener varias, y borrar todas
 * para reponer una perdía los demás títulos sin avisar.
 *
 * - Si viene eliminar_formacion, se borra la fila indicada (o todas, si no se indica
 * cuál: así un empleado con dos títulos se puede dejar sin formación completa).
 * - Si viene una fila completa, se actualiza la que se indica por su id o se inserta
 * como nueva.
 * - Si no viene nada de eso, no se toca la formación. Es el caso normal al editar otro
 * dato: que abrir y guardar el modal no borre información que la pantalla ni muestra.
*/
    private function actualizarFormacion(int $idEmpleado, array $data): void
    {
        $filas = $data['formacion'] ?? [];
        $idEnEdicion = (int) ($data['id_formacion_academica'] ?? 0);
        $eliminar = !empty($data['eliminar_formacion']);

        if ($eliminar) {
            if ($idEnEdicion > 0) {
                $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :emp AND id_formacion_academica = :id')
                     ->execute(['emp' => $idEmpleado, 'id' => $idEnEdicion]);
            } else {
                $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :emp')
                     ->execute(['emp' => $idEmpleado]);
            }

            return;
        }

        if ($filas === []) {
            return;
        }

        $fila = $filas[0];

        /* formacion_academica.fecha_obtencion es NOT NULL. validate() ya rechaza la fila sin
 fecha con un mensaje claro, pero si algún camino la escribiera igual, el INSERT
 reventaría con un error de MariaDB que el usuario vería como "ocurrió un problema".
 Aquí se corta antes y se avisa en el log: un dato que no se puede guardar se dice,
 no se traga.*/
        if (empty($fila['fecha_obtencion'])) {
            error_log(
                'EmpleadoModel::actualizarFormacion(): no se guardó la formación del empleado '
                . $idEmpleado . ' porque fecha_obtencion venía vacía (columna NOT NULL).'
            );

            return;
        }

        if ($idEnEdicion > 0) {
            $this->pdo->prepare(
                'UPDATE formacion_academica
                    SET id_titulo = :titulo, id_grado_academico = :grado,
                        id_institucion = :inst, fecha_obtencion = :fecha
                  WHERE id_empleado = :emp AND id_formacion_academica = :id'
            )->execute([
                'titulo' => (int) $fila['id_titulo'],
                'grado'  => (int) $fila['id_grado_academico'],
                'inst'   => (int) $fila['id_institucion'],
                'fecha'  => $fila['fecha_obtencion'],
                'emp'    => $idEmpleado,
                'id'     => $idEnEdicion,
            ]);

            return;
        }

        // Fila nueva: se inserta sin pasar por reemplazarFormacion(), que borraría las de
        // más. El id no se toma del INSERT por si el UPDATE de arriba no tocó nada.
        $this->pdo->prepare(
            'INSERT INTO formacion_academica
                (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion)
             VALUES (:emp, :titulo, :grado, :inst, :fecha)'
        )->execute([
            'emp'    => $idEmpleado,
            'titulo' => (int) $fila['id_titulo'],
            'grado'  => (int) $fila['id_grado_academico'],
            'inst'   => (int) $fila['id_institucion'],
            'fecha'  => $fila['fecha_obtencion'],
        ]);
    }

    private function reemplazarFormacion(int $idEmpleado, array $formacion): void
    {
        $this->pdo->prepare('DELETE FROM formacion_academica WHERE id_empleado = :id')
             ->execute(['id' => $idEmpleado]);

        if ($formacion === []) {
            return;
        }

        $sql = 'INSERT INTO formacion_academica
                (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion)
                VALUES (:emp, :titulo, :grado, :inst, :fecha)';
        $stmt = $this->pdo->prepare($sql);

        foreach ($formacion as $item) {
            $stmt->execute([
                'emp'    => $idEmpleado,
                'titulo' => $item['id_titulo'],
                'grado'  => $item['id_grado_academico'],
                'inst'   => $item['id_institucion'],
                'fecha'  => $item['fecha_obtencion'],
            ]);
        }
    }
}
