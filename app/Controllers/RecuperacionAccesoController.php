<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RecuperacionAccesoModel;
use PDO;
use Throwable;

class RecuperacionAccesoController extends CrudController
{
    protected string $viewPath    = 'usuarios/recuperacion';
    protected string $routeName   = 'recuperacionAcceso';

    /**
     * Crea la instancia concreta del modelo cuando sea requerida.
     */
    protected function createModel(PDO $pdo): object
    {
        return new RecuperacionAccesoModel($pdo);
    }

    private const ESTADOS_META = [
        'pendiente' => ['label' => 'Pendiente', 'clase' => 'pending'],
        'aprobado'  => ['label' => 'Aprobado',  'clase' => 'active'],
        'rechazado' => ['label' => 'Rechazado', 'clase' => 'inactive'],
    ];

    protected function getFiltersFromRequest(): array
    {
        return [
            'estado' => trim($_GET['estado'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->getModel();

        return [
            'solicitudes'         => $items,
            'stats'               => $model->getStats(),
            'estadosMeta'         => self::ESTADOS_META,
            'usuariosDisponibles' => $model->getUsuariosDisponibles(),
        ];
    }

    /**
     * Este módulo no admite creación manual: la genera el usuario final
     * desde el flujo de recuperación, no el admin.
     */
    public function create(): void
    {
        $this->setFlash('error', 'Esta sección no admite creación manual de solicitudes.');
        $this->redirect('index');
    }

    /**
     * Tampoco hay edición directa: las únicas transiciones válidas son
     * aprobar/rechazar, manejadas por updateEstado().
     */
    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no admite edición directa de solicitudes.');
        $this->redirect('index');
    }

    /**
     * store(), update(), delete() y toggleStatus() no se sobrescriben:
     * RecuperacionAccesoModel no implementa Creatable/Updatable/
     * Deletable/Toggleable, así que si alguien las invoca por error
     * heredan el comportamiento 405 de CrudController automáticamente.
     */

    public function updateEstado(): void
    {
        $id     = (int) ($_POST['id'] ?? 0);
        $accion = $_POST['accion'] ?? '';

        if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
            $this->jsonResponse(false, null, 'Solicitud inválida.', 400);
        }

        $model = $this->getModel();

        try {
            if ($accion === 'aprobar') {
                $resultado = $model->aprobarSolicitud($id, $this->idUsuarioActual());

                if ($resultado === null) {
                    $this->jsonResponse(false, null, 'La solicitud ya fue atendida o no existe.', 409);
                }

                $this->jsonResponse(true, $resultado, 'Solicitud aprobada. Contraseña provisional generada.');
            }

            $rechazada = $model->rechazarSolicitud($id, $this->idUsuarioActual());

            if (!$rechazada) {
                $this->jsonResponse(false, null, 'La solicitud ya fue atendida o no existe.', 409);
            }

            $this->jsonResponse(true, null, 'Solicitud rechazada.');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de Acceso');
        }
    }

    public function getDetalle(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $model = $this->getModel();

        try {
            $detalle = $model->getDetalleSolicitud($id);

            if (!$detalle) {
                $this->jsonResponse(false, null, 'La solicitud no existe.', 404);
            }

            $this->jsonResponse(true, $detalle);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de Acceso');
        }
    }

    public function resetPassword(): void
    {
        $usuarioId = (int) ($_POST['usuario_id'] ?? 0);

        if ($usuarioId <= 0) {
            $this->jsonResponse(false, null, 'Debes seleccionar un usuario.', 400);
        }

        $model = $this->getModel();

        try {
            $claveProvisional = $model->resetPassword($usuarioId, $this->idUsuarioActual());
            $usuario           = $model->getUsuarioBasico($usuarioId);

            $this->jsonResponse(true, [
                'clave'   => $claveProvisional,
                'usuario' => $usuario['usuario'] ?? '',
                'nombre'  => $usuario['nombre'] ?? '',
            ], 'Contraseña restablecida correctamente.');
        } catch (Throwable $e) {
            $this->jsonError($e, 'Recuperación de Acceso');
        }
    }
}