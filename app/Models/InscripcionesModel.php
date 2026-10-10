<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\AnioEscolarCerradoException;
use App\Exceptions\DatoFamiliarIncompletoException;
use App\Support\AniosEscolares;
use App\Interfaces\Creatable;
use App\Interfaces\Listable;
use App\Support\Documento;
use App\Support\Instituciones;
use App\Support\Matriculas;

use App\Traits\CupoEstructuraTrait;
use App\Traits\FamiliaresModelTrait;
use App\Traits\RecaudosModelTrait;
use App\Traits\RetiradoTrait;
use InvalidArgumentException;
use Throwable;

/**
 * Gestión de inscripciones (inscripcion ↔ matricula ↔ estudiante): el alta crea persona, estudiante, matrícula e inscripción en una transacción.
 * Opcionalmente también contacto, dirección, ficha médica y familiares.
 * La inscripción no tiene columna de estado: todo registro creado por el wizard se considera "completa".
*/
class InscripcionesModel extends BaseModel implements Listable, Creatable
{
    /* La escritura de familiares vive en un trait porque inscripción y
 ratificación hacen EXACTAMENTE lo mismo, y `persona_estudiante` tiene
 UNIQUE (id_persona, id_estudiante): una de las dos copias se olvidaría del
 UPDATE y reventaría con 1062 al volver a guardar al mismo familiar.*/
    use FamiliaresModelTrait;
    use RecaudosModelTrait;
    /* Estado "retirado" derivado (ver RetiradoTrait). Lo necesita el listado para
 ocultarlos por defecto y marcarlos cuando el filtro es "Todos".*/
    use RetiradoTrait;
    use CupoEstructuraTrait;

