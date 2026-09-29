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
use App\Traits\ContactoDireccionTrait;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Módulo Personal > Empleados: reparte los datos en persona (incluye el estado ACTIVO/INACTIVO), empleado, contacto, direccion y formacion_academica (borrado lógico).
 * create() y update() escriben todas las filas en una sola transacción; no implementa Deletable, al personal se le desactiva y delete() responde 405.
 * Sobrescribe getAll/countAll (JOIN con persona y cargo), create/update y toggleStatus, y traduce los filtros de la vista (q, cargo, estado) con buildFilters().
 */
class EmpleadoModel extends BaseModel implements Listable, Creatable, Updatable, Toggleable
{
    use AvatarTrait;
    use ContactoDireccionTrait;
    protected string $table      = 'empleado';
    protected string $primaryKey = 'id_empleado';

    /** Columnas de `persona` que este módulo puede escribir (lista blanca; lo demás se descarta). */
    private const CAMPOS_PERSONA = [
        'tipo_documento',
        'numero_documento',
        'fecha_nacimiento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'sexo',
        'foto',
    ];

    /** Columnas de `persona` que aceptan NULL: si llegan vacías, guardo NULL. */
    private const PERSONA_NULABLES = ['segundo_nombre', 'segundo_apellido', 'foto'];

    /** Columnas de `empleado` que update() puede modificar. */
    private const CAMPOS_EMPLEADO = ['id_cargo', 'fecha_ingreso'];

    // Las columnas escribibles de `contacto` y `direccion` y el SQL del upsert viven en ContactoDireccionTrait.

