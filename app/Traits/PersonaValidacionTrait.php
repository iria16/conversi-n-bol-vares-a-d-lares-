<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\CedulaEscolar;
use App\Support\Documento;


trait PersonaValidacionTrait
{
    /**
 * Valida los cuatro campos de nombre/apellido y agrega los errores a $errores por referencia.
 * Primer nombre y primer apellido son obligatorios (mínimo 2 caracteres); los segundos son opcionales,
 * pero si vienen con contenido se les exige el mismo mínimo.
 *
 * @param array $data Datos ya extraídos (extractData()).
 * @param array $errores Errores por campo, por referencia.
*/
    protected function validarNombresApellidos(array $data, array &$errores): void
    {
        // Regex que permite letras (incluye acentos, ñ, Ñ) y espacios
        $patronTexto = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/u';

        $primerNombre   = (string) ($data['primer_nombre'] ?? '');
        $primerApellido = (string) ($data['primer_apellido'] ?? '');
        $segundoNombre   = (string) ($data['segundo_nombre'] ?? '');
        $segundoApellido = (string) ($data['segundo_apellido'] ?? '');

        // 1. Validar Primer Nombre
        if (mb_strlen($primerNombre) < 2) {
            $errores['primer_nombre'] = 'El primer nombre debe tener al menos 2 caracteres.';
        } elseif (!preg_match($patronTexto, $primerNombre)) {
            $errores['primer_nombre'] = 'El primer nombre solo debe contener letras.';
        }

        // 2. Validar Primer Apellido
        if (mb_strlen($primerApellido) < 2) {
            $errores['primer_apellido'] = 'El primer apellido debe tener al menos 2 caracteres.';
        } elseif (!preg_match($patronTexto, $primerApellido)) {
            $errores['primer_apellido'] = 'El primer apellido solo debe contener letras.';
        }

        // 3. Validar Segundo Nombre (opcional)
        if ($segundoNombre !== '') {
            if (mb_strlen($segundoNombre) < 2) {
                $errores['segundo_nombre'] = 'El segundo nombre debe tener al menos 2 caracteres.';
            } elseif (!preg_match($patronTexto, $segundoNombre)) {
                $errores['segundo_nombre'] = 'El segundo nombre solo debe contener letras.';
            }
        }

        // 4. Validar Segundo Apellido (opcional)
        if ($segundoApellido !== '') {
            if (mb_strlen($segundoApellido) < 2) {
                $errores['segundo_apellido'] = 'El segundo apellido debe tener al menos 2 caracteres.';
            } elseif (!preg_match($patronTexto, $segundoApellido)) {
                $errores['segundo_apellido'] = 'El segundo apellido solo debe contener letras.';
            }
        }
    }

    /**
 * Formato por tipo de documento.
 * V / E → 7-8 dígitos; el prefijo y los separadores son opcionales y los resuelve App\Support\Documento.
 * CE → cédula escolar: letra (V o E) + 10 dígitos, generada por App\Support\CedulaEscolar; este regex garantiza el formato.
*/
    protected function validarNumeroDocumento(string $numeroDocumento, string $tipoDocumento = 'V'): bool
    {
        $numero = trim($numeroDocumento);

        return match ($tipoDocumento) {
            'CE'    => (bool) preg_match(CedulaEscolar::PATRON, $numero),
            default => Documento::esCedula($numero),
        };
    }

    /**
 * Mensaje de error si el tipo de documento no está entre los permitidos; null si es válido.
 *
 * Es la regla pura (sin consultar la base ni escribir en $errores), para que la usen
 * tanto validarDocumento() como los módulos que validan documentos dentro de otro
 * bloque, como FamiliaresValidacionTrait.
 *
 * @param string[] $tiposPermitidos
*/
    protected function errorTipoDocumento(string $tipo, array $tiposPermitidos): ?string
    {
        if (in_array($tipo, $tiposPermitidos, true)) {
            return null;
        }

        return 'Selecciona un tipo de documento válido (' . implode(', ', $tiposPermitidos) . ').';
    }

