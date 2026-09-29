<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\Telefonos;

trait ContactoValidacionTrait
{
    /**
     * Valida teléfonos y correo electrónico.
     */
    protected function validarContacto(
        array $data,
        array &$errores,
        bool $esObligatorio = true
    ): void {
        $contacto = $data['contacto'] ?? $data;

        $tlfPrincipal   = trim((string) ($contacto['numero_telefono_principal'] ?? ''));
        $codPrincipal   = trim((string) ($contacto['codigo_telefono_principal'] ?? ''));
        $tlfAlternativo = trim((string) ($contacto['numero_telefono_alternativo'] ?? ''));
        $codAlternativo = trim((string) ($contacto['codigo_telefono_alternativo'] ?? ''));
        $correo         = trim((string) ($contacto['correo_electronico'] ?? ''));

        // 1. Teléfono Principal
        if ($esObligatorio) {
            if ($tlfPrincipal === '') {
                $errores['numero_telefono_principal'] = 'El teléfono principal es obligatorio.';
            } elseif (!Telefonos::esNumeroValido($tlfPrincipal)) {
                $errores['numero_telefono_principal'] = 'El teléfono principal debe tener exactamente 7 dígitos.';
            }

            if ($codPrincipal === '' || !Telefonos::esCodigoValido($codPrincipal)) {
                $errores['codigo_telefono_principal'] = 'Selecciona un prefijo válido para el teléfono principal.';
            }
        } elseif ($tlfPrincipal !== '') {
            // Si no es obligatorio pero escribió un número, exigimos formato y prefijo
            if (!Telefonos::esNumeroValido($tlfPrincipal)) {
                $errores['numero_telefono_principal'] = 'El teléfono principal debe tener exactamente 7 dígitos.';
            }

            if ($codPrincipal === '' || !Telefonos::esCodigoValido($codPrincipal)) {
                $errores['codigo_telefono_principal'] = 'Selecciona un prefijo válido para el teléfono principal.';
            }
        } elseif ($codPrincipal !== '' && Telefonos::esCodigoValido($codPrincipal)) {
            // Seleccionó prefijo pero dejó el número vacío
            $errores['numero_telefono_principal'] = 'Indica el número para el teléfono principal.';
        }

        // 2. Teléfono Alternativo (Opcional)
        if ($tlfAlternativo !== '') {
            if (!Telefonos::esNumeroValido($tlfAlternativo)) {
                $errores['numero_telefono_alternativo'] = 'El teléfono alternativo debe tener exactamente 7 dígitos.';
            }

            if ($codAlternativo === '' || !Telefonos::esCodigoValido($codAlternativo)) {
                $errores['codigo_telefono_alternativo'] = 'Debes seleccionar un prefijo válido para el teléfono alternativo.';
            }
        } elseif ($codAlternativo !== '' && Telefonos::esCodigoValido($codAlternativo)) {
            $errores['numero_telefono_alternativo'] = 'Indica el número para el teléfono alternativo.';
        }

        // 3. Correo Electrónico (Opcional)
        if ($correo !== '') {
            if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false || !preg_match('/\.[a-zA-Z]{2,}$/', $correo)) {
                $errores['correo_electronico'] = 'Indica un correo electrónico válido.';
            }
        }
    }
}