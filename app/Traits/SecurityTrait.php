<?php

declare(strict_types=1);

namespace App\Traits;

if (!function_exists(__NAMESPACE__ . '\\sigde_rol_clave')) {
    /**
 * Convierte el nombre de un rol de la BD en la clave que usa la matriz de rutas de index.php.
 * Un rol desconocido se devuelve en minúsculas y sin tildes, y no coincidirá con ninguna ruta (403).
*/
    function sigde_rol_clave(string $rol): string
    {
        $rol = mb_strtolower(trim($rol), 'UTF-8');
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        return match ($rol) {
            'administrador', 'administrator'    => 'admin',
            'secretaria'                        => 'secretaria',
            'director', 'directora'             => 'directivo',
            'coordinador', 'coordinadora'       => 'coordinador',
            'docente', 'profesor', 'profesora'  => 'docente',
            default                             => $rol,
        };
    }
}

trait SecurityTrait
{
    /**
 * Limpia y valida un correo electrónico.
 * Devuelve el correo sin espacios o false si el formato no es válido.
*/
    public function sanitizarEmail(string $email): string|false
    {
        $emailLimpio = trim($email);
        if (!filter_var($emailLimpio, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return $emailLimpio;
    }

    /**
 * Escapa un texto para imprimirlo en HTML. Úsalo solo en la SALIDA (vistas).
 * No guardes el resultado en la BD ni en $_SESSION: corrompe el dato (O'Brien pasa a O&#039;Brien).
*/
    public function sanitizarTexto(string $texto): string
    {
        return htmlspecialchars(trim($texto), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
 * Comprueba que haya un usuario autenticado; si no, avisa con SweetAlert y redirige al login.
 * Es una segunda línea de defensa: index.php ya filtra las rutas privadas antes de llegar aquí.
 * Tras el login siempre se va al dashboard (ver AuthController::authenticate()).
*/
    public function verificarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['swal'] = [
                'icon'  => 'warning',
                'title' => 'Sesión requerida',
                'text'  => 'Debes iniciar sesión para acceder a esta sección.'
            ];
            $this->redireccionar('auth/login');
        }
    }

    /**
 * Valida los requisitos mínimos de una contraseña: 8 a 64 caracteres, una letra y un número.
 * Coincide con el checklist de main.js. No pide mayúscula ni símbolo: el bloqueo por intentos ya frena la fuerza bruta.
 * Devuelve true si es válida o el mensaje de error.
*/
    public function validarPasswordFuerte(string $password): string|true
    {
        $longitud = mb_strlen($password, 'UTF-8');

        if ($longitud < 8) {
            return 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($longitud > 64) {
            return 'La contraseña no puede tener más de 64 caracteres.';
        }
        // bcrypt ignora todo lo que pase de 72 BYTES; con tildes 64 caracteres pueden superarlos.
        if (strlen($password) > 72) {
            return 'La contraseña es demasiado larga. Use menos caracteres o evite las tildes.';
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return 'La contraseña debe contener al menos una letra.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'La contraseña debe contener al menos un número.';
        }

        return true;
    }

    /**
 * Valida un nombre completo: de 3 a 100 caracteres con letras (con tildes y ñ), espacios, apóstrofos y guiones.
 * Admite nombres como D'Ambrosio o María-José y rechaza números y otros símbolos.
 * Devuelve true si es válido o el mensaje de error.
*/
    public function validarNombreCompleto(string $nombre): string|true
    {
        $nombreLimpio = trim($nombre);
        $longitud     = mb_strlen($nombreLimpio, 'UTF-8');

        if ($longitud < 3) {
            return 'El nombre debe tener al menos 3 caracteres.';
        }

        if ($longitud > 100) {
            return 'El nombre no puede exceder los 100 caracteres.';
        }

        // \p{L} letras, \p{M} tildes escritas como carácter combinado; el espacio es explícito (sin saltos de línea).
        if (!preg_match('/^[\p{L}\p{M}\' \-]+$/u', $nombreLimpio)) {
            return 'El nombre solo debe contener letras, espacios, apóstrofos y guiones (sin números ni otros símbolos).';
        }

        return true;
    }

    /**
 * Redirige a una ruta del sistema (BASE_URL + $url) y corta la ejecución.
 * Es `never`: PHP y el análisis estático saben que nada se ejecuta después de llamarlo.
*/
    private function redireccionar(string $url): never
    {
        $urlCompleta = BASE_URL . ltrim($url, '/');

        header("Location: {$urlCompleta}");
        exit;
    }
}