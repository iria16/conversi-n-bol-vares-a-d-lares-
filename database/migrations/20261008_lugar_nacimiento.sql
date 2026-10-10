-- Lugar de nacimiento en la tabla persona
--
-- `persona` solo guardaba la FECHA de nacimiento. Faltaba dónde, y esa es la parte que
-- un expediente escolar necesita: la cédula dice el lugar, y sin él no hay forma de
-- resolver una duda de identidad.
--
-- Por qué dos columnas y no tres:
--
--   id_estado_nacimiento      para TODAS las personas
--   id_parroquia_nacimiento   solo para el estudiante
--
-- El municipio NO se guarda porque se deduce de la parroquia, que es lo que ya hace la
-- tabla `direccion`: guarda id_parroquia y deriva estado y municipio por JOIN. Guardar
-- el municipio además sería guardar dos veces el mismo dato y abrir la puerta a que
-- quedaran inconsistentes entre sí.
--
-- En el formulario se le pregunta al estudiante por los tres niveles —entidad federal,
-- municipio y parroquia— porque es lo que la secretaria sabe de memoria, pero de los
-- tres solo sobreviven dos. A las demás personas (empleados, familiares) se les pregunta
-- únicamente la entidad federal, que es el único dato que se guarda para ellas.
--
-- Ambas columnas admiten NULL a propósito: las personas ya registradas en el sistema no
-- tienen el dato, y una columna NOT NULL obligaría a inventarlo o a bloquear el guardado
-- de fichas existentes. El formulario sí lo exige al capturar.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

-- ------------------------------------------------------------------ columnas
SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona'
       AND COLUMN_NAME = 'id_estado_nacimiento'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE persona
    ADD COLUMN id_estado_nacimiento INT(11) NULL AFTER sexo,
    ADD COLUMN id_parroquia_nacimiento INT(11) NULL AFTER id_estado_nacimiento', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------ índices
SET @existe = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona'
       AND INDEX_NAME = 'idx_persona_nacimiento'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE persona
    ADD KEY idx_persona_nacimiento (id_estado_nacimiento, id_parroquia_nacimiento)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------ claves foráneas
SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'persona'
       AND CONSTRAINT_NAME = 'fk_persona_estado_nacimiento'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE persona
    ADD CONSTRAINT fk_persona_estado_nacimiento FOREIGN KEY (id_estado_nacimiento)
        REFERENCES estado (id_estado) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'persona'
       AND CONSTRAINT_NAME = 'fk_persona_parroquia_nacimiento'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE persona
    ADD CONSTRAINT fk_persona_parroquia_nacimiento FOREIGN KEY (id_parroquia_nacimiento)
        REFERENCES parroquia (id_parroquia) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
