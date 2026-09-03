<?php

/*
  SecurityTrait
 
 Centraliza las herramientas de seguridad, validación y redirección 
 compartidas por todos los controladores de la aplicación.
 */
if (!function_exists('sigde_rol_clave')) {
    function sigde_rol_clave(string $rol): string
    {
        $rol = mb_strtolower(trim($rol), 'UTF-8');
        $rol = strtr($rol, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        return match ($rol) {
            'administrador', 'administrator' => 'admin',
            'secretaria'                   => 'secretaria',
            'director', 'directora'        => 'directivo',
            default                         => $rol,
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
      Sanitiza cadenas de texto para prevenir ataques XSS.
     */
    public function sanitizarTexto(string $texto): string
    {
        $textoLimpio = trim($texto);
        return htmlspecialchars($textoLimpio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function validarUsuario(string $usuario): bool
    {
    // Solo letras (mayúsculas/minúsculas) y números
    return preg_match('/^[a-zA-Z0-9]+$/', $usuario);
    }

    /*
      Genera (o reutiliza) el token CSRF de la sesión actual. Se llama
      desde la vista de login.php para imprimirlo en el input hidden
      csrf_token.
     */
    public function generarCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /*
      Valida el token CSRF recibido por POST contra el guardado en sesión.
      Usa hash_equals() para comparación segura contra timing attacks.
      Regenera el token tras cada uso (evita reenvío/replay del mismo
      token en otro formulario).
     */
    public function validarCsrfToken(string $tokenRecibido): bool
    {
        if (empty($tokenRecibido) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        $esValido = hash_equals($_SESSION['csrf_token'], $tokenRecibido);

        unset($_SESSION['csrf_token']);

        return $esValido;
    }

    /*
      Garantiza que la sesión esté iniciada y que el usuario esté autenticado.
     */
    public function verificarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_id'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            
            // Usamos SweetAlert para avisar que debe iniciar sesión
            $_SESSION['swal'] = [
                'icon'  => 'warning',
                'title' => 'Sesión requerida',
                'text'  => 'Debes iniciar sesión para acceder a esta sección.'
            ];
            $this->redireccionar('auth/login');
        }
    }

    /*
      Comprueba si el rol del usuario autenticado tiene acceso permitido.
     */
    public function verificarRol(array $rolesPermitidos): void
    {
        $this->verificarSesion(); // Primero aseguramos que haya sesión

        $rolActual = sigde_rol_clave($_SESSION['rol'] ?? 'usuario');
        $rolesPermitidosLower = array_map('sigde_rol_clave', $rolesPermitidos);

        if (!in_array($rolActual, $rolesPermitidosLower, true)) {
            error_log("DEBUG SecurityTrait: Rol actual '$rolActual' no está en roles permitidos: " . print_r($rolesPermitidosLower, true));
            // Unificamos el mensaje de error para que use SweetAlert
            $_SESSION['swal'] = [
                'icon'  => 'error',
                'title' => 'Acceso denegado',
                'text'  => 'No tienes los permisos necesarios para ver esta sección.'
            ];
            $this->redireccionar('dashboard/index');
        }
    }

    /*
     Destruye la sesión de forma agresiva porque el usuario ha sido bloqueado.
     */
    public function destruirSesionPorBloqueo(int $minutosRestantes): void
    {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }

        session_destroy();
        session_start();
        
        // Unificamos el mensaje de bloqueo para que use SweetAlert
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
      carácter especial a propósito -- el bloqueo tras 3 intentos
      fallidos ya cubre la fuerza bruta, y el objetivo es reducir
      fricción para personal no técnico.
     */
    public function validarPasswordFuerte(string $password): string|true 
    {
        if (strlen($password) < 8) {
            return "La contraseña debe tener al menos 8 caracteres.";
        }
         if (strlen($password) > 20) {
        return "La contraseña no puede tener más de 20 caracteres.";
        }
        if (!preg_match('/[a-zA-Z]/', $password)) {
            return "La contraseña debe contener al menos una letra.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "La contraseña debe contener al menos un número.";
        }
        
        return true; // Si pasa las pruebas, cumple el mínimo
    }

    /*
     Valida que un nombre completo solo contenga letras y espacios.
     (Evita inyección de números o símbolos en el nombre).
     */
    public function validarNombreCompleto(string $nombre): string|true 
    {
        $nombreLimpio = trim($nombre);
        
        if (strlen($nombreLimpio) < 3) {
            return "El nombre debe tener al menos 3 caracteres.";
        }
        
        if (strlen($nombreLimpio) > 100) {
            return "El nombre no puede exceder los 100 caracteres.";
        }

        // Regex: solo letras (con tildes y ñ) y espacios. Sin números ni símbolos.
        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombreLimpio)) {
            return "El nombre solo debe contener letras y espacios (sin números ni caracteres especiales).";
        }
        
        return true;
    }

    /*
     Helper privado para centralizar las redirecciones y cortar la ejecución.
     Ahora es "a prueba de subcarpetas" gracias a BASE_URL.
     */
    private function redireccionar(string $url): void
    {
        // ltrim quita la barra inicial si la hay, y le pega el BASE_URL
        $urlCompleta = BASE_URL . ltrim($url, '/');
        
        header("Location: {$urlCompleta}");
        exit; // CRÍTICO: Siempre usar exit después de un header Location
    }
}