    /**
 * Mensaje de error si el número no tiene el formato que exige su tipo; null si es válido.
 * No comprueba que el número venga: eso depende de cada módulo (el estudiante siempre,
 * un familiar solo si es representante legal).
*/
    protected function errorFormatoDocumento(string $numeroDocumento, string $tipoDocumento = 'V'): ?string
    {
        if ($this->validarNumeroDocumento($numeroDocumento, $tipoDocumento)) {
            return null;
        }

        return $tipoDocumento === 'CE'
            ? 'La cédula escolar debe tener una letra (V o E) y 10 dígitos.'
            : 'El documento solo admite números (7 a 8 dígitos).';
    }

    protected function validarDocumento(
        array $data,
        array &$errores,
        array $tiposPermitidos,
        ?int $ignorarId = null,
        string $mensajeDuplicado = 'Ya existe un registro con ese documento.'
    ): void {
        $tipo   = (string) ($data['tipo_documento'] ?? '');
        $numero = (string) ($data['numero_documento'] ?? '');

        $errorTipo = $this->errorTipoDocumento($tipo, $tiposPermitidos);
        if ($errorTipo !== null) {
            $errores['tipo_documento'] = $errorTipo;
        }

        if ($numero === '') {
            $errores['numero_documento'] = 'El documento es obligatorio.';
        } elseif (($errorFormato = $this->errorFormatoDocumento($numero, $tipo)) !== null) {
            $errores['numero_documento'] = $errorFormato;
        } elseif ($this->documentoYaRegistrado($numero, $ignorarId)) {
            $errores['numero_documento'] = $mensajeDuplicado;
        }
    }

    /**
 * ¿El modelo del módulo ya tiene ese documento?
 * Solo algunos modelos exponen existeDocumento(), así que se usa is_callable():
 * si el método existe se consulta; si no, no hay duplicado que verificar.
*/
    private function documentoYaRegistrado(string $numeroDocumento, ?int $ignorarId = null): bool
    {
        if (!method_exists($this, 'getModel')) {
            return false;
        }

        $model = $this->getModel();

        return is_callable([$model, 'existeDocumento'])
            && $model->existeDocumento($numeroDocumento, $ignorarId);
    }

    /** Valida una fecha de nacimiento contra un rango de edad y agrega el error a $errores por referencia.*/
    protected function validarFechaNacimiento(
        array $data,
        array &$errores,
        string $campo = 'fecha_nacimiento',
        int $edadMinima = 0,
        int $edadMaxima = 120,
        string $etiqueta = ''
    ): void {
        $valor  = (string) ($data[$campo] ?? '');
        $sufijo = $etiqueta !== '' ? " {$etiqueta}" : '';

        $fechaMinima = date('Y-m-d', strtotime("-{$edadMaxima} years"));
        $fechaMaxima = date('Y-m-d', strtotime("-{$edadMinima} years"));

        if ($valor === '' || !$this->fechaValida($valor)) {
            $errores[$campo] = "Indica una fecha de nacimiento{$sufijo} válida.";
        } elseif ($valor < $fechaMinima) {
            $errores[$campo] = "La edad{$sufijo} no puede superar los {$edadMaxima} años.";
        } elseif ($valor > $fechaMaxima) {
            $errores[$campo] = "La edad{$sufijo} debe ser de al menos {$edadMinima} años.";
        }
    }

