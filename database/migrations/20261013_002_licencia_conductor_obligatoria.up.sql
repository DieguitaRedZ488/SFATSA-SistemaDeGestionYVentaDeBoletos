-- SFATSA | Migracion 20261013_002 | UP
-- Estado: PREPARADA, NO EJECUTADA. No aplicar sin autorizacion explicita.
-- Objetivo: requerir numero_licencia conservando VARCHAR(40), charset,
-- collation e indice UNIQUE existentes.
--
-- PRECONDICIONES OBLIGATORIAS:
-- 1. Autorizacion explicita para ejecutar este DDL en el ambiente objetivo.
-- 2. Respaldo completo, restaurable y probado en aislamiento.
-- 3. Todos los escritores suspendidos; mantenerlos suspendidos hasta verificar.
-- 4. Confirmar que conductor.numero_licencia es VARCHAR(40), nullable,
--    utf8mb4/utf8mb4_uca1400_ai_ci y conserva el indice UNIQUE numero_licencia.
-- 5. Confirmar que no hay valores NULL, vacios/solo espacios ni duplicados
--    conforme a la collation existente. Detenerse si existe alguno.
-- 6. Verificar que no hay una aplicacion previa o cambio de esquema concurrente.
--
-- ALTER TABLE puede esperar bloqueos de metadatos y causa commit implicito.
-- Aplicar esta sentencia una vez, revisar errores/avisos y verificar el esquema.
-- Ante un resultado incierto, no reejecutar a ciegas: inspeccionar antes.

ALTER TABLE sfatza.conductor
    MODIFY COLUMN numero_licencia
        VARCHAR(40)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL;
