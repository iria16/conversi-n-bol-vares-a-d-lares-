<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\CapacidadInsuficienteException;
use App\Models\AcademicStructureModel;
use App\Models\BitacoraModel;
use PDO;
use RuntimeException;
use Throwable;

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
        /** @var AcademicStructureModel $model*/
        $model = $this->getModel();

        return $model;
    }

    protected function createModel(PDO $pdo): object
    {
        return new AcademicStructureModel($pdo);
    }

    /**
 * El año que se está consultando.
 *
 * Antes esto devolvía siempre el ACTIVO e ignoraba cualquier ?anio= de la URL, así que
 * la estructura de un período cerrado no se podía consultar: había que entrar a la base
 * de datos para auditar cómo había quedado un año. Ahora el año llega por la URL, se
 * valida contra los que existen de verdad y, si no viene o no es válido, se cae al
 * ACTIVO.
 *
 * Se valida contra el catálogo y no con un cast a entero: un ?anio=999 no puede
 * convertirse en un id que llegue al SQL. Y si no hay ningún año activo, se elige el
 * más reciente para que la pantalla no salga vacía sin explicación.
 *
 * @return int 0 solo si no hay ningún año escolar registrado.
*/
private function anioConsultado(): int
{
    $anios    = $this->modelo()->getAniosParaConsultar();
    $pedido   = (int) ($_GET['anio'] ?? 0);

    foreach ($anios as $anio) {
        if ((int) $anio['id'] === $pedido && $pedido > 0) {
            return $pedido;
        }
    }

    $activo = $this->modelo()->getActiveSchoolYearId();
    if ($activo !== null) {
        return $activo;
    }

    return $anios !== [] ? (int) $anios[0]['id'] : 0;
}

/**
 * Si el año consultado admite escritura.
 *
 * Es un derivado, no una decisión de la vista: el mismo criterio que aplican
 * validar() en el alta y update() en la edición (solo se toca el ACTIVO), para que un
 * año cerrado se vea pero no se pueda escribir en él.
 *
 * La barrera real sigue siendo el servidor, que rechaza una actualización sobre un
 * año cerrado aunque la pantalla llegue a mostrarlo como editable por un error. Lo de
 * aquí es la primera línea: que nadie se entere por el camino.
*/
private function anioEsConsultable(int $idAnio): bool
{
    return $idAnio > 0 && $this->modelo()->esAnioActivo($idAnio);
}

protected function getFiltersFromRequest(): array
{
    return [
        'anio'    => (string) $this->anioConsultado(),
        'grado'   => trim($_GET['grado'] ?? ''),
        'seccion' => trim($_GET['seccion'] ?? ''),
        'turno'   => trim($_GET['turno'] ?? ''),
        'q'       => trim($_GET['q'] ?? ''),
    ];
}

    /* Las estadísticas y el nombre del año salen del año que se está mirando. Antes getStats() contaba
 solo el ACTIVO: al consultar 2025-2026 la pantalla enseñaba los totales de 2026-2027. Una
 lista de un período con las cifras de otro se lee bien y no quiere decir nada.*/
