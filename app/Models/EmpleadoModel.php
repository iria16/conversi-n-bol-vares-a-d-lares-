<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Interfaces\Toggleable;
use App\Interfaces\Updatable;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * EmpleadoModel
 *
 * Modelo del módulo Personal > Empleados. Un empleado son dos filas:
 * los datos personales viven en `persona` y lo laboral (cargo, fechas de
 * ingreso y egreso) en `empleado`, enlazadas por id_persona. El estado
 * ACTIVO/INACTIVO NO está en `empleado`: vive en `persona.estado`.
 * Por eso create() y update() escriben en ambas tablas dentro de una
 * transacción.
 *
 * Qué implementa y qué no:
 *  - Listable: sobrescribo getAll() y countAll(), que son los que consume
 *    ListableIndexTrait. Lo hago porque el listado necesita JOIN con persona
 *    y cargo, y el SELECT * de una sola tabla de BaseModel no alcanza.
 *  - Creatable y Updatable: los sobrescribo porque BaseModel solo sabe
 *    insertar/actualizar en una tabla.
 *  - Toggleable: sobrescribo toggleStatus() porque el de BaseModel alterna
 *    el campo en la tabla del modelo (`empleado`), y ahí no hay estado.
 *  - Deletable: NO lo implemento a propósito. Al personal se le desactiva,
 *    no se le borra, así EmpleadoController responde 405 a delete().
 *
 * Los filtros llegan con los nombres de la vista (q, cargo, estado) y los
 * traduzco con mi propio buildFilters().
 *
 * @author Logística
 * @package App\Models
 */
class EmpleadoModel extends BaseModel implements Listable, Creatable, Updatable, Toggleable
{
    protected string $table      = 'empleado';
    protected string $primaryKey = 'id_empleado';

    /**
     * Columnas de `persona` que este módulo puede escribir. Es la lista
     * blanca: lo que no esté aquí se descarta antes de armar el SQL.
     */
    private const CAMPOS_PERSONA = [
        'tipo_documento',
        'numero_documento',
        'fecha_nacimiento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'sexo',
        'nacionalidad',
    ];

    /** Columnas de `persona` que aceptan NULL: si llegan vacías, guardo NULL. */
    private const PERSONA_NULABLES = ['segundo_nombre', 'segundo_apellido'];

    /** Columnas de `empleado` que update() puede modificar. */
    private const CAMPOS_EMPLEADO = ['id_cargo', 'fecha_ingreso'];

    /**
     * Catálogos de los modales: clave que espera la vista => tabla.
     * Todas tienen `nombre` y `estado`; solo devuelvo los ACTIVO.
     */
    private const CATALOGOS = [
        'grados_academicos' => 'grado_academico',
        'titulos'           => 'titulo',
        'instituciones'     => 'institucion',
        'tipos_institucion' => 'tipo_institucion',
        'estados'           => 'estado',
    ];

    private const NOMBRE_COMPLETO = "CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)";

    /** JOIN base del listado y del detalle. */
    private const FROM = 'FROM empleado e
        INNER JOIN persona p ON p.id_persona = e.id_persona
        LEFT JOIN cargo c ON c.id_cargo = e.id_cargo';

    /** Columnas que devuelvo al listado y al detalle (mismo formato en ambos). */
    private const SELECT = 'SELECT e.id_empleado, e.id_persona, e.id_cargo, e.fecha_ingreso, e.fecha_egreso,
        p.estado,
        p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
        p.sexo, p.nacionalidad, p.foto,
        c.nombre AS cargo,
        ' . self::NOMBRE_COMPLETO . ' AS nombre_completo';

    // ---------- Listado (contrato Listable) ----------

