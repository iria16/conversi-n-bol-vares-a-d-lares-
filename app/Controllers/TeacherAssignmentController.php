<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TeacherAssignmentModel;
use PDO;

/**
 * Asignación docente (configuracion_academica/asignacionDocente), consumida por teacher-assignment.js.
 * Endpoints: teacherAssignment/index (GET, listado), asignar (POST, docente a sección) y retirar (POST, asignación vigente).
*/
class TeacherAssignmentController extends CrudController
{
    protected string $viewPath  = 'configuracion_academica/asignacionDocente';
    protected string $routeName = 'teacherAssignment';

    /**
 * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
 * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
*/
    private function modelo(): TeacherAssignmentModel
    {
        /** @var TeacherAssignmentModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new TeacherAssignmentModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'anio'    => (string) ($this->modelo()->getActiveSchoolYearId() ?? 0),
            'grado'   => trim($_GET['grado'] ?? ''),
            'seccion' => trim($_GET['seccion'] ?? ''),
            'turno'   => trim($_GET['turno'] ?? ''),
            'q'       => trim($_GET['q'] ?? ''),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        return [
            'stats'          => $model->getStats(),
            'asignaciones'   => $items,
            'anioActivo'     => $model->getActiveSchoolYearName() ?? 'No hay un año escolar activo',
            'grados'         => $model->getOptions('grado'),
            'secciones'      => $model->getOptions('seccion'),
            'turnos'         => $model->getOptions('turno'),
            'estructuras'    => $model->getStructures(),
            'tiposAsignacion' => $model->getTypes(),
        ];
    }

    public function asignar(): void
    {
        $model = $this->modelo();

        $estructura = (int) ($_POST['id_estructura_academica'] ?? 0);
        $docente    = (int) ($_POST['id_docente'] ?? 0);
        $tipo       = (int) ($_POST['id_tipo_asignacion'] ?? 0);
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');

        if ($estructura <= 0) {
            $this->jsonResponse(false, null, 'Debe seleccionar una sección académica.', 422);
        }
        if ($docente <= 0) {
            $this->jsonResponse(false, ['id_docente' => 'Seleccione el docente a asignar.'], 'Hay errores en el formulario.', 422);
        }
        if ($tipo <= 0) {
            $this->jsonResponse(false, ['id_tipo_asignacion' => 'Seleccione el tipo de asignación.'], 'Hay errores en el formulario.', 422);
        }
        if ($fechaInicio === '') {
            $this->jsonResponse(false, ['fecha_inicio' => 'Indique la fecha de inicio.'], 'Hay errores en el formulario.', 422);
        }

        $resultado = $model->assign([
            'id_estructura_academica' => $estructura,
            'id_docente'              => $docente,
            'id_tipo_asignacion'      => $tipo,
            'fecha_inicio'            => $fechaInicio,
        ]);

        if (!$resultado['ok']) {
            $this->jsonResponse(false, null, $resultado['mensaje'], 422);
        }
        $this->jsonResponse(true, null, $resultado['mensaje']);
    }

    public function actualizar(): void
    {
        $idAsignacion = (int) ($_POST['id_asignacion'] ?? 0);
        $docente = (int) ($_POST['id_docente'] ?? 0);
        $tipo = (int) ($_POST['id_tipo_asignacion'] ?? 0);
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');

        if ($idAsignacion <= 0) {
            $this->jsonResponse(false, null, 'La asignación que desea editar no es válida.', 422);
        }
        if ($docente <= 0 || $tipo <= 0 || $fechaInicio === '') {
            $this->jsonResponse(false, null, 'Seleccione el docente, el tipo de asignación y la fecha de inicio.', 422);
        }

        $resultado = $this->modelo()->updateAssignment([
            'id_asignacion' => $idAsignacion,
            'id_docente' => $docente,
            'id_tipo_asignacion' => $tipo,
            'fecha_inicio' => $fechaInicio,
        ]);

        if (!$resultado['ok']) {
            $this->jsonResponse(false, null, $resultado['mensaje'], 422);
        }
        $this->jsonResponse(true, null, $resultado['mensaje']);
    }

    public function getDocentesPorEstructura(): void
    {
        $estructura = (int) ($_GET['id'] ?? 0);
        if ($estructura <= 0) {
            $this->jsonResponse(false, null, 'Seleccione una sección académica válida.', 400);
        }

        $this->jsonResponse(true, $this->modelo()->getTeachersForStructure($estructura));
    }

    public function retirar(): void
    {
        $model = $this->modelo();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $ok = $model->remove($id);
        $this->jsonResponse(
            $ok,
            null,
            $ok ? 'Asignación retirada correctamente.' : 'No se pudo retirar la asignación.',
            $ok ? 200 : 409
        );
    }
}