protected function getExtraIndexData(array $items, array $filters): array
{
    $model     = $this->modelo();
    $idAnio    = (int) $filters['anio'];
    $consultable = $this->anioEsConsultable($idAnio);

    $nombreAnio = 'No hay un año escolar activo';
    foreach ($model->getAniosParaConsultar() as $anio) {
        if ((int) $anio['id'] === $idAnio) {
            $nombreAnio = (string) $anio['nombre'];
            break;
        }
    }

    $anios = $model->getAniosParaConsultar();

    return [
        'stats'        => $model->getStats($idAnio),
        'estructuras'  => $items,
        /* Los años que se pueden consultar. Se pasan todos, con su estado, para que la
 vista pueda distinguir "el que se está editando" de "uno cerrado, de solo
 lectura".*/
        'anios'        => $anios,
        /* Los que se pueden escribir, para el <select> de año del modal de alta y de
 edición. Es el mismo listado filtrado en memoria y no una consulta aparte: el
 modal ofreciendo años cerrados no era un error de validación, era una
 invitación a capturar un formulario entero para recibir un 422 al final.*/
        'aniosEditables' => array_values(array_filter(
            $anios,
            static fn (array $anio): bool => (bool) $anio['es_editable']
        )),
        'anioActivo'   => $consultable ? $nombreAnio : $nombreAnio . ' (solo consulta)',
        'anioEsLectura' => !$consultable,
        'grados'       => $model->getOptions('grado'),
        'secciones'    => $model->getOptions('seccion'),
        'turnos'       => $model->getOptions('turno'),
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
 * Edición.
 *
 * Sobrescribe CrudController::update() porque el padre mete todo Throwable en jsonError(), que
 * responde 500 con "ocurrió un problema al procesar la solicitud". Bajar la capacidad de una
 * sección con 30 alumnos produciría un error genérico sin decir por qué, que es justo lo que
 * la secretaria necesita saber.
*/
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'ID inválido.', 400);
        }

        $data = $this->extractData($_POST);
        if ($data === []) {
            error_log(static::class . ': extractData() devolvió un arreglo vacío; ¿falta sobrescribirlo?');
            $this->jsonResponse(false, null, 'No se recibieron datos válidos para actualizar.', 422);
        }

        $errores = $this->validate($data, $id);
        if (!empty($errores)) {
            $this->jsonResponse(false, $errores, 'Hay errores en el formulario.', 422);
        }

        /* Se lee el estado ANTES de escribir. El modelo ya confirmó y la fila tiene
 los valores nuevos, así que si esto se preguntara después solo se podría
 reconstruir el pasado con un diff de la petición, que además mentiría si
 el campo no se envió.*/
        $antes = $this->modelo()->disponibilidadDe($id);

        try {
            $this->modelo()->update($id, $data);
        } catch (CapacidadInsuficienteException $e) {
            /* 422 y no 500: el formulario está bien, lo que no se admite es una
 capacidad que dejaría alumnos sin cupo. Se cuelga el mensaje en el
 campo para que el modal lo marque donde está el problema.*/
            $this->jsonResponse(
                false,
                $e->campo() !== null ? [$e->campo() => $e->getMessage()] : null,
                $e->getMessage(),
                422
            );
        } catch (RuntimeException $e) {
            /* "No se pueden modificar estructuras de años escolares cerrados": es un
 rechazo de negocio con nombre propio, no una falla del servidor. 409,
 como el resto de estados que cambiaron bajo los pies de la pantalla.*/
            $this->registrarError($e, static::class);
            $this->jsonResponse(false, null, $e->getMessage(), 409);
        } catch (Throwable $e) {
            $this->jsonError($e, static::class);
        }

        $this->registrarCambioEnBitacora($id, $antes);

        $this->jsonResponse(true, null, 'Registro actualizado correctamente.');
    }

    /**
 * El nombre de una combinación, abreviado para la bitácora.
 *
 * disponibilidadDe() devuelve "2do Grado · A · Mañana", que en pantalla está bien
 * pero en la bitácora se come el espacio justo donde iría el cambio de capacidad.
 * Con los separadores sin espacio cabe el registro entero y no hay recorte.
*/
    private function nombreCompacto(string $nombre): string
    {
        return trim(str_replace(' · ', '·', $nombre));
    }

    /* Deja rastro en la bitácora de lo que se acaba de cambiar en la estructura. Mover 30 alumnos de
 un turno a otro no dejaba nada: la matrícula apunta a la fila, la fila cambia de nombre y
 no queda constancia de quién lo hizo ni desde cuándo.*/
    private function registrarCambioEnBitacora(int $id, ?array $antes): void
    {
        $despues = $this->modelo()->disponibilidadDe($id);

        if ($antes === null || $despues === null) {
            return;
        }

        $cambioCombinacion = $antes['nombre'] !== $despues['nombre'];
        $cambioCapacidad   = (int) $antes['capacidad_maxima'] !== (int) $despues['capacidad_maxima'];

        if (!$cambioCombinacion && !$cambioCapacidad) {
            return;
        }

        /* Los alumnos se leen de `despues` porque la actualización no los mueve de
 fila: son los mismos que tenía antes, y ese es justamente el número que
 hace relevante el cambio.*/
        $ocupados = (int) $despues['ocupados'];
        $plural   = $ocupados === 1 ? 'alumno' : 'alumnos';

        /* El texto se arma pensando en los 100 caracteres de la columna: si se
 recorta, lo que se pierde es el final, así que el número de alumnos va
 primero, que es lo que no se puede deducir leyendo el resto.

 Los nombres van con separador corto ("2do Grado·A·Mañana") y la capacidad
 abreviada ("cap 30 → 30") porque con los separadores largos de la pantalla
 el texto se pasaba de 100 y el recorte se comía justo la capacidad, que es
 la mitad de lo que se quiso dejar anotado.*/
        if ($cambioCombinacion) {
            $accion = 'ACTUALIZACIÓN ESTRUCTURA · ' . $ocupados . ' ' . $plural . ' · '
                . $this->nombreCompacto($antes['nombre']) . ' → ' . $this->nombreCompacto($despues['nombre'])
                . ' · cap ' . $antes['capacidad_maxima'] . ' → ' . $despues['capacidad_maxima'];
        } else {
            $accion = 'ACTUALIZACIÓN ESTRUCTURA · ' . $this->nombreCompacto($despues['nombre'])
                . ' · cap ' . $antes['capacidad_maxima'] . ' → ' . $despues['capacidad_maxima']
                . ' · ' . $ocupados . ' ' . $plural;
        }

        /* registrar() trunca a 100 y se traga sus propio fallos en el log, así que
 una bitácora caída no puede tumbar una edición ya guardada.*/
        (new BitacoraModel($this->conexion()))->registrar(
            (int) ($this->idUsuarioActual() ?? 0),
            $accion,
            'Estructura académica'
        );
    }

    /**
 * La ocupación contra la que hay que comparar la capacidad, y de dónde sale.
 *
 * En un ALTA se mira la combinación que se está creando: normalmente 0, y así se
 * evita solaparse con otra fila.
 *
 * En una EDICIÓN se mira la FILA que se está editando, no la combinación del
 * formulario. Son la misma cifra mientras no se toque el grado, la sección o el
 * turno, pero en cuanto se tocan dejan de serlo: la combinación de destino está
 * vacía y mirarla daría 0. Ese hueco era exactamente lo que dejaba crear una
 * sección con 1 cupo y los 3 alumnos de la anterior dentro.
 *
 * Los alumnos contados aquí no son un dato informativo: son los que el UPDATE va a
 * mover, porque matricula.id_estructura_academica sigue apuntando a la misma fila.
 *
 * @return array{ocupados:int,minimo:int}
*/
    private function capacidadMinima(array $data, ?int $id): array
    {
        if ($id !== null && $id > 0) {
            $ocupados = $this->modelo()->ocupadosDeEstructura($id);
            return ['ocupados' => $ocupados, 'minimo' => $ocupados];
        }

        $ocupados = $this->modelo()->ocupadosDeCombinacion(
            (int) $data['id_anio_escolar'],
            (int) $data['id_grado'],
            (int) $data['id_seccion'],
            (int) $data['id_turno']
        );

        return ['ocupados' => $ocupados, 'minimo' => $ocupados];
    }

    /**
 * Devuelve los errores por campo.
 * El duplicado de la combinación se lo pregunta al modelo, que conoce la restricción UNIQUE.
*/
    protected function validate(array $data, ?int $id = null): array
    {
        $errores = [];

        if ($data['id_anio_escolar'] <= 0) {
            $errores['id_anio_escolar'] = 'Seleccione el año escolar.';
        } elseif (!$this->modelo()->esAnioActivo($data['id_anio_escolar'])) {
            $errores['id_anio_escolar'] = 'Las operaciones académicas nuevas deben usar el año escolar activo.';
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

        /* La capacidad no puede quedar por debajo de los que ya están: una sección con 30
 alumnos y capacidad 5 deja los cupos disponibles en -5, la pantalla muestra una
 cifra que no existe y la sección queda bloqueada para siempre sin que nadie pueda
 matricular más. El conteo lo pregunta al modelo porque depende de la matrícula,
 no del formulario.*/
        if ($errores === []) {
            $minimo = $this->capacidadMinima($data, $id);
            if ((int) $data['capacidad_maxima'] < $minimo['minimo']) {
                $errores['capacidad_maxima'] = "La sección tiene {$minimo['minimo']} estudiante(s) matriculado(s); "
                    . 'la capacidad no puede ser menor.';
            }
        }

        // $id llega de update() y permite excluir la fila en edición del chequeo de
        // duplicados; en store() CrudController lo pasa como null, y $id ?? 0 no excluye nada.
        if ($errores === [] && $this->modelo()->existeCombination($data, $id ?? 0)) {
            $errores['id_grado'] = 'Esta combinación académica ya existe.';
        }

        return $errores;
    }

    /* A cuántos alumnos movería la edición de $id, y si lo movería. El frontend lo consulta antes
 de enviar para escribir "¿Esta acción afectará a X alumnos?" con el número real, en lugar de
 un texto genérico que obliga a decidir sin saber a qué atenerse.*/
    public function impacto(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->jsonResponse(false, null, 'Indique la estructura académica.', 400);
        }

        $model  = $this->modelo();
        $actual = $model->estructuraActual($id);

        if ($actual === null) {
            $this->jsonResponse(false, null, 'La estructura académica no existe.', 404);
        }

        $propuesto = [
            'id_grado'   => (int) ($_GET['id_grado']   ?? 0),
            'id_seccion' => (int) ($_GET['id_seccion'] ?? 0),
            'id_turno'   => (int) ($_GET['id_turno']   ?? 0),
        ];

        $ocupados = $model->ocupadosDeEstructura($id);

        $this->jsonResponse(true, [
            'ocupados' => $ocupados,
            'mueve'    => $model->cambiaCombinacion($actual, $propuesto),
            'actual'   => $actual,
            'propuesto' => $propuesto,
            /* Capacidad mínima que el servidor va a exigir. Se manda para que el JS
 avise en el formulario, y no solo después de pulsar Guardar.*/
            'capacidad_minima' => $ocupados,
        ]);
    }
}