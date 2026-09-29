<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AniosEscolares;
use App\Exceptions\DatoFamiliarIncompletoException;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Support\Documento;
use App\Support\Instituciones;
use App\Traits\ContactoDireccionTrait;
use App\Traits\CupoEstructuraTrait;
use App\Traits\FotoSubidaTrait;
use InvalidArgumentException;
use Throwable;

/**
 * Gestión de inscripciones (inscripcion ↔ matricula ↔ estudiante): el alta crea persona, estudiante, matrícula e inscripción en una transacción.
 * Opcionalmente también contacto, dirección, ficha médica y familiares.
 * La inscripción no tiene columna de estado: todo registro creado por el wizard se considera "completa".
 */
class InscripcionesModel extends BaseModel implements Listable, Creatable
{
    // Combinaciones del año y cupos: inscripción y ratificación leen lo mismo, así el número de la secretaria coincide con el del servidor.
    use CupoEstructuraTrait;

    // Para borrar la foto subida cuando la transacción del alta falla.
    use FotoSubidaTrait;
    // Upsert de contacto y dirección de los familiares, compartido con el módulo de empleados.
    use ContactoDireccionTrait;

    protected string $table = 'inscripcion';
    protected string $primaryKey = 'id_inscripcion';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT i.id_inscripcion AS id,
                       COALESCE(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido), 'Estudiante') AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       p.foto AS avatar_img,
                       COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       DATE_FORMAT(m.fecha_matricula, '%d/%m/%Y') AS fecha_inscripcion,
                       'completa' AS estado,
                       COALESCE(a.nombre_periodo, '') AS anio_escolar
                FROM inscripcion i
                INNER JOIN matricula m ON m.id_matricula = i.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.fecha_matricula DESC, p.primer_apellido, p.primer_nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id']          = (int) $row['id'];
            $row['avatar_color'] = 'primary';
            $row['iniciales']   = $row['iniciales'] ?: 'ES';
        }
        return $rows;
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->filters($filters);

        $sql = "SELECT COUNT(*) FROM inscripcion i
                INNER JOIN matricula m ON m.id_matricula = i.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getStats(): array
    {
        $inscritosAnio = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM inscripcion i
             INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
             INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
             WHERE a.estado = 'ACTIVO'"
        )->fetchColumn();

        $trendInscritos = 0;
        $trend = $this->pdo->query(
            "SELECT
                COALESCE(SUM(MONTH(m.fecha_matricula) = MONTH(CURDATE())), 0) AS mes_actual,
                COALESCE(SUM(MONTH(m.fecha_matricula) = MONTH(CURDATE()) - 1), 0) AS mes_anterior
             FROM inscripcion i
             INNER JOIN matricula m ON m.id_matricula = i.id_matricula
             WHERE YEAR(m.fecha_matricula) = YEAR(CURDATE())"
        )->fetch(\PDO::FETCH_ASSOC);
        $mesActual  = (int) ($trend['mes_actual'] ?? 0);
        $mesAnterior = (int) ($trend['mes_anterior'] ?? 0);
        if ($mesAnterior > 0) {
            $trendInscritos = (int) round((($mesActual - $mesAnterior) / $mesAnterior) * 100);
        }

        $capacidad = (int) $this->pdo->query('SELECT COALESCE(SUM(capacidad_maxima), 0) FROM estructura_academica')->fetchColumn();
        $inscritos = (int) $this->pdo->query('SELECT COUNT(*) FROM inscripcion')->fetchColumn();
        $cuposDisponibles = max(0, $capacidad - $inscritos);

        $inscripcionesMes = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM matricula m
             WHERE MONTH(m.fecha_matricula) = MONTH(CURDATE()) AND YEAR(m.fecha_matricula) = YEAR(CURDATE())"
        )->fetchColumn();

        return [
            'inscritosAnio'    => $inscritosAnio,
            'trendInscritos'   => $trendInscritos,
            'cuposDisponibles' => $cuposDisponibles,
            'inscripcionesMes' => $inscripcionesMes,
        ];
    }

    /** Listas de strings para los filtros de la tabla. */
    public function getOptions(string $type): array
    {
        switch ($type) {
            case 'grados':
                return $this->pdo->query("SELECT nombre FROM grado WHERE estado = 'ACTIVO' ORDER BY nombre")
                                ->fetchAll(\PDO::FETCH_COLUMN);
            case 'secciones':
                return $this->pdo->query("SELECT nombre FROM seccion WHERE estado = 'ACTIVO' ORDER BY nombre")
                                ->fetchAll(\PDO::FETCH_COLUMN);
            default:
                return [];
        }
    }

    /** Opciones id + nombre para los selects del modal wizard. */
    public function getOptionsForModal(string $table): array
    {
        $allowed = [
            'grado'   => 'id_grado',
            'seccion' => 'id_seccion',
            'turno'   => 'id_turno',
        ];
        if (!isset($allowed[$table])) {
            return [];
        }
        return $this->pdo->query("SELECT {$allowed[$table]} AS id, nombre FROM {$table} WHERE estado = 'ACTIVO' ORDER BY nombre")
                         ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Instituciones de procedencia del estudiante: solo etapas del sistema escolar (preescolar o escuela), no una universidad.
     * Usa App\Support\Instituciones, la misma clase que el wizard de empleados usa para el caso contrario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInstituciones(): array
    {
        $marcadores = implode(', ', array_fill(0, count(Instituciones::TIPOS_DE_ESTUDIANTE), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT i.id_institucion AS id, i.nombre
             FROM institucion i
             WHERE i.estado = 'ACTIVO'
               AND i.tipo IN ({$marcadores})
             ORDER BY i.nombre"
        );
        $stmt->execute(Instituciones::TIPOS_DE_ESTUDIANTE);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tipos de institución que se pueden registrar desde el wizard de inscripciones: solo los de una etapa del estudiante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTiposInstitucionEstudiante(): array
    {
        return (new InstitucionModel($this->pdo))
            ->tiposFormulario(Instituciones::TIPOS_DE_ESTUDIANTE);
    }

    /**
     * Tipo (código del enum Instituciones) de la institución de procedencia elegida en el paso 2.
     * El servidor lo necesita para la regla de grado, que varía por tipo: solo la escuela exige un grado anterior de 1 a 6.
     */
    public function getTipoInstitucion(int $idInstitucion): ?string
    {
        return (new InstitucionModel($this->pdo))->tipoDeInstitucion($idInstitucion);
    }

    /**
     * Nombre del catálogo `grado` de un id, para deducir su número (Instituciones::ordinalGrado).
     * No se compara por id porque los ids del catálogo no siguen el orden de los grados.
     */
    public function getNombreGrado(int $idGrado): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT nombre FROM grado WHERE id_grado = :id AND estado = 'ACTIVO'"
        );
        $stmt->execute(['id' => $idGrado]);
        $nombre = $stmt->fetchColumn();

        return $nombre === false ? null : (string) $nombre;
    }

    /** Parentescos, niveles de instrucción y ocupaciones para el sub-modal de familiares. */
    public function getFamiliarCatalogs(): array
    {
        return [
            'parentescos'        => $this->pdo->query("SELECT id_parentesco AS id, nombre FROM parentesco WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC),
            'nivelInstruccion'   => $this->pdo->query("SELECT id_nivel_instruccion AS id, nombre FROM nivel_instruccion WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC),
            'ocupaciones'        => $this->pdo->query("SELECT id_ocupacion AS id, nombre FROM ocupacion WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Estados para el <select> de la cascada de dirección del familiar; municipio y parroquia llegan por AJAX porque son catálogos grandes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEstados(): array
    {
        return $this->pdo->query("SELECT id_estado, nombre FROM estado WHERE estado = 'ACTIVO' ORDER BY nombre")
                         ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Parroquias activas para el paso de dirección del wizard. */
    public function getParroquias(): array
    {
        return $this->pdo->query("SELECT id_parroquia AS id, nombre FROM parroquia WHERE estado = 'ACTIVO' ORDER BY nombre")
                         ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** ¿Ya existe un estudiante con ese número de documento? */
    public function existeDocumento(string $documento): bool
    {
        if (trim($documento) === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT e.id_estudiante
             FROM estudiante e
             INNER JOIN persona p ON p.id_persona = e.id_persona
             WHERE p.numero_documento = :doc LIMIT 1"
        );
        $stmt->execute(['doc' => trim($documento)]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Devuelve el id de la persona del familiar, creándola solo si su documento es nuevo.
     * Un representante con varios hijos no debe duplicarse en `persona` (el UNIQUE de numero_documento lo rechazaría);
     * si ya existe se reutiliza sin tocar sus datos personales, y solo se actualizan contacto y domicilio con los upsert de abajo.
     *
     * @param array $nombres primer_nombre, segundo_nombre, primer_apellido, segundo_apellido.
     */
    private function buscarOCrearPersonaFamiliar(
        string $tipoDocumento,
        string $documento,
        array $nombres,
        string $sexo,
        string $fechaNacimiento
    ): int {
        $fechaNacimiento = $this->normalizarFechaNacimiento($fechaNacimiento);

        $stmt = $this->pdo->prepare('SELECT id_persona FROM persona WHERE numero_documento = :documento LIMIT 1');
        $stmt->execute(['documento' => $documento]);
        $existente = (int) $stmt->fetchColumn();

        // En un alta nueva la fecha es obligatoria (columna NOT NULL) y no se inventa una de relleno.
        if ($existente === 0 && $fechaNacimiento === '') {
            throw new InvalidArgumentException(
                'Indique la fecha de nacimiento del familiar.'
            );
        }

        // Una persona existente se reutiliza tal cual: cambiar sus nombres, sexo o fecha desde aquí dejaría registros inconsistentes.
        if ($existente > 0) {
            return $existente;
        }

        $this->pdo->prepare(
            "INSERT INTO persona (tipo_documento, numero_documento, fecha_nacimiento,
             primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, estado)
             VALUES (:tipo, :documento, :fecha_nacimiento,
             :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :sexo, 'ACTIVO')"
        )->execute([
            'tipo'              => $tipoDocumento,
            'documento'         => $documento,
            'fecha_nacimiento'  => $fechaNacimiento,
            'primer_nombre'     => $nombres['primer_nombre'],
            'segundo_nombre'    => $nombres['segundo_nombre'] ?: null,
            'primer_apellido'   => $nombres['primer_apellido'],
            'segundo_apellido'  => $nombres['segundo_apellido'] ?: null,
            'sexo'              => $sexo,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca una persona por documento para el sub-modal de familiares, para que el wizard cargue sus datos si ya está registrada.
     * Devuelve null si no existe; si existe, nombres, sexo, contacto, domicilio y cuántos estudiantes representa.
     * La cédula se normaliza (App\Support\Documento) para que "V-12.345.678" encuentre a la misma persona que "12345678".
     */
    public function buscarPersonaPorDocumento(string $documento): ?array
    {
        $documento = Documento::normalizar($documento);

        if ($documento === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT p.id_persona, p.tipo_documento, p.numero_documento, p.fecha_nacimiento,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, p.sexo
             FROM persona p
             WHERE p.numero_documento = :documento
             LIMIT 1"
        );
        $stmt->execute(['documento' => $documento]);
        $persona = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$persona) {
            return null;
        }

        $id = (int) $persona['id_persona'];

        $stmt = $this->pdo->prepare(
            'SELECT codigo_telefono_principal, numero_telefono_principal,
                    codigo_telefono_alternativo, numero_telefono_alternativo, correo_electronico
             FROM contacto WHERE id_persona = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $contacto = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->pdo->prepare(
            'SELECT d.sector_urbanizacion, d.calle_avenida, d.nro_casa_apto, d.punto_referencia, d.id_parroquia
             FROM direccion d WHERE d.id_persona = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $direccion = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        // `direccion` solo guarda id_parroquia: estado y municipio se deducen de ella para dejar la cascada completa.
        if ($direccion) {
            $ubicacion = (new UbicacionModel($this->pdo))->ubicacionDeParroquia((int) $direccion['id_parroquia']);
            $direccion['id_estado']   = $ubicacion['estado'] ?? 0;
            $direccion['id_municipio'] = $ubicacion['municipio'] ?? 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM persona_estudiante WHERE id_persona = :id AND es_representante_legal = 1'
        );
        $stmt->execute(['id' => $id]);

        return [
            'persona' => [
                'tipo_documento'   => $persona['tipo_documento'],
                'numero_documento' => $persona['numero_documento'],
                'primer_nombre'    => $persona['primer_nombre'] ?? '',
                'segundo_nombre'   => $persona['segundo_nombre'] ?? '',
                'primer_apellido'  => $persona['primer_apellido'] ?? '',
                'segundo_apellido' => $persona['segundo_apellido'] ?? '',
                'sexo'             => $persona['sexo'] ?? 'M',
                'fecha_nacimiento' => $persona['fecha_nacimiento'] ?? '',
            ],
            'contacto' => $contacto === null ? null : [
                'codigo'     => $contacto['codigo_telefono_principal'] ?? '',
                'numero'     => $contacto['numero_telefono_principal'] ?? '',
                'codigo_alt' => $contacto['codigo_telefono_alternativo'] ?? '',
                'numero_alt' => $contacto['numero_telefono_alternativo'] ?? '',
                'correo'     => $contacto['correo_electronico'] ?? '',
            ],
            'direccion' => $direccion === null ? null : [
                'sector'     => $direccion['sector_urbanizacion'] ?? '',
                'calle'      => $direccion['calle_avenida'] ?? '',
                'casa'       => $direccion['nro_casa_apto'] ?? '',
                'referencia' => $direccion['punto_referencia'] ?? '',
                'parroquia'  => (int) ($direccion['id_parroquia'] ?? 0),
                // Estado y municipio deducidos de la parroquia, para dejar los tres niveles elegidos.
                'estado'     => (int) ($direccion['id_estado'] ?? 0),
                'municipio'  => (int) ($direccion['id_municipio'] ?? 0),
            ],
            'representante_de' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
     * Orden del próximo hijo de un representante legal, para la cédula escolar [letra][orden][año][7 dígitos del representante].
     * Se deduce contando los hijos del representante con el mismo AÑO de nacimiento (no fecha completa, porque la cédula lleva solo 2 dígitos del año),
     * incluidos los ya inactivos, para que el orden no cambie si un hermano se gradúa o se retira.
     *
     * @param string $cedulaRepresentante Cédula del representante (con o sin letra), porque el familiar del wizard aún no está en la base.
     */
    public function proximoOrdenHermano(string $cedulaRepresentante, string $fechaNacimiento): int
    {
        // persona.numero_documento se guarda solo con dígitos; sin normalizar el conteo saldría en cero y dos hermanos tendrían orden 1.
        $cedula = Documento::normalizar($cedulaRepresentante);
        $anio   = (int) substr($fechaNacimiento, 0, 4);

        if ($cedula === '' || $anio <= 0) {
            return 1;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT e.id_estudiante)
             FROM persona_estudiante pe
             INNER JOIN persona rep ON rep.id_persona = pe.id_persona
             INNER JOIN estudiante e ON e.id_estudiante = pe.id_estudiante
             INNER JOIN persona est ON est.id_persona = e.id_persona
             WHERE pe.es_representante_legal = 1
               AND (rep.numero_documento = :cedula
                    OR LPAD(rep.numero_documento, 8, '0') = :cedulaConCero)
               AND YEAR(est.fecha_nacimiento) = :anio"
        );
        // Dos marcadores distintos: con prepares reales MySQL no admite repetir el mismo en una consulta.
        $stmt->execute([
            'cedula'       => $cedula,
            'cedulaConCero' => str_pad($cedula, 8, '0', STR_PAD_LEFT),
            'anio'         => $anio,
        ]);

        return ((int) $stmt->fetchColumn()) + 1;
    }

    /** Un segundo nombre/apellido, o null si no se escribió. */
    private function segundoNombre(string $valor): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : mb_substr($valor, 0, 50);
    }

    /**
     * Una fecha de nacimiento en Y-m-d, o '' si no vino ninguna utilizable.
     * No inventa una fecha: persona.fecha_nacimiento es NOT NULL y una falsa deja basura difícil de detectar.
     */
    private function normalizarFechaNacimiento(string $fecha): string
    {
        $fecha = trim($fecha);
        $corte = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        return $corte !== false && $corte->format('Y-m-d') === $fecha ? $fecha : '';
    }

    /** Año escolar activo para el paso 3 del wizard (solo lectura). */
    public function getAnioActivo(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_anio_escolar AS id, nombre_periodo AS nombre
             FROM anio_escolar WHERE estado = 'ACTIVO'
             ORDER BY anio_inicio DESC LIMIT 1"
        );
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['id' => '', 'nombre' => ''];
    }

    /**
     * Datos del paso 3 (Académico) del wizard; las opciones salen de `estructura_academica` y no del catálogo,
     * así solo se ofrecen las combinaciones registradas para el año activo.
     * Cada combinación trae lo ocupado y lo que queda, para mostrar deshabilitado el turno lleno.
     *
     * @return array{anio:array<string,mixed>, grados:array, secciones:array,
     *               turnos:array, combinaciones:array}
     */
    public function opcionesPasoAcademico(): array
    {
        $anio     = $this->getAnioActivo();
        $opciones = $this->opcionesAcademicas((int) $anio['id']);

        return [
            'anio'          => $anio,
            'grados'        => $opciones['grados'],
            'secciones'     => $opciones['secciones'],
            'turnos'        => $opciones['turnos'],
            'combinaciones' => $opciones['combinaciones'],
        ];
    }

    /**
     * La combinación grado + sección + turno del año con su disponibilidad, o null si no está registrada (ver CupoEstructuraTrait).
     *
     * @return array<string, mixed>|null
     */
    public function combinacionDeGradoSeccionTurno(int $idGrado, int $idSeccion, int $idTurno, int $idAnioEscolar): ?array
    {
        return $this->combinacionConCupo($idGrado, $idSeccion, $idTurno, $idAnioEscolar);
    }

    /**
     * Crea una inscripción completa en una transacción: persona, estudiante, matrícula, inscripción y, opcionalmente,
     * talla, ficha médica y familiares. Recibe los datos ya normalizados por el controlador y relanza cualquier error tras el rollback.
     */
    public function create(array $data): bool
    {
        // La foto llega en $data['foto'] (ver extractData() del controlador); el resto de la persona va en $data['persona'].
        $persona = $data['persona'] ?? [];
        $persona['foto'] = $data['foto'] ?? null;

        try {
            $this->pdo->beginTransaction();

            /* 0. Se bloquea la estructura antes de escribir o leer nada: matricula tiene FK contra ella y su INSERT toma un lock compartido,
               así que un FOR UPDATE posterior lo subiría a exclusivo y varias secretarias a la vez caerían en deadlock (1213).
               Además, en REPEATABLE READ una lectura previa fija la foto de la transacción y el conteo de cupos saldría viejo. */
            $estructuraId = (int) ($data['id_estructura_academica'] ?? 0);
            if ($estructuraId <= 0) {
                throw new \RuntimeException('Debe seleccionar una estructura académica válida.');
            }
            $disponibilidad = $this->reservarCupo($estructuraId);
            $anioEscolar = (int) ($disponibilidad['id_anio_escolar'] ?? 0);
            if ($anioEscolar <= 0) {
                throw new \RuntimeException('La estructura académica seleccionada no tiene un año escolar válido.');
            }

            // 1. Persona + estudiante (el wizard siempre registra estudiante nuevo)
            $stmt = $this->pdo->prepare(
                "INSERT INTO persona (tipo_documento, numero_documento, fecha_nacimiento,
                 primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo, estado, foto)
                 VALUES (:tipo_documento, :numero_documento, :fecha_nacimiento,
                 :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :sexo, 'ACTIVO', :foto)"
            );
            $stmt->execute([
                'tipo_documento'   => $persona['tipo_documento'],
                'numero_documento' => mb_substr((string) $persona['numero_documento'], 0, 20),
                'fecha_nacimiento' => $persona['fecha_nacimiento'],
                'primer_nombre'    => mb_substr((string) $persona['primer_nombre'], 0, 50),
                'segundo_nombre'   => mb_substr((string) ($persona['segundo_nombre'] ?? ''), 0, 50) ?: null,
                'primer_apellido'  => mb_substr((string) $persona['primer_apellido'], 0, 50),
                'segundo_apellido' => mb_substr((string) ($persona['segundo_apellido'] ?? ''), 0, 50) ?: null,
                'sexo'             => $persona['sexo'],
                'foto'             => $persona['foto'] ?? null,
            ]);
            $personaId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('INSERT INTO estudiante (id_persona) VALUES (:persona)')
                      ->execute(['persona' => $personaId]);
            $estudianteId = (int) $this->pdo->lastInsertId();

            // 2. Matrícula
            // El wizard no manda fecha: la matrícula se registra con la del día.
            $fecha = ($data['fecha_matricula'] ?? '') ?: date('Y-m-d');

            // La estructura se valida antes de insertar la matrícula porque es dato obligatorio de la fila (año y sección donde cursa).
            $estructuraId = (int) ($data['id_estructura_academica'] ?? 0);
            if ($estructuraId <= 0) {
                throw new \RuntimeException('Debe seleccionar una estructura académica válida.');
            }
            $stmt = $this->pdo->prepare('SELECT id_anio_escolar FROM estructura_academica WHERE id_estructura_academica = :id');
            $stmt->execute(['id' => $estructuraId]);
            $anioEscolar = (int) ($stmt->fetchColumn() ?: 0);
            if ($anioEscolar <= 0) {
                throw new \RuntimeException('La estructura académica seleccionada no tiene un año escolar válido.');
            }

            /* Primer ingreso: sin historial solo se entra por INSCRIPCION (tipo fijado por App\Support\Matriculas) y nunca se repite.
               La llave única (estudiante, año, tipo) no frena una segunda INSCRIPCION en un año nuevo,
               así que se comprueba que no exista ninguna matrícula previa. */
            $previas = $this->pdo->prepare(
                'SELECT tipo, id_anio_escolar FROM matricula WHERE id_estudiante = :e ORDER BY id_anio_escolar DESC LIMIT 1'
            );
            $previas->execute(['e' => $estudianteId]);
            $previa = $previas->fetch(\PDO::FETCH_ASSOC);

            if ($previa) {
                throw new \RuntimeException(
                    'Este estudiante ya tiene una matrícula de tipo ' . $previa['tipo']
                    . ', así que no se inscribe: lo que corresponde es ratificarlo.'
                );
            }

            $this->pdo->prepare(
                "INSERT INTO matricula (id_estudiante, fecha_matricula, id_anio_escolar, id_estructura_academica, tipo)
                 VALUES (:estudiante, :fecha, :anio, :estructura, 'INSCRIPCION')"
            )->execute([
                'estudiante' => $estudianteId,
                'fecha'      => $fecha,
                'anio'       => $anioEscolar,
                'estructura' => $estructuraId,
            ]);
            $matriculaId = (int) $this->pdo->lastInsertId();

            // 3. Inscripción (el cupo ya está reservado y bloqueado arriba)
            $idInstitucion = (int) ($data['id_institucion'] ?? 0);
            if ($idInstitucion <= 0) {
                $idInstitucion = (int) $this->pdo->query('SELECT id_institucion FROM institucion WHERE estado = "ACTIVO" ORDER BY nombre LIMIT 1')->fetchColumn();
            }
            if ($idInstitucion <= 0) {
                throw new \RuntimeException('No hay instituciones registradas para completar la inscripción.');
            }

            $this->pdo->prepare(
                'INSERT INTO inscripcion (id_matricula, id_estudiante, id_estructura_academica, ult_grado_cursado,
                 es_primer_ingreso, motivo_egreso, anio_escolar_pre, id_institucion)
                 VALUES (:matricula, :estudiante, :estructura, :ult_grado, :primer_ingreso, :motivo, :anio_pre, :institucion)'
            )->execute([
                'matricula'      => $matriculaId,
                'estudiante'     => $estudianteId,
                'estructura'     => $estructuraId,
                'ult_grado'      => mb_substr((string) ($data['ult_grado_cursado'] ?? ''), 0, 20) ?: null,
                /* `es_primer_ingreso` es un dato de procedencia ("Primera vez" o "Traslado"), no de proceso; no depende de que la inscripción sea única.
                   Los que vienen de otro colegio llenan ult_grado_cursado, anio_escolar_pre y motivo_egreso.
                   El default espeja el de la columna; el controlador siempre manda el dato (el checkbox sin marcar lo traduce a 0). */
                'primer_ingreso' => (int) ($data['es_primer_ingreso'] ?? 1),
                'motivo'         => mb_substr((string) ($data['motivo_egreso'] ?? ''), 0, 100) ?: null,
                'anio_pre'       => mb_substr((string) ($data['anio_escolar_pre'] ?? ''), 0, 9) ?: null,
                'institucion'    => $idInstitucion,
            ]);
            $inscripcionId = (int) $this->pdo->lastInsertId();

            // 4. Talla uniforme (opcional). El estudiante es menor: su contacto y dirección van con el representante, en insertFamiliares().
            if ($this->tieneTalla($data['talla_uniforme'] ?? [])) {
                $talla = $data['talla_uniforme'];
                // UNIQUE en id_estudiante: un reingreso actualiza la talla en vez de fallar.
                // Las tres tallas son NOT NULL y fecha_actualizacion no tiene default (de ahí '' y CURDATE()).
                $this->pdo->prepare(
                    'INSERT INTO talla_uniforme (id_estudiante, talla_camisa, talla_pantalon, talla_zapato, observaciones, fecha_actualizacion)
                     VALUES (:estudiante, :camisa, :pantalon, :zapato, :observaciones, CURDATE())
                     ON DUPLICATE KEY UPDATE
                        talla_camisa = VALUES(talla_camisa),
                        talla_pantalon = VALUES(talla_pantalon),
                        talla_zapato = VALUES(talla_zapato),
                        observaciones = VALUES(observaciones),
                        fecha_actualizacion = CURDATE()'
                )->execute([
                    'estudiante'    => $estudianteId,
                    'camisa'        => (string) ($talla['camisa'] ?? ''),
                    'pantalon'      => (string) ($talla['pantalon'] ?? ''),
                    'zapato'        => (string) ($talla['zapato'] ?? ''),
                    'observaciones' => $talla['observaciones'] ?? null,
                ]);
            }

            // 5. Ficha médica (opcional)
            if (!empty($data['ficha_medica'])) {
                $this->pdo->prepare(
                    "INSERT INTO ficha_medica (id_estudiante, condiciones_especiales, medicamentos,
                     observaciones, alergias, enfermedades, tipo_sangre, fecha_actualizacion)
                     VALUES (:estudiante, :condiciones, :medicamentos, :observaciones, :alergias, :enfermedades, :sangre, CURDATE())"
                )->execute([
                    'estudiante'   => $estudianteId,
                    'condiciones'  => $data['ficha_medica']['condiciones_especiales'] ?? null,
                    'medicamentos' => $data['ficha_medica']['medicamentos'] ?? null,
                    'observaciones' => $data['ficha_medica']['observaciones'] ?? null,
                    'alergias'     => $data['ficha_medica']['alergias'] ?? null,
                    'enfermedades' => $data['ficha_medica']['enfermedades'] ?? null,
                    'sangre'       => $data['ficha_medica']['tipo_sangre'] ?? null,
                ]);
            }

            /* 6. Familiares: ya no es best-effort; si algún familiar no declara parentesco sin ser "no es familiar",
               se lanza DatoFamiliarIncompletoException y create() hace rollBack. */
            $this->insertFamiliares($estudianteId, $data['familiares'] ?? []);

            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // La fila no se guardó: se elimina la foto subida para no dejar archivos huérfanos (mismo criterio que EmpleadoController).
            $this->borrarFoto($data['foto'] ?? null);
            error_log('InscripcionesModel::create(): ' . $e->getMessage());
            throw $e;
        }
    }

    /** Detalle de una inscripción para la vista "ver". */
    public function getById(int $id): ?array
    {
        $sql = "SELECT i.id_inscripcion AS id, i.es_primer_ingreso, i.ult_grado_cursado, i.motivo_egreso, i.anio_escolar_pre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre,
                       DATE_FORMAT(m.fecha_matricula, '%d/%m/%Y') AS fecha_inscripcion,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno, a.nombre_periodo AS anio_escolar,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       p.foto AS avatar_img
                FROM inscripcion i
                INNER JOIN matricula m ON m.id_matricula = i.id_matricula
                INNER JOIN estudiante e ON e.id_estudiante = m.id_estudiante
                INNER JOIN persona p ON p.id_persona = e.id_persona
                INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
                INNER JOIN grado g ON g.id_grado = ea.id_grado
                INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
                INNER JOIN turno t ON t.id_turno = ea.id_turno
                INNER JOIN anio_escolar a ON a.id_anio_escolar = ea.id_anio_escolar
                WHERE i.id_inscripcion = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['avatar_color'] = 'primary';
        $row['iniciales'] = $row['iniciales'] ?: 'ES';
        return $row;
    }

    /**
     * Indica si el bloque de talla trae algo que guardar; si el wizard no lo completó no se crea la fila, para no dejar un registro vacío.
     *
     * @param array<string, mixed> $talla
     */
    private function tieneTalla(array $talla): bool
    {
        foreach (['camisa', 'pantalon', 'zapato', 'observaciones'] as $campo) {
            if (trim((string) ($talla[$campo] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Contacto del familiar, contra la persona recién registrada: traduce los nombres cortos del wizard (codigo, numero, codigo_alt...) a columnas
     * y delega el upsert en ContactoDireccionTrait (el mismo de EmpleadoModel).
     * Sin teléfono principal no se escribe nada, porque es obligatorio para cualquier familiar; su validez la revisa ContactoValidacionTrait en el controlador.
     */
    private function upsertContactoPersona(int $personaId, array $contacto): void
    {
        $codigo = trim((string) ($contacto['codigo'] ?? ''));
        $numero = trim((string) ($contacto['numero'] ?? ''));

        if ($codigo === '' || $numero === '') {
            return;
        }

        $this->guardarContacto($personaId, [
            'codigo_telefono_principal'   => $codigo,
            'numero_telefono_principal'   => $numero,
            'codigo_telefono_alternativo' => $contacto['codigo_alt'] ?? '',
            'numero_telefono_alternativo' => $contacto['numero_alt'] ?? '',
            'correo_electronico'          => $contacto['correo'] ?? '',
        ]);
    }

    /**
     * Domicilio del familiar, contra la persona recién registrada; traduce los nombres del formulario y delega el upsert en ContactoDireccionTrait.
     * Es opcional: si falta sector, calle, número o parroquia no se escribe nada, para no dejar una fila a medias en `direccion`.
     */
    private function upsertDireccionPersona(int $personaId, array $direccion): void
    {
        $sector    = trim((string) ($direccion['sector'] ?? ''));
        $calle     = trim((string) ($direccion['calle'] ?? ''));
        $casa      = trim((string) ($direccion['casa'] ?? ''));
        // La clave es `id_parroquia` (la emite decodificarFamiliares() y usa guardarDireccion()); leer solo `parroquia` descartaba la dirección sin avisar.
        $parroquia = (int) ($direccion['id_parroquia'] ?? $direccion['parroquia'] ?? 0);

        if ($sector === '' || $calle === '' || $casa === '' || $parroquia <= 0) {
            return;
        }

        $this->guardarDireccion($personaId, [
            'sector_urbanizacion' => $sector,
            'calle_avenida'        => $calle,
            'nro_casa_apto'        => $casa,
            'punto_referencia'     => $direccion['referencia'] ?? '',
            'id_parroquia'         => $parroquia,
        ]);
    }
    private function insertFamiliares(int $estudianteId, array $familiares): void
    {
        foreach ($familiares as $familiar) {
            // El parentesco es opcional: quien solo está autorizado para retirar se guarda sin vínculo (id_parentesco NULL, migración 0002).
            // Se lee arriba si es representante legal porque lo usan dos reglas de abajo (domicilio y datos personales).
            $esRepresentante = (int) ($familiar['representante_principal'] ?? 0) === 1;
            $esNoFamiliar = (int) ($familiar['solo_autorizado'] ?? 0) === 1;
            $parentescoId = $this->resolveCatalogId('parentesco', 'id_parentesco', $familiar['parentesco'] ?? '');

            /* Sin parentesco solo se guarda si se marcó "no es familiar"; resolveCatalogId() da null tanto si no vino el nombre
               como si no existe en el catálogo, y este segundo caso dejaba filas "Sin parentesco" en silencio.
               Se comprueba aquí, único sitio que escribe, y se lanza excepción para que create() haga rollBack. */
            if ($parentescoId === null && !$esNoFamiliar) {
                throw new DatoFamiliarIncompletoException(
                    'No se pudo resolver el parentesco de "' . ($familiar['nombre_referencia'] ?? 'un familiar')
                    . '". Revise el catálogo de parentescos o márquelo como "no es familiar".'
                );
            }
            /* Nivel de instrucción y ocupación (por nombre) son obligatorios solo para el representante legal, el padre o la madre;
               a los demás no se les pregunta y van NULL (migración 0005), en lugar de un dato falso tomado de la primera fila del catálogo.
               La condición usa el nombre del parentesco y no su id, igual que el controlador, porque los ids pueden cambiar. */
            $pideDatosPersonales = $esRepresentante || $this->esPadreOMadre((string) ($familiar['parentesco'] ?? ''));
            $nivelId = $pideDatosPersonales
                ? $this->resolveCatalogId('nivel_instruccion', 'id_nivel_instruccion', (string) ($familiar['nivel_instruccion'] ?? ''))
                : null;
            $ocupacionId = $pideDatosPersonales
                ? $this->resolveCatalogId('ocupacion', 'id_ocupacion', (string) ($familiar['ocupacion'] ?? ''))
                : null;

            // Si los pide y no vienen o no existen en el catálogo, el payload es incoherente y se rechaza (red que no depende del cliente).
            if ($pideDatosPersonales && ($nivelId === null || $ocupacionId === null)) {
                throw new DatoFamiliarIncompletoException(
                    'No se pudo resolver el nivel de instrucción o la ocupación de "'
                    . ($familiar['nombre_referencia'] ?? 'un familiar') . '". Revise los catálogos.'
                );
            }
            // NULL es válido (no se preguntó); 0 no se puede guardar en una FK. resolveCatalogId() nunca devuelve 0, así que esto filtra un 0 externo.
            if ($nivelId === 0 || $ocupacionId === 0) {
                throw new DatoFamiliarIncompletoException(
                    'Nivel de instrucción u ocupación con un id que no existe.'
                );
            }

            $nombre = trim($familiar['nombre_referencia'] ?? '');
            if ($nombre === '') {
                continue;
            }
            [$primerNombre, $primerApellido] = $this->splitNombre($nombre);

            // Tipo de documento y sexo salen del modal; hardcodear 'V' y 'M' registraba como venezolano a un representante extranjero y rompía la letra de la cédula escolar.
            $tipoDocumento = mb_strtoupper(trim((string) ($familiar['tipo_documento'] ?? '')), 'UTF-8');
            if (!in_array($tipoDocumento, ['V', 'E'], true)) {
                $tipoDocumento = 'V';
            }
            $sexo = ($familiar['sexo'] ?? 'M') === 'F' ? 'F' : 'M';

            $documento = trim((string) ($familiar['numero_documento'] ?? ''));

            if ($documento === '' && $esRepresentante) {
                // El representante aporta los 7 dígitos de la cédula escolar; sin documento real no hay cédula (validate() ya avisa antes).
                continue;
            }

            // Solo los familiares que no son el representante toleran un documento sintético, porque no interviene en la cédula escolar.
            if ($documento === '') {
                $documento = 'FAM' . $estudianteId . '-' . random_int(100000, 999999);
            }
            $documento = mb_substr($documento, 0, 20);

            // La fecha de nacimiento es obligatoria para el representante y opcional para los demás, pero persona.fecha_nacimiento es NOT NULL: nunca se inventa una de relleno.
            $fechaNacimiento = $this->normalizarFechaNacimiento(
                (string) ($familiar['fecha_nacimiento'] ?? '')
            );

            $personaId = $this->buscarOCrearPersonaFamiliar(
                $tipoDocumento,
                $documento,
                [
                    'primer_nombre'    => $primerNombre,
                    // Los segundos nombres vacíos van como NULL y no como ''.
                    'segundo_nombre'   => $this->segundoNombre($familiar['segundo_nombre'] ?? ''),
                    'primer_apellido'  => $primerApellido,
                    'segundo_apellido' => $this->segundoNombre($familiar['segundo_apellido'] ?? ''),
                ],
                $sexo,
                $fechaNacimiento
            );

            /* ¿La relación ya existe? La tabla tiene UNIQUE (id_persona, id_estudiante) y un INSERT a secas reventaba con el error 1062
               al volver a guardar al mismo familiar. Ahora es alta o actualización según exista,
               y se guarda `estado_relacion`, que permite desactivar un familiar sin borrarlo. */
            $existe = $this->pdo->prepare(
                'SELECT id_persona_estudiante FROM persona_estudiante
                  WHERE id_persona = :persona AND id_estudiante = :estudiante
                  LIMIT 1'
            );
            $existe->execute(['persona' => $personaId, 'estudiante' => $estudianteId]);
            $relacionId = $existe->fetchColumn();

            $estadoRelacion = (string) ($familiar['estado_relacion'] ?? 'ACTIVO');
            if (!in_array($estadoRelacion, ['ACTIVO', 'INACTIVO', 'SUSPENDIDO'], true)) {
                $estadoRelacion = 'ACTIVO';
            }

            if ($relacionId) {
                $this->pdo->prepare(
                    "UPDATE persona_estudiante
                        SET es_representante_legal = :representante,
                            es_autorizado_retiro   = :autorizado,
                            vive_con_estudiante    = :vive_con,
                            id_parentesco          = :parentesco,
                            id_nivel_instruccion   = :nivel,
                            id_ocupacion           = :ocupacion,
                            estado_relacion        = :estado
                      WHERE id_persona_estudiante = :relacion"
                )->execute([
                    'representante' => $esRepresentante ? 1 : 0,
                    'autorizado'    => (int) ($familiar['autorizado_retiros'] ?? 0),
                    'vive_con'      => (int) ($familiar['vive_con_estudiante'] ?? 0),
                    'parentesco'    => $parentescoId,
                    'nivel'         => $nivelId,
                    'ocupacion'     => $ocupacionId,
                    'estado'        => $estadoRelacion,
                    'relacion'      => $relacionId,
                ]);
            } else {
                $this->pdo->prepare(
                  "INSERT INTO persona_estudiante (id_persona, id_estudiante, es_representante_legal,
                   es_autorizado_retiro, vive_con_estudiante, estado_vital, estado_relacion,
                   id_parentesco, id_nivel_instruccion, id_ocupacion)
                   VALUES (:persona, :estudiante, :representante, :autorizado, :vive_con,
                    'VIVO', :estado, :parentesco, :nivel, :ocupacion)"
                )->execute([
                    'persona'      => $personaId,
                    'estudiante'   => $estudianteId,
                    'representante' => $esRepresentante ? 1 : 0,
                    'autorizado'   => (int) ($familiar['autorizado_retiros'] ?? 0),
                    // Si esa persona vive con el menor es dato de la relación, no de la persona (migración 0003).
                    'vive_con'    => (int) ($familiar['vive_con_estudiante'] ?? 0),
                    'estado'      => $estadoRelacion,
                    'parentesco'   => $parentescoId,
                    'nivel'        => $nivelId,
                    'ocupacion'    => $ocupacionId,
                ]);
            }

            /* El teléfono principal es obligatorio para todos (lo exige el controlador).
               El domicilio se guarda solo para el representante legal, para que el menor no tenga dos domicilios;
               la regla vive aquí, único sitio que escribe, porque el payload viene del cliente y puede armarse a mano. */
            if (!empty($familiar['contacto'])) {
                $this->upsertContactoPersona($personaId, $familiar['contacto']);
            }

            if ($esRepresentante && !empty($familiar['direccion'])) {
                $this->upsertDireccionPersona($personaId, $familiar['direccion']);
            }
        }
    }

    /**
     * Indica si ese parentesco es el del padre o el de la madre.
     * Compara por nombre y no por id del catálogo, igual que InscripcionesController::esPadreOMadre y esPadreOMadreSegun en enrollments.js.
     */
    private function esPadreOMadre(string $parentesco): bool
    {
        $nombre = mb_strtolower(trim($parentesco));

        return $nombre === 'madre' || $nombre === 'padre';
    }

    /**
     * Id de un catálogo por su nombre, o null si no viene nombre.
     * Devuelve null y no 0 para distinguir "no eligió" de "eligió algo que no existe", ya que 0 en una FK sería un error de integridad.
     */
    private function resolveCatalogId(string $table, string $idColumn, string $nombre): ?int
    {
        if (trim($nombre) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT {$idColumn} FROM {$table} WHERE nombre = :nombre LIMIT 1");
        $stmt->execute(['nombre' => $nombre]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function splitNombre(string $nombre): array
    {
        $partes = preg_split('/\s+/', trim($nombre));
        if (count($partes) <= 1) {
            return [mb_substr($nombre, 0, 50), $nombre];
        }
        return [mb_substr($partes[0], 0, 50), mb_substr($partes[1], 0, 50)];
    }

    /**
     * El año escolar en curso, que acota la lista a los ingresos de este año; al cambiar de período los del anterior pasan a Ratificación.
     * Sin año en curso devuelve 0 y la lista sale vacía, preferible a mostrar alumnos de cualquier año.
     */
    private function anioEnCurso(): int
    {
        // Sin caché a propósito: si dentro del proceso se abre o cierra un año, un valor guardado haría que inscripciones y ratificación se contradigan.
        return AniosEscolares::idEnCurso($this->pdo);
    }

    private function filters(array $filters): array
    {
        /* La lista es solo del año en curso: al cambiar de año queda vacía y los alumnos anteriores pasan a Ratificación.
           Se filtra por el año de la ESTRUCTURA de la inscripción (cuando entró) y no por el de la matrícula,
           que para un alumno antiguo es el año en curso y no lo sacaría de la lista. */
        $where = ['ea.id_anio_escolar = ' . $this->anioEnCurso()];
        $params = [];

        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {
                $params["q$i"] = '%' . $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
        }
        // El año no es un filtro: la lista es del año en curso, y aceptarlo por URL (?anio=...) ampliaría su alcance sin que la interfaz lo muestre.
        if (!empty($filters['grado'])) {
            $where[] = 'g.nombre = :grado';
            $params['grado'] = $filters['grado'];
        }
        if (!empty($filters['seccion'])) {
            $where[] = 's.nombre = :seccion';
            $params['seccion'] = $filters['seccion'];
        }
        return [$where, $params];
    }
}