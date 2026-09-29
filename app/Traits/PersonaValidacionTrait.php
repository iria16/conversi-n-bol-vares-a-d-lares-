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
     * @param array $data    Datos ya extraídos (extractData()).
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
     *   V / E → 7-8 dígitos; el prefijo y los separadores son opcionales y los resuelve App\Support\Documento.
     *   CE    → cédula escolar: letra (V o E) + 10 dígitos, generada por App\Support\CedulaEscolar; este regex garantiza el formato.
     */
    protected function validarNumeroDocumento(string $numeroDocumento, string $tipoDocumento = 'V'): bool
    {
        $numero = trim($numeroDocumento);

        return match ($tipoDocumento) {
            'CE'    => (bool) preg_match(CedulaEscolar::PATRON, $numero),
            default => Documento::esCedula($numero),
        };
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

        if (!in_array($tipo, $tiposPermitidos, true)) {
            $errores['tipo_documento'] = 'Selecciona un tipo de documento válido (' . implode(', ', $tiposPermitidos) . ').';
        }

        if ($numero === '') {
            $errores['numero_documento'] = 'El documento es obligatorio.';
        } elseif (!$this->validarNumeroDocumento($numero, $tipo)) {
            $mensajeFormato = $tipo === 'CE'
                ? 'La cédula escolar debe tener una letra (V o E) y 10 dígitos.'
                : 'El documento solo admite números (7 a 8 dígitos).';
            $errores['numero_documento'] = $mensajeFormato;
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

    /** Valida una fecha de nacimiento contra un rango de edad y agrega el error a $errores por referencia. */
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
}