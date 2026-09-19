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
     * Capitaliza cada palabra de un texto, dejando en minúscula los
     * conectores comunes (salvo cuando son la primera palabra).
     * Pensado para nombres de catálogo como "Licenciado en Informática"
     * o "Director de Área".
     */
    protected function capitalizarTitulo(string $texto): string
    {
        $texto = trim(mb_strtolower($texto, 'UTF-8'));
        if ($texto === '') {
            return '';
        }

        // Palabras que deben mantenerse en minúscula (salvo si son la primera palabra)
        $conectores = ['de', 'del', 'en', 'con', 'por', 'para', 'a', 'e', 'i', 'o', 'u', 'y', 'la', 'las', 'el', 'los'];

        $palabras = explode(' ', $texto);

        foreach ($palabras as $indice => $palabra) {
            // La primera palabra SIEMPRE se capitaliza; las demás solo si no son conectores
            if ($indice === 0 || !in_array($palabra, $conectores, true)) {
                $palabras[$indice] = mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8');
            }
        }

        return implode(' ', $palabras);
    }
}