    /**
     * Catálogos de los modales: clave que espera la vista => tabla (solo los ACTIVO).
     * 'instituciones' se filtra por tipo (getInstitucionesFormulario()) y 'tipos_institucion' sale del enum Instituciones.
     */
    private const CATALOGOS = [
        'grados_academicos' => 'grado_academico',
        'titulos'           => 'titulo',
        'instituciones'     => 'institucion',
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
        p.sexo, p.foto,
        c.nombre AS cargo,
        ' . self::NOMBRE_COMPLETO . ' AS nombre_completo';

    // ---------- Listado (contrato Listable) ----------

    /**
     * Cuenta los empleados que cumplen los filtros (q, cargo y estado), para la paginación.
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
     * Trae una página de empleados, del más reciente al más antiguo, con los filtros q, cargo y estado.
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

        return array_map([$this, 'enriquecerAvatar'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Traduce los filtros de la vista a SQL: q (cédula, nombre o cargo con LIKE), cargo (id exacto) y estado ('activo'/'inactivo').
     * Cada LIKE lleva su propio placeholder (q0, q1...) porque con prepares nativos PDO no admite repetirlos.
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
     * Catálogos activos que necesitan los modales (wizard, título e institución), con las claves de CATALOGOS.
     *
     * @return array<string, array>
     */
    public function getCatalogosFormulario(): array
    {
        $catalogos = [];

        foreach (self::CATALOGOS as $clave => $tabla) {
            // 'instituciones' tiene su propia consulta (JOIN + filtro por tipo).
            if ($clave === 'instituciones') {
                $catalogos[$clave] = $this->getInstitucionesFormulario();
                continue;
            }

            $catalogos[$clave] = $this->pdo
                ->query("SELECT * FROM {$tabla} WHERE estado = 'ACTIVO' ORDER BY nombre")
                ->fetchAll(PDO::FETCH_ASSOC);
        }

        // Los tipos salen del enum Instituciones, no de una tabla; aquí solo vale TIPO_TITULO_ACADEMICO.
        $catalogos['tipos_institucion'] = (new InstitucionModel($this->pdo))
            ->tiposFormulario([Instituciones::TIPO_TITULO_ACADEMICO]);

        return $catalogos;
    }

    /**
     * Indica si existe el catálogo al que apunta una FK NOT NULL del formulario ('grado_academico', 'titulo' o 'institucion').
     * El controlador lo pregunta antes de guardar para responder 422 con el error del campo en vez de un 500.
     * El tipo de institución no se valida aquí: lo resuelve InstitucionModel contra el enum.
     */
    public function existeCatalogo(string $catalogo, int $id): bool
    {
        $permitidos = [
            'grado_academico' => 'id_grado_academico',
            'titulo'          => 'id_titulo',
            'institucion'     => 'id_institucion',
        ];

        if (!isset($permitidos[$catalogo]) || $id <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$catalogo} WHERE {$permitidos[$catalogo]} = :id");
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Instituciones ACTIVO del <select> del paso 5, solo las de tipo TIPO_TITULO_ACADEMICO.
     * Filtra por el código del enum que guarda `institucion.tipo`, sin depender de una tabla de tipos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getInstitucionesFormulario(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*
             FROM institucion i
             WHERE i.estado = 'ACTIVO'
               AND i.tipo = :tipo
             ORDER BY i.nombre"
        );
        $stmt->execute(['tipo' => Instituciones::TIPO_TITULO_ACADEMICO]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- Lectura puntual ----------

    /**
     * Un empleado con datos personales, cargo, contacto, dirección y formación (sobrescribe el de BaseModel).
     * Sirve a los modales "Ver" (telefono, correo, formacion en texto) y "Editar" (campos planos de contacto,
     * dirección y el primer título, porque el modal edita una sola fila).
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' ' . self::FROM . ' WHERE e.id_empleado = :id'
        );
        $stmt->execute(['id' => $id]);

        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$empleado) {
            return null;
        }

        // Contacto (una fila por persona), también como campos planos para el modal de edición.
        $contacto = $this->findContacto((int) $empleado['id_persona']);
        if ($contacto) {
            $empleado = array_merge($empleado, $contacto);
            $empleado['telefono'] = trim(
                ($contacto['codigo_telefono_principal'] ?? '') . '-' . ($contacto['numero_telefono_principal'] ?? ''),
                '-'
            ) ?: null;
        } else {
            $empleado['telefono'] = null;
        }

        // Dirección de habitación, con id_municipio e id_estado para repoblar la cascada del modal de edición.
        $direccion = $this->findDireccion((int) $empleado['id_persona']);
        if ($direccion) {
            $empleado = array_merge($empleado, $direccion);
        }

        // Formación: lista completa para el detalle y primer registro para la edición.
        $formacion = $this->findFormacion((int) $empleado['id_empleado']);
        $empleado['formacion'] = $this->formatearFormacion($formacion);

        if ($formacion !== []) {
            $primera = $formacion[0];
            $empleado['id_titulo']          = $primera['id_titulo'];
            $empleado['id_grado_academico'] = $primera['id_grado_academico'];
            $empleado['id_institucion']     = $primera['id_institucion'];
            $empleado['fecha_obtencion']    = $primera['fecha_obtencion'];
        }

        return $this->enriquecerAvatar($empleado);
    }

    /**
     * Agrega a la fila las claves de avatar (avatar_tipo, avatar_foto, iniciales, avatar_color) según AvatarTrait.
     * Las consumen components/avatar.php y el JS de los modales.
     */
    private function enriquecerAvatar(array $fila): array
    {
        $avatar = $this->resolverAvatar(
            $fila['foto'] ?? null,
            $fila['primer_nombre'] ?? null,
            $fila['primer_apellido'] ?? null,
            $fila['rol'] ?? null,
            $fila['nombre_completo'] ?? 'E'
        );

        $fila['avatar_tipo']    = $avatar['tipo'];
        $fila['avatar_foto']    = $avatar['foto'];
        $fila['iniciales']      = $avatar['iniciales'];
        $fila['avatar_color']   = $avatar['avatar_color'];

        return $fila;
    }

    /**
     * Fila de contacto de una persona, o null si no tiene.
     */
    private function findContacto(int $idPersona): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . implode(', ', self::COLUMNAS_CONTACTO) . '
             FROM contacto WHERE id_persona = :id_persona LIMIT 1'
        );
        $stmt->execute(['id_persona' => $idPersona]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Fila de dirección de una persona, o null si no tiene.
     * Aporta también id_municipio e id_estado (cascada parroquia->municipio->estado) para repoblar los <select>.
     */
    private function findDireccion(int $idPersona): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id_parroquia, d.sector_urbanizacion, d.calle_avenida,
                    d.nro_casa_apto, d.punto_referencia,
                    m.id_municipio, e.id_estado,
                    e.nombre AS estado_nombre,
                    m.nombre AS municipio_nombre,
                    p.nombre AS parroquia_nombre
             FROM direccion d
             INNER JOIN parroquia p ON p.id_parroquia = d.id_parroquia
             INNER JOIN municipio m ON m.id_municipio = p.id_municipio
             INNER JOIN estado e    ON e.id_estado = m.id_estado
             WHERE d.id_persona = :id_persona LIMIT 1'
        );
        $stmt->execute(['id_persona' => $idPersona]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Formación académica ACTIVA de un empleado con los nombres de título, grado e institución, o [] si no tiene.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findFormacion(int $idEmpleado): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fa.id_titulo, fa.id_grado_academico, fa.id_institucion,
                    fa.fecha_obtencion,
                    t.nombre AS titulo_nombre,
                    g.nombre AS grado_nombre,
                    i.nombre AS institucion_nombre
             FROM formacion_academica fa
             INNER JOIN titulo t           ON t.id_titulo = fa.id_titulo
             LEFT JOIN grado_academico g   ON g.id_grado_academico = fa.id_grado_academico
             LEFT JOIN institucion i       ON i.id_institucion = fa.id_institucion
             WHERE fa.id_empleado = :id_empleado AND fa.estado = \'ACTIVO\'
             ORDER BY fa.fecha_obtencion'
        );
        $stmt->execute(['id_empleado' => $idEmpleado]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Arma el texto de las formaciones para el modal de detalle, p. ej. "Lic. en Educación (Universidad Caldas) · 2020".
     */
    private function formatearFormacion(array $formacion): ?string
    {
        $textos = [];

        foreach ($formacion as $fila) {
            $titulo    = trim((string) $fila['titulo_nombre']);
            $grado     = trim((string) $fila['grado_nombre']);
            $institucion = trim((string) $fila['institucion_nombre']);
            $fecha     = trim((string) $fila['fecha_obtencion']);
            $anio      = $fecha !== '' ? date('Y', strtotime($fecha)) : '';

            $parte = trim(implode(' ', array_filter([$titulo, $grado, $institucion])));

            $textos[] = $anio !== '' ? "{$parte} · {$anio}" : $parte;
        }

        $texto = implode('; ', array_filter($textos));

        return $texto !== '' ? $texto : null;
    }

    /**
     * Indica si ya existe un empleado con esa cédula; $ignorarId excluye al que se está editando.
     * Es la validación previa al INSERT; la garantía real es el UNIQUE de persona.numero_documento,
     * que buscarOCrearPersona() traduce a DocumentoDuplicadoException en altas concurrentes.
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

    // ---------- Ubicación: cascada del wizard ----------

    /**
     * Municipios ACTIVO de un estado, para el <select> del paso 3 del wizard.
     */
    public function getMunicipiosPorEstado(int $idEstado): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_municipio, nombre FROM municipio
             WHERE id_estado = :id_estado AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_estado' => $idEstado]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Parroquias ACTIVO de un municipio, para el <select> del paso 3 del wizard.
     */
    public function getParroquiasPorMunicipio(int $idMunicipio): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_parroquia, nombre FROM parroquia
             WHERE id_municipio = :id_municipio AND estado = 'ACTIVO' ORDER BY nombre"
        );
        $stmt->execute(['id_municipio' => $idMunicipio]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------- Escritura ----------

    /**
     * Registra un empleado (persona si no existía, empleado, contacto, dirección y títulos) en una sola transacción.
     * Si la cédula ya está en `persona` con otro rol, la reutiliza en vez de duplicarla; que ya sea empleado lo detecta el controlador.
     *
     * @throws DocumentoDuplicadoException Si dos altas simultáneas chocan con la misma cédula.
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

            $idEmpleado = (int) $this->pdo->lastInsertId();

            if (!empty($data['contacto'])) {
                $this->guardarContacto($idPersona, $data['contacto']);
            }

            if (!empty($data['direccion'])) {
                $this->guardarDireccion($idPersona, $data['direccion']);
            }

            $this->reemplazarFormacion($idEmpleado, $data['formacion'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Actualiza datos personales, cargo, fecha de ingreso, contacto, dirección y formación; $id es el id_empleado.
     * Los datos personales viven en `persona`, así que si tiene otros roles el cambio también se ve ahí.
     * El modal no envía los teléfonos alternativos, así que solo se actualizan las columnas de contacto que llegaron.
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

            if (!empty($data['contacto'])) {
                $this->guardarContacto((int) $idPersona, $data['contacto']);
            }

            // El bloque de dirección del modal siempre trae parroquia; guardarDireccion() inserta o actualiza.
            if (!empty($data['direccion'])) {
                $this->guardarDireccion((int) $idPersona, $data['direccion']);
            }

            // Solo se reemplaza la formación si llega una fila completa; vacío conserva la existente
            // (evita borrar títulos en silencio al editar otros datos).
            if (!empty($data['formacion'])) {
                $this->reemplazarFormacion($id, $data['formacion']);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Activa o desactiva al empleado alternando `persona.estado`, porque `empleado` no tiene estado.
     * Conserva $field solo por la firma del padre; desactivarlo también inactiva a la persona en sus otros roles.
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
     * Devuelve el id_persona de esa cédula o, si no existe, crea la persona y devuelve el nuevo id.
     * El SELECT previo es una optimización; la unicidad la da el UNIQUE de la BD, cuya violación (SQLSTATE 23000)
     * se traduce a DocumentoDuplicadoException para responder 422 en vez de 500.
     *
     * @throws DocumentoDuplicadoException Si la cédula ya existe en `persona`.
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

        try {
            $this->ejecutar($this->pdo->prepare($sql), $persona);
        } catch (PDOException $e) {
            // Solo es "cédula duplicada" si el motor señala numero_documento; otras violaciones siguen como error genérico.
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'numero_documento')) {
                throw new DocumentoDuplicadoException(
                    'Ya existe una persona registrada con esa cédula.',
                    0,
                    $e
                );
            }
            throw $e;
        }

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Deja en $data solo las columnas permitidas de `persona` y convierte a NULL las opcionales vacías.
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
     * Reemplaza la formación con borrado lógico: pone las filas anteriores en INACTIVO y agrega las nuevas como ACTIVO.
     * Cada fila debe traer id_titulo, id_grado_academico e id_institucion (NOT NULL); si falta alguno, el INSERT falla
     * y se revierte la transacción. Asume que `formacion_academica` tiene columna `estado`.
     *
     * @param array $filas Lista de {id_titulo, id_grado_academico, id_institucion, fecha_obtencion}.
     */
    private function reemplazarFormacion(int $idEmpleado, array $filas): void
    {
        $this->ejecutar(
            $this->pdo->prepare(
                "UPDATE formacion_academica SET estado = 'INACTIVO' WHERE id_empleado = :id_empleado"
            ),
            ['id_empleado' => $idEmpleado]
        );

        $sql = 'INSERT INTO formacion_academica
                    (id_empleado, id_titulo, id_grado_academico, id_institucion, fecha_obtencion, estado)
                VALUES
                    (:id_empleado, :id_titulo, :id_grado_academico, :id_institucion, :fecha_obtencion, \'ACTIVO\')';

        $stmt = $this->pdo->prepare($sql);

        foreach ($filas as $fila) {
            $this->ejecutar($stmt, [
                'id_empleado'        => $idEmpleado,
                'id_titulo'          => (int) ($fila['id_titulo'] ?? 0),
                'id_grado_academico' => (int) ($fila['id_grado_academico'] ?? 0),
                'id_institucion'     => (int) ($fila['id_institucion'] ?? 0),
                'fecha_obtencion'    => $fila['fecha_obtencion'] ?? null,
            ]);
        }
    }

    /**
     * Ejecuta la sentencia y, si falla sin lanzar excepción, la lanza para que la transacción se revierta.
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