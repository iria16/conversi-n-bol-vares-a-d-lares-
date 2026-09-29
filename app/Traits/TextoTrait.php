<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Utilidades de formato de texto compartidas entre controladores.
 *
 * Se deja como trait (en vez de método propio de CatalogoController)
 * para poder reutilizar capitalizarTitulo() en cualquier otro
 * controlador que también guarde nombres/títulos (p. ej. si en el
 * futuro EstudiantesController o CuentaUsuarioController necesitan
 * la misma normalización).
 */
trait TextoTrait
{
    /**
     * Conectores que van en minúscula salvo que sean la primera palabra.
     */
    private const CONECTORES = [
        'de', 'del', 'en', 'da', 'con', 'por', 'para', 'a', 'e', 'i', 'o', 'u', 'y',
        'la', 'las', 'el', 'los', 'las', 'lo',
    ];

    /**
     * Deja el texto en un solo espacio entre palabras y sin espacios en los
     * extremos. Punto de partida de ambas capitalizaciones: sin esto,
     * "JUAN   CARLOS" se guardaba con los espacios que el usuario escribió.
     */
    protected function normalizarEspacios(string $texto): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim($texto));

        return $texto ?? '';
    }

    /**
     * Detecta siglas escritas con punto ("u.e.", "s.a.", "c.a.") para no
     * romperlas: mb_convert_case() las dejaría como "U.e.", que no es como
     * se escribe una sigla en español.
     */
    private function esSigla(string $palabra): bool
    {
        return (bool) preg_match('/^[a-záéíóúñ]{1,3}(\.[a-záéíóúñ]{1,3})+\.?$/u', $palabra);
    }

    /**
     * Capitaliza cada palabra, dejando en minúscula los conectores comunes
     * (salvo cuando son la primera palabra) y respetando las siglas.
     * Pensado para nombres cortos de persona o de catálogo, de una o pocas
     * palabras: "JUAN de la CRUZ" → "Juan de la Cruz".
     */
    protected function capitalizarTitulo(string $texto): string
    {
        $texto = $this->normalizarEspacios(mb_strtolower($texto, 'UTF-8'));

        if ($texto === '') {
            return '';
        }

        $palabras = explode(' ', $texto);

        foreach ($palabras as $indice => $palabra) {
            if ($this->esSigla($palabra)) {
                $palabras[$indice] = mb_strtoupper($palabra, 'UTF-8');
                continue;
            }
            // La primera palabra SIEMPRE se capitaliza; las demás solo si no son conectores
            if ($indice === 0 || !in_array($palabra, self::CONECTORES, true)) {
                $palabras[$indice] = mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8');
            }
        }

        return implode(' ', $palabras);
    }

    /**
     * Pone en mayúscula solo la primera letra del texto; el resto en
     * minúscula. Es el estilo natural en español para frases largas
     * ("autorización del consejo de protección"), a diferencia de
     * capitalizarTitulo() que se ve forzado en frases de varias palabras.
     */
    protected function capitalizarOracion(string $texto): string
    {
        $texto = $this->normalizarEspacios(mb_strtolower($texto, 'UTF-8'));

        if ($texto === '') {
            return '';
        }

        $espacio         = mb_strpos($texto, ' ');
        $primeraPalabra  = $espacio === false ? $texto : mb_substr($texto, 0, $espacio);
        $resto           = $espacio === false ? '' : mb_substr($texto, $espacio + 1);
        // "u.e. bolivariana" → "U.E. bolivariana": si la primera palabra es
        // una sigla se conserva en mayúsculas en vez de romperla en "U.e.".
        if ($this->esSigla($primeraPalabra)) {
            $primeraPalabra = mb_strtoupper($primeraPalabra, 'UTF-8');
        } else {
            $primeraPalabra = mb_strtoupper(mb_substr($primeraPalabra, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($primeraPalabra, 1, null, 'UTF-8');
        }

        return $primeraPalabra . ($resto === '' ? '' : ' ' . $resto);
    }
}