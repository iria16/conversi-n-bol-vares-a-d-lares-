<?php
declare(strict_types=1);

namespace App\Models;

use App\Exceptions\DocumentoDuplicadoException;
use App\Support\Documento;
use PDOException;
use Throwable;

class PersonaModel extends BaseModel
{
    protected string $table = 'persona';
    protected string $primaryKey = 'id_persona';

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
        /* El lugar de nacimiento se guarda en dos columnas, no en tres: el estado para
           toda persona y la parroquia solo para el estudiante. El municipio se deduce de
           la parroquia, igual que hace la tabla `direccion`. Las dos son nulables porque
           las personas ya registradas no tienen el dato. */
        'id_estado_nacimiento',
        'id_parroquia_nacimiento',
        /* Nacionalidad: la columna es de la persona pero SOLO el estudiante la
           completa; empleados y familiares quedan en NULL. Guarda el id del catálogo
           `nacionalidad`, no el texto, como el resto de los catálogos. */
        'id_nacionalidad',
    ];

    private const PERSONA_NULABLES = [
        'segundo_nombre',
        'segundo_apellido',
        'foto',
        'id_estado_nacimiento',
        'id_parroquia_nacimiento',
        'id_nacionalidad',
    ];

    /**
 * Busca una persona por cédula y devuelve los datos compartidos que completa
 * el formulario de familiares de inscripción y ratificación.
 *
 * @param int|null $idEstudiante Para qué estudiante se está completando el
 * familiar. Solo sirve para escolher DE DÓNDE salen el nivel de
 * instrucción y la ocupación: esos dos NO están en `persona`, están en la
 * relación `persona_estudiante`, así que una misma persona puede tenerlos
 * distintos según de qué menor es familiar. Con el estudiante se toma la
 * relación de ese menor, que es la que se está editando; sin él se toma la
 * más reciente que los tenga, que es lo mejor que se puede saber cuando
 * todavía no hay menor (es el caso del alta de inscripción).
*/
    public function buscarPersonaPorDocumento(string $documento, ?int $idEstudiante = null): ?array
    {
        $documento = Documento::normalizar($documento);

        if ($documento === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id_persona, tipo_documento, numero_documento, fecha_nacimiento,
                    id_estado_nacimiento, id_parroquia_nacimiento, id_nacionalidad,
                    primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, sexo
               FROM persona
              WHERE numero_documento = :documento
              LIMIT 1'
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
               FROM contacto
              WHERE id_persona = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $contacto = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->pdo->prepare(
            'SELECT sector_urbanizacion, calle_avenida, nro_casa_apto,
                    punto_referencia, id_parroquia
               FROM direccion
              WHERE id_persona = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $direccion = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($direccion) {
            $ubicacion = (new UbicacionModel($this->pdo))
                ->ubicacionDeParroquia((int) $direccion['id_parroquia']);
            $direccion['id_estado'] = $ubicacion['estado'] ?? 0;
            $direccion['id_municipio'] = $ubicacion['municipio'] ?? 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
               FROM persona_estudiante
              WHERE id_persona = :id
                AND es_representante_legal = 1'
        );
        $stmt->execute(['id' => $id]);

        /* Nivel de instrucción y ocupación: están en persona_estudiante, no en
 persona. Se piden por NOMBRE del catálogo porque es lo que viaja en el
 <select> del sub-modal y en el payload (el modelo lo resuelve a id).*/
        $datosRelacion = $this->nivelYOcupacionDe($id, $idEstudiante);

        return [
            'persona' => [
                'tipo_documento' => $persona['tipo_documento'],
                'numero_documento' => $persona['numero_documento'],
                'primer_nombre' => $persona['primer_nombre'] ?? '',
                'segundo_nombre' => $persona['segundo_nombre'] ?? '',
                'primer_apellido' => $persona['primer_apellido'] ?? '',
                'segundo_apellido' => $persona['segundo_apellido'] ?? '',
                'sexo' => $persona['sexo'] ?? 'M',
                'fecha_nacimiento' => $persona['fecha_nacimiento'] ?? '',
                /* Vuelve el lugar de nacimiento para que el formulario de edición muestre
                   lo que ya está guardado en vez de un select vacío. */
                'id_estado_nacimiento' => (int) ($persona['id_estado_nacimiento'] ?? 0),
                'id_parroquia_nacimiento' => (int) ($persona['id_parroquia_nacimiento'] ?? 0),
            ],
            'nivel_instruccion' => $datosRelacion['nivel_instruccion'],
            'ocupacion' => $datosRelacion['ocupacion'],
            'contacto' => $contacto === null ? null : [
                'codigo' => $contacto['codigo_telefono_principal'] ?? '',
                'numero' => $contacto['numero_telefono_principal'] ?? '',
                'codigo_alt' => $contacto['codigo_telefono_alternativo'] ?? '',
                'numero_alt' => $contacto['numero_telefono_alternativo'] ?? '',
                'correo' => $contacto['correo_electronico'] ?? '',
            ],
            'direccion' => $direccion === null ? null : [
                'sector' => $direccion['sector_urbanizacion'] ?? '',
                'calle' => $direccion['calle_avenida'] ?? '',
                'casa' => $direccion['nro_casa_apto'] ?? '',
                'referencia' => $direccion['punto_referencia'] ?? '',
                'parroquia' => (int) ($direccion['id_parroquia'] ?? 0),
                'estado' => (int) ($direccion['id_estado'] ?? 0),
                'municipio' => (int) ($direccion['id_municipio'] ?? 0),
            ],
            'representante_de' => (int) $stmt->fetchColumn(),
        ];
    }

    /**
 * Nivel de instrucción y ocupación que ya tiene esta persona como familiar.
 *
 * Se resuelve en dos pasos a propósito. Primero se mira la relación con el
 * estudiante que se está editando, que es la respuesta correcta: si esa persona
 * ya era familiar de ese menor, esos son los valores con los que está
 * registrada. Solo si ahí no hay (o no los tiene) se recurre a la relación más
 * reciente que los tenga, que es lo que se puede ofrecer cuando aún no se sabe
 * de qué menor se trata.
 *
 * Se devuelven como '' cuando no hay nada: es lo que espera el <select>, y así
 * el sub-modal puede preguntar el dato en vez de dejarlo vacío sin explicación.
 *
 * @return array{nivel_instruccion:string, ocupacion:string}
*/
    private function nivelYOcupacionDe(int $idPersona, ?int $idEstudiante): array
    {
        $base = 'FROM persona_estudiante pe
                 LEFT JOIN nivel_instruccion ni ON ni.id_nivel_instruccion = pe.id_nivel_instruccion
                 LEFT JOIN ocupacion oc         ON oc.id_ocupacion         = pe.id_ocupacion
                WHERE pe.id_persona = :persona';

        if ($idEstudiante !== null && $idEstudiante > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT COALESCE(ni.nombre, "") AS nivel, COALESCE(oc.nombre, "") AS ocupacion '
                . $base . ' AND pe.id_estudiante = :estudiante LIMIT 1'
            );
            $stmt->execute(['persona' => $idPersona, 'estudiante' => $idEstudiante]);
            $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

            /* Si de ese menor los tiene, ya está: no se busca en otro lado.*/
            if ($fila && ($fila['nivel'] !== '' || $fila['ocupacion'] !== '')) {
                return ['nivel_instruccion' => $fila['nivel'], 'ocupacion' => $fila['ocupacion']];
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(ni.nombre, "") AS nivel, COALESCE(oc.nombre, "") AS ocupacion '
            . $base
            . ' AND (ni.nombre IS NOT NULL OR oc.nombre IS NOT NULL)
              ORDER BY pe.id_persona_estudiante DESC LIMIT 1'
        );
        $stmt->execute(['persona' => $idPersona]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fila) {
            return ['nivel_instruccion' => '', 'ocupacion' => ''];
        }

        return ['nivel_instruccion' => $fila['nivel'], 'ocupacion' => $fila['ocupacion']];
    }

    /**
 * Devuelve la persona del documento, y la crea si no existe.
 *
 * Reutiliza una persona que YA está en la base, así que solo corresponde a los
 * módulos donde repetir el mismo documento es lo esperado: empleados y familiares,
 * que pueden cargar dos veces a la misma persona. La inscripción NO lo usa: allí
 * reutilizar una persona existente mezclaría dos fichas distintas bajo un mismo
 * documento (un docente que entra como alumno, por ejemplo), y para eso está crear().
*/
    public function buscarOCrear(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_persona FROM persona WHERE numero_documento = :numero LIMIT 1'
        );
        $stmt->execute(['numero' => mb_substr((string) ($data['numero_documento'] ?? ''), 0, 20)]);

        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        return $this->crear($data);
    }

    /**
 * Inserta una persona NUEVA. Si el documento ya está registrado devuelve
 * DocumentoDuplicadoException en vez de devolver la fila existente.
 *
 * Existe para que "no reutilizar una persona ajena" sea una regla del modelo y no
 * solo del formulario: si alguien salta la validación y llama a crear() con un
 * documento repetido, el UNIQUE de persona.numero_documento lo detiene y la
 * transacción de quien llama se revierte.
 *
 * @throws DocumentoDuplicadoException si el documento ya existe.
*/
    public function crear(array $data): int
    {
        $campos = $this->soloCamposPersona($data);
        if (!isset($campos['estado'])) {
            $campos['estado'] = 'ACTIVO';
        }

        $columnas = array_keys($campos);
        $sql = 'INSERT INTO persona (' . implode(', ', $columnas) . ') VALUES ('
             . implode(', ', array_map(fn (string $c) => ":{$c}", $columnas)) . ')';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($campos);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'numero_documento')) {
                throw new DocumentoDuplicadoException(
                    'Ese documento de identidad ya está registrado en el sistema.'
                );
            }
            throw $e;
        }
    }

    public function updatePersona(int $id_persona, array $data): bool
    {
        $campos = $this->soloCamposPersona($data);
        if (empty($campos)) {
            return true;
        }

        $sets = array_map(fn (string $c) => "{$c} = :{$c}", array_keys($campos));
        $campos['id_persona'] = $id_persona;

        $stmt = $this->pdo->prepare('UPDATE persona SET ' . implode(', ', $sets) . ' WHERE id_persona = :id_persona');
        return $stmt->execute($campos);
    }

    private function soloCamposPersona(array $data): array
    {
        $filtrado = array_intersect_key($data, array_flip(self::CAMPOS_PERSONA));
        foreach (self::PERSONA_NULABLES as $col) {
            if (array_key_exists($col, $filtrado) && $filtrado[$col] === '') {
                $filtrado[$col] = null;
            }
        }
        return $filtrado;
    }
}