    /**
     * Valida el lugar de nacimiento.
     *
     * La entidad federal se pide a toda persona. El municipio y la parroquia se piden solo
     * al estudiante, y de los tres niveles solo se guardan dos: el estado siempre y la
     * parroquia cuando la hay. El municipio se deduce de la parroquia, igual que hace la
     * tabla `direccion`.
     *
     * Se comprueba además que la parroquia pertenezca al estado elegido. Sin eso se podría
     * guardar «Caracas» con una parroquia de Carabobo y el dato quedaría mintiendo.
     *
     * @param array $data Datos ya extraídos, con las claves del formulario.
     * @param array $errores Errores por campo, por referencia.
     * @param bool $exigeParroquia true para el estudiante, false para las demás personas.
     */
    protected function validarLugarNacimiento(array $data, array &$errores, bool $exigeParroquia, bool $exigir = true): void
    {
        $estado = (int) ($data['id_estado_nacimiento'] ?? 0);
        $parroquia = (int) ($data['id_parroquia_nacimiento'] ?? 0);

        if ($estado <= 0) {
            /* $exigir en false es para EDITAR un registro que ya existía sin el dato. Las
            personas que estaban antes de que estos campos existieran los tienen en NULL, y
            exigirlos hacía que no se pudiera editar a nadie: el campo se_CREÓ con el
            requisito, y un requisito no puede aplicarse hacia atrás a datos que nunca
            pudieron cumplirlo. Si viene informado, igual se comprueba que sea coherente. */
            if ($exigir) {
                $errores['id_estado_nacimiento'] = 'Indica la entidad federal donde nació.';
            }
            return;
        }

        if (!$exigeParroquia) {
            return;
        }

        if ($parroquia <= 0) {
            $errores['id_parroquia_nacimiento'] = 'Indica la parroquia donde nació el alumno.';
            return;
        }

        $estadoDeLaParroquia = $this->estadoDeParroquia($parroquia);
        if ($estadoDeLaParroquia === null) {
            $errores['id_parroquia_nacimiento'] = 'La parroquia indicada no existe.';
            return;
        }

        if ($estadoDeLaParroquia !== $estado) {
            $errores['id_parroquia_nacimiento'] = 'La parroquia indicada no pertenece a la entidad federal elegida.';
        }
    }

    /**
     * Nacionalidad del estudiante.
     *
     * Ahora es un id del catálogo `nacionalidad`, así que la comprobación es la misma
     * que la de cualquier otro catálogo: que exista y que esté activo. No hace falta
     * medir la longitud del texto porque ya no se guarda texto.
     *
     * @param array $data Datos de la persona ya normalizados
     * @param array $errores Errores por campo, se escribe por referencia
     */
    protected function validarNacionalidad(array $data, array &$errores, bool $exigir = true): void
    {
        $id = (int) ($data['id_nacionalidad'] ?? 0);

        if ($id <= 0) {
            /* Misma razón que en validarLugarNacimiento(): la nacionalidad se añadió DESPUÉS
            de que existieran las personas, así que las anteriores la tienen en NULL. Si se
            exigiera siempre, no se podría editar ni a un alumno. Cuando sí viene, se
            comprueba contra el catálogo como antes. */
            if ($exigir) {
                $errores['id_nacionalidad'] = 'Selecciona la nacionalidad del alumno.';
            }
            return;
        }

        if (!$this->nacionalidadActiva($id)) {
            $errores['id_nacionalidad'] = 'La nacionalidad indicada no existe o está inactiva.';
        }
    }

    /**
     * Si una nacionalidad del catálogo está activa.
     *
     * Se consulta en vez de confiar en el id porque el formulario se puede mandar a
     * mano: sin esta comprobación, un POST con un id viejo o desactivado escribiría una
     * nacionalidad que el catálogo ya no ofrece.
     *
     * @return bool
     */
    private function nacionalidadActiva(int $id): bool
    {
        $stmt = $this->conexion()->prepare("SELECT 1 FROM nacionalidad WHERE id_nacionalidad = ? AND estado = 'ACTIVO'");
        $stmt->execute([$id]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Entidad federal a la que pertenece una parroquia.
     *
     * Devuelve null si la parroquia no existe, que el mensaje de arriba distingue de "sí
     * existe pero es de otro estado": son dos errores distintos para quien corrige.
     */
    private function estadoDeParroquia(int $idParroquia): ?int
    {
        static $cache = [];

        if (array_key_exists($idParroquia, $cache)) {
            return $cache[$idParroquia];
        }

        $stmt = $this->conexion()->prepare(
            'SELECT m.id_estado
               FROM parroquia p
              INNER JOIN municipio m ON m.id_municipio = p.id_municipio
              WHERE p.id_parroquia = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $idParroquia]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $cache[$idParroquia] = $fila === false ? null : (int) $fila['id_estado'];
    }

    /**
     * La conexión la aporta el controlador. Se declara aquí porque este trait consulta:
     * el resto de sus validaciones son reglas puras y no tocan la base.
     */
    abstract protected function conexion(): \PDO;
}