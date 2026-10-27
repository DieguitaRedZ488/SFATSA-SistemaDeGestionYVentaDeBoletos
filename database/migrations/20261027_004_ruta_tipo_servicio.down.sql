-- SFATSA | 20261027_004_ruta_tipo_servicio.down.sql
-- PREPARADA, NO EJECUTADA. Requiere autorizacion, respaldo restaurable y ventana sin escritores.
-- No cambia filas de negocio. ALTER causa commits implicitos y puede bloquear.
-- Configurar lock_wait_timeout en cliente; no usar --force ni desactivar restricciones.
DELIMITER $$
BEGIN NOT ATOMIC
    IF DATABASE() IS NULL OR (DATABASE()<>'sfatza' AND LEFT(DATABASE(),CHAR_LENGTH('sfatza_s02_hu04_ensayo_'))<>'sfatza_s02_hu04_ensayo_') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='HU04: seleccionar explicitamente una base permitida.';
    END IF;
    IF LOCATE('MariaDB',VERSION())=0 OR @@autocommit<>1 OR @@in_transaction<>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='HU04: requiere MariaDB y sesion dedicada sin transaccion.';
    END IF;
    IF @@foreign_key_checks<>1 OR @@check_constraint_checks<>1 OR
       (FIND_IN_SET('STRICT_ALL_TABLES',@@sql_mode)=0 AND FIND_IN_SET('STRICT_TRANS_TABLES',@@sql_mode)=0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='HU04: modo estricto, FK y CHECK deben estar habilitados.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ruta' AND CONSTRAINT_NAME='fk_ruta_tipo_servicio' AND COLUMN_NAME='id_tipo_servicio' AND REFERENCED_TABLE_NAME='tipo_servicio' AND REFERENCED_COLUMN_NAME='id_tipo_servicio')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 DOWN: relacion esperada no encontrada; detener.';
    END IF;
    IF EXISTS(SELECT 1 FROM ruta WHERE id_tipo_servicio IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 DOWN: hay asociaciones guardadas; no se perderan automaticamente.';
    END IF;
    ALTER TABLE ruta DROP FOREIGN KEY fk_ruta_tipo_servicio,
        DROP INDEX fk_ruta_tipo_servicio, DROP COLUMN id_tipo_servicio;
END$$
DELIMITER ;