    /**
     * Cuenta los empleados que cumplen los filtros (para la paginación).
     *
     * @param array $filters q, cargo y estado, como los deja el controlador.
     */
    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = 'SELECT COUNT(*) ' . self::FROM;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Trae una página de empleados, del más reciente al más antiguo.
     *
     * @param array $filters q, cargo y estado.
     * @param int   $page    Número de página (desde 1).
     * @param int   $perPage Filas por página.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = self::SELECT . ' ' . self::FROM;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.id_empleado DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Traduce los filtros de la vista a condiciones SQL.
     *
     *  - q       → búsqueda por cédula, nombre completo o cargo (LIKE).
     *  - cargo   → id_cargo exacto.
     *  - estado  → 'activo'/'inactivo' de la vista, en mayúsculas para el
     *              ENUM de persona.estado.
     *
     * Cada LIKE lleva su propio placeholder (q0, q1, ...): no repito nombres
     * porque con prepares nativos PDO no lo permite.
     *
     * @return array{0: string[], 1: array<string, mixed>}
     */
    protected function buildFilters(array $filters): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conds = [];
            foreach ($this->searchableFields() as $i => $columna) {
                $clave          = "q{$i}";
                $conds[]        = "{$columna} LIKE :{$clave}";
                $params[$clave] = "%{$q}%";
            }
            $where[] = '(' . implode(' OR ', $conds) . ')';
        }

        $cargo = (string) ($filters['cargo'] ?? '');
        if ($cargo !== '') {
            $where[]         = 'e.id_cargo = :cargo';
            $params['cargo'] = (int) $cargo;
        }

        $estado = strtoupper((string) ($filters['estado'] ?? ''));
        if (in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            $where[]          = 'p.estado = :estado';
            $params['estado'] = $estado;
        }

        return [$where, $params];
    }

    /**
     * Expresiones sobre las que busca el filtro `q`.
     */
    protected function searchableFields(): array
    {
        return ['p.numero_documento', self::NOMBRE_COMPLETO, 'c.nombre'];
    }

    // ---------- Estadísticas y catálogos ----------

    /**
     * Totales para las tarjetas de la vista.
     *
     * @return array{total:int, activos:int, inactivos:int, cargos:int}
     */
    public function getEstadisticas(): array
    {
        $fila = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(p.estado = 'ACTIVO'), 0)   AS activos,
                    COALESCE(SUM(p.estado = 'INACTIVO'), 0) AS inactivos,
                    COUNT(DISTINCT e.id_cargo)              AS cargos
             FROM empleado e
             INNER JOIN persona p ON p.id_persona = e.id_persona"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'     => (int) ($fila['total'] ?? 0),
            'activos'   => (int) ($fila['activos'] ?? 0),
            'inactivos' => (int) ($fila['inactivos'] ?? 0),
            'cargos'    => (int) ($fila['cargos'] ?? 0),
        ];
    }

    /**
     * Cargos activos para el <select> de filtro y del formulario.
     */
    public function getCargos(): array
    {
        return $this->pdo
            ->query("SELECT id_cargo, nombre FROM cargo WHERE estado = 'ACTIVO' ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Catálogos activos que necesitan los modales (wizard, título e institución).
     * Las claves del resultado son las de CATALOGOS.
     *
     * @return array<string, array>
     */
    public function getCatalogosFormulario(): array
    {
        $catalogos = [];

        foreach (self::CATALOGOS as $clave => $tabla) {
            $catalogos[$clave] = $this->pdo
                ->query("SELECT * FROM {$tabla} WHERE estado = 'ACTIVO' ORDER BY nombre")
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        return $catalogos;
    }

    // ---------- Lectura puntual ----------

    /**
     * Un empleado con sus datos personales y el nombre del cargo.
     * Sobrescribe el de BaseModel para devolver el mismo formato del listado.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' ' . self::FROM . ' WHERE e.id_empleado = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Indica si ya existe un empleado con esa cédula.
     *
     * $ignorarId es el id_empleado que se está editando: lo excluyo para que
     * el propio registro no cuente como duplicado (lo usa update() del
     * controlador).
     */
    public function existeDocumento(string $numero, ?int $ignorarId = null): bool
    {
        $sql    = 'SELECT 1 ' . self::FROM . ' WHERE p.numero_documento = :numero';
        $params = ['numero' => $numero];

        if ($ignorarId !== null) {
            $sql .= ' AND e.id_empleado <> :id';
            $params['id'] = $ignorarId;
        }

        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    // ---------- Escritura ----------

    /**
     * Registra un empleado: la persona (si no existía) y su fila en `empleado`.
     *
     * `persona.numero_documento` es UNIQUE en toda la tabla, así que busco
     * primero por número: si esa persona ya está en el sistema con otro rol,
     * la reutilizo en lugar de duplicarla. Que ya sea empleado lo detecta el
     * controlador con existeDocumento() antes de llegar aquí.
     *
     * Todo va en una transacción: o se guardan las dos filas o ninguna.
     *
     * @param array $data Datos de extractData() (persona + id_cargo + fecha_ingreso).
     */
    public function create(array $data): bool
    {
        $this->pdo->beginTransaction();

        try {
            $idPersona = $this->buscarOCrearPersona($data);

            $this->ejecutar(
                $this->pdo->prepare(
                    'INSERT INTO empleado (id_persona, fecha_ingreso, id_cargo)
                     VALUES (:id_persona, :fecha_ingreso, :id_cargo)'
                ),
                [
                    'id_persona'    => $idPersona,
                    'fecha_ingreso' => $data['fecha_ingreso'],
                    'id_cargo'      => (int) $data['id_cargo'],
                ]
            );

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Actualiza los datos personales, el cargo y la fecha de ingreso.
     *
     * Ojo: los datos personales se guardan en `persona`, así que si esa
     * persona tiene otros roles en el sistema, el cambio también se ve ahí.
     *
     * @param int   $id   id_empleado.
     * @param array $data Datos de extractData().
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('SELECT id_persona FROM empleado WHERE id_empleado = :id');
        $stmt->execute(['id' => $id]);
        $idPersona = $stmt->fetchColumn();

        if ($idPersona === false) {
            throw new RuntimeException("El empleado {$id} no existe.");
        }

        $this->pdo->beginTransaction();

        try {
            $persona = $this->soloCamposPersona($data);
            if ($persona) {
                $sets = array_map(fn (string $c) => "{$c} = :{$c}", array_keys($persona));
                $persona['id_persona'] = (int) $idPersona;

                $this->ejecutar(
                    $this->pdo->prepare('UPDATE persona SET ' . implode(', ', $sets) . ' WHERE id_persona = :id_persona'),
                    $persona
                );
            }

            $laboral = array_intersect_key($data, array_flip(self::CAMPOS_EMPLEADO));
            if ($laboral) {
                $sets = array_map(fn (string $c) => "{$c} = :{$c}", array_keys($laboral));
                $laboral['id'] = $id;

                $this->ejecutar(
                    $this->pdo->prepare('UPDATE empleado SET ' . implode(', ', $sets) . ' WHERE id_empleado = :id'),
                    $laboral
                );
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Activa o desactiva a un empleado alternando `persona.estado`.
     *
     * Sobrescribe el de BaseModel, que tocaría la tabla `empleado` (sin
     * columna de estado). Se conserva $field solo para mantener la firma del
     * padre; aquí el campo siempre es persona.estado.
     *
     * Ojo: como el estado es de la persona, desactivar a un empleado también
     * la marca inactiva en cualquier otro rol que tenga.
     *
     * @param int $id id_empleado.
     */
    public function toggleStatus(int $id, string $field = 'estado'): bool
    {
        $sql = "UPDATE persona p
                INNER JOIN empleado e ON e.id_persona = p.id_persona
                SET p.estado = CASE WHEN p.estado = 'ACTIVO' THEN 'INACTIVO' ELSE 'ACTIVO' END
                WHERE e.id_empleado = :id";

        return $this->pdo->prepare($sql)->execute(['id' => $id]);
    }

    // ---------- Apoyo interno ----------

    /**
     * Devuelve el id_persona de quien tenga ese número de documento o, si no
     * existe, crea la persona y devuelve el nuevo id. El estado queda en el
     * valor por defecto de la tabla (ACTIVO).
     */
    private function buscarOCrearPersona(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_persona FROM persona WHERE numero_documento = :numero LIMIT 1'
        );
        $stmt->execute(['numero' => $data['numero_documento']]);

        $existente = $stmt->fetchColumn();
        if ($existente !== false) {
            return (int) $existente;
        }

        $persona  = $this->soloCamposPersona($data);
        $columnas = array_keys($persona);
        $sql      = 'INSERT INTO persona (' . implode(', ', $columnas) . ') VALUES ('
                  . implode(', ', array_map(fn (string $c) => ":{$c}", $columnas)) . ')';

        $this->ejecutar($this->pdo->prepare($sql), $persona);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Filtra $data dejando solo las columnas permitidas de `persona`, y
     * convierte a NULL las opcionales que vengan vacías.
     */
    private function soloCamposPersona(array $data): array
    {
        $persona = array_intersect_key($data, array_flip(self::CAMPOS_PERSONA));

        foreach (self::PERSONA_NULABLES as $campo) {
            if (array_key_exists($campo, $persona) && $persona[$campo] === '') {
                $persona[$campo] = null;
            }
        }

        return $persona;
    }

    /**
     * Ejecuta y, si falla sin lanzar excepción (según el modo de error de
     * PDO), la lanzo yo para que la transacción se revierta.
     */
    private function ejecutar(PDOStatement $stmt, array $params): void
    {
        if (!$stmt->execute($params)) {
            throw new RuntimeException('No se pudo ejecutar la consulta de escritura.');
        }
    }

    private function revertir(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}