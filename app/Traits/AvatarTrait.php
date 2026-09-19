<?php
namespace App\Traits;
/**
 * Trait: AvatarTrait
 * Genera las iniciales y el color de avatar para un usuario, a partir de su
 * nombre/apellido y rol, cuando no tiene una foto asignada.
 *
 * Antes esta lógica estaba duplicada en CuentaUsuarioModel y
 * RecuperacionAccesoModel, cada uno con su propia regla de color — lo que
 * hacía que el MISMO usuario apareciera con un color de avatar distinto
 * según la pantalla (primary en Cuentas, dark en Recuperación de Acceso).
 *
 * Cualquier Model que necesite mostrar un avatar con iniciales debe usar
 * este trait en vez de reimplementar la lógica.
 */
trait AvatarTrait
{
    /**
     * @param string|null $nombre    primer_nombre de la persona
     * @param string|null $apellido  primer_apellido de la persona
     * @param string|null $fallback  texto de respaldo si no hay nombre/apellido
     *                               (normalmente nombre_usuario)
     */
    protected function generarIniciales(?string $nombre, ?string $apellido, ?string $fallback = 'U'): string
    {
        $n = mb_substr($nombre ?? '', 0, 1);
        $a = mb_substr($apellido ?? '', 0, 1);
        $iniciales = mb_strtoupper($n . $a);

        return $iniciales !== '' ? $iniciales : mb_strtoupper(mb_substr($fallback ?? 'U', 0, 1));
    }

    /**
     * Color de fondo del avatar según el rol. Única fuente de verdad para
     * esta regla — si se agregan más roles con color propio (ej. docente,
     * coordinador), se ajusta aquí una sola vez y se propaga a todo el
     * sistema.
     */
    protected function colorPorRol(?string $rol): string
    {
        return match (mb_strtolower($rol ?? '')) {
            'admin', 'administrador' => 'primary',
            default                  => 'success',
        };
    }

    /**
     * Decide cómo debe pintarse el avatar: si la persona tiene foto
     * asignada (persona.foto), se usa esa; si no, se cae a iniciales+color.
     * Única fuente de verdad para esta decisión — así ninguna pantalla
     * puede "olvidarse" de revisar la foto antes de generar iniciales.
     *
     * @param string|null $foto      valor de persona.foto (ruta relativa, ej. 'uploads/fotos/123.jpg')
     * @param string|null $nombre    primer_nombre de la persona
     * @param string|null $apellido  primer_apellido de la persona
     * @param string|null $rol       rol del usuario, para elegir color si no hay foto
     * @param string|null $fallback  texto de respaldo para iniciales (normalmente nombre_usuario)
     *
     * @return array{tipo: string, foto: ?string, iniciales: ?string, avatar_color: ?string}
     *         tipo = 'foto' | 'iniciales'
     */
    protected function resolverAvatar(?string $foto, ?string $nombre, ?string $apellido, ?string $rol, ?string $fallback = 'U'): array
    {
        $foto = trim($foto ?? '');

        if ($foto !== '') {
            // Se quita la barra inicial: la ruta guardada en BD es absoluta
            // al dominio ('/uploads/...'), pero el proyecto puede correr en
            // una subcarpeta (BASE_URL calculado dinámicamente). Al dejarla
            // relativa, BASE_URL . $foto arma la URL correcta sin importar
            // si el sitio está en la raíz o en /sigde/ (o donde sea).
            $foto = ltrim($foto, '/');

            return [
                'tipo'         => 'foto',
                'foto'         => $foto,
                'iniciales'    => null,
                'avatar_color' => null,
            ];
        }

        return [
            'tipo'         => 'iniciales',
            'foto'         => null,
            'iniciales'    => $this->generarIniciales($nombre, $apellido, $fallback),
            'avatar_color' => $this->colorPorRol($rol),
        ];
    }
}