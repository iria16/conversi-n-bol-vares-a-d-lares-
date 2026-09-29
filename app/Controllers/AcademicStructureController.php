<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicStructureModel;
use PDO;

/**
 * Estructura académica (configuracion_academica/estructuraAcademica), consumida por academic-structure.js.
 * Endpoints: academicStructure/index (GET, listado), store y update (POST, combinación año/grado/sección/turno).
 * store() y update() vienen de CrudController; aquí solo se arman extractData() y validate().
 */
class AcademicStructureController extends CrudController
{
    protected string $viewPath  = 'configuracion_academica/estructuraAcademica';
    protected string $routeName = 'academicStructure';

    /**
     * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
     * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
     */
    private function modelo(): AcademicStructureModel
    {
        /** @var AcademicStructureModel $model */
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new AcademicStructureModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'anio'  => trim($_GET['anio'] ?? ''),
            'grado' => trim($_GET['grado'] ?? ''),
            'turno' => trim($_GET['turno'] ?? ''),
            'q'     => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        return [
            'stats'      => $model->getStats(),
            'estructuras' => $items,
            'anios'      => $model->getOptions('anio_escolar'),
            'grados'     => $model->getOptions('grado'),
            'secciones'  => $model->getOptions('seccion'),
            'turnos'     => $model->getOptions('turno'),
        ];
    }

    /**
     * Campos de la estructura tal como los manda el modal (contrato de CrudController::store()/update()).
     */
    protected function extractData(array $source): array
    {
        return [
            'id_anio_escolar'  => (int) ($source['id_anio_escolar'] ?? 0),
            'id_grado'         => (int) ($source['id_grado'] ?? 0),
            'id_seccion'       => (int) ($source['id_seccion'] ?? 0),
            'id_turno'         => (int) ($source['id_turno'] ?? 0),
            'capacidad_maxima' => (int) ($source['capacidad_maxima'] ?? 0),
        ];
    }

    /**
     * Devuelve los errores por campo.
     * El duplicado de la combinación se lo pregunta al modelo, que conoce la restricción UNIQUE.
     */
    protected function validate(array $data): array
    {
        $errores = [];

        if ($data['id_anio_escolar'] <= 0) {
            $errores['id_anio_escolar'] = 'Seleccione el año escolar.';
        }
        if ($data['id_grado'] <= 0) {
            $errores['id_grado'] = 'Seleccione el grado.';
        }
        if ($data['id_seccion'] <= 0) {
            $errores['id_seccion'] = 'Seleccione la sección.';
        }
        if ($data['id_turno'] <= 0) {
            $errores['id_turno'] = 'Seleccione el turno.';
        }
        if ($data['capacidad_maxima'] < 1 || $data['capacidad_maxima'] > 60) {
            $errores['capacidad_maxima'] = 'La capacidad debe estar entre 1 y 60.';
        }

        if ($errores === [] && $this->modelo()->existeCombination($data, $this->idEnEdicion())) {
            $errores['id_grado'] = 'Esta combinación académica ya existe.';
        }

        return $errores;
    }

    /**
     * Id que llega en el POST de update(); validate() lo usa para excluir la fila en edición del chequeo de duplicados.
     * En store() no hay id y vale 0 (no excluye nada).
     */
    private function idEnEdicion(): int
    {
        return (int) ($_POST['id'] ?? 0);
    }
}