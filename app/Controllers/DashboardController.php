<?php

namespace App\Controllers;

use App\Traits\SecurityTrait;
use function App\Traits\sigde_rol_clave;

class DashboardController
{
    use SecurityTrait;

    // Roles válidos del sistema y su método/ruta correspondiente 
    private const RUTAS_POR_ROL = [
        'admin'       => 'dashboard/admin/index',
        'docente'     => 'dashboard/docente/index',
        'coordinador' => 'dashboard/coordinador/index',
        'directivo'   => 'dashboard/directivo/index',
        'secretaria'  => 'dashboard/secretaria/index',
    ];

    public function __construct()
    {
        // Igual que en AuthController: sin sesión activa, no hay dashboard.
        if (!isset($_SESSION['usuario_id'])) {
            $this->redireccionar('auth/login');
        }
    }

    // Punto de entrada genérico: ejecuta directamente el dashboard correspondiente según el rol
    public function index(): void
    {
        $rol = sigde_rol_clave($_SESSION['rol'] ?? '');

        if (!method_exists($this, $rol)) {
            // Rol desconocido o sesión corrupta: fuera.
            $this->redireccionar('auth/login');
        }

        // Ejecuta dinámicamente el método del rol ($this->admin(), $this->docente(), etc.)
        $this->$rol();
    }

    public function admin(): void
    {
        $this->verificarAccesoRol('admin');
        require_once __DIR__ . '/../Views/dashboard/admin/index.php';
    }

    public function docente(): void
    {
        $this->verificarAccesoRol('docente');
        require_once __DIR__ . '/../Views/dashboard/docente/index.php';
    }

    public function coordinador(): void
    {
        $this->verificarAccesoRol('coordinador');
        require_once __DIR__ . '/../Views/dashboard/coordinador/index.php';
    }

    public function directivo(): void
    {
        $this->verificarAccesoRol('directivo');
        require_once __DIR__ . '/../Views/dashboard/directivo/index.php';
    }

    public function secretaria(): void
    {
        $this->verificarAccesoRol('secretaria');
        require_once __DIR__ . '/../Views/dashboard/secretaria/index.php';
    }

    // Evita que un usuario con rol X entre por URL directa al dashboard de rol Y
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