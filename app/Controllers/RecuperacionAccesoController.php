<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\RecuperacionAccesoModel;
use PDO;
use Throwable;

/**
 * RecuperacionAccesoController
 *
 * Lado del administrador del flujo de recuperación de acceso: atiende las
 * solicitudes que los usuarios envían cuando olvidan su clave y permite
 * restablecer contraseñas directamente.
 *
 * Contexto del flujo completo:
 *  1. El usuario pide recuperar el acceso desde el login
 *     (AuthController::requestRecovery), que crea una solicitud pendiente.
 *  2. El administrador la ve aquí, en el listado, y la aprueba o la rechaza.
 *  3. Al aprobar se genera una contraseña provisional que se le entrega al
 *     usuario; al iniciar sesión con ella, AuthController lo obliga a
 *     cambiarla (cambiarPasswordProvisional).
 *  4. Aparte, el administrador puede restablecer la clave de cualquier
 *     usuario sin que haya una solicitud previa (resetPassword).
 *
 * Es un módulo de solo consulta y transiciones controladas: no se crean ni
 * se editan solicitudes a mano, y las acciones genéricas de escritura de
 * CrudController (store, update, delete, toggleStatus) quedan bloqueadas
 * porque el modelo no implementa las interfaces correspondientes.
 *
 * Vistas: Views/usuarios/recuperacion/.
 *
 * Rutas: `recuperacionAcceso/{accion}`. Las acciones propias (updateEstado,
 * getDetalle, resetPassword) deben estar en el whitelist `$rutasPermitidas`
 * del front controller (public/index.php).
 *
 * @author Logística
 * @package App\Controllers
 */
class RecuperacionAccesoController extends CrudController
{
    /** Carpeta de vistas del módulo (Views/usuarios/recuperacion). */
    protected string $viewPath    = 'usuarios/recuperacion';

    /** Segmento de ruta del controlador; lo usa redirect() del BaseController. */
    protected string $routeName   = 'recuperacionAcceso';

    /**
     * Crea la instancia concreta del modelo cuando sea requerida.
     *
     * @param PDO $pdo Conexión abierta por BaseController::getModel().
     */
    protected function createModel(PDO $pdo): object
    {
        return new RecuperacionAccesoModel($pdo);
    }

    /**
     * Metadatos de presentación de cada estado de solicitud.
     *
     * La clave es el estado tal como lo devuelve el modelo (en minúsculas).
     * `label` es el texto que ve el usuario y `clase` es la variante del
     * componente visual de estado (status-badge). Se la paso a la vista
     * como $estadosMeta para que no repita este mapa.
     */
    private const ESTADOS_META = [
        'pendiente' => ['label' => 'Pendiente', 'clase' => 'pending'],
        'aprobado'  => ['label' => 'Aprobado',  'clase' => 'active'],
        'rechazado' => ['label' => 'Rechazado', 'clase' => 'inactive'],
    ];

    /**
     * Lee los filtros del listado desde la URL (gancho de ListableIndexTrait).
     *
     * @return array{estado: string, q: string} Estado de la solicitud y texto de búsqueda.
     */
    protected function getFiltersFromRequest(): array
    {
        return [
            'estado' => trim($_GET['estado'] ?? ''),
            'q'      => trim($_GET['q'] ?? ''),
        ];
    }

    /**
     * Variables extra que necesita la vista de listado.
     *
     *  - solicitudes:         filas de la página actual.
     *  - stats:               contadores para las tarjetas de resumen.
     *  - estadosMeta:         etiquetas y clases de cada estado (ESTADOS_META).
     *  - usuariosDisponibles: usuarios entre los que elegir en el
     *                         restablecimiento manual de contraseña.
     *
     * @param array $items   Filas de la página actual.
     * @param array $filters Filtros aplicados.
     */
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

    // store(), update(), delete() y toggleStatus() no se sobrescriben:
    // RecuperacionAccesoModel no implementa Creatable/Updatable/
    // Deletable/Toggleable, así que si alguien las invoca por error
    // heredan el comportamiento 405 de CrudController automáticamente.

    /**
     * Aprueba o rechaza una solicitud pendiente.
     * Ruta: recuperacionAcceso/updateEstado (POST, responde JSON).
     *
     * Recibe `id` de la solicitud y `accion` ('aprobar' o 'rechazar');
     * cualquier otro valor responde 400. Registra en el modelo qué usuario
     * atendió la solicitud (idUsuarioActual()).
     *
     *  - aprobar:  el modelo genera una contraseña provisional y la devuelvo
     *              en `data` para que el administrador se la entregue al usuario.
     *  - rechazar: solo cambia el estado.
     *
     * En ambos casos, si el modelo devuelve null/false es porque la solicitud
     * ya fue atendida o no existe, y respondo 409. Así dos administradores no
     * pueden procesar la misma solicitud a la vez.
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

    /**
     * Devuelve el detalle de una solicitud en JSON (modal "Ver detalle").
     * Ruta: recuperacionAcceso/getDetalle?id=... (GET).
     *
     * Respuestas: 400 si el ID es inválido, 404 si la solicitud no existe,
     * 500 si el modelo falla y 200 con el detalle si todo sale bien.
     */
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

    /**
     * Restablece manualmente la contraseña de un usuario, sin solicitud previa.
     * Ruta: recuperacionAcceso/resetPassword (POST, responde JSON).
     *
     * Recibe `usuario_id`. El modelo genera la clave provisional (registrando
     * qué administrador la solicitó) y devuelvo en `data`: la clave, el nombre
     * de usuario y el nombre de la persona, para mostrarlos en el modal y que
     * el administrador se los entregue. Si no se encuentran los datos básicos
     * del usuario, esos dos campos salen como cadena vacía.
     *
     * Ojo: la clave viaja en la respuesta JSON, así que no debe escribirse en
     * logs ni guardarse en el front más allá de mostrarla.
     */
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