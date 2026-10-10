<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\Documento;

/**
 * Lectura y validación del bloque de familiares que manda el formulario, antes de que se
 * escriba nada. Es un trait porque inscripción y ratificación reciben el mismo
 * `familiares_json` y necesitan las mismas comprobaciones: si cada una las tuviera, una
 * corregida en un lado y no en el otro daría dos reglas para lo mismo.*/
trait FamiliaresValidacionTrait
{
    /** Caché de nombres de parentesco válidos, para no consultar por cada familiar.*/
    private ?array $nombresParentescoCache = null;

    /**
 * Convierte el `familiares_json` del formulario en un arreglo de familiares
 * ya normalizado, con las claves que espera FamiliaresModelTrait.
 *
 * Devuelve [] si no vino un JSON utilizable, y no lanza: un bloque ausente es
 * un caso legítimo, el que resuelve el módulo según si exige representante.
 *
 * @return array<int, array<string, mixed>>
*/
    protected function decodificarFamiliares(string $crudo): array
    {
        $crudo = trim($crudo);
        if ($crudo === '') {
            return [];
        }

        $decodificado = json_decode($crudo, true);
        if (!is_array($decodificado)) {
            return [];
        }

        $familiares = [];
        foreach ($decodificado as $item) {
            if (!is_array($item)) {
                continue;
            }

            $soloAutorizado = !empty($item['solo_autorizado']);

            $familiares[] = [
                'parentesco'   => trim((string) ($item['parentesco'] ?? '')),
                /* Sin parentesco solo se guarda "no es familiar"; sin esa marca la
 fila no tendría vínculo y hay que avisarlo.*/
                'solo_autorizado'          => $soloAutorizado,
                // Autorizado para retirar es lo mismo que no tener parentesco: se marca solo.
                'autorizado_retiros'       => $soloAutorizado ? 1 : (int) ($item['autorizado_retiros'] ?? 0),
                'vive_con_estudiante'      => (int) ($item['vive_con_estudiante'] ?? 0),
                'nombre_referencia'        => trim((string) ($item['nombre_referencia'] ?? '')),
                'primer_nombre'            => $this->capitalizarTitulo(trim((string) ($item['primer_nombre'] ?? ''))),
                'segundo_nombre'           => $this->capitalizarTitulo(trim((string) ($item['segundo_nombre'] ?? ''))),
                'primer_apellido'          => $this->capitalizarTitulo(trim((string) ($item['primer_apellido'] ?? ''))),
                'segundo_apellido'         => $this->capitalizarTitulo(trim((string) ($item['segundo_apellido'] ?? ''))),
                'nivel_instruccion'        => $this->capitalizarOracion(trim((string) ($item['nivel_instruccion'] ?? ''))),
                'ocupacion'                => $this->capitalizarOracion(trim((string) ($item['ocupacion'] ?? ''))),
                'tipo_documento'           => $this->tipoDocumentoFamiliar($item),
                'numero_documento'         => $this->normalizarDocumentoFamiliar($item),
                'representante_principal'  => (int) ($item['representante_principal'] ?? 0),
                'estado_relacion'          => (string) ($item['estado_relacion'] ?? 'ACTIVO'),
                'sexo'                     => ((string) ($item['sexo'] ?? '')) === 'F' ? 'F' : 'M',
                /* Entidad federal de nacimiento. Solo se guarda para el representante legal,
                   la madre y el padre; para los demás llega 0 y se guarda como NULL. */
                'id_estado_nacimiento'     => (int) ($item['id_estado_nacimiento'] ?? 0) ?: null,
                /* La nacionalidad se le pide a todo familiar, no solo al que declara el
                lugar de nacimiento. Es un dato de la persona y no del vínculo. */
                'id_nacionalidad'          => (int) ($item['id_nacionalidad'] ?? 0) ?: null,
                'fecha_nacimiento'         => $this->normalizarEspacios((string) ($item['fecha_nacimiento'] ?? '')),

                'contacto' => [
                    'codigo'   => trim((string) ($item['contacto']['codigo'] ?? '')),
                    'numero'   => trim((string) ($item['contacto']['numero'] ?? '')),
                    'codigo_alt' => trim((string) ($item['contacto']['codigo_alt'] ?? '')),
                    'numero_alt' => trim((string) ($item['contacto']['numero_alt'] ?? '')),
                    'correo'     => mb_strtolower(trim((string) ($item['contacto']['correo'] ?? ''))),
                ],

                'direccion' => [
                    // Se acepta `parroquia` como alias de `id_parroquia`, porque el
                    // formulario y el modelo no siempre usan el mismo nombre y
                    // perder la dirección en silencio era el síntoma.
                    'id_parroquia' => (int) ($item['direccion']['id_parroquia'] ?? $item['direccion']['parroquia'] ?? 0),
                    'sector'        => $this->capitalizarTitulo(trim((string) ($item['direccion']['sector'] ?? ''))),
                    'calle'         => $this->capitalizarTitulo(trim((string) ($item['direccion']['calle'] ?? ''))),
                    'casa'          => mb_strtoupper(trim((string) ($item['direccion']['casa'] ?? '')), 'UTF-8'),
                    'referencia'    => trim((string) ($item['direccion']['referencia'] ?? '')),
                ],
            ];
        }

        return $familiares;
    }

