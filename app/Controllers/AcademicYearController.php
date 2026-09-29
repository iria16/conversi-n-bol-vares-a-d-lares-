<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use PDO;

/**
 * Gestión de años escolares (configuracion_academica/anios_escolares), consumida por academic-years.js.
 * Endpoints: academicYear/index (GET), store, update y toggleStatus (POST).
 * store(), update() y toggleStatus() vienen de CrudController; aquí solo se arman extractData() y validate().
 */
class AcademicYearController extends CrudController
{
    protected string $viewPath  = 'configuracion_academica/anios_escolares';
    protected string $routeName = 'academicYear';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): AcademicYearModel
    {
        /** @var AcademicYearModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new AcademicYearModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'estado' => trim($_GET['estado'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        return [
            'stats' => $this->modelo()->getStats(),
            'anios' => $items,
        ];
    }

    /**
     * Campos del período tal como los manda el modal (contrato de CrudController::store()/update()).
     */
    protected function extractData(array $source): array
    {
        return [
            // Oración (no Título Case): el nombre es tipo "Año escolar 2026-2027".
            'nombre'       => $this->capitalizarOracion(trim($source['nombre'] ?? '')),
            'fecha_inicio' => trim($source['fecha_inicio'] ?? ''),
            'fecha_cierre' => trim($source['fecha_cierre'] ?? ''),
            'estado'       => isset($source['estado']) ? 'ACTIVO' : 'CERRADO',
        ];
    }

    /**
     * Devuelve los errores por campo.
     * El nombre repetido (UNIQUE nombre_periodo) se le pregunta al modelo, que conoce la restricción.
     */
    protected function validate(array $data): array
    {
        $errores = [];

        if ($data['nombre'] === '') {
            $errores['nombre'] = 'Indique el nombre del período.';
        }
        if ($data['fecha_inicio'] === '') {
            $errores['fecha_inicio'] = 'Indique la fecha de inicio.';
        }
        if ($data['fecha_cierre'] === '') {
            $errores['fecha_cierre'] = 'Indique la fecha de cierre.';
        }
        if ($data['fecha_inicio'] !== '' && $data['fecha_cierre'] !== '' && $data['fecha_inicio'] > $data['fecha_cierre']) {
            $errores['fecha_cierre'] = 'La fecha de cierre no puede ser anterior al inicio.';
        }

        if ($errores === [] && $this->modelo()->existePorNombre($data['nombre'], $this->idEnEdicion())) {
            $errores['nombre'] = 'Ya existe un año escolar con ese nombre.';
        }

        return $errores;
    }

    /**
     * Id que llega en el POST de update(); validate() lo usa para excluir la fila en edición.
     * En store() no hay id y vale 0.
     */
    private function idEnEdicion(): int
    {
        return (int) ($_POST['id'] ?? 0);
    }
}