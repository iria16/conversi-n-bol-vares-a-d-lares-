-- Reapertura controlada del año escolar.
--
-- Un cierre erróneo hoy es irreversible desde la aplicación: no hay ruta ni método para
-- deshacerlo, así que la única salida es un UPDATE por consola que además no deja rastro.
-- Estos campos guardan quién lo reabrió, cuándo y por qué.
--
-- `cerrado_en` / `cerrado_por` NO se borran al reabrir: documentan el cierre anterior, y
-- si el periodo se vuelve a cerrar se sobrescriben con los datos del nuevo ciclo. El
-- historial completo de cierres y reaperturas queda en la bitácora.

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anio_escolar' AND COLUMN_NAME = 'reapierto_en'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE anio_escolar
    ADD COLUMN reapierto_en DATETIME NULL AFTER cerrado_por,
    ADD COLUMN reapierto_por INT(11) NULL AFTER reapierto_en,
    ADD COLUMN motivo_reapertura VARCHAR(200) NULL AFTER reapierto_por', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anio_escolar' AND COLUMN_NAME = 'reapierto_por'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE anio_escolar
    ADD KEY idx_anio_escolar_reapierto_por (reapierto_por)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'anio_escolar'
       AND CONSTRAINT_NAME = 'fk_anio_escolar_reapierto_por'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE anio_escolar
    ADD CONSTRAINT fk_anio_escolar_reapierto_por FOREIGN KEY (reapierto_por)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;