    /**
 * Número de documento del familiar: solo dígitos. La letra (V/E) va aparte,
 * en tipoDocumentoFamiliar().
 *
 * @param array<string, mixed> $item
*/
    private function normalizarDocumentoFamiliar(array $item): string
    {
        return Documento::normalizar(trim((string) ($item['numero_documento'] ?? '')));
    }

    /**
 * Tipo de documento del familiar. Si el formulario no lo mandó, se deduce de la
 * cédula: un representante extranjero sin prefijo se guardaría como venezolano
 * y eso rompe la letra de la cédula escolar del alumno.
 *
 * Un tipo que SÍ vino (por ejemplo "CE") no se corrige aquí: se deja pasar tal
 * cual para que validarFamiliares() lo rechace, en vez de ocultar el error.
 *
 * @param array<string, mixed> $item
*/
    private function tipoDocumentoFamiliar(array $item): string
    {
        $tipo  = mb_strtoupper(trim((string) ($item['tipo_documento'] ?? '')), 'UTF-8');
        $crudo = trim((string) ($item['numero_documento'] ?? ''));

        if ($tipo === '' && Documento::normalizar($crudo) !== '') {
            return Documento::letraDe($crudo);
        }

        return $tipo;
    }

    /**
 * El familiar marcado como representante legal, o null si no hay ninguno.
 *
 * @param array<int, array<string, mixed>> $familiares
 * @return array<string, mixed>|null
*/
    protected function representanteLegal(array $familiares): ?array
    {
        foreach ($familiares as $familiar) {
            if ((int) ($familiar['representante_principal'] ?? 0) === 1) {
                return $familiar;
            }
        }

        return null;
    }

