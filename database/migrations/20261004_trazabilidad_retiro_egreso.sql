-- Trazabilidad de los cierres de ciclo (retiro y egreso).
--
-- Hasta ahora `retiro` y `egreso` solo guardaban QUÉ pasó (id_matricula + motivo), nunca
-- QUIÉN lo pidió, QUIÉN lo resolvió ni CUÁNDO. Sin eso no hay forma de auditar una salida:
-- el listado incluso llegaba a mostrar la fecha de la matrícula como si fuera la de la
-- solicitud. Estos campos son los que hacen falta para que el rastro exista.
--
-- Idempotencia: se puede ejecutar más de una vez sin romper nada (todo es IF NOT EXISTS).

-- ---------------------------------------------------------------- retiro
SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retiro' AND COLUMN_NAME = 'fecha_solicitud'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE retiro
    ADD COLUMN fecha_solicitud DATETIME NULL AFTER estado_solicitud,
    ADD COLUMN fecha_resolucion DATETIME NULL AFTER fecha_solicitud,
    ADD COLUMN id_usuario_registro INT(11) NULL AFTER fecha_resolucion,
    ADD COLUMN id_usuario_resolucion INT(11) NULL AFTER id_usuario_registro', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retiro' AND COLUMN_NAME = 'id_usuario_registro'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE retiro
    ADD KEY idx_retiro_usuarios (id_usuario_registro, id_usuario_resolucion)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'retiro'
       AND CONSTRAINT_NAME = 'fk_retiro_usuario_registro'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE retiro
    ADD CONSTRAINT fk_retiro_usuario_registro FOREIGN KEY (id_usuario_registro)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'retiro'
       AND CONSTRAINT_NAME = 'fk_retiro_usuario_resolucion'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE retiro
    ADD CONSTRAINT fk_retiro_usuario_resolucion FOREIGN KEY (id_usuario_resolucion)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------- egreso
SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'egreso' AND COLUMN_NAME = 'fecha_egreso'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE egreso
    ADD COLUMN fecha_egreso DATETIME NULL AFTER id_matricula,
    ADD COLUMN id_usuario INT(11) NULL AFTER fecha_egreso', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'egreso' AND COLUMN_NAME = 'id_usuario'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE egreso
    ADD KEY idx_egreso_usuario (id_usuario)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'egreso'
       AND CONSTRAINT_NAME = 'fk_egreso_usuario'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE egreso
    ADD CONSTRAINT fk_egreso_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;