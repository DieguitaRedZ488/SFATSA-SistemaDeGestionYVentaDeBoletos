-- SFATSA | Migracion 20261027_003 | DOWN
-- Estado: PREPARADA, NO EJECUTADA. Requiere autorizacion propia.
-- Leer README.md, seccion 20261027_003, antes de ejecutar.
-- MariaDB 12.0.2. Cliente que interprete DELIMITER; no ejecutar por PDO.
-- Seleccionar explicitamente sfatza o una base aislada cuyo nombre comience
-- por sfatza_s02_estados_ensayo_. No hay USE ni destino oculto en este archivo.
-- Operador: respaldo restaurado/ensayado, esquema completo y FK revisados,
-- sin escritores ni cambios de esquema concurrentes hasta verificar el final.
-- La comprobacion SELECT no cierra la carrera antes del ALTER: suspender
-- TODOS los escritores es una precondicion operativa, no una garantia del bloque.
-- No convertir, eliminar ni reemplazar filas; tabla mantenimiento intacta.
-- ALTER puede reconstruir la tabla, bloquear y causar commits implicitos.
-- COPY/EXCLUSIVE prioriza un ensayo predecible, no disponibilidad continua.
-- WAIT/NO WAIT no es valido en este bloque en MariaDB 12.0.2.
-- El operador limita la espera con lock_wait_timeout en una sesion dedicada.
-- El script no cambia variables de sesion; timeout no limita la duracion COPY.
-- No desactivar controles ni usar --force/--ignore. No reintentar a ciegas.
-- DOWN restaura el tipo, no datos historicos ni autorizacion de mantenimiento.

DELIMITER $$
BEGIN NOT ATOMIC
    IF DATABASE() IS NULL OR (
        DATABASE() <> 'sfatza'
        AND LEFT(DATABASE(), CHAR_LENGTH('sfatza_s02_estados_ensayo_'))
            <> 'sfatza_s02_estados_ensayo_'
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: seleccionar una base destino permitida.';
    END IF;

    IF LOCATE('MariaDB', VERSION()) = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: requiere MariaDB; revisar version y ensayo.';
    END IF;

    IF @@autocommit <> 1 OR @@in_transaction <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: usar sesion dedicada sin transaccion abierta.';
    END IF;

    IF FIND_IN_SET('STRICT_TRANS_TABLES', @@sql_mode) = 0
        AND FIND_IN_SET('STRICT_ALL_TABLES', @@sql_mode) = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: modo SQL estricto requerido.';
    END IF;

    IF @@foreign_key_checks <> 1 OR @@check_constraint_checks <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: FK y CHECK deben permanecer habilitados.';
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehiculo'
            AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB') <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: vehiculo debe existir como tabla InnoDB.';
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehiculo'
            AND COLUMN_NAME = 'estado'
            AND BINARY COLUMN_TYPE = BINARY 'enum(''ACTIVO'',''INACTIVO'')'
            AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = QUOTE('ACTIVO')
            AND CHARACTER_SET_NAME = 'utf8mb4'
            AND COLLATION_NAME = 'utf8mb4_uca1400_ai_ci'
            AND EXTRA = '' AND COLUMN_COMMENT = '') <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: definicion origen distinta o migracion ya aplicada; detener.';
    END IF;

    IF EXISTS (SELECT 1 FROM vehiculo
        WHERE estado IS NULL
            OR BINARY CAST(estado AS CHAR) NOT IN (BINARY 'ACTIVO', BINARY 'INACTIVO')) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'S02-003 DOWN: hay estados incompatibles; no se modificaron registros.';
    ELSE
        -- Unico DDL de este archivo; no sacarlo de la rama protegida.
        ALTER TABLE vehiculo
            MODIFY COLUMN estado
                ENUM('ACTIVO','MANTENIMIENTO','INACTIVO')
                CHARACTER SET utf8mb4
                COLLATE utf8mb4_uca1400_ai_ci
                NOT NULL DEFAULT 'ACTIVO',
            ALGORITHM = COPY,
            LOCK = EXCLUSIVE;
    END IF;
END$$
DELIMITER ;
