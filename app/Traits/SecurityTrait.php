<?php

declare(strict_types=1);

namespace App\Traits;

if (!function_exists(__NAMESPACE__ . '\\sigde_rol_clave')) {
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
    /*
     Limpia y valida un correo electrónico.
     */
    public function sanitizarEmail(string $email): string|false
    {
        $emailLimpio = trim($email);
        if (!filter_var($emailLimpio, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return $emailLimpio;
    }

    /*
      Escapa una cadena para imprimirla dentro de HTML.

      IMPORTANTE: este helper es para la SALIDA (vistas), no para la entrada.
      Nunca guardes el resultado en base de datos ni en $_SESSION: eso corrompe
      el dato original (un apellido como O'Brien queda como O&#039;Brien) y no
      protege si ese mismo dato se envía luego en JSON o en un PDF.
     */
    public function sanitizarTexto(string $texto): string
    {
        return htmlspecialchars(trim($texto), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /*
      Garantiza que la sesión esté iniciada y que el usuario esté autenticado.
      Si no hay sesión, guarda la URL solicitada y redirige al login.
     */
    public function verificarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';

            $_SESSION['swal'] = [
                'icon'  => 'warning',
                'title' => 'Sesión requerida',
                'text'  => 'Debes iniciar sesión para acceder a esta sección.'
            ];
            $this->redireccionar('auth/login');
        }
    }

    /*
     Destruye la sesión de forma agresiva porque el usuario ha sido bloqueado.
     */
    public function destruirSesionPorBloqueo(int $minutosRestantes): never
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        session_start();

        $_SESSION['swal'] = [
            'icon'  => 'warning',
            'title' => 'Cuenta bloqueada',
            'text'  => "Tu cuenta ha sido bloqueada por seguridad. Intente nuevamente en {$minutosRestantes} minuto(s)."
        ];

        $this->redireccionar('auth/login');
    }

    /*
      Valida que una contraseña cumpla con los requisitos mínimos de
      seguridad. Alineado con el checklist visual del frontend
      (main.js: initPasswordRequirementsChecklist): longitud mínima,
      al menos una letra y al menos un número. No exige mayúscula ni
      carácter especial a propósito: el bloqueo tras 3 intentos
      fallidos ya cubre la fuerza bruta, y el objetivo es reducir
      fricción para personal no técnico.

      Se mide con mb_strlen (caracteres, no bytes) para que las tildes
      no cuenten doble.
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
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return 'La contraseña debe contener al menos una letra.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'La contraseña debe contener al menos un número.';
        }

        return true; // Si pasa las pruebas, cumple el mínimo
    }

    /*
     Valida que un nombre completo solo contenga letras y espacios.
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

        // Solo letras (con tildes y ñ) y espacios. Sin números ni símbolos.
        if (!preg_match('/^[\p{L}\s]+$/u', $nombreLimpio)) {
            return 'El nombre solo debe contener letras y espacios (sin números ni caracteres especiales).';
        }

        return true;
    }

    /*
     Helper para centralizar las redirecciones y cortar la ejecución.
     Declarado ": never" para que PHP y el análisis estático sepan que
     nada se ejecuta después de llamarlo.
     */
    private function redireccionar(string $url): never
    {
        $urlCompleta = BASE_URL . ltrim($url, '/');

        header("Location: {$urlCompleta}");
        exit;
    }
}