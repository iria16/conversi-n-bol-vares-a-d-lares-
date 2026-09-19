<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RecoveryModel extends Model
{
    /**
     * Busca un usuario por su nombre de usuario o por cédula, para
     * validar que la solicitud de recuperación corresponda a alguien
     * real antes de notificar al administrador.
     */
    public function buscarPorUsuarioOCedula(string $valor): array|false
    {
        $sql = "SELECT u.id_usuario AS id, u.nombre_usuario, p.numero_documento
                FROM usuario u
                INNER JOIN persona p ON u.id_persona = p.id_persona
                WHERE (u.nombre_usuario = :valor1 OR p.numero_documento = :valor2)
                AND u.estado != 'INACTIVO'
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'valor1' => $valor,
            'valor2' => $valor
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Evita solicitudes duplicadas: si ya hay una PENDIENTE para este
     * usuario, no crea otra.
     */
    public function tieneSolicitudPendiente(int $usuarioId): bool
    {
        $sql = "SELECT id_recuperacion FROM recuperacion_acceso
                WHERE id_usuario = :usuario_id AND estado_recuperacion = 'PENDIENTE'
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea la solicitud de recuperación (la "notificación" que verá
     * el administrador en su panel). fecha_solicitud y estado_recuperacion
     * usan sus valores por defecto (CURRENT_TIMESTAMP / PENDIENTE).
     */
    public function crearSolicitud(int $usuarioId): bool
    {
        $sql = "INSERT INTO recuperacion_acceso (id_usuario) VALUES (:usuario_id)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['usuario_id' => $usuarioId]);
    }

    /**
     * Lista las solicitudes pendientes para mostrar al administrador
     * (panel/badge de notificaciones).
     */
    public function listarPendientes(): array
    {
        $sql = "SELECT ra.id_recuperacion AS id, ra.fecha_solicitud, u.nombre_usuario,
                       p.primer_nombre, p.primer_apellido
                FROM recuperacion_acceso ra
                INNER JOIN usuario u ON ra.id_usuario = u.id_usuario
                INNER JOIN persona p ON u.id_persona = p.id_persona
                WHERE ra.estado_recuperacion = 'PENDIENTE'
                ORDER BY ra.fecha_solicitud ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca una solicitud como atendida, registrando qué administrador
     * la gestionó (ej. al restablecer manualmente la contraseña
     * provisional del usuario).
     *
     * NOTA: el ENUM de `estado_recuperacion` en el esquema es
     * ('PENDIENTE','COMPLETADO','CANCELADO') — no existe 'ATENDIDA',
     * se usa 'COMPLETADO'.
     */
    public function marcarComoAtendida(int $solicitudId, int $adminId): bool
    {
        $sql = "UPDATE recuperacion_acceso
                SET estado_recuperacion = 'COMPLETADO', 
                    id_admin_atendio = :admin_id, 
                    fecha_atencion = NOW()
                WHERE id_recuperacion = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['admin_id' => $adminId, 'id' => $solicitudId]);
    }

    /**
     * Marca una solicitud como cancelada (ej. si el admin la rechaza
     * o el usuario se contactó por otro medio).
     *
     * NOTA: el valor del ENUM es 'CANCELADO' (no 'CANCELADA').
     */
    public function marcarComoCancelada(int $solicitudId, int $adminId): bool
    {
        $sql = "UPDATE recuperacion_acceso
                SET estado_recuperacion = 'CANCELADO', 
                    id_admin_atendio = :admin_id, 
                    fecha_atencion = NOW()
                WHERE id_recuperacion = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['admin_id' => $adminId, 'id' => $solicitudId]);
    }
}