-- SFATSA | Migracion 20261013_001 | UP
-- Estado: PREPARADA, NO EJECUTADA. Preparar el archivo no autoriza ejecutarlo.
-- Destino autorizado para revision: sfatza, MariaDB 12.0.2, tablas InnoDB.
-- Leer database/migrations/README.md antes de cualquier aplicacion.
--
-- PRECONDICIONES OBLIGATORIAS, verificadas por el operador antes del primer ALTER:
-- 1. Autorizacion explicita para ejecutar DDL; autorizacion de archivos no basta.
-- 2. usuario y conductor vacios (COUNT(*) = 0 en ambas tablas).
-- 3. nombre existe en ambas como VARCHAR(120) NOT NULL, sin DEFAULT,
--    CHARACTER SET utf8mb4, COLLATE utf8mb4_uca1400_ai_ci.
--    nombres y apellidos estan ausentes; las demas columnas/claves coinciden.
-- 4. Respaldo completo y restaurable, con restauracion comprobada en aislamiento.
-- 5. Revision administrativa de vistas, triggers, rutinas, eventos y dependencias.
--    El diagnostico previo tuvo visibilidad limitada; no descarta objetos ocultos.
-- 6. Suspender TODOS los escritores y trabajos de fondo hasta verificar el final.
--
-- DETENERSE sin ejecutar ALTER si hay registros o alguna condicion incumplida.
-- Los SELECT de precondiciones del README son comprobaciones, no bloqueos,
-- no impiden escrituras concurrentes y no abortan automaticamente este archivo.
-- Este archivo no contiene un ejecutor ni comprobaciones automaticas.
-- Aplicar UNA sentencia por vez: usuario, verificar; conductor, verificar.
-- No enviar el archivo completo como lote desatendido ni reejecutarlo a ciegas.
-- ALTER TABLE provoca commits implicitos. Los dos ALTER no son una transaccion
-- conjunta y no se deshacen mediante ROLLBACK. Ante fallo parcial, detenerse
-- y seguir la recuperacion supervisada del README; DOWN no es automatico.
--
-- Se conservan identificadores, AUTO_INCREMENT, PK, UNIQUE, FK y demas columnas.
-- No se alteran rol ni otras tablas ni se crea una columna de nombre completo.

ALTER TABLE sfatza.usuario
    CHANGE COLUMN nombre nombres
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL,
    ADD COLUMN apellidos
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL
        AFTER nombres;

-- PUNTO DE CONTROL: verificar usuario y revalidar las condiciones de conductor.
-- Continuar unicamente con escritores suspendidos y todas las condiciones validas.

ALTER TABLE sfatza.conductor
    CHANGE COLUMN nombre nombres
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL,
    ADD COLUMN apellidos
        VARCHAR(120)
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_uca1400_ai_ci
        NOT NULL
        AFTER nombres;
