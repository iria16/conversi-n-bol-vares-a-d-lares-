<?php
/**
 * Helpers de presentación compartidos por el layout interno.
 * Solo se cargan una vez (guard function_exists).
 */

if (!function_exists('sigde_iniciales')) {
    function sigde_iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/u', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $primera = $partes[0] ?? '';
        $ultima  = count($partes) > 1 ? (string) end($partes) : '';
        $a = $primera !== '' ? mb_strtoupper(mb_substr($primera, 0, 1)) : '';
        $b = $ultima !== '' ? mb_strtoupper(mb_substr($ultima, 0, 1)) : '';

        return $a . $b ?: 'SG';
    }
}

if (!function_exists('sigde_rol_clave')) {
    /**
     * Clave canónica de rol usada en menú, dashboard y guards.
     * La BD puede devolver "Administrador", "ADMIN", "admin", etc.
     */
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

if (!function_exists('sigde_rol_etiqueta')) {
    function sigde_rol_etiqueta(string $rol): string
    {
        return match (sigde_rol_clave($rol)) {
            'admin'       => 'Administrador',
            'directivo'   => 'Directivo',
            'secretaria'  => 'Secretaría',
            'docente'     => 'Docente',
            'coordinador' => 'Coordinador',
            default       => 'Usuario',
        };
    }
}

if (!function_exists('sigde_nav_sections')) {
    /**
     * Menú lateral filtrado por rol. Los href "#" son placeholders de diseño
     * hasta que existan las rutas reales.
     *
     * Los items pueden declarar su propio 'roles'; si no lo declaran,
     * heredan los roles de la sección (útil cuando toda la sección es
     * exclusiva de un solo rol, como "Usuarios"). Esto permite que una
     * misma sección (ej. "Reportes") muestre ítems distintos según el rol
     * sin duplicar la sección completa.
     *
     * @return list<array{label:string,items:list<array{id:string,label:string,icon:string,href:string}>}>
     */
    function sigde_nav_sections(string $rol): array
    {
        $rol = sigde_rol_clave($rol);
        $dashboardHref = BASE_URL . 'dashboard/' . ($rol !== '' ? $rol : 'index');


        $todas = [
            [
                'label' => 'Panel',
                'roles' => ['admin', 'directivo', 'secretaria', 'docente', 'coordinador'],
                'items' => [
                    ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'href' => $dashboardHref],
                ],
            ],
            [
                'label' => 'Usuarios',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'cuentas', 'label' => 'Cuentas de usuario', 'icon' => 'bi-people', 'href' => BASE_URL . 'userAccount/index'],
                    ['id' => 'recuperacion', 'label' => 'Recuperación de acceso', 'icon' => 'bi-key', 'href' => BASE_URL .'accessRecovery/index'],
                ],
            ],
            [
                'label' => 'Gestión de estudiantes',
                'roles' => ['secretaria'],
                'items' => [
                    ['id' => 'estudiantes',   'label' => 'Estudiantes',   'icon' => 'bi-mortarboard',      'href' => BASE_URL .'estudiantes/index'],
                    ['id' => 'inscripcion',   'label' => 'Inscripción',   'icon' => 'bi-person-plus',      'href' => BASE_URL . 'inscripciones/index'],
                    ['id' => 'ratificacion',  'label' => 'Ratificación',  'icon' => 'bi-arrow-repeat',     'href' => '#'],
                    ['id' => 'retiro',        'label' => 'Retiro',        'icon' => 'bi-box-arrow-right',  'href' => '#'],
                    ['id' => 'egreso',        'label' => 'Egreso',        'icon' => 'bi-mortarboard-fill', 'href' => '#'],
                ],
            ],
            [
                'label' => 'Constancias',
                'roles' => ['secretaria'],
                'items' => [
                    ['id' => 'constancia-estudio',     'label' => 'Constancia de Estudio',     'icon' => 'bi-file-earmark-text',  'href' => '#'],
                    ['id' => 'constancia-inscripcion', 'label' => 'Constancia de Inscripción', 'icon' => 'bi-file-earmark-check', 'href' => '#'],
                ],
            ],
            [
                'label' => 'Notificaciones',
                'roles' => ['directivo'],
                'items' => [
                    ['id' => 'retiro', 'label' => 'Retiros Pendientes', 'icon' => 'bi-person-dash', 'href' => BASE_URL . 'withdrawal/index'],
                ],
            ],
            [
                'label' => 'Personal',
                'roles' => ['directivo'],
                'items' => [
                    ['id' => 'empleados', 'label' => 'Empleados', 'icon' => 'bi-person-badge', 'href' => BASE_URL . 'staff/index'],
                ],
            ],
            [
                'label' => 'Configuración académica',
                'roles' => ['directivo'],
                'items' => [
                    ['id' => 'anios-escolares', 'label' => 'Años Escolares', 'icon' => 'bi-calendar3', 'href' => BASE_URL . 'academicYear/index'],
                    ['id' => 'estructura-academica', 'label' => 'Estructura Académica', 'icon' => 'bi-diagram-3', 'href' => BASE_URL . 'academicStructure/index'],
                    ['id' => 'asignacion-docente', 'label' => 'Asignación Docente', 'icon' => 'bi-person-video3', 'href' => BASE_URL . 'teacherAssignment/index'],
                ],
            ],
            [
                'label' => 'Académico',
                'roles' => ['docente', 'coordinador'],
                'items' => [
                    ['id' => 'secciones', 'label' => 'Mis secciones', 'icon' => 'bi-collection', 'href' => '#'],
                ],
            ],
            [
                'label' => 'Reportes',
                'roles' => ['directivo', 'coordinador'],
                'items' => [
                    ['id' => 'personal-docente', 'label' => 'Personal Docente', 'icon' => 'bi-person-lines-fill', 'href' => '#', 'roles' => ['directivo']],
                    ['id' => 'estadistico-general', 'label' => 'Estadístico General', 'icon' => 'bi-bar-chart', 'href' => '#', 'roles' => ['directivo']],
                    ['id' => 'expedientes', 'label' => 'Expediente personal', 'icon' => 'bi-folder2-open', 'href' => '#', 'roles' => ['coordinador']],
                ],
            ],
            [
                'label' => 'Configuración',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'catalogos', 'label' => 'Catálogos', 'icon' => 'bi-sliders', 'href' => BASE_URL . 'catalogo/index'],
                ],
            ],
            [
                'label' => 'Auditoría',
                'roles' => ['admin'],
                'items' => [
                    ['id' => 'bitacora', 'label' => 'Bitácora', 'icon' => 'bi-clock-history', 'href' => BASE_URL . 'bitacora/index'],
                ],
            ],
        ];

        $secciones = [];
        foreach ($todas as $seccion) {
            if (!in_array($rol, $seccion['roles'], true)) {
                continue;
            }

            $itemsVisibles = array_values(array_filter(
                $seccion['items'],
                fn($item) => !isset($item['roles']) || in_array($rol, $item['roles'], true)
            ));

            if (empty($itemsVisibles)) {
                continue;
            }

            $secciones[] = [
                'label' => $seccion['label'],
                'items' => $itemsVisibles,
            ];
        }

        return $secciones;
    }
}