INSERT INTO docente (id_empleado)
SELECT e.id_empleado
  FROM empleado e
  INNER JOIN cargo c ON c.id_cargo = e.id_cargo
  LEFT JOIN docente d ON d.id_empleado = e.id_empleado
 WHERE c.nombre IN ('Docente de Aula', 'Docente de Educación Especial')
   AND d.id_docente IS NULL;
