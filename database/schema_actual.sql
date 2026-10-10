/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `anio_escolar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anio_escolar` (
  `id_anio_escolar` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_periodo` varchar(20) NOT NULL,
  `anio_inicio` date NOT NULL,
  `anio_cierre` date NOT NULL,
  `estado` enum('PLANIFICADO','ACTIVO','CERRADO') NOT NULL DEFAULT 'PLANIFICADO',
  `iniciado_en` datetime DEFAULT NULL,
  `iniciado_por` int(11) DEFAULT NULL,
  `cerrado_en` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL,
  `reapierto_en` datetime DEFAULT NULL,
  `reapierto_por` int(11) DEFAULT NULL,
  `motivo_reapertura` varchar(200) DEFAULT NULL,
  `activo_unico` tinyint(4) GENERATED ALWAYS AS (case when `estado` = 'ACTIVO' then 1 else NULL end) STORED,
  PRIMARY KEY (`id_anio_escolar`),
  UNIQUE KEY `nombre_periodo` (`nombre_periodo`),
  UNIQUE KEY `uk_anio_escolar_activo_unico` (`activo_unico`),
  KEY `idx_anio_escolar_fechas` (`anio_inicio`,`anio_cierre`),
  KEY `idx_anio_escolar_estado` (`estado`),
  KEY `idx_anio_escolar_iniciado_por` (`iniciado_por`),
  KEY `idx_anio_escolar_cerrado_por` (`cerrado_por`),
  KEY `fk_anio_escolar_reapierto_por` (`reapierto_por`),
  CONSTRAINT `fk_anio_escolar_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_anio_escolar_iniciado_por` FOREIGN KEY (`iniciado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_anio_escolar_reapierto_por` FOREIGN KEY (`reapierto_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=593 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_anio_uno_en_curso
BEFORE INSERT ON anio_escolar
FOR EACH ROW
BEGIN
    IF NEW.estado = 'ACTIVO' AND EXISTS (
        SELECT 1 FROM anio_escolar WHERE estado = 'ACTIVO'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya hay un año escolar en curso. Ciérralo antes de abrir otro.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_anio_uno_en_curso_upd
BEFORE UPDATE ON anio_escolar
FOR EACH ROW
BEGIN
    
    IF NEW.estado = 'ACTIVO' AND OLD.estado <> 'ACTIVO' AND EXISTS (
        SELECT 1 FROM anio_escolar
         WHERE estado = 'ACTIVO' AND id_anio_escolar <> NEW.id_anio_escolar
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya hay un año escolar en curso. Ciérralo antes de abrir otro.';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `asignacion_docente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignacion_docente` (
  `id_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_docente` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `id_tipo_asignacion` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_asignacion`),
  UNIQUE KEY `uk_asignacion_docente_estructura` (`id_docente`,`id_estructura_academica`,`fecha_inicio`),
  KEY `idx_asignacion_estructura` (`id_estructura_academica`),
  KEY `idx_asignacion_tipo` (`id_tipo_asignacion`),
  CONSTRAINT `fk_asignacion_docente` FOREIGN KEY (`id_docente`) REFERENCES `docente` (`id_docente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_asignacion_tipo` FOREIGN KEY (`id_tipo_asignacion`) REFERENCES `tipo_asignacion` (`id_tipo_asignacion`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bitacora`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitacora` (
  `id_bitacora` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `direccion_ip` varchar(45) NOT NULL,
  PRIMARY KEY (`id_bitacora`),
  KEY `idx_bitacora_usuario` (`id_usuario`),
  KEY `idx_bitacora_fecha` (`fecha`),
  KEY `idx_bitacora_modulo` (`modulo`),
  KEY `idx_bitacora_accion` (`accion`),
  KEY `idx_bitacora_fecha_usuario` (`fecha`,`id_usuario`),
  CONSTRAINT `fk_bitacora_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3556 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cargo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cargo` (
  `id_cargo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_cargo`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacto` (
  `id_contacto` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `codigo_telefono_principal` enum('0412','0414','0416','0422','0424','0426','0212','0241','0251','0261','0271','0281','0291','0231','0254','0255','0256','0257','0258','0259') NOT NULL,
  `numero_telefono_principal` varchar(15) NOT NULL,
  `codigo_telefono_alternativo` enum('0412','0414','0416','0422','0424','0426','0212','0241','0251','0261','0271','0281','0291','0231','0254','0255','0256','0257','0258','0259') DEFAULT NULL,
  `numero_telefono_alternativo` varchar(15) DEFAULT NULL,
  `correo_electronico` varchar(100) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_contacto`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_contacto_telefono_principal` (`numero_telefono_principal`),
  KEY `idx_contacto_correo` (`correo_electronico`),
  CONSTRAINT `fk_contacto_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2864 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `direccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `direccion` (
  `id_direccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `sector_urbanizacion` varchar(100) NOT NULL,
  `calle_avenida` varchar(100) NOT NULL,
  `nro_casa_apto` varchar(20) NOT NULL,
  `punto_referencia` text DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_parroquia` int(11) NOT NULL,
  PRIMARY KEY (`id_direccion`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_direccion_parroquia` (`id_parroquia`),
  CONSTRAINT `fk_direccion_parroquia` FOREIGN KEY (`id_parroquia`) REFERENCES `parroquia` (`id_parroquia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_direccion_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1143 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `docente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `docente` (
  `id_docente` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  PRIMARY KEY (`id_docente`),
  UNIQUE KEY `id_empleado` (`id_empleado`),
  CONSTRAINT `fk_docente_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `egreso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `egreso` (
  `id_egreso` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `fecha_egreso` datetime DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_egreso`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  KEY `fk_egreso_usuario` (`id_usuario`),
  CONSTRAINT `fk_egreso_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egreso_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empleado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `empleado` (
  `id_empleado` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_egreso` date DEFAULT NULL,
  `id_cargo` int(11) DEFAULT NULL,
  `id_turno` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `id_persona` (`id_persona`),
  KEY `idx_empleado_fechas` (`fecha_ingreso`,`fecha_egreso`),
  KEY `fk_empleado_cargo` (`id_cargo`),
  KEY `idx_empleado_turno` (`id_turno`),
  CONSTRAINT `fk_empleado_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargo` (`id_cargo`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_empleado_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estado` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estructura_academica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estructura_academica` (
  `id_estructura_academica` int(11) NOT NULL AUTO_INCREMENT,
  `id_anio_escolar` int(11) NOT NULL,
  `id_grado` int(11) NOT NULL,
  `id_seccion` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `capacidad_maxima` int(11) NOT NULL,
  PRIMARY KEY (`id_estructura_academica`),
  UNIQUE KEY `uk_estructura_academica` (`id_anio_escolar`,`id_grado`,`id_seccion`,`id_turno`),
  KEY `idx_estructura_anio` (`id_anio_escolar`),
  KEY `idx_estructura_grado` (`id_grado`),
  KEY `idx_estructura_seccion` (`id_seccion`),
  KEY `idx_estructura_turno` (`id_turno`),
  CONSTRAINT `fk_estructura_anio_escolar` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_grado` FOREIGN KEY (`id_grado`) REFERENCES `grado` (`id_grado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_seccion` FOREIGN KEY (`id_seccion`) REFERENCES `seccion` (`id_seccion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estructura_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1539 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante` (
  `id_estudiante` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  PRIMARY KEY (`id_estudiante`),
  UNIQUE KEY `id_persona` (`id_persona`),
  CONSTRAINT `fk_estudiante_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `estudiante_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiante_documento` (
  `id_estudiante_documento` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `id_tipo_documento` int(11) NOT NULL,
  `observacion` text DEFAULT NULL,
  `consignado` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` datetime DEFAULT NULL,
  `actualizado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_estudiante_documento`),
  UNIQUE KEY `uk_estudiante_documento` (`id_estudiante`,`id_tipo_documento`),
  KEY `idx_estudiante_documento_tipo` (`id_tipo_documento`),
  KEY `idx_estudiante_documento_consignado` (`consignado`),
  KEY `idx_estudiante_documento_usuario` (`actualizado_por`),
  CONSTRAINT `fk_estudiante_documento_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_tipo` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipo_documento` (`id_tipo_documento`) ON UPDATE CASCADE,
  CONSTRAINT `fk_estudiante_documento_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ficha_medica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ficha_medica` (
  `id_ficha_medica` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `condiciones_especiales` text DEFAULT NULL,
  `medicamentos` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `enfermedades` text DEFAULT NULL,
  `tipo_sangre` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `fecha_actualizacion` date NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ficha_medica`),
  UNIQUE KEY `id_estudiante` (`id_estudiante`),
  KEY `idx_ficha_medica_fecha` (`fecha_actualizacion`),
  CONSTRAINT `fk_ficha_medica_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=484 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formacion_academica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `formacion_academica` (
  `id_formacion_academica` int(11) NOT NULL AUTO_INCREMENT,
  `id_empleado` int(11) NOT NULL,
  `id_titulo` int(11) NOT NULL,
  `id_grado_academico` int(11) NOT NULL,
  `id_institucion` int(11) NOT NULL,
  `fecha_obtencion` date NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_formacion_academica`),
  KEY `idx_formacion_academica_empleado` (`id_empleado`),
  KEY `idx_formacion_academica_titulo` (`id_titulo`),
  KEY `idx_formacion_academica_grado` (`id_grado_academico`),
  KEY `idx_formacion_academica_institucion` (`id_institucion`),
  KEY `idx_formacion_academica_estado` (`estado`),
  KEY `idx_formacion_academica_fecha` (`fecha_obtencion`),
  CONSTRAINT `fk_formacion_academica_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_grado` FOREIGN KEY (`id_grado_academico`) REFERENCES `grado_academico` (`id_grado_academico`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `institucion` (`id_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_formacion_academica_titulo` FOREIGN KEY (`id_titulo`) REFERENCES `titulo` (`id_titulo`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grado` (
  `id_grado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_grado`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_grado_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grado_academico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grado_academico` (
  `id_grado_academico` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_grado_academico`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inscripcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscripcion` (
  `id_inscripcion` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `id_anio_escolar` int(11) NOT NULL,
  `numero_inscripcion` int(11) NOT NULL,
  `ult_grado_cursado` varchar(20) DEFAULT NULL,
  `es_primer_ingreso` tinyint(1) NOT NULL DEFAULT 1,
  `motivo_egreso` varchar(100) DEFAULT NULL,
  `anio_escolar_pre` varchar(9) DEFAULT NULL,
  `id_institucion` int(11) NOT NULL,
  PRIMARY KEY (`id_inscripcion`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  UNIQUE KEY `uk_inscripcion_estudiante` (`id_estudiante`),
  UNIQUE KEY `uk_inscripcion_anio_folio` (`id_anio_escolar`,`numero_inscripcion`),
  KEY `idx_inscripcion_institucion` (`id_institucion`),
  KEY `idx_inscripcion_estructura` (`id_estructura_academica`),
  KEY `idx_inscripcion_estudiante` (`id_estudiante`),
  CONSTRAINT `fk_inscripcion_anio_escolar` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `institucion` (`id_institucion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_inscripcion_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19168 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `institucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `institucion` (
  `id_institucion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `tipo` varchar(32) NOT NULL,
  PRIMARY KEY (`id_institucion`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_institucion_tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=725 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `matricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matricula` (
  `id_matricula` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `fecha_matricula` date NOT NULL,
  `id_anio_escolar` int(11) NOT NULL,
  `id_estructura_academica` int(11) NOT NULL,
  `tipo` enum('INSCRIPCION','RATIFICACION','RETIRO','EGRESO') NOT NULL,
  `motivo_anticipo` varchar(200) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_matricula`),
  UNIQUE KEY `uk_matricula_estudiante_anio_tipo` (`id_estudiante`,`id_anio_escolar`,`tipo`),
  KEY `idx_matricula_estudiante` (`id_estudiante`),
  KEY `idx_matricula_fecha` (`fecha_matricula`),
  KEY `idx_matricula_anio` (`id_anio_escolar`),
  KEY `idx_matricula_estructura` (`id_estructura_academica`),
  KEY `idx_matricula_tipo` (`tipo`),
  CONSTRAINT `fk_matricula_anio` FOREIGN KEY (`id_anio_escolar`) REFERENCES `anio_escolar` (`id_anio_escolar`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_estructura` FOREIGN KEY (`id_estructura_academica`) REFERENCES `estructura_academica` (`id_estructura_academica`) ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20840 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_matricula_transicion
BEFORE INSERT ON matricula
FOR EACH ROW
BEGIN
    DECLARE v_previo   VARCHAR(20) DEFAULT NULL;
    DECLARE v_previo_anio INT DEFAULT NULL;
    DECLARE v_ordinal  INT DEFAULT NULL;

    
    
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

    
    

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    IF NEW.tipo = 'RATIFICACION' AND (
        SELECT an2.anio_inicio FROM anio_escolar an2
         WHERE an2.id_anio_escolar = NEW.id_anio_escolar
    ) <= (
        SELECT an1.anio_inicio FROM anio_escolar an1
         WHERE an1.id_anio_escolar = v_previo_anio
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La ratificacion debe ir en un anio escolar posterior al de la anterior.';
    END IF;

    IF NEW.tipo IN ('RETIRO', 'EGRESO') AND (
        SELECT an3.anio_inicio FROM anio_escolar an3
         WHERE an3.id_anio_escolar = NEW.id_anio_escolar
    ) < (
        SELECT an4.anio_inicio FROM anio_escolar an4
         WHERE an4.id_anio_escolar = v_previo_anio
    ) THEN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `motivo_retiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `motivo_retiro` (
  `id_motivo_retiro` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_motivo_retiro`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_motivo_retiro_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `municipio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipio` (
  `id_municipio` int(11) NOT NULL AUTO_INCREMENT,
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_municipio`),
  UNIQUE KEY `uk_municipio_estado_nombre` (`id_estado`,`nombre`),
  KEY `idx_municipio_nombre` (`nombre`),
  CONSTRAINT `fk_municipio_estado` FOREIGN KEY (`id_estado`) REFERENCES `estado` (`id_estado`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=371 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nivel_instruccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nivel_instruccion` (
  `id_nivel_instruccion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_nivel_instruccion`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nacionalidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nacionalidad` (
  `id_nacionalidad` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_nacionalidad`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/* Los valores iniciales. Venezolano tiene id 1 a propósito: es el valor por defecto del
   formulario del estudiante y no se depende del AUTO_INCREMENT para eso. */
INSERT INTO `nacionalidad` (`id_nacionalidad`, `nombre`) VALUES
  (1, 'Venezolano'),
  (2, 'Colombiano'),
  (3, 'Brasileño'),
  (4, 'Peruano'),
  (5, 'Ecuatoriano'),
  (6, 'Boliviano'),
  (7, 'Argentino'),
  (8, 'Chileno'),
  (9, 'Paraguayo'),
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
DROP TABLE IF EXISTS `ocupacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ocupacion` (
  `id_ocupacion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_ocupacion`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parentesco`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parentesco` (
  `id_parentesco` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_parentesco`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `parroquia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `parroquia` (
  `id_parroquia` int(11) NOT NULL AUTO_INCREMENT,
  `id_municipio` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_parroquia`),
  UNIQUE KEY `uk_parroquia_municipio_nombre` (`id_municipio`,`nombre`),
  KEY `idx_parroquia_nombre` (`nombre`),
  CONSTRAINT `fk_parroquia_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipio` (`id_municipio`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona` (
  `id_persona` int(11) NOT NULL AUTO_INCREMENT,
  `foto` varchar(255) DEFAULT NULL,
  `tipo_documento` enum('V','E','CE','P') NOT NULL,
  `numero_documento` varchar(20) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `primer_nombre` varchar(50) NOT NULL,
  `segundo_nombre` varchar(50) DEFAULT NULL,
  `primer_apellido` varchar(50) NOT NULL,
  `segundo_apellido` varchar(50) DEFAULT NULL,
  `sexo` enum('F','M') NOT NULL,
  `id_estado_nacimiento` int(11) DEFAULT NULL,
  `id_parroquia_nacimiento` int(11) DEFAULT NULL,
  `id_nacionalidad` int(11) DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_persona`),
  UNIQUE KEY `numero_documento` (`numero_documento`),
  KEY `idx_persona_nacimiento` (`id_estado_nacimiento`,`id_parroquia_nacimiento`),
  KEY `idx_persona_nacionalidad` (`id_nacionalidad`),
  CONSTRAINT `fk_persona_estado_nacimiento` FOREIGN KEY (`id_estado_nacimiento`) REFERENCES `estado` (`id_estado`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_persona_parroquia_nacimiento` FOREIGN KEY (`id_parroquia_nacimiento`) REFERENCES `parroquia` (`id_parroquia`) ON DELETE SET NULL ON UPDATE CASCADE
  CONSTRAINT `fk_persona_nacionalidad` FOREIGN KEY (`id_nacionalidad`) REFERENCES `nacionalidad` (`id_nacionalidad`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24449 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persona_estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persona_estudiante` (
  `id_persona_estudiante` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_estudiante` int(11) NOT NULL,
  `es_representante_legal` tinyint(1) NOT NULL DEFAULT 0,
  `es_autorizado_retiro` tinyint(1) NOT NULL DEFAULT 0,
  `vive_con_estudiante` tinyint(1) NOT NULL DEFAULT 0,
  `estado_vital` enum('VIVO','FALLECIDO') NOT NULL DEFAULT 'VIVO',
  `estado_relacion` enum('ACTIVO','INACTIVO','SUSPENDIDO') NOT NULL DEFAULT 'ACTIVO',
  `id_parentesco` int(11) DEFAULT NULL,
  `id_nivel_instruccion` int(11) DEFAULT NULL,
  `id_ocupacion` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_persona_estudiante`),
  UNIQUE KEY `uk_relacion_persona_estudiante` (`id_persona`,`id_estudiante`),
  KEY `idx_relacion_estudiante` (`id_estudiante`),
  KEY `idx_relacion_estado` (`estado_vital`),
  KEY `idx_relacion_estado_rel` (`estado_relacion`),
  KEY `idx_relacion_parentesco` (`id_parentesco`),
  KEY `idx_relacion_nivel_instruccion` (`id_nivel_instruccion`),
  KEY `idx_relacion_ocupacion` (`id_ocupacion`),
  CONSTRAINT `fk_relacion_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_nivel_instruccion` FOREIGN KEY (`id_nivel_instruccion`) REFERENCES `nivel_instruccion` (`id_nivel_instruccion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_ocupacion` FOREIGN KEY (`id_ocupacion`) REFERENCES `ocupacion` (`id_ocupacion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_parentesco` FOREIGN KEY (`id_parentesco`) REFERENCES `parentesco` (`id_parentesco`) ON UPDATE CASCADE,
  CONSTRAINT `fk_relacion_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2073 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recuperacion_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `recuperacion_acceso` (
  `id_recuperacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `estado_recuperacion` enum('PENDIENTE','APROVADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
  `origen` enum('SOLICITUD','ADMIN') NOT NULL DEFAULT 'SOLICITUD',
  `fecha_atencion` datetime DEFAULT NULL,
  `id_admin_atendio` int(11) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_recuperacion`),
  KEY `idx_recuperacion_estado` (`estado_recuperacion`),
  KEY `idx_recuperacion_usuario` (`id_usuario`),
  KEY `idx_recuperacion_admin` (`id_admin_atendio`),
  KEY `idx_recuperacion_fechas` (`fecha_solicitud`,`fecha_atencion`),
  CONSTRAINT `fk_recuperacion_admin` FOREIGN KEY (`id_admin_atendio`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_recuperacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `retiro` (
  `id_retiro` int(11) NOT NULL AUTO_INCREMENT,
  `id_matricula` int(11) NOT NULL,
  `id_motivo_retiro` int(11) NOT NULL,
  `estado_solicitud` enum('PENDIENTE','APROBADO','RECHAZADO') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_solicitud` datetime DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `id_usuario_registro` int(11) DEFAULT NULL,
  `id_usuario_resolucion` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_retiro`),
  UNIQUE KEY `id_matricula` (`id_matricula`),
  KEY `idx_retiro_motivo` (`id_motivo_retiro`),
  KEY `idx_retiro_estado` (`estado_solicitud`),
  KEY `fk_retiro_usuario_registro` (`id_usuario_registro`),
  KEY `fk_retiro_usuario_resolucion` (`id_usuario_resolucion`),
  CONSTRAINT `fk_retiro_matricula` FOREIGN KEY (`id_matricula`) REFERENCES `matricula` (`id_matricula`) ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_motivo` FOREIGN KEY (`id_motivo_retiro`) REFERENCES `motivo_retiro` (`id_motivo_retiro`) ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_usuario_registro` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_retiro_usuario_resolucion` FOREIGN KEY (`id_usuario_resolucion`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=573 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `seccion` (
  `id_seccion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(10) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_seccion`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_seccion_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `talla_uniforme`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `talla_uniforme` (
  `id_talla_uniforme` int(11) NOT NULL AUTO_INCREMENT,
  `id_estudiante` int(11) NOT NULL,
  `talla_camisa` varchar(10) NOT NULL,
  `talla_pantalon` varchar(10) NOT NULL,
  `talla_zapato` varchar(10) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_actualizacion` date NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_talla_uniforme`),
  UNIQUE KEY `id_estudiante` (`id_estudiante`),
  KEY `idx_talla_uniforme_fecha` (`fecha_actualizacion`),
  CONSTRAINT `fk_talla_uniforme_estudiante` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiante` (`id_estudiante`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipo_asignacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_asignacion` (
  `id_tipo_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_asignacion`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_tipo_asignacion_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipo_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_documento` (
  `id_tipo_documento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_documento`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipo_institucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_institucion` (
  `id_tipo_institucion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_tipo_institucion`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `titulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `titulo` (
  `id_titulo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `id_grado_academico` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_titulo`),
  UNIQUE KEY `uk_titulo_nombre_grado` (`nombre`,`id_grado_academico`),
  KEY `fk_titulo_grado_academico` (`id_grado_academico`),
  CONSTRAINT `fk_titulo_grado_academico` FOREIGN KEY (`id_grado_academico`) REFERENCES `grado_academico` (`id_grado_academico`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `turno` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_turno`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_turno_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `id_persona` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `password_provisional` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) NOT NULL,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `intentos_fallidos` smallint(6) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `id_persona` (`id_persona`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  KEY `fk_usuario_rol` (`id_rol`),
  CONSTRAINT `fk_usuario_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1869 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

