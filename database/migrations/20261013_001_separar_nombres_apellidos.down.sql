-- SFATSA | Migracion 20261013_001 | DOWN CONDICIONADO
-- Estado: PREPARADA, NO EJECUTADA. NO es un rollback automatico o transaccional.
-- ADVERTENCIA DE PERDIDA DE DATOS: DROP COLUMN apellidos elimina sus valores.
-- Utilizar este DOWN despues de registrar informacion puede perder apellidos.
-- PROHIBIDO usarlo con registros existentes en usuario o conductor.
-- No elimina filas ni las vacia para cumplir las precondiciones.
-- Leer database/migrations/README.md; no ejecutar como lote desatendido.
--
-- PRECONDICIONES OBLIGATORIAS, verificadas por el operador:
-- 1. Autorizacion explicita para esta reversion, respaldo restaurable y
--    restauracion comprobada en aislamiento. La autorizacion de UP no autoriza DOWN.
-- 2. TODOS los escritores suspendidos; COUNT(*) = 0 en AMBAS tablas.
-- 3. Revision administrativa de dependencias de nombres/apellidos y de cambios
--    posteriores. Detenerse ante nuevos indices, claves u objetos dependientes.
-- 4. La tabla que se revierta esta realmente migrada: nombre ausente, nombres y
--    apellidos VARCHAR(120) NOT NULL sin DEFAULT, utf8mb4/utf8mb4_uca1400_ai_ci,
--    con las demas columnas y claves originales conservadas.
-- 5. Si UP fue parcial, evaluar solo la sentencia de la tabla realmente migrada;
--    no ejecutar este archivo completo ni intentar revertir una tabla original.
--
-- Los SELECT previos no bloquean escritores ni abortan automaticamente el DDL.
-- DETENERSE si hay datos, discrepancias o una condicion incumplida.
-- Con datos, conservarlos y preparar otra recuperacion con autorizacion propia;
-- no concatenar/truncar automaticamente para regresar a nombre VARCHAR(120).
-- Una concatenacion futura puede alcanzar 241 caracteres y no caber en nombre.
-- ALTER TABLE provoca commits implicitos: ROLLBACK no revierte estas sentencias.
-- Aplicar UNA sentencia por vez en orden inverso a UP: conductor, verificar;
-- usuario, verificar. Ante fallo parcial, detenerse y revisar el estado real.
-- Se conservan identificadores, AUTO_INCREMENT, PK, UNIQUE, FK y demas columnas.
-- No se alteran rol ni otras tablas.

ALTER TABLE sfatza.conductor
    DROP COLUMN apellidos,
    CHANGE COLUMN nombres nombre
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL;

-- PUNTO DE CONTROL: verificar conductor y revalidar las condiciones de usuario.
-- No continuar si la tabla usuario no esta migrada y vacia.

ALTER TABLE sfatza.usuario
    DROP COLUMN apellidos,
    CHANGE COLUMN nombres nombre
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL;
