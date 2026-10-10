ALTER TABLE empleado
    ADD COLUMN id_turno INT(11) NULL AFTER id_cargo,
    ADD KEY idx_empleado_turno (id_turno),
    ADD CONSTRAINT fk_empleado_turno
        FOREIGN KEY (id_turno) REFERENCES turno (id_turno)
        ON UPDATE CASCADE
        ON DELETE SET NULL;
