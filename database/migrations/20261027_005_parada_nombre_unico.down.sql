-- SFATSA | 20261027_005_parada_nombre_unico.down.sql
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
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='parada' AND INDEX_NAME='nombre' AND COLUMN_NAME='nombre_normalizado' AND NON_UNIQUE=0)<>1 OR
       (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='parada' AND COLUMN_NAME='nombre_normalizado' AND IS_GENERATED='ALWAYS')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='005 DOWN: restriccion esperada no encontrada; detener.';
    END IF;
    ALTER TABLE parada DROP INDEX nombre, DROP COLUMN nombre_normalizado;
END$$
DELIMITER ;
