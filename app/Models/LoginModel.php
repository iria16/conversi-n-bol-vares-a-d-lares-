<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class LoginModel extends Model

{
    public const MAX_INTENTOS = 3;
    public const MINUTOS_BLOQUEO = 15;

    private function consultarUsuarioBase(string $condicion, array $parametros): array|false
    {
        $sql = "SELECT 
                    u.id_usuario AS id, 
                    p.primer_nombre AS nombre,
                    p.primer_apellido AS apellido,
                    u.nombre_usuario, 
                    u.password_hash, 
                    r.nombre AS rol, 
                    u.intentos_fallidos, 
                    u.bloqueado_hasta,
                    u.password_provisional,
                    u.estado AS usuario_estado
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                INNER JOIN rol r ON u.id_rol = r.id_rol
                WHERE {$condicion} 
                AND u.estado != 'INACTIVO'
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    // MÉTODOS DE AUTENTICACIÓN Y CONSULTA

    /**
     * Obtiene los datos del usuario mediante su nombre de usuario (Login).
     */
    public function obtenerPorUsuario(string $usuario): array|false
    {
        return $this->consultarUsuarioBase("u.nombre_usuario = :usuario", ['usuario' => $usuario]);
    }

    /**
     * Obtiene los datos del usuario mediante su ID (Sesiones y validaciones).
     */
    public function obtenerPorId(int $id): array|false
    {
        return $this->consultarUsuarioBase("u.id_usuario = :id", ['id' => $id]);
    }

    /**
     * Verifica bloqueo a partir del valor de bloqueado_hasta YA obtenido
     * en obtenerPorUsuario(). Úsalo en authenticate(): evita un SELECT
     * redundante porque ese dato ya viaja en el array del usuario.
     *
     * @param string|null $bloqueadoHasta Valor crudo de la columna
     *        bloqueado_hasta (formato DATETIME de MySQL) o null.
     */
    public function verificarBloqueo(?string $bloqueadoHasta): array
    {
        return $this->calcularEstadoBloqueo($bloqueadoHasta);
    }

    /**
     * Verifica bloqueo consultando la BD por id de usuario. Úsalo cuando
     * NO tienes el array fresco a mano, como al validar una sesión ya
     * activa en el constructor de AuthController (ahí solo se cuenta con
     * $_SESSION['usuario_id'], no con el resultado de obtenerPorUsuario).
     */
    public function verificarBloqueoPorId(int $usuarioId): array
    {
        $sql = "SELECT bloqueado_hasta FROM usuario WHERE id_usuario = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $usuarioId]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->calcularEstadoBloqueo($fila['bloqueado_hasta'] ?? null);
    }

    /**
     * Lógica compartida de cálculo de bloqueo, usada tanto por
     * verificarBloqueo() como por verificarBloqueoPorId() para no
     * duplicar el cálculo de minutos restantes.
     */
    private function calcularEstadoBloqueo(?string $bloqueadoHasta): array
    {
        if (empty($bloqueadoHasta)) {
            return ['bloqueado' => false, 'minutos_restantes' => 0];
        }

        $tsBloqueo = strtotime($bloqueadoHasta);
        $tsActual = time();

        if ($tsBloqueo !== false && $tsBloqueo > $tsActual) {
            $diffSegundos = $tsBloqueo - $tsActual;
            $minutosRestantes = (int)ceil($diffSegundos / 60);
            $minutosRestantes = max(1, $minutosRestantes);

            return [
                'bloqueado' => true,
                'minutos_restantes' => $minutosRestantes
            ];
        }

        return ['bloqueado' => false, 'minutos_restantes' => 0];
    }

    /**
     * Registra un intento fallido de login.
     * Si llega a MAX_INTENTOS, bloquea por MINUTOS_BLOQUEO.
     *
     * NOTA: la columna `usuario.estado` en el esquema solo admite
     * ENUM('ACTIVO','INACTIVO'), no existe el valor 'BLOQUEADO'.
     * Por eso el bloqueo se controla únicamente con `bloqueado_hasta`
     * (una fecha futura = usuario bloqueado), sin tocar `estado`.
     */
    public function registrarIntentoFallido(int $usuarioId, int $intentosActuales): int
    {
        $nuevosIntentos = $intentosActuales + 1;

        if ($nuevosIntentos >= self::MAX_INTENTOS) {
            $sql = "UPDATE usuario 
                    SET intentos_fallidos = :intentos, 
                        bloqueado_hasta = DATE_ADD(NOW(), INTERVAL " . self::MINUTOS_BLOQUEO . " MINUTE)
                    WHERE id_usuario = :id";
        } else {
            $sql = "UPDATE usuario 
                    SET intentos_fallidos = :intentos 
                    WHERE id_usuario = :id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['intentos' => $nuevosIntentos, 'id' => $usuarioId]);

        return $nuevosIntentos;
    }

    /**
     * Reinicia los intentos fallidos y quita el bloqueo tras login exitoso.
     * No modifica `estado`: el esquema solo admite ACTIVO/INACTIVO y el
     * estado de bloqueo se representa exclusivamente vía `bloqueado_hasta`.
     */
    public function reiniciarIntentos(int $usuarioId): void
    {
        $sql = "UPDATE usuario 
                SET intentos_fallidos = 0, 
                    bloqueado_hasta = NULL
                WHERE id_usuario = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $usuarioId]);
    }

    // CAMBIO DE CONTRASEÑA

    /**
     * Actualiza la contraseña del usuario y limpia el flag de
     * contraseña provisional. Se usa tanto en el cambio obligatorio
     * de primer ingreso como en un futuro restablecimiento.
     */
    public function actualizarPassword(int $usuarioId, string $nuevaPassword): bool
    {
        $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE usuario 
                SET password_hash = :hash, 
                    password_provisional = 0
                WHERE id_usuario = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'hash' => $hash,
            'id'   => $usuarioId
        ]);
    }
}
