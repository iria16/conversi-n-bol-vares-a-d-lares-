<?php

declare(strict_types=1);

namespace App\Traits;

    /* Estado "Retirado" de un alumno. POR QUÉ UN TRAIT Y NO UNA COLUMNA: no hay ningún campo
 "retirado" en `estudiante` ni en `matricula`, y a propósito. El retiro es una fila MÁS de
 `matricula` con tipo = 'RETIRO', al lado de la de inscripción, que sigue apuntando al
 alumno y deja ver su historia completa.*/
trait RetiradoTrait
{
    /**
 * Fragmento SQL reutilizable: "este alumno tiene fila de retiro".
 *
 * Se usa dentro del SELECT (para traer el dato ya resuelto y no una
 * subconsulta por fila) y dentro del WHERE (para excluir). Se apoya en un
 * EXISTS y no en un JOIN para no duplicar filas: un alumno puede tener más de
 * una fila en `matricula`, y un JOIN las multiplicaría en el listado.
 *
 * @param string $aliasMatricula Alias de la tabla en la consulta que lo usa
 * (p. ej. 'm' en el listado de inscripciones).
 * @return string Fragmento que evalúa a 1 si el alumno está retirado.
*/
    private static function retiradoSql(string $aliasMatricula = 'm'): string
    {
        return "EXISTS (SELECT 1 FROM matricula mr
                        WHERE mr.id_estudiante = {$aliasMatricula}.id_estudiante
                          AND mr.tipo = 'RETIRO')";
    }

    /**
 * El alumno tiene al menos una fila de retiro.
*/
    private function esRetirado(int $idEstudiante): bool
    {
        if ($idEstudiante <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM matricula WHERE id_estudiante = :id AND tipo = 'RETIRO'"
        );
        $stmt->execute(['id' => $idEstudiante]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
 * La fila de retiro del alumno, con los datos que hacen falta para explicar por
 * qué está retirado: cuándo, por qué y quién lo resolvió.
 *
 * Se toma la más reciente cuando hay más de una, que es lo que se ve.
 * `LEFT JOIN retiro` porque la fila RETIRO puede haberse creado desde otro
 * módulo (o en una migración) sin fila en `retiro`: en ese caso se muestran los
 * datos de la matrícula y no se inventa motivo ni autor.
 *
 * @return array<string, mixed>|null Null si el alumno no está retirado.
*/
    private function retiroDeEstudiante(int $idEstudiante): ?array
    {
        if ($idEstudiante <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.id_matricula, m.fecha_matricula, m.motivo_anticipo,
                    r.id_retiro, r.observaciones, r.estado_solicitud,
                    r.fecha_solicitud, r.fecha_resolucion,
                    an.nombre_periodo, g.nombre AS grado, s.nombre AS seccion,
                    CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS resuelto_por
               FROM matricula m
               INNER JOIN anio_escolar an ON an.id_anio_escolar = m.id_anio_escolar
               LEFT JOIN estructura_academica ea ON ea.id_estructura_academica = m.id_estructura_academica
               LEFT JOIN grado g ON g.id_grado = ea.id_grado
               LEFT JOIN seccion s ON s.id_seccion = ea.id_seccion
               LEFT JOIN retiro r ON r.id_matricula = m.id_matricula
               LEFT JOIN usuario u ON u.id_usuario = r.id_usuario_resolucion
               LEFT JOIN persona pu ON pu.id_persona = u.id_persona
              WHERE m.id_estudiante = :id AND m.tipo = 'RETIRO'
              ORDER BY m.fecha_creacion DESC, m.id_matricula DESC
              LIMIT 1"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $fila ?: null;
    }
}
