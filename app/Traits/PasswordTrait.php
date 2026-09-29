<?php

declare(strict_types=1);

namespace App\Traits;

use PDO;

/**
 * PasswordTrait
 *
 * Generación y aplicación de contraseñas provisionales, y lectura de los
 * datos básicos del usuario (id, nombre de usuario, nombre de persona).
 * Compartido por los modelos que entregan credenciales generadas por el
 * servidor: CuentaUsuarioModel (alta de cuenta) y RecuperacionAccesoModel
 * (aprobación de solicitud y restablecimiento manual).
 *
 * Requiere que la clase que lo usa (un Model) exponga `$this->pdo`.
 */
trait PasswordTrait
{
    /**
     * Genera una clave provisional legible: sin caracteres ambiguos
     * (0/O/1/l/I) y sin símbolos que cuesten teclear o dictar.
     */
    public function generarClaveProvisional(int $longitud = 10): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $clave    = '';

        for ($i = 0; $i < $longitud; $i++) {
            $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $clave;
    }

    /**
     * Fija password_hash y marca la clave como provisional
     * (password_provisional = 1) en el usuario indicado.
     */
    public function aplicarClaveProvisional(int $usuarioId, string $clave): void
    {
        $this->pdo->prepare(
            "UPDATE usuario
             SET password_hash = :hash, password_provisional = 1
             WHERE id_usuario = :id_usuario"
        )->execute([
            'hash'       => password_hash($clave, PASSWORD_DEFAULT),
            'id_usuario' => $usuarioId,
        ]);
    }

    /**
     * Datos básicos de un usuario: id, nombre de usuario y nombre de persona.
     */
    public function getUsuarioBasico(int $usuarioId): ?array
    {
        $sql = "SELECT
                    u.id_usuario,
                    u.nombre_usuario AS usuario,
                    CONCAT(p.primer_nombre, ' ', p.primer_apellido) AS nombre
                FROM usuario u
                INNER JOIN persona p ON p.id_persona = u.id_persona
                WHERE u.id_usuario = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $usuarioId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}