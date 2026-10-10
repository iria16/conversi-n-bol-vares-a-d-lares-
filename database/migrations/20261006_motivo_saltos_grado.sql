-- Justificación de los saltos de grado en la ratificación.
--
-- La ratificación continúa al grado siguiente por defecto. Se permite repetir el mismo grado
-- (quien no alcanzó) y se bloquea retroceder. Adelantar más de un nivel es una decisión
-- excepcional y queda con su motivo, para que el historial no muestre un salto sin explicación.

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matricula' AND COLUMN_NAME = 'motivo_anticipo'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE matricula
    ADD COLUMN motivo_anticipo VARCHAR(200) NULL AFTER tipo', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;