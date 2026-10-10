-- Trazabilidad de los recaudos del estudiante.
--
-- La tabla estudiante_documento existe desde siempre pero nunca se escribió: el paso 5 del
-- asistente de inscripción mostraba la lista y el switch, y al guardar todo se perdía. Ya
-- se llena (RecaudosModelTrait), pero marcaba "sí, lo entregaron" sin decir quién lo marcó
-- ni cuándo, que para un requisito de matrícula es justo lo primero que se pregunta después:
-- "yo lo entregué, ¿quién lo anotó?".
--
-- Estos dos campos no sustituyen a la bitácora: la bitácora registra el hecho de la
-- actualización, y aquí queda en la propia fila quién fue el último que la hizo.

SET @existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'estudiante_documento' AND COLUMN_NAME = 'actualizado_en'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE estudiante_documento
    ADD COLUMN actualizado_en DATETIME NULL AFTER consignado,
    ADD COLUMN actualizado_por INT(11) NULL AFTER actualizado_en', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existe = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'estudiante_documento'
       AND CONSTRAINT_NAME = 'fk_estudiante_documento_usuario'
);
SET @sql = IF(@existe = 0, 'ALTER TABLE estudiante_documento
    ADD KEY idx_estudiante_documento_usuario (actualizado_por),
    ADD CONSTRAINT fk_estudiante_documento_usuario FOREIGN KEY (actualizado_por)
        REFERENCES usuario (id_usuario) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