    /**
 * Comprueba el bloque de familiares completo.
 *
 * Las reglas, en orden de importancia:
 * 1. Debe existir al menos un familiar (salvo que el módulo no lo exija).
 * 2. Solo puede haber UN representante legal. Dos representantes harían
 * ambiguo de quién es la cédula escolar y con quién se habla.
 * 3. Una persona sin parentesco ("no es familiar") no puede ser el
 * representante legal, porque de ella sale la cédula escolar y eso exige
 * un vínculo con el menor.
 * 4. El tipo de documento de un familiar es V o E: la cédula escolar (CE) es
 * solo del estudiante. Todo familiar necesita número de documento, de 7 a 8
 * dígitos, porque con él se identifica a la persona al guardar la relación.
 * 5. Todo familiar con parentesco declarado necesita teléfono principal.
 * 6. El representante necesita además domicilio completo y fecha de
 * nacimiento de mayor de edad.
 *
 * Los mensajes dicen QUÉ falta y dónde, porque llegan al formulario como un
 * solo error en `familiares`.
 *
 * @param array<int, array<string, mixed>> $familiares
 * @param array<string, string> $errores campo => mensaje, por referencia
 * @param bool $exigirRepresentante false cuando el módulo solo edita: el
 * alumno ya está inscripto y su representante ya existe.
*/
    protected function validarFamiliares(array $familiares, array &$errores, bool $exigirRepresentante = true): void
    {
        if ($familiares === []) {
            if ($exigirRepresentante) {
                $errores['familiares'] = 'Debe registrar al menos un familiar.';
            }
            return;
        }

        $representantes = array_values(array_filter(
            $familiares,
            static fn (array $f): bool => (int) ($f['representante_principal'] ?? 0) === 1
        ));

        if ($exigirRepresentante && $representantes === []) {
            $errores['familiares'] = 'Debe marcar un familiar como representante legal.';
            return;
        }

        if (count($representantes) > 1) {
            $errores['familiares'] = 'Solo puede haber un representante legal por estudiante; deje marcado uno solo.';
            return;
        }

        $erroresRol = [];

        foreach ($familiares as $familiar) {
            $esRepresentante = (int) ($familiar['representante_principal'] ?? 0) === 1;
            $soloAutorizado  = (bool) ($familiar['solo_autorizado'] ?? false);
            $parentesco      = trim((string) ($familiar['parentesco'] ?? ''));
            $contacto        = $familiar['contacto'] ?? [];
            $faltantes       = [];

            if ($soloAutorizado && $esRepresentante) {
                $erroresRol[] = 'Una persona sin parentesco no puede ser el representante legal: márquela como autorizada '
                    . 'para retiros o deje de marcar el representante.';
                continue;
            }

            if (!$soloAutorizado && $parentesco === '') {
                $erroresRol[] = 'Falta el parentesco con el estudiante: márquelo en el campo de parentesco, o active '
                    . '"no es familiar" si no tiene ningún vínculo.';
                continue;
            }

            if ($parentesco !== '' && !$this->existeParentesco($parentesco)) {
                $erroresRol[] = 'El parentesco "' . $parentesco . '" no existe en el catálogo. Revise la lista de parentescos.';
                continue;
            }

            // Documento: un familiar solo puede tener cédula V o E. La cédula escolar
            // (CE) se genera para el estudiante y nunca identifica a un familiar.
            $tipoDoc   = (string) ($familiar['tipo_documento'] ?? '');
            $numeroDoc = (string) ($familiar['numero_documento'] ?? '');

            if ($tipoDoc !== '' && $this->errorTipoDocumento($tipoDoc, ['V', 'E']) !== null) {
                $faltantes[] = 'un tipo de documento V o E (la cédula escolar es solo del estudiante)';
            } elseif ($numeroDoc === '') {
                // El número es lo que identifica a la persona al guardar la relación
                // (buscarOCrearPersonaFamiliar), así que lo necesita todo familiar.
                $faltantes[] = 'su número de cédula';
            } elseif ($this->errorFormatoDocumento($numeroDoc, $tipoDoc) !== null) {
                $faltantes[] = 'una cédula de 7 a 8 dígitos';
            }

            $erroresContacto = [];
            $this->validarContacto([
                'contacto' => [
                    'codigo_telefono_principal'   => $contacto['codigo'] ?? '',
                    'numero_telefono_principal'   => $contacto['numero'] ?? '',
                    'codigo_telefono_alternativo' => $contacto['codigo_alt'] ?? '',
                    'numero_telefono_alternativo' => $contacto['numero_alt'] ?? '',
                    'correo_electronico'          => $contacto['correo'] ?? '',
                ],
            ], $erroresContacto, true);

            if (isset($erroresContacto['numero_telefono_principal'], $erroresContacto['codigo_telefono_principal'])) {
                $faltantes[] = 'el teléfono principal (prefijo y número válidos)';
            } elseif (isset($erroresContacto['numero_telefono_principal'])) {
                $faltantes[] = 'un teléfono principal con 7 dígitos';
            } elseif (isset($erroresContacto['codigo_telefono_principal'])) {
                $faltantes[] = 'un prefijo válido para el teléfono principal';
            }

            if (isset($erroresContacto['numero_telefono_alternativo'], $erroresContacto['codigo_telefono_alternativo'])) {
                $faltantes[] = 'un teléfono alternativo válido (o dejarlo vacío)';
            }

            if (isset($erroresContacto['correo_electronico'])) {
                $faltantes[] = 'un correo electrónico válido';
            }

            // El domicilio se solicita al representante legal, a la madre o al
            // padre. La edad adulta solo se exige al representante legal.
            $parentescoNormalizado = mb_strtolower($parentesco, 'UTF-8');
            $requiereDireccion = $esRepresentante
                || in_array($parentescoNormalizado, ['madre', 'padre'], true);
            if ($requiereDireccion) {
                $direccion = $familiar['direccion'] ?? [];

                foreach (['sector' => 'el sector', 'calle' => 'la calle', 'casa' => 'el número de casa'] as $campo => $etiqueta) {
                    if (trim((string) ($direccion[$campo] ?? '')) === '') {
                        $faltantes[] = $etiqueta;
                    }
                }

                if ((int) ($direccion['id_parroquia'] ?? 0) <= 0) {
                    $faltantes[] = 'la parroquia (seleccione estado, municipio y parroquia)';
                }
            }

            /* El lugar de nacimiento se pregunta a los mismos tres que el domicilio: el
               representante legal, la madre y el padre. Para un tío, un primo o un vecino
               no se pregunta y tampoco se exige: la institución necesita saber dónde nació
               quien firma y responde por el menor, no el resto del árbol familiar.
               Se compara por NOMBRE de parentesco y no por id, igual que el JS, porque los
               ids del catálogo pueden cambiar si alguien rehace el catálogo. */
            /* $esNuevo se deduce del id_persona: si viene, ese familiar ya estaba
            registrado. Y es la diferencia entre exigir y no, porque los dos campos se
            añadieron DESPUÉS de que existieran las personas: todos los familiares que
            había están en NULL. Exigírselos hacía que no se pudiera guardar ni una
            edición, que era justo lo que había que evitar.

            Regla: en un alta se exigen los dos. En una edición, si el campo viene se
            comprueba que sea válido; si no viene, se deja en NULL y se podrá rellenar la
            próxima vez que se abra. Un requisito no puede aplicarse hacia atrás a datos
            que nunca pudieron cumplirlo. */
            $esNuevo = (int) ($familiar['id_persona'] ?? 0) <= 0;

            if ($requiereDireccion && (int) ($familiar['id_estado_nacimiento'] ?? 0) <= 0 && $esNuevo) {
                $faltantes[] = 'la entidad federal donde nació';
            } elseif ((int) ($familiar['id_estado_nacimiento'] ?? 0) > 0) {
                /* Viene informado, así que sí tiene que ser coherente. */
                $erroresLugar = [];
                $this->validarLugarNacimiento($familiar, $erroresLugar, false);
                if ($erroresLugar !== []) {
                    $faltantes[] = 'una entidad federal de nacimiento válida';
                }
            }

            /* La nacionalidad se le pide a TODO familiar, a diferencia del lugar de
            nacimiento, que tiene la excepción del representante, la madre y el padre. Se
            comprueba contra el catálogo y no solo que venga: un id viejo o desactivado
            también invalida. */
            if ((int) ($familiar['id_nacionalidad'] ?? 0) > 0) {
                $erroresNacionalidad = [];
                $this->validarNacionalidad($familiar, $erroresNacionalidad, false);
                if (isset($erroresNacionalidad['id_nacionalidad'])) {
                    $faltantes[] = 'una nacionalidad válida';
                }
            } elseif ($esNuevo) {
                $faltantes[] = 'la nacionalidad';
            }

            if ($esRepresentante) {
                $erroresFecha = [];
                $this->validarFechaNacimiento($familiar, $erroresFecha, 'fecha_nacimiento', 18, 100);
                if (isset($erroresFecha['fecha_nacimiento'])) {
                    $faltantes[] = 'una fecha de nacimiento válida (mayor de edad)';
                }
            }

            if ($faltantes !== []) {
                $quien = $esRepresentante ? 'El representante legal' : 'El familiar';
                /* En español una lista se cierra con "y", no con una coma: "necesita la
                entidad federal donde nació, la nacionalidad" se lee como un frase mal
                puesta y no como dos cosas que faltan. */
                $erroresRol[] = $quien . ' necesita ' . $this->enumerar($faltantes) . '.';
            }
        }

        if ($erroresRol !== []) {
            $errores['familiares'] = implode(' ', $erroresRol);
        }
    }

    /**
     * Une una lista de cosas en español: "a", "a y b", "a, b y c".
     *
     * implode(', ') no sirve para un texto que termina en punto: "necesita la entidad
     * federal donde nació, la nacionalidad" parece una frase mal escrita en vez de dos
     * cosas que faltan, que es justo lo que la secretaria tiene que entender para saber
     * qué corregir.
     *
     * @param string[] $cosas
     */
    private function enumerar(array $cosas): string
    {
        $n = count($cosas);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $cosas[0];
        }
        $ultima = array_pop($cosas);

        return implode(', ', $cosas) . ' y ' . $ultima;
    }

    /** ¿Ese nombre de parentesco está en el catálogo? Cachea la lista por request.*/
    private function existeParentesco(string $nombre): bool
    {
        if ($this->nombresParentescoCache === null) {
            $stmt = $this->conexion()->query("SELECT nombre FROM parentesco WHERE estado = 'ACTIVO'");
            $this->nombresParentescoCache = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $activo) {
                $this->nombresParentescoCache[(string) $activo] = true;
            }
        }

        return isset($this->nombresParentescoCache[$nombre]);
    }
}