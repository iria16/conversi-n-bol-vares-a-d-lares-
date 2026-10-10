-- Nacionalidad como catálogo.
--
-- Va en Configuración → Catálogos, igual que parentesco, ocupación y nivel de
-- instrucción. `persona` no guarda el texto: guarda el id, como hace con el resto de
-- catálogos, para que cambiar "Venezolano" por "Venezolana" en un solo lugar no
-- obligue a editar cada alumno.
--
-- El nombre va en género femenino porque describe a la persona: "estudiante
-- Venezuelan", no "Venezuela". El catálogo es de nacionalidades, no de países, y por
-- eso no se confunde con el lugar de nacimiento.
--
-- La columna se crea como VARCHAR(60) en una migración anterior y aquí se cambia a
-- INT. Es seguro porque en este momento todas las filas están en NULL: se aúnan
-- primero para no perder nada si alguien ya había escrito algo.
--
-- Es idempotente: se puede ejecutar más de una vez sin romper nada.

-- 1) La tabla del catálogo.
CREATE TABLE IF NOT EXISTS nacionalidad (
    id_nacionalidad INT(11) NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(50) NOT NULL,
    estado          ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    PRIMARY KEY (id_nacionalidad),
    UNIQUE KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- 2) Los valores iniciales. "Venezolano" entra primero y a propósito con id 1:
--    es el valor por defecto del formulario, y fijarlo aquí evita depender de que el
--    AUTO_INCREMENT se comporte como se espera en una base ya estrenada.
INSERT IGNORE INTO nacionalidad (id_nacionalidad, nombre) VALUES
    (1,  'Venezolano'),
    (2,  'Colombiano'),
    (3,  'Brasileño'),
    (4,  'Peruano'),
    (5,  'Ecuatoriano'),
    (6,  'Boliviano'),
    (7,  'Argentino'),
    (8,  'Chileno'),
    (9,  'Paraguayo'),
    (10, 'Uruguayo'),
    (11, 'Guyanesa'),
    (12, 'Surinamense'),
    (13, 'Panameño'),
    (14, 'Costarricense'),
    (15, 'Nicaragüense'),
    (16, 'Hondureño'),
    (17, 'Salvadoreño'),
    (18, 'Guatemalteco'),
    (19, 'Mexicano'),
    (20, 'Estadounidense'),
    (21, 'Español'),
    (22, 'Portugués'),
    (23, 'Italiano'),
    (24, 'Dominicano'),
    (25, 'Cubano'),
    (26, 'Haitiano');

-- 3) La columna de la persona pasa a ser el id del catálogo.
--    El orden importa: primero se AGREGA la columna nueva, después se pasan los textos
--    viejos al catálogo, y solo al final se tira la columna de texto. Al revés no se
--    podría enlazar, porque `SET` necesita que la columna destino ya exista.
SET @tipoTexto = (
    SELECT DATA_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona' AND COLUMN_NAME = 'nacionalidad'
);
SET @yaEsId = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona' AND COLUMN_NAME = 'id_nacionalidad'
);

SET @sql = IF(@yaEsId = 0,
    'ALTER TABLE persona ADD COLUMN id_nacionalidad INT(11) NULL DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- El enlace es por nombre igual, en minúsculas. Lo que no encuentre par se queda en
-- NULL a propósito: no se inventa una nacionalidad. El SELECT avisa cuántas quedaron
-- sin par para que quien aplique esto a mano sepa si hay que revisarlas.
SET @sql = IF(@tipoTexto = 'varchar',
    'UPDATE persona p
       JOIN nacionalidad n ON LOWER(n.nombre) = LOWER(p.nacionalidad)
        SET p.id_nacionalidad = n.id_nacionalidad
      WHERE p.nacionalidad IS NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Va también por PREPARE a propósito: un SELECT normal que mencione la columna de
-- texto se prepara SIEMPRE, y en la segunda corrida la columna ya no existe y el
-- allusion revienta la migración entera aunque el IF devuelva la otra rama.
SET @sql = IF(@tipoTexto = 'varchar',
    'SELECT CONCAT(''  textos sin par en el catalogo (quedan en NULL): '', COUNT(*))
       FROM persona WHERE nacionalidad IS NOT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@tipoTexto = 'varchar', 'ALTER TABLE persona DROP COLUMN nacionalidad', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) La llave foránea, solo si la columna existe y todavía no tiene el índice.
-- La llave se busca en KEY_COLUMN_USAGE y no en TABLE_CONSTRAINTS porque
-- REFERENCED_TABLE_NAME vive en la primera: en TABLE_CONSTRAINTS no existe la columna
-- y la migración revienta con "Unknown column".
SET @tieneFk = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'persona'
       AND REFERENCED_TABLE_NAME = 'nacionalidad'
       AND REFERENCED_COLUMN_NAME = 'id_nacionalidad'
);
SET @sql = IF(@tieneFk = 0 AND @yaEsId = 1,
    'ALTER TABLE persona
        ADD CONSTRAINT fk_persona_nacionalidad
        FOREIGN KEY (id_nacionalidad) REFERENCES nacionalidad (id_nacionalidad)
        ON UPDATE CASCADE ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
