<?php

require_once __DIR__ . '/SecurityTrait.php';

class DashboardController
{
    use SecurityTrait;

    // Roles válidos del sistema y su vista de dashboard correspondiente.
    // Se usa tanto para el guard de acceso como para el redirect de index().
    private const RUTAS_POR_ROL = [
        'admin'        => 'dashboard/admin',
        'docente'      => 'dashboard/docente',
        'coordinador'  => 'dashboard/coordinador',
        'directivo'    => 'dashboard/directivo',
        'secretaria'   => 'dashboard/secretaria',
    ];

    public function __construct()
    {
        // Igual que en AuthController: sin sesión activa, no hay dashboard.
        if (!isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }
    }

    // Punto de entrada genérico: redirige al dashboard específico del rol
    // en sesión. Útil como destino único (dashboard/index) sin tener que
    // resolver el rol en cada lugar que arma un link.
    public function index(): void
    {
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');
        $ruta = self::RUTAS_POR_ROL[$rol] ?? null;

        if ($ruta === null) {
            // Rol desconocido o sesión corrupta: fuera.
            $this->redireccionar('auth/login');
        }

        header('Location: ' . BASE_URL . $ruta);
        exit;
    }

    public function admin(): void
    {
        $this->verificarAccesoRol('admin');
        require_once __DIR__ . '/../Views/dashboard/admin.php';
    }

    public function docente(): void
    {
        $this->verificarAccesoRol('docente');
        require_once __DIR__ . '/../Views/dashboard/docente.php';
    }

    public function coordinador(): void
    {
        $this->verificarAccesoRol('coordinador');
        require_once __DIR__ . '/../Views/dashboard/coordinador.php';
    }

    public function directivo(): void
    {
        $this->verificarAccesoRol('directivo');
        require_once __DIR__ . '/../Views/dashboard/directivo.php';
    }

    public function secretaria(): void
    {
        $this->verificarAccesoRol('secretaria');
        require_once __DIR__ . '/../Views/dashboard/secretaria.php';
    }

    // Evita que un usuario con rol X entre por URL directa al dashboard
    // de rol Y (ej. secretaria escribiendo /dashboard/admin en la barra).
    // Si el rol no coincide, lo mandamos a SU propio dashboard, no al login
    // (ya tiene sesión válida, solo no tiene permiso sobre esa vista).
    private function verificarAccesoRol(string $rolRequerido): void
    {
        $rolSesion = sigde_rol_clave($_SESSION['rol'] ?? '');

        if ($rolSesion !== $rolRequerido) {
            $ruta = self::RUTAS_POR_ROL[$rolSesion] ?? 'auth/login';
            header('Location: ' . BASE_URL . $ruta);
            exit;
        }
    }
}