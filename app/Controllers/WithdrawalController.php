<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\WithdrawalModel;
use PDO;

use function App\Traits\sigde_rol_clave;

/**
 * Retiros (notificaciones/retiros_pendientes): la secretaria los registra (store) y el directivo los resuelve (updateEstado).
 * Endpoints: withdrawal/index (GET), withdrawal/store (POST, secretaria) y withdrawal/updateEstado (POST, directivo).
 * El alta usa store() de CrudController (el modelo implementa Creatable); aquí solo se arman extractData() y validate().
 */
class WithdrawalController extends CrudController
{
    private const ESTADOS = ['PENDIENTE', 'APROBADO', 'RECHAZADO'];

    protected string $viewPath  = 'notificaciones/retiros_pendientes';
    protected string $routeName = 'withdrawal';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): WithdrawalModel
    {
        /** @var WithdrawalModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new WithdrawalModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'motivo' => trim($_GET['motivo'] ?? ''),
            'estado' => trim($_GET['estado'] ?? ''),
            'anio'   => trim($_GET['anio'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        $rol   = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Solo la secretaria registra retiros: los candidatos se piden solo en ese caso.
        $esSecretaria = $rol === 'secretaria';

        return [
            'stats'                 => $model->getStats(),
            'solicitudes'           => $items,
            'aniosEscolares'        => $model->getYears(),
            'motivos'               => $model->getReasons(),
            'puedeRegistrar'        => $esSecretaria,
            'puedeResolver'         => $rol === 'directivo',
            'estudiantesDisponibles' => $esSecretaria ? $model->getEstudiantesParaRetiro() : [],
        ];
    }

    /**
     * Datos del retiro tal como los manda el modal de la secretaria (contrato de CrudController::store()).
     */
    protected function extractData(array $source): array
    {
        return [
            'id_matricula'     => (int) ($source['id_matricula'] ?? 0),
            'id_motivo_retiro' => (int) ($source['id_motivo_retiro'] ?? 0),
            // Texto libre guardado como oración, para que los listados no mezclen mayúsculas.
            'observaciones'    => mb_substr($this->capitalizarOracion(trim($source['observaciones'] ?? '')), 0, 500),
        ];
    }

    /**
     * Devuelve los errores por campo del alta.
     * Los que requieren BD (motivo inexistente, retiro duplicado) se los pregunta al modelo.
     */
    protected function validate(array $data): array
    {
        $errores = [];

        if ($data['id_matricula'] <= 0) {
            $errores['id_matricula'] = 'Seleccione el estudiante.';
        } elseif ($this->modelo()->existeRetiro($data['id_matricula'])) {
            $errores['id_matricula'] = 'Ese estudiante ya tiene un retiro registrado.';
        }

        if ($data['id_motivo_retiro'] <= 0) {
            $errores['id_motivo_retiro'] = 'Seleccione el motivo del retiro.';
        } elseif (!$this->modelo()->existeMotivo($data['id_motivo_retiro'])) {
            $errores['id_motivo_retiro'] = 'El motivo seleccionado no es válido.';
        }

        return $errores;
    }

    /**
     * Aprueba o rechaza una solicitud pendiente.
     * Ruta: withdrawal/updateEstado (POST, responde JSON). Solo directivo.
     */
    public function updateEstado(): void
    {
        $model = $this->modelo();

        $id     = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper(trim($_POST['estado'] ?? ''));

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            $this->jsonResponse(false, null, 'Estado de retiro no válido.', 422);
        }

        $ok = $model->updateStatus($id, $estado);
        $this->jsonResponse(
            $ok,
            null,
            $ok ? 'Estado de la solicitud actualizado.' : 'No se pudo actualizar la solicitud.',
            $ok ? 200 : 409
        );
    }
}