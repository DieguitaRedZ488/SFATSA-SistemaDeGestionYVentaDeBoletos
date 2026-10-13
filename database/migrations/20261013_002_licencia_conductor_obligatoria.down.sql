-- SFATSA | Migracion 20261013_002 | DOWN
-- Estado: PREPARADA, NO EJECUTADA. Requiere autorizacion propia.
-- Revierte unicamente NOT NULL; no elimina ni transforma datos.
--
-- PRECONDICIONES:
-- 1. Autorizacion explicita para esta reversa y respaldo restaurable.
-- 2. Confirmar que la columna tiene el esquema de UP de esta migracion.
-- 3. Todos los escritores suspendidos durante el DDL.
-- 4. Verificar si hubo cambios posteriores y si la aplicacion que acepte
--    licencias NULL esta autorizada. La aplicacion de SFATSA puede seguir
--    exigiendo licencia incluso cuando la columna vuelve a permitir NULL.
--
-- Aplicar una vez y verificar metadata, indice UNIQUE y datos. No restaurar
-- ciegamente un respaldo sobre el ambiente activo: podria perder cambios
-- posteriores. ALTER TABLE causa commit implicito.

ALTER TABLE sfatza.conductor
    MODIFY COLUMN numero_licencia
        VARCHAR(40)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NULL
        DEFAULT NULL;
