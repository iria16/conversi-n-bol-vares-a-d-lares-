<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Interfaces\Updatable;
use App\Models\CuentaUsuarioModel;
use App\Traits\DetalleJsonTrait;
use PDO;
use Throwable;

/**
 * Gestión de cuentas de usuario: listado, alta desde un empleado, edición
 * (usuario y rol), detalle y activación. Todo va por modales AJAX (vistas en
 * Views/usuarios/cuentas/).
 *
 * @author Logística
 * @package App\Controllers
*/
class CuentaUsuarioController extends CrudController
{
    use DetalleJsonTrait;

    protected string $viewPath  = 'usuarios/cuentas';
    protected string $routeName = 'cuentaUsuario';

    private function modelo(): CuentaUsuarioModel
    {
        /** @var CuentaUsuarioModel $model*/
        $model = $this->getModel();
        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new CuentaUsuarioModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'id_rol' => (int) ($_GET['id_rol'] ?? 0),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
        ];
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();

        return [
            'usuarios'             => $items,
            'stats'                => $model->getStats(),
            'roles'                => $model->getRoles(),
            'empleadosDisponibles' => $model->getEmpleadosDisponibles(),
            'usuarioActualId'      => $this->idUsuarioActual(),
        ];
    }

    /**
 * Reemplaza extraData para coincidir con el gancho del CrudController.
*/
    protected function extractData(array $source): array
    {
        $rawUsuario = (string) ($source['nombre_usuario'] ?? $source['usuario'] ?? '');

        return [
            'nombre_usuario' => $this->sanitizarNombreUsuario($rawUsuario),
            'persona_id'     => (int) ($source['empleado_id'] ?? $source['persona_id'] ?? 0),
            'rol_id'         => (int) ($source['rol_id'] ?? 0),
        ];
    }

    /**
 * Validación centralizada: chequea formato, unicidad y reglas de negocio.
*/
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];
        $model   = $this->modelo();

        // 1. Validaciones dependientes del contexto (Crear vs Actualizar)
        if ($id !== null) {
            if ($id <= 0) {
                $errores[] = 'ID de usuario inválido.';
            } else {
                // Reglas de negocio para edición
                $usuarioActual = $model->getDetalleById($id);
                if (!$usuarioActual) {
                    $errores[] = 'Usuario no encontrado.';
                } elseif (($usuarioActual['estado'] ?? '') === 'inactivo') {
                    $errores[] = 'No se puede modificar una cuenta de usuario inactiva. Debe activarla primero.';
                }
            }
        } else {
            // Reglas de negocio para creación
            $personaId = (int) ($data['persona_id'] ?? 0);
            if ($personaId <= 0) {
                $errores[] = 'Debe seleccionar un empleado.';
            } elseif ($model->personaTieneUsuario($personaId)) {
                $errores[] = 'El empleado seleccionado ya posee una cuenta de usuario.';
            }
        }

        // 2. Rol obligatorio
        if (($data['rol_id'] ?? 0) <= 0) {
            $errores[] = 'Debe seleccionar un rol.';
        }

        // 3. Formato del nombre de usuario
        $usuario = (string) ($data['nombre_usuario'] ?? '');
        if ($usuario === '') {
            $errores[] = 'El nombre de usuario es obligatorio.';
            return $errores; // Retorno temprano para no romper el regex
        }

        $longitud = mb_strlen($usuario, 'UTF-8');
        if ($longitud < 3 || $longitud > 50) {
            $errores[] = 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        }

        if (!preg_match('/^[a-z0-9._]+$/', $usuario)) {
            $errores[] = 'El nombre de usuario solo puede contener letras minúsculas, números, puntos y guiones bajos.';
        }

        // 4. Unicidad del nombre de usuario (Aprovecha el $id)
        if ($model->existeNombreUsuario($usuario, $id ?? 0)) {
            $errores[] = "El nombre de usuario \"{$usuario}\" ya está en uso.";
        }

        return $errores;
    }

    public function getDetalle(): void
    {
        $this->responderDetalle(
            'getDetalleCompletoById',
            'ID de usuario inválido.',
            'Usuario no encontrado.',
            'Cuentas de usuario'
        );
    }

    public function create(): void
    {
        try {
            $model                = $this->modelo();
            $empleadosDisponibles = $model->getEmpleadosDisponibles();
            $roles                = $model->getRoles();

            $modalPath = __DIR__ . "/../Views/{$this->viewPath}/modals/create-modal.php";
            if (!file_exists($modalPath)) {
                throw new \RuntimeException("No se encontró el modal en: {$modalPath}");
            }

            require $modalPath;
        } catch (Throwable $e) {
            $this->handleError($e, 'Cuentas de usuario');
        }
    }

    public function edit(): void
    {
        $this->setFlash('error', 'Esta sección no tiene una página de edición independiente.');
        $this->redirect('index');
    }

    /**
 * Se sobreescribe porque `crearUsuario` genera una contraseña inicial y
 * devuelve una respuesta en formato RAW para el frontend.
*/
    public function store(): void
    {
        $data = $this->extractData($_POST);

        $errores = $this->validate($data);
        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            $resultado = $this->modelo()->crearUsuario(
                $data['persona_id'],
                $data['rol_id'],
                $data['nombre_usuario']
            );
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (empty($resultado['ok'])) {
            $this->jsonResponse(false, null, $resultado['mensaje'] ?? 'No se pudo crear el usuario.', 400);
        }

        $this->jsonRaw($resultado);
    }

    /**
 * Se sobreescribe porque al guardar devuelve todo el detalle del usuario
 * para actualizar la fila de la tabla en tiempo real.
*/
    public function update(): void
    {
        $model = $this->modelo();

        if (!$model instanceof Updatable) {
            $this->jsonResponse(false, null, 'Esta sección no admite edición de cuentas.', 405);
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $data = $this->extractData($_POST);

        $errores = $this->validate($data, $id);
        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, implode(' ', $errores), 422);
        }

        try {
            // Toda la validación de unicidad e inactividad ya se hizo arriba
            $ok = $model->update($id, [
                'rol_id'         => $data['rol_id'],
                'nombre_usuario' => $data['nombre_usuario'],
            ]);
        } catch (Throwable $e) {
            $this->jsonError($e, 'Cuentas de usuario');
        }

        if (!$ok) {
            $this->jsonResponse(false, null, 'No se pudo actualizar el usuario.', 400);
        }

        $datos = $model->getDetalleById($id);
        $this->jsonResponse(true, $datos, 'Usuario actualizado correctamente.');
    }

    protected function beforeToggleStatus(int $id): void
    {
        $idActual = $this->idUsuarioActual();

        if ($idActual === null || $id === $idActual) {
            $this->jsonResponse(false, null, 'No puedes desactivar tu propia cuenta.', 403);
        }
    }

    private function sanitizarNombreUsuario(string $nombreUsuario): string
    {
        $nombreUsuario = trim(mb_strtolower($nombreUsuario, 'UTF-8'));

        if (class_exists(\Transliterator::class)) {
            $trans = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($trans !== null) {
                $nombreUsuario = $trans->transliterate($nombreUsuario);
            }
        } else {
            $search  = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ä', 'ë', 'ï', 'ö', 'ü'];
            $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u'];
            $nombreUsuario = str_replace($search, $replace, $nombreUsuario);
        }

        $nombreUsuario = (string) preg_replace('/\s+/', '.', $nombreUsuario);
        $nombreUsuario = (string) preg_replace('/[^a-z0-9._]/', '', $nombreUsuario);
        $nombreUsuario = (string) preg_replace('/\.+/', '.', $nombreUsuario);

        return trim($nombreUsuario, '._');
    }
}