    /**
 * Indica si el bloque de talla trae algo que guardar; si el wizard no lo
 * completó no se crea la fila, para no dejar un registro vacío.
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
 * Indica si el bloque de ficha médica trae algo que guardar.
 *
 * Existe el mismo control para la talla (tieneTalla()) y faltaba para la ficha: el
 * `if (!empty($data['ficha_medica']))` de abajo era SIEMPRE cierto, porque extractData()
 * arma el arreglo con sus claves puestas a null aunque la secretaria no haya escrito
 * nada. Resultado: cada inscripción dejaba una ficha médica vacía, y una ficha en
 * blanco no significa "sin datos" sino "no se registró", que no es lo mismo en un
 * expediente escolar.
*/
    private function tieneFichaMedica(array $ficha): bool
    {
        foreach (['condiciones_especiales', 'medicamentos', 'observaciones', 'alergias', 'enfermedades', 'tipo_sangre'] as $campo) {
            if (trim((string) ($ficha[$campo] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    // Combinaciones del año y cupos: inscripción y ratificación leen lo mismo, así el número de la secretaria coincide con el del servidor.
    

    /* Upsert de contacto y dirección. No lo usa el modelo directamente: lo usa
 FamiliaresModelTrait al guardar cada familiar, y por eso tiene que estar
 disponible en la clase que lo consume.*/
    

    protected string $table = 'inscripcion';
    protected string $primaryKey = 'id_inscripcion';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        [$where, $params] = $this->filters($filters);

$sql = "SELECT i.id_inscripcion AS id,
                       i.numero_inscripcion,
                       COALESCE(CONCAT_WS(' ', p.primer_nombre, p.primer_apellido), 'Estudiante') AS nombre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       UPPER(CONCAT(LEFT(p.primer_nombre, 1), COALESCE(LEFT(p.primer_apellido, 1), ''))) AS iniciales,
                       p.foto AS avatar_img,
                       COALESCE(g.nombre, 'Sin grado') AS grado,
                       COALESCE(s.nombre, 'Sin sección') AS seccion,
                       DATE_FORMAT(m.fecha_matricula, '%d/%m/%Y') AS fecha_inscripcion,
                       'completa' AS estado,
                       /* Estado derivado, no una columna: es si el alumno tiene fila
 de retiro. Ver RetiradoTrait. Se resuelve en el SELECT para no
 consultar por fila.*/
                       " . self::retiradoSql('m') . " AS es_retirado,
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
            $row['numero_inscripcion'] = (int) $row['numero_inscripcion'];
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

    /**
 * Métricas del listado, del AÑO SELECCIONADO.
 *
 * Antes leía sola `AniosEscolares::idEnCurso()`, así que al elegir un año pasado
 * en el selector las tarjetas seguían enseñando los números del año en curso: el
 * listadoHistorical y su resumen contaban cosas distintas.
*/
    public function getStats(?int $idTurno = null, ?int $idAnioEscolar = null): array
    {
        $idAnio = $idAnioEscolar !== null && $idAnioEscolar > 0
            ? $idAnioEscolar
            : AniosEscolares::idEnCurso($this->pdo);
        $filtroTurno = $idTurno !== null ? ' AND ea.id_turno = :turno' : '';
        $parametros = ['anio' => $idAnio];
        if ($idTurno !== null) {
            $parametros['turno'] = $idTurno;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM inscripcion i
             INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
             WHERE ea.id_anio_escolar = :anio{$filtroTurno}"
        );
        $stmt->execute($parametros);
        $inscritosAnio = (int) $stmt->fetchColumn();

        $trendInscritos = 0;
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(
                    m.fecha_matricula >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                    AND m.fecha_matricula < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                ), 0) AS mes_actual,
                COALESCE(SUM(
                    m.fecha_matricula >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                    AND m.fecha_matricula < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                ), 0) AS mes_anterior
             FROM inscripcion i
             INNER JOIN matricula m ON m.id_matricula = i.id_matricula
             INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
             WHERE ea.id_anio_escolar = :anio
               AND m.fecha_matricula >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
               AND m.fecha_matricula < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
               {$filtroTurno}"
        );
        $stmt->execute($parametros);
        $trend = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $mesActual  = (int) ($trend['mes_actual'] ?? 0);
        $mesAnterior = (int) ($trend['mes_anterior'] ?? 0);
        if ($mesAnterior > 0) {
            $trendInscritos = (int) round((($mesActual - $mesAnterior) / $mesAnterior) * 100);
        }

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(ea.capacidad_maxima), 0)
               FROM estructura_academica ea
              WHERE ea.id_anio_escolar = :anio' . $filtroTurno
        );
        $stmt->execute($parametros);
        $capacidad = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT m.id_estudiante)
               FROM matricula m
               INNER JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
              WHERE ea.id_anio_escolar = :anio
                AND ' . Matriculas::condicionCursando('m') . $filtroTurno
        );
        $stmt->execute($parametros);
        $inscritos = (int) $stmt->fetchColumn();
        $cuposDisponibles = max(0, $capacidad - $inscritos);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM inscripcion i
             INNER JOIN matricula m ON m.id_matricula = i.id_matricula
             INNER JOIN estructura_academica ea ON ea.id_estructura_academica = i.id_estructura_academica
             WHERE ea.id_anio_escolar = :anio
               AND m.fecha_matricula >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
               AND m.fecha_matricula < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
               {$filtroTurno}"
        );
        $stmt->execute($parametros);
        $inscripcionesMes = (int) $stmt->fetchColumn();

        return [
            'inscritosAnio'    => $inscritosAnio,
            'trendInscritos'   => $trendInscritos,
            'cuposDisponibles' => $cuposDisponibles,
            'inscripcionesMes' => $inscripcionesMes,
        ];
    }

    /** Listas de strings para los filtros de la tabla.*/
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

    /**
 * Grados y secciones que TIENEN estructura en un año escolar.
 *
 * Los filtros de grado y sección se alimentan de aquí y no de getOptions(): el
 * catálogo de grados y secciones es global, así que con el catálogo a secas el
 * filtro ofrecería combinaciones que ese año nunca tuvo. Al mirar un año
 * anterior, solo deben ofrecerse los grados y secciones que de verdad se
 * impartieron.
 *
 * Si el año no tiene estructuras devuelve la lista vacía: no hay nada que
 * filtrar, y es preferible a ofrecer el catálogo entero.
 *
 * @return array<int, string>
*/
    public function getGradosSeccionesDelAnio(int $idAnioEscolar, string $tipo): array
    {
        $columna = match ($tipo) {
            'grado'   => 'g.nombre',
            'seccion' => 's.nombre',
            default   => null,
        };

        if ($columna === null || $idAnioEscolar <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT {$columna} AS nombre
               FROM estructura_academica ea
               INNER JOIN grado g ON g.id_grado = ea.id_grado
               INNER JOIN seccion s ON s.id_seccion = ea.id_seccion
              WHERE ea.id_anio_escolar = :anio
              ORDER BY nombre"
        );
        $stmt->execute(['anio' => $idAnioEscolar]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Opciones id + nombre para los selects del modal wizard.*/
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

    /**
     * Nacionalidades para el <select> del paso 1 del estudiante.
     *
     * Va aparte de getFamiliarCatalogs() a propósito: ese método arma el sub-modal de
     * familiares, que no lleva nacionalidad, y meterla ahí mezclaría dos formularios
     * que no se parecen. Trae `id_por_defecto` porque el campo del estudiante arranca
     * en Venezolano en vez de en "Seleccione".
     *
     * @return array{nacionalidades:array<int,array<string,mixed>>,id_por_defecto:int}
     */
    public function getNacionalidades(): array
    {
        return [
            'nacionalidades' => $this->pdo->query("SELECT id_nacionalidad, nombre FROM nacionalidad
                                                    WHERE estado = 'ACTIVO' ORDER BY nombre")
                                     ->fetchAll(\PDO::FETCH_ASSOC),
            'id_por_defecto'  => (int) \App\Models\NacionalidadModel::ID_POR_DEFECTO,
        ];
    }

    /** Parentescos, niveles de instrucción y ocupaciones para el sub-modal de familiares.*/
    public function getFamiliarCatalogs(): array
    {
        return [
            'parentescos'         => $this->pdo->query("SELECT nombre FROM parentesco WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_COLUMN),
            'niveles_instruccion' => $this->pdo->query("SELECT nombre FROM nivel_instruccion WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_COLUMN),
            'ocupaciones'         => $this->pdo->query("SELECT nombre FROM ocupacion WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll(\PDO::FETCH_COLUMN),
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

    /** Parroquias activas para el paso de dirección del wizard.*/
    public function getParroquias(): array
    {
        return $this->pdo->query("SELECT id_parroquia AS id, nombre FROM parroquia WHERE estado = 'ACTIVO' ORDER BY nombre")
                         ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ¿Ese documento ya está en el sistema? Consulta `persona` y NO `estudiante`, a propósito:
 preguntar solo por `estudiante` dejaba pasar a quien ya tuviera ficha sin ser alumno, como
 un docente o el representante de otro alumno. La inscripción comparte la tabla `persona`.*/
    public function existeDocumento(string $documento): bool
    {
        if (trim($documento) === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM persona WHERE numero_documento = :doc LIMIT 1'
        );
        $stmt->execute(['doc' => trim($documento)]);
        return $stmt->fetchColumn() !== false;
    }

    /**
 * Por qué ese documento no se puede usar para una inscripción, o null si no está en uso.
 *
 * Los tres casos se responden distinto porque la secretaria tiene que saber a qué
 * módulo acudir: un alumno que ya existe se ratifica, un docente o empleado no se
 * inscribe desde aquí, y un familiar tampoco.
*/
    public function descripcionDocumentoExistente(string $documento): ?string
    {
        $documento = trim($documento);
        if ($documento === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_persona FROM persona WHERE numero_documento = :doc LIMIT 1'
        );
        $stmt->execute(['doc' => $documento]);
        $idPersona = $stmt->fetchColumn();
        if ($idPersona === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM estudiante WHERE id_persona = :persona LIMIT 1'
        );
        $stmt->execute(['persona' => (int) $idPersona]);
        if ($stmt->fetchColumn() !== false) {
            return 'Ya existe un estudiante con ese número de documento; '
                . 'lo que corresponde es ratificarlo, no inscribirlo de nuevo.';
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM empleado WHERE id_persona = :persona LIMIT 1'
        );
        $stmt->execute(['persona' => (int) $idPersona]);
        if ($stmt->fetchColumn() !== false) {
            return 'Ese número de documento ya está registrado como empleado o docente de la institución. '
                . 'Un empleado no se inscribe como estudiante: revise el registro del empleado.';
        }

        return 'Ese número de documento ya está registrado en el sistema como persona. '
            . 'La inscripción es solo para estudiantes nuevos que nunca han pertenecido al plantel.';
    }

    /**
 * Busca una persona por documento para el sub-modal de familiares, para que el wizard cargue sus datos si ya está registrada.
 * Devuelve null si no existe; si existe, nombres, sexo, contacto, domicilio y cuántos estudiantes representa.
 * La cédula se normaliza (App\Support\Documento) para que "V-12.345.678" encuentre a la misma persona que "12345678".
*/
    public function buscarPersonaPorDocumento(string $documento, ?int $idEstudiante = null): ?array
    {
        return (new PersonaModel($this->pdo))->buscarPersonaPorDocumento($documento, $idEstudiante);
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

    /** Año escolar activo para el paso 3 del wizard (solo lectura).*/
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
 * turnos:array, combinaciones:array}
*/
    public function opcionesPasoAcademico(?int $idTurno = null): array
    {
        $anio     = $this->getAnioActivo();
        $opciones = $this->opcionesAcademicas((int) $anio['id'], $idTurno);

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
 * Folio de inscripción siguiente dentro de un año escolar: 1, 2, 3... y vuelve a 1 en el año nuevo.
 *
 * Es un consecutivo de negocio, no el `id_inscripcion`: ese es un AUTO_INCREMENT técnico que deja huecos
 * (si se borra una inscripción, el siguiente número salta) y nunca se reinicia, así que no sirve como folio
 * para citar en un documento. El UNIQUE (id_anio_escolar, numero_inscripcion) es lo que garantiza que no se repita.
 *
 * El FOR UPDATE sobre la fila del año serializa a las secretarias: sin él, dos inscripciones simultáneas del
 * mismo año leerían el mismo MAX() y el INSERT de la segunda moriría con duplicate key (1062).
 * El orden de bloqueos es estructura (reservarCupo, antes) y luego año (aquí), y ningún otro camino del
 * proyecto bloquea el año, así que ese orden no se cruza con nadie.
 *
 * @throws \RuntimeException si el año escolar indicado no existe.
*/
    private function siguienteFolio(int $idAnioEscolar): int
    {
        $lock = $this->pdo->prepare(
            'SELECT id_anio_escolar FROM anio_escolar WHERE id_anio_escolar = :anio FOR UPDATE'
        );
        $lock->execute(['anio' => $idAnioEscolar]);
        if ($lock->fetchColumn() === false) {
            throw new \RuntimeException('El año escolar de la inscripción no existe.');
        }

        $max = $this->pdo->prepare(
            'SELECT COALESCE(MAX(numero_inscripcion), 0) FROM inscripcion WHERE id_anio_escolar = :anio'
        );
        $max->execute(['anio' => $idAnioEscolar]);

        return (int) $max->fetchColumn() + 1;
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
 Además, en REPEATABLE READ una lectura previa fija la foto de la transacción y el conteo de cupos saldría viejo.*/
            $estructuraId = (int) ($data['id_estructura_academica'] ?? 0);
            if ($estructuraId <= 0) {
                throw new \RuntimeException('Debe seleccionar una estructura académica válida.');
            }
            $disponibilidad = $this->reservarCupo($estructuraId);
            $anioEscolar = (int) ($disponibilidad['id_anio_escolar'] ?? 0);
            if ($anioEscolar <= 0) {
                throw new \RuntimeException('La estructura académica seleccionada no tiene un año escolar válido.');
            }

    /* GUARDIA DE ESCRITURA SOBRE UN AÑO CERRADO. Se comprueba el año de la ESTRUCTURA elegida,
 que es donde se va a escribir, y no "el que esté activo": con lo anterior bastaba que
 existiera ALGÚN año activo para poder escribir, y se podía mandar a mano un id de
 estructura de un período cerrado.*/
            $stmt = $this->pdo->prepare(
                'SELECT nombre_periodo, estado
                   FROM anio_escolar
                  WHERE id_anio_escolar = :anio
                  FOR UPDATE'
            );
            $stmt->execute(['anio' => $anioEscolar]);
            $anio = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$anio) {
                throw new \RuntimeException('El año escolar de la estructura seleccionada no existe.');
            }

            if (strtoupper((string) $anio['estado']) !== 'ACTIVO') {
                throw new AnioEscolarCerradoException(
                    'El año escolar ' . $anio['nombre_periodo'] . ' está cerrado y es solo de consulta. '
                    . 'No se pueden registrar inscripciones en un período cerrado.'
                );
            }

            // 1. Persona + estudiante (el wizard siempre registra estudiante nuevo)
            // crear() y no buscarOCrear(): la persona tiene que ser nueva. Reutilizar una existente
            // (un docente, un empleado, el representante de otro alumno) dejaría la misma persona
            // con dos fichas y su documento solo podría pertenecer a una.
            $personaModel = new PersonaModel($this->pdo);$personaId = $personaModel->crear($persona);

            $this->pdo->prepare('INSERT INTO estudiante (id_persona) VALUES (:persona)')
                      ->execute(['persona' => $personaId]);
            $estudianteId = (int) $this->pdo->lastInsertId();

            // 2. Matrícula
            // El wizard no manda fecha: la matrícula se registra con la del día.
            $fecha = ($data['fecha_matricula'] ?? '') ?: date('Y-m-d');

            /* Primer ingreso: sin historial solo se entra por INSCRIPCION (tipo fijado por App\Support\Matriculas) y nunca se repite.
 La llave única (estudiante, año, tipo) no frena una segunda INSCRIPCION en un año nuevo,
 así que se comprueba que no exista ninguna matrícula previa.*/
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

// El folio se pide después de reservar el cupo: el año ya está resuelto arriba y el bloqueo de la
            // estructura ya está tomado, que es el orden que evita el deadlock que comenta el paso 0.
            $folio = $this->siguienteFolio($anioEscolar);

            $this->pdo->prepare(
                'INSERT INTO inscripcion (id_matricula, id_estudiante, id_estructura_academica, id_anio_escolar,
                 numero_inscripcion, ult_grado_cursado,
                 es_primer_ingreso, motivo_egreso, anio_escolar_pre, id_institucion)
                 VALUES (:matricula, :estudiante, :estructura, :anio, :folio, :ult_grado,
                 :primer_ingreso, :motivo, :anio_pre, :institucion)'
            )->execute([
                'matricula'      => $matriculaId,
                'estudiante'     => $estudianteId,
                'estructura'     => $estructuraId,
                'anio'           => $anioEscolar,
                'folio'          => $folio,
                'ult_grado'      => mb_substr((string) ($data['ult_grado_cursado'] ?? ''), 0, 20) ?: null,
                /* `es_primer_ingreso` es un dato de procedencia ("Primera vez" o "Traslado"), no de proceso; no depende de que la inscripción sea única.
 Los que vienen de otro colegio llenan ult_grado_cursado, anio_escolar_pre y motivo_egreso.
 El default espeja el de la columna; el controlador siempre manda el dato (el checkbox sin marcar lo traduce a 0).*/
                'primer_ingreso' => (int) ($data['es_primer_ingreso'] ?? 1),
                'motivo'         => mb_substr((string) ($data['motivo_egreso'] ?? ''), 0, 100) ?: null,
                'anio_pre'       => mb_substr((string) ($data['anio_escolar_pre'] ?? ''), 0, 9) ?: null,
                'institucion'    => $idInstitucion,
            ]);
            $inscripcionId = (int)$this->pdo->lastInsertId();

            // 4. Talla uniforme (opcional). El estudiante es menor: su contacto y dirección van con el representante, en insertFamiliares().
            if ($this->tieneTalla($data['talla_uniforme'] ?? [])) {
                $talla =$data['talla_uniforme'];
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
            if ($this->tieneFichaMedica((array) ($data['ficha_medica'] ?? []))) {$this->pdo->prepare(
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

            /* 6. Recaudos que entregó el representante. Antes esta información se mostraba en el
 paso 5 del asistente y se perdía al guardar: la tabla estudiante_documento
 estaba creada para esto y nunca se escribió. Van todos los recaudos del
 catálogo, no solo los marcados, para que quede la lista de lo que se pidió en
 esa inscripción y se pueda mostrar lo que falta.*/
            $this->guardarRecaudos($estudianteId, $data['recaudos'] ?? []);

            /* 7. Familiares: ya no es best-effort; si algún familiar no declara parentesco sin ser "no es familiar",
 se lanza DatoFamiliarIncompletoException y create() hace rollBack.*/
            $this->guardarFamiliares($estudianteId, $data['familiares'] ?? []);

            /* Se audita antes del commit y en la misma conexión: si el commit fallara, el
 rollback también deshace la bitácora, y nunca queda constancia de una
 inscripción que no ocurrió. El id del usuario lo pasa el controlador.*/
            $idUsuario = (int) ($data['id_usuario'] ?? 0);
            if ($idUsuario > 0) {
                (new BitacoraModel($this->pdo))->registrar(
                    $idUsuario,
                    'INSCRIPCIÓN ' . trim((string) $this->descripcionEstudiante($estudianteId))
                        . ' · ' . (string) $disponibilidad['nombre'],
                    'Inscripciones'
                );
            }

            $this->pdo->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {$this->pdo->rollBack();
            }
            error_log('InscripcionesModel::create(): ' . $e->getMessage());
            throw $e;
        }
    }

    /** Nombre y cédula de un estudiante, para que la bitácora se lea sin consultar la ficha.*/
    private function descripcionEstudiante(int $estudianteId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.primer_nombre, p.primer_apellido, p.numero_documento
               FROM estudiante e
               INNER JOIN persona p ON p.id_persona = e.id_persona
              WHERE e.id_estudiante = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $estudianteId]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return "estudiante {$estudianteId}";
        }

        return trim($fila['primer_nombre'] . ' ' . $fila['primer_apellido'])
            . ' (' . $fila['numero_documento'] . ')';
    }

    /** Detalle de una inscripción para la vista "ver".*/
    public function getById(int $id): ?array
    {
        $sql = "SELECT i.id_inscripcion AS id, i.numero_inscripcion, i.es_primer_ingreso, i.ult_grado_cursado, i.motivo_egreso, i.anio_escolar_pre,
                       CONCAT(p.tipo_documento, '-', p.numero_documento) AS cedula,
                       p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                       DATE_FORMAT(m.fecha_matricula, '%d/%m/%Y') AS fecha_inscripcion,
                       g.nombre AS grado, s.nombre AS seccion, t.nombre AS turno, ea.id_turno, a.nombre_periodo AS anio_escolar,
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
        $stmt->execute(['id' =>$id]);
        $row =$stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }$row['id'] = (int) $row['id'];
        $row['numero_inscripcion'] = (int) $row['numero_inscripcion'];
        $row['avatar_color'] = 'primary';
        $row['iniciales'] =$row['iniciales'] ?: 'ES';
        return $row;
    }

    /**
 * El año escolar en curso, sin caché a propósito: si dentro del proceso se abre
 * o cierra un año, un valor guardado haría que inscripciones y ratificación se
 * contradigan.
*/
    private function anioEnCurso(): int
    {
        return AniosEscolares::idEnCurso($this->pdo);
    }

    /**
 * Años escolares existentes, para el selector del listado.
 *
 * Trae el estado de cada uno porque la vista lo necesita para decidir si el año
 * seleccionado admite inscripciones: un año cerrado es de consulta y no debe
 * ofrecer escrituras. Sin el estado aquí, la vista tendría que adivinarlo.
 *
 * @return array<int, array{id:int, nombre:string, estado:string, es_lectura:bool}>
*/
    public function getAniosEscolares(): array
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
                /* Solo el ACTIVO admite escritura. Un año CERRADO es historial: se
 consulta pero no se le escribe nada.*/
                'es_lectura'  => $estado !== 'ACTIVO',
            ];
        }, $filas);
    }

    /**
 * El año que hay que mostrar: el que pide la URL, si existe, y si no el que está
 * en curso.
 *
 * Se valida contra la lista real de años en lugar de castear a entero y ya: un
 * `?anio=999` o un `?anio=abc` no puede convertirse en un id que se meta en el SQL
 * como si fuera un período real, y el usuario no ve una lista vacía sin
 * explicación, ve el año en curso.
 *
 * @param array $filters Filtros de la petición.
 * @return array{id:int, nombre:string, estado:string, es_lectura:bool}
*/
    public function getAnioSeleccionado(array $filters): array
    {
        $anios = $this->getAniosEscolares();
        $pedido = (int) ($filters['anio'] ?? 0);

        foreach ($anios as $anio) {
            if ($anio['id'] === $pedido && $pedido > 0) {
                return $anio;
            }
        }

        // Sin año válido en la URL: al que está en curso, o el primero si no hay ninguno.
        $enCurso = $this->anioEnCurso();
        foreach ($anios as $anio) {
            if ($anio['id'] === $enCurso) {
                return $anio;
            }
        }

        return $anios[0] ?? ['id' => 0, 'nombre' => 'sin año', 'estado' => 'CERRADO', 'es_lectura' => true];
    }

    private function filters(array $filters): array
    {
    /* La lista es del AÑO SELECCIONADO, por defecto el que está en curso. Se filtra por el año de la
 ESTRUCTURA de la inscripción (cuándo entró) y no por el de la matrícula, que para un alumno
 antiguo es el año en curso y no lo sacaría del listado.*/
        $idAnio = $this->getAnioSeleccionado($filters)['id'];
        $where = $idAnio > 0
            ? ['ea.id_anio_escolar = :anio_listado']
            : ['1 = 0'];
        $params = ['anio_listado' => $idAnio];

        if (!empty($filters['q'])) {
            foreach (['p.primer_nombre', 'p.primer_apellido', 'p.numero_documento'] as $i => $col) {$params["q$i"] = '\%' . $filters['q'] . '%';
            }
            $where[] = '(p.primer_nombre LIKE :q0 OR p.primer_apellido LIKE :q1 OR p.numero_documento LIKE :q2)';
        }
        // El año no es un filtro: la lista es del año en curso, y aceptarlo por URL (?anio=...) ampliaría su alcance sin que la interfaz lo muestre.
        if (!empty($filters['grado'])) {$where[] = 'g.nombre = :grado';
            $params['grado'] =$filters['grado'];
        }
        if (!empty($filters['seccion'])) {$where[] = 's.nombre = :seccion';
            $params['seccion'] =$filters['seccion'];
        }
        if (array_key_exists('id_turno', $filters)) {
            $where[] = 'ea.id_turno = :id_turno';
            $params['id_turno'] = (int) $filters['id_turno'];
        }

        /* Estado del alumno: por defecto solo los que NO están retirados.
 El retiro no es una inscripción nueva, es el mismo alumno que dejó de
 estar activo, así que el filtro trabaja sobre el alumno (EXISTS) y no sobre
 el tipo de la fila: la fila RETIRO no tiene fila en `inscripcion` y
 filtrar por `matricula.tipo` no sacaría al alumno de la lista.
 Con estado=todos se ve también, con su insignia, y sin acciones de
 edición: el registro se consulta igual de uno u otro modo.*/
        if (($filters['estado'] ?? 'activos') !== 'todos') {
            $where[] = 'NOT ' . self::retiradoSql('m');
        }

        return [$where,$params];
    }
}
