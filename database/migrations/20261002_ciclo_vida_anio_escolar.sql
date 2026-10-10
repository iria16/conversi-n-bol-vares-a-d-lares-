ALTER TABLE anio_escolar
    MODIFY estado ENUM(
        'ACTIVO',
        'INACTIVO',
        'CERRADO',
        'PLANIFICACIÓN',
        'PLANIFICADO'
    ) NOT NULL DEFAULT 'PLANIFICADO';

UPDATE anio_escolar
   SET estado = CASE
       WHEN estado = 'INACTIVO' THEN 'CERRADO'
       WHEN estado = 'PLANIFICACIÓN' THEN 'PLANIFICADO'
       ELSE estado
   END;

ALTER TABLE anio_escolar
    MODIFY estado ENUM('PLANIFICADO', 'ACTIVO', 'CERRADO') NOT NULL DEFAULT 'PLANIFICADO',
    ADD COLUMN iniciado_en DATETIME NULL AFTER estado,
    ADD COLUMN iniciado_por INT(11) NULL AFTER iniciado_en,
    ADD COLUMN cerrado_en DATETIME NULL AFTER iniciado_por,
    ADD COLUMN cerrado_por INT(11) NULL AFTER cerrado_en,
    ADD COLUMN activo_unico TINYINT
        GENERATED ALWAYS AS (CASE WHEN estado = 'ACTIVO' THEN 1 ELSE NULL END) STORED,
    ADD UNIQUE KEY uk_anio_escolar_activo_unico (activo_unico),
    ADD KEY idx_anio_escolar_iniciado_por (iniciado_por),
    ADD KEY idx_anio_escolar_cerrado_por (cerrado_por),
    ADD CONSTRAINT fk_anio_escolar_iniciado_por
        FOREIGN KEY (iniciado_por) REFERENCES usuario (id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_anio_escolar_cerrado_por
        FOREIGN KEY (cerrado_por) REFERENCES usuario (id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE;
