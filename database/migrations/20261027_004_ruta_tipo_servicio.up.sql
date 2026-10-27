-- SFATSA | 20261027_004_ruta_tipo_servicio.up.sql
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
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('ruta','tipo_servicio') AND ENGINE='InnoDB')<>2 OR
       (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tipo_servicio' AND COLUMN_NAME='id_tipo_servicio' AND COLUMN_TYPE='tinyint(3) unsigned')<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 UP: esquema origen inesperado.';
    END IF;
    IF EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ruta' AND COLUMN_NAME='id_tipo_servicio') OR
       EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ruta' AND INDEX_NAME='fk_ruta_tipo_servicio') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 UP: columna o indice ya existen; no repetir.';
    END IF;
    -- Version final: el usuario autorizo retirar SOLO las rutas de prueba.
    -- No inferir un tipo para rutas existentes ni cambiar sus datos automaticamente.
    IF EXISTS(SELECT 1 FROM ruta) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='004 UP: ruta debe estar vacia tras limpieza autorizada y respaldada.';
    END IF;
    ALTER TABLE ruta
        ADD COLUMN id_tipo_servicio TINYINT UNSIGNED NOT NULL,
        ADD KEY fk_ruta_tipo_servicio (id_tipo_servicio),
        ADD CONSTRAINT fk_ruta_tipo_servicio FOREIGN KEY (id_tipo_servicio)
            REFERENCES tipo_servicio(id_tipo_servicio) ON DELETE RESTRICT ON UPDATE RESTRICT;
END$$
DELIMITER ;
