<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcademicYearModel;
use DateTimeImmutable;
use PDO;

/**
 * Gestión de años escolares (configuracion_academica/anios_escolares), consumida por academic-years.js.
 * Las transiciones de estado son acciones explícitas: iniciar() y cerrar().
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
        /** @var AcademicYearModel $model*/
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
            // Quién crea el período: la bitácora del módulo lo necesita y no está en la tabla.
            'id_usuario'   => (int) ($this->idUsuarioActual() ?? 0),
        ];
    }

    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data = $this->extractData($_POST);
        $errores = $this->validate($data, $id);
        if ($errores !== []) {
            $this->jsonResponse(false, $errores, 'Hay errores en el formulario.', 422);
        }

        if (!$this->modelo()->update($id, $data)) {
            $this->jsonResponse(false, null, 'No se puede editar un año escolar cerrado o inexistente.', 422);
        }

        $this->jsonResponse(true, null, 'Año escolar actualizado correctamente.');
    }

    public function iniciar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $usuario = $this->idUsuarioActual();
        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }

        $resultado = $this->modelo()->iniciar($id, $usuario);
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    public function cerrar(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $usuario = $this->idUsuarioActual();
        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }

        $resultado = $this->modelo()->cerrar($id, $usuario);
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    /**
 * Reabre un año escolar cerrado (solo directiva).
 *
 * A diferencia de iniciar y cerrar, el motivo es OBLIGATORIO: reabrir cambia el
 * período de referencia de matrículas y estructuras, así que sin una razón escrita el
 * registro no dice por qué se deshizo un cierre ni quién lo hizo.
*/
    public function reabrir(): void
    {
        $id       = (int) ($_POST['id'] ?? 0);
        $motivo   = trim((string) ($_POST['motivo'] ?? ''));
        $usuario  = $this->idUsuarioActual();

        if ($id <= 0 || $usuario === null) {
            $this->jsonResponse(false, null, 'No se pudo identificar el año escolar o el usuario.', 422);
        }
        if ($motivo === '') {
            $this->jsonResponse(false, ['motivo' => 'Indique el motivo de la reapertura.'], 'Falta el motivo.', 422);
        }
        if (mb_strlen($motivo, 'UTF-8') < 10) {
            $this->jsonResponse(
                false,
                ['motivo' => 'Explique el motivo con al menos 10 caracteres: quedará registrado.'],
                'Motivo demasiado corto.',
                422
            );
        }

        $resultado = $this->modelo()->reabrir($id, $usuario, $this->capitalizarOracion($motivo));
        $this->jsonResponse($resultado['ok'], null, $resultado['mensaje'], $resultado['ok'] ? 200 : 422);
    }

    /**
 * Devuelve los errores por campo.
 * El nombre repetido (UNIQUE nombre_periodo) se le pregunta al modelo, que conoce la restricción.
*/
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];

        if ($data['nombre'] === '') {
            $errores['nombre'] = 'Indique el nombre del período.';
        } elseif (mb_strlen($data['nombre'], 'UTF-8') > 20) {
            /* `nombre_periodo` es varchar(20). Sin esta comprobación el INSERT revienta
 con "Data too long" y la secretaria recibe un error 500 sin explicación,
 en vez de un mensaje que le diga qué hacer.*/
            $errores['nombre'] = 'El nombre del período puede tener hasta 20 caracteres '
                . '(por ejemplo, 2026-2027).';
        }
        $inicio = $data['fecha_inicio'] !== ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['fecha_inicio'])
            : false;
        $cierre = $data['fecha_cierre'] !== ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['fecha_cierre'])
            : false;

        if ($data['fecha_inicio'] === '') {
            $errores['fecha_inicio'] = 'Indique la fecha de inicio.';
        } elseif (!$inicio || $inicio->format('Y-m-d') !== $data['fecha_inicio']) {
            $errores['fecha_inicio'] = 'Indique una fecha de inicio válida.';
        }
        if ($data['fecha_cierre'] === '') {
            $errores['fecha_cierre'] = 'Indique la fecha de cierre.';
        } elseif (!$cierre || $cierre->format('Y-m-d') !== $data['fecha_cierre']) {
            $errores['fecha_cierre'] = 'Indique una fecha de cierre válida.';
        }
        if ($inicio && $cierre && $inicio > $cierre) {
            $errores['fecha_cierre'] = 'La fecha de cierre no puede ser anterior al inicio.';
        }

        // $id llega de update() y permite excluir la fila en edición de unicidad;
        // en store() CrudController lo pasa como null, y $id ?? 0 excluye nada.
        if ($errores === [] && $this->modelo()->existePorNombre($data['nombre'], $id ?? 0)) {
            $errores['nombre'] = 'Ya existe un año escolar con ese nombre.';
        }

        return $errores;
    }
}