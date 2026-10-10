<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\WithdrawalModel;
use App\Traits\TextoTrait;
use PDO;

use function App\Traits\sigde_rol_clave;

/**
 * Retiros (gestion_estudiante/retiro): la secretaria los registra (store) y el directivo los resuelve (updateEstado).
 * Es un proceso de matrícula, igual que inscripción, ratificación y egreso; las notificaciones solo lo avisan.
 * Endpoints: withdrawal/index (GET), withdrawal/buscarEstudiante (GET, secretaria),
 * withdrawal/store (POST, secretaria) y withdrawal/updateEstado (POST, directivo).
 * El alta usa store() de CrudController (el modelo implementa Creatable); aquí solo se arman extractData() y validate().
*/
class WithdrawalController extends CrudController
{
    // Importamos TextoTrait para tener acceso a capitalizarOracion y protección XSS
    use TextoTrait;

    private const ESTADOS = ['PENDIENTE', 'APROBADO', 'RECHAZADO'];

    /** Caracteres mínimos de la cédula: con menos hay resultados de más.*/
    private const MIN_BUSQUEDA = 3;

    protected string $viewPath  = 'gestion_estudiante/retiro';
    protected string $routeName = 'withdrawal';

    /**
 * Devuelve el modelo con su tipo real, porque getModel() retorna ?object y el editor no resuelve sus métodos.
 * Este envoltorio fija el tipo de createModel() y evita los subrayados rojos.
*/
    private function modelo(): WithdrawalModel
    {
        /** @var WithdrawalModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new WithdrawalModel($pdo);
    }

    protected function getFiltersFromRequest(): array
    {
        $filters = [
            // Casting a (string) para cumplir estrictamente con strict_types
            'motivo' => trim((string) ($_GET['motivo'] ?? '')),
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'anio'   => trim((string) ($_GET['anio'] ?? '')),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null) {
            $filters['id_turno'] = $turno;
        }
        return $filters;
    }

    protected function getExtraIndexData(array $items, array $filters): array
    {
        $model = $this->modelo();
        $rol   = sigde_rol_clave($_SESSION['rol'] ?? '');

        // Solo la secretaria registra retiros: los candidatos se piden solo en ese caso.
        $esSecretaria = $rol === 'secretaria';

        return [
            'stats'          => $model->getStats($filters['id_turno'] ?? null),
            'solicitudes'    => $items,
            'aniosEscolares' => $model->getYears(),
            'motivos'        => $model->getReasons(),
            'puedeRegistrar' => $esSecretaria,
            'puedeResolver'  => $rol === 'directivo',
        ];
    }

    /* Busca el estudiante del retiro por cédula. Ruta: withdrawal/buscarEstudiante (GET, secretaria),
 responde JSON. Devuelve también a los que NO se pueden retirar, con el motivo, para que el
 formulario pueda decir "este alumno ya egresó" en vez de un "no se encontró" que no
 distinguiría las dos cosas.*/
    public function buscarEstudiante(): void
    {
        $termino = trim((string) ($_GET['q'] ?? ''));

        /* Prefijo sin número ("V", "CE-", "V."): se contesta antes que la regla
 del mínimo, porque "V" mide un carácter y de lo contrario saldría por
 ese camino con un "sin coincidencias" que parece un documento que no
 existe. Se comparan solo una o dos letras tras quitar separadores.*/
        $soloPrefijo = preg_replace('/[\s.\-]/u', '', $termino);
        if ($soloPrefijo !== '' && preg_match('/^[VEPCvepc]{1,2}$/', $soloPrefijo) === 1) {
            $this->jsonResponse(true, [
                'resultados' => [],
                'aviso'      => 'Falta el número de la cédula. El prefijo V, E o CE es opcional.',
                'esperando'  => false,
            ], null);
            return;
        }

        // Con menos dígitos hay demasiados resultados y ninguno útil.
        if (mb_strlen($termino, 'UTF-8') < self::MIN_BUSQUEDA) {
            $this->jsonResponse(true, ['resultados' => [], 'aviso' => null, 'esperando' => true], null);
            return;
        }

        try {
            $this->jsonResponse(true, [
                'resultados' => $this->modelo()->buscarPorDocumento(
                    $termino,
                    10,
                    $this->turnoSecretariaActual()
                ),
                'aviso'      => null,
                'esperando'  => false,
            ]);
        } catch (\Throwable $e) {
            $this->jsonError($e, static::class);
        }
    }

    /**
 * Datos del retiro tal como los manda el modal de la secretaria (contrato de CrudController::store()).
*/
    protected function extractData(array $source): array
    {
        return [
            'id_matricula'     => (int) ($source['id_matricula'] ?? 0),
            'id_motivo_retiro' => (int) ($source['id_motivo_retiro'] ?? 0),
            // Quién registra la solicitud: sin esto el retiro no sabe quién lo pidió.
            'id_usuario'       => (int) ($this->idUsuarioActual() ?? 0),
            // Uso correcto de capitalizarOracion (el trait ya quita HTML, recorta y colapsa espacios)
            'observaciones'    => mb_substr($this->capitalizarOracion((string) ($source['observaciones'] ?? '')), 0, 500),
        ];
    }

    /**
 * Devuelve los errores por campo del alta.
 * Los que requieren BD (motivo inexistente, retiro duplicado) se los pregunta al modelo.
*/
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];

        if ($data['id_matricula'] <= 0) {
            $errores['id_matricula'] = 'Seleccione el estudiante.';
        }
        $turno = $this->turnoSecretariaActual();
        if ($turno !== null
            && $data['id_matricula'] > 0
            && !$this->modelo()->matriculaPerteneceTurno($data['id_matricula'], $turno)) {
            $errores['id_matricula'] = 'El estudiante no pertenece a su turno asignado.';
        } elseif ($data['id_matricula'] > 0) {
            /* La elegibilidad se comprueba AQUÍ, en el servidor, y no solo en el buscador:
 antes el formulario mostraba "elegible=false" para un egresado o un retirado,
 pero un POST directo se saltaba esa regla y creaba la solicitud igual. El
 motivo lo pone el modelo, que es donde vive la máquina de estados.*/
            $bloqueo = $this->modelo()->bloqueoParaRetiro($data['id_matricula']);
            if ($bloqueo !== '') {
                $errores['id_matricula'] = $bloqueo;
            }
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
    /* Detalle de una solicitud de retiro (GET withdrawal/ver/{id}). El botón "ver" de la fila ya no
 lleva los datos en atributos data-: duplicar el modelo en el markup obliga a mantenerlo en dos
 lugares y se desincroniza solo. Con el id basta y los datos se piden aquí.*/
    public function ver(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            $this->setFlash('error', 'Solicitud de retiro no válida.');
            $this->redirect('index');
        }

        $item = $this->modelo()->getDetail($id);
        if ($item === null) {
            $this->setFlash('error', 'La solicitud de retiro no existe.');
            $this->redirect('index');
        }

        require __DIR__ . "/../Views/{$this->viewPath}/ver.php";
    }

    public function updateEstado(): void
    {
        // 1. Validar que la petición sea POST (aunque las rutas devuelvan 405, es buena defensa en profundidad)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(false, null, 'Método no permitido.', 405);
            return; // Detiene la ejecución
        }

        $model = $this->modelo();

        $id     = (int) ($_POST['id'] ?? 0);
        $estado = strtoupper(trim((string) ($_POST['estado'] ?? '')));

        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
            return;
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            $this->jsonResponse(false, null, 'Estado de retiro no válido.', 422);
            return;
        }

        try {
            $ok = $model->updateStatus($id, $estado, (int) ($this->idUsuarioActual() ?? 0));
            $this->jsonResponse(
                $ok,
                null,
                // El motivo real del fallo lo traduce el modelo: el throw original es un error
                // de SQL y con el mensaje genérico no se sabe si reintentar sirve.
                $ok ? 'Estado de la solicitud actualizado.' : ($model->ultimoError() ?: 'No se pudo actualizar la solicitud.'),
                $ok ? 200 : 409
            );
        } catch (\Throwable $e) {
            error_log('Error en WithdrawalController::updateEstado: ' . $e->getMessage());
            $this->jsonResponse(false, null, 'Ocurrió un error interno al actualizar el estado.', 500);
        }
    }
}