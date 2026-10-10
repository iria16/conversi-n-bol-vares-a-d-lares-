-- Orden correcto al calcular la transición de matrícula
--
-- El trigger trg_matricula_transicion decidía el tipo de matrícula anterior del alumno
-- con:
--
--     ORDER BY m.id_anio_escolar DESC
--
-- El problema: `id_anio_escolar` es un id de inserción, no un orden cronológico. Un año
-- cargado antes puede tener id mayor que uno cargado después, así que la matrícula que
-- el trigger tomaba como anterior podía ser en realidad la más reciente. De ahí salía
-- que una ratificación pareciera anterior a la inscripción y fuera rechazada, o que un
-- cierre de ciclo se aceptara dos veces.
--
-- El orden tiene que ser por FECHA del año escolar, no por id:
--
--     ORDER BY a.anio_inicio DESC, m.id_matricula DESC
--
-- El respaldo del disparador anterior está en
-- database/backups/trg_matricula_transicion--antes-20261006_114659.sql
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

DROP TRIGGER IF EXISTS trg_matricula_transicion;

DELIMITER //

CREATE TRIGGER trg_matricula_transicion
BEFORE INSERT ON matricula
FOR EACH ROW
BEGIN
    DECLARE v_previo      VARCHAR(20) DEFAULT NULL;
    DECLARE v_previo_anio INT         DEFAULT NULL;
    DECLARE v_ordinal     INT         DEFAULT NULL;

    SELECT m.tipo, m.id_anio_escolar INTO v_previo, v_previo_anio
      FROM matricula m
      INNER JOIN anio_escolar a ON a.id_anio_escolar = m.id_anio_escolar
     WHERE m.id_estudiante = NEW.id_estudiante
     ORDER BY a.anio_inicio DESC, m.id_matricula DESC
     LIMIT 1;

    reglas: BEGIN

        IF v_previo IS NULL THEN
            IF NEW.tipo <> 'INSCRIPCION' THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Un estudiante sin matriculas previas no se ratifica, no se retira y no egresa: se inscribe.';
            END IF;
            LEAVE reglas;
        END IF;

        IF v_previo IN ('RETIRO', 'EGRESO') THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El ciclo de este estudiante ya se cerro, no admite mas matriculas.';
        END IF;

        IF NEW.tipo = 'INSCRIPCION' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'La inscripcion ocurre una sola vez en toda la vida escolar del alumno: los que ya estan inscriptos se ratifican.';
        END IF;

        IF NEW.tipo = 'RATIFICACION'
           AND (
                SELECT an2.anio_inicio
                  FROM anio_escolar an2
                 WHERE an2.id_anio_escolar = NEW.id_anio_escolar
               ) <= (
                SELECT an1.anio_inicio
                  FROM anio_escolar an1
                 WHERE an1.id_anio_escolar = v_previo_anio
               )
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'La ratificacion debe ir en un anio escolar posterior al de la anterior.';
        END IF;

        IF NEW.tipo IN ('RETIRO', 'EGRESO')
           AND (
                SELECT an3.anio_inicio
                  FROM anio_escolar an3
                 WHERE an3.id_anio_escolar = NEW.id_anio_escolar
               ) < (
                SELECT an4.anio_inicio
                  FROM anio_escolar an4
                 WHERE an4.id_anio_escolar = v_previo_anio
               )
        THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El cierre no puede ir en un anio anterior al de la matricula del alumno.';
        END IF;

        IF NEW.tipo = 'EGRESO' THEN
            SELECT CAST(REGEXP_SUBSTR(g.nombre, '^[0-9]+') AS UNSIGNED) INTO v_ordinal
              FROM estructura_academica ea
              INNER JOIN grado g ON g.id_grado = ea.id_grado
             WHERE ea.id_estructura_academica = NEW.id_estructura_academica
             LIMIT 1;
            IF v_ordinal IS NOT NULL AND v_ordinal <> 6 THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'El egreso es solo para quien culmina 6to grado.';
            END IF;
        END IF;

    END reglas;
END//

DELIMITER ;
