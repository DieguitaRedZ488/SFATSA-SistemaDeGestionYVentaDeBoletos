# Migraciones de SFATSA

## Estado y alcance autorizado

Migración **20261013_001: APLICADA Y VERIFICADA MANUALMENTE, SEGÚN CONFIRMACIÓN DEL USUARIO**. usuario y conductor tienen nombres y apellidos VARCHAR(120) NOT NULL, sin la columna antigua nombre, y sfatza conserva 22 claves foráneas. Ambas tablas estaban vacías antes del cambio. El usuario confirmó respaldo privado creado, restauración exitosa y ensayo de ALTER en sfatza_restore_test_20261013 antes de aplicarlos a sfatza; Figma original no fue modificado.

El estado de 20261013_001 indicado aquí corresponde a la confirmación del responsable del proyecto. El inicializador para instalaciones nuevas y el estado de 20261013_002 se describen más adelante en «Esquema inicial para instalaciones nuevas».

Archivos:

- [UP](20261013_001_separar_nombres_apellidos.up.sql): renombrar nombre a nombres y agregar apellidos en usuario y conductor.
- [DOWN condicionado](20261013_001_separar_nombres_apellidos.down.sql): regresar al esquema anterior únicamente si ambas tablas siguen vacías y se cumplen todas sus condiciones.

**No reejecutar UP en sfatza: el cambio ya está aplicado.** Los scripts UP/DOWN se mantienen idénticos a la preparación revisada; sus comentarios PREPARADA, NO EJECUTADA son históricos y no representan el estado operativo de esta revisión. El estado de aplicación se registra en este README y los documentos del sprint, sin editar los artefactos SQL después de su aplicación reportada.

Cualquier nueva ejecución, incluida una eventual reversión, necesita autorización explícita propia. Los archivos de 20261013_001 contienen DDL y comentarios, no un ejecutor ni verificaciones automáticas. La migración 20261027_003, descrita al final, sí incluye guardas automáticas en un bloque anónimo. No ejecutarlos como lotes desatendidos, al iniciar la aplicación o al correr pruebas. No se informó que DOWN hubiera sido ensayado o aplicado.

## Propósito y contrato

Destino acordado: sfatza, MariaDB 12.0.2, InnoDB, charset utf8mb4 y collation utf8mb4_uca1400_ai_ci. UP contiene explícitamente charset/collation y ambos VARCHAR(120) NOT NULL; la inspección de solo lectura del Prompt 22 confirmó esas columnas en `usuario` y `conductor`, junto con los metadatos completos usados para el inicializador. Los detalles y la procedencia de la aplicación manual de 001 siguen siendo los registrados por el usuario en EV-08.

## Esquema inicial para instalaciones nuevas

`../install.sql` es un inicializador separado de las migraciones históricas. La lectura de `sfatza` mediante `SHOW CREATE TABLE` e `information_schema` confirmó 15 tablas InnoDB, charset/collation `utf8mb4`/`utf8mb4_uca1400_ai_ci` y 22 columnas de clave foránea con reglas `RESTRICT` para UPDATE y DELETE. El archivo inicial reproduce esas tablas, índices, claves, checks y tipos; elimina los valores incidentales de `AUTO_INCREMENT` observados en la base existente.

Para que una instalación vacía nazca con el contrato actual, el inicializador incluye el resultado de 20261013_001 (`usuario` y `conductor` con `nombres` y `apellidos`) y el objetivo de 20261013_002 (`conductor.numero_licencia VARCHAR(40) NOT NULL`, conservando el índice UNIQUE). También inserta únicamente los catálogos consultados por nombre/estado: roles, métodos de pago y tipos de servicio. No inserta usuarios, hashes, conductores ni datos operativos. El inicializador incorpora también el objetivo de 20261027_003: vehiculo.estado solo ACTIVO/INACTIVO. En una instalación nueva no se ejecutan estas migraciones después del inicializador.

El inicializador está destinado exclusivamente a una base nueva que aún no exista. No es seguro usarlo para actualizar, completar o restaurar una instalación existente. Para bases existentes, 20261013_001 está reportada como aplicada en `sfatza`; la consulta de solo lectura de esta preparación observó `numero_licencia` nullable, por lo que 20261013_002 permanece sin aplicar. Seguir únicamente el procedimiento correspondiente más abajo y obtener autorización explícita antes de cualquier DDL.

La lectura de metadatos no consultó filas de `usuario` ni `conductor` ni modificó la base. Las filas leídas se limitaron a nombres/estados de los tres catálogos indicados.

| Tabla | Contrato anterior, histórico | Contrato aplicado según el usuario |
| --- | --- | --- |
| usuario | nombre VARCHAR(120) NOT NULL | nombres VARCHAR(120) NOT NULL y apellidos VARCHAR(120) NOT NULL |
| conductor | nombre VARCHAR(120) NOT NULL | nombres VARCHAR(120) NOT NULL y apellidos VARCHAR(120) NOT NULL |

Las columnas objetivo conservan charset/collation explícitos y no tienen DEFAULT. UP renombra la columna existente y añade únicamente apellidos después de nombres. No añade una columna persistida de nombre completo ni modifica otros atributos, identificadores, AUTO_INCREMENT, PK, UNIQUE o FK. rol.nombre y todas las otras tablas quedan fuera de la migración.

La aplicación futura captura Nombre(s) y Apellidos por separado. Ambos son obligatorios, rechazan omisión, NULL, vacío o solo espacios y admiten como máximo 120 caracteres UTF-8 cada uno. Validar en servidor y no truncar; NOT NULL en SQL no impide cadenas vacías. Recortes, espacios internos y otras normalizaciones no aprobadas siguen pendientes en DEC-07. Estos archivos no añaden CHECK ni implementan validación PHP.

Figma Make permanece como referencia visual de solo lectura; no se cambia el archivo ni se adopta React/Tailwind como arquitectura de SFATSA.

## Precondiciones de UP — referencia histórica

El siguiente procedimiento describe el estado anterior requerido para aplicar UP; no es una instrucción para volver a ejecutarlo en la base original ya migrada. En sfatza ahora existen nombres/apellidos y nombre está ausente, por lo que las precondiciones originales ya no se cumplen. Para un eventual entorno nuevo autorizado, comprobar todas antes del primer ALTER y mantenerlas durante la operación:

1. Autorización explícita de ejecución y operador con permisos DDL apropiados. Utilizar una cuenta administrativa autorizada para la migración; no ampliar automáticamente los permisos de la cuenta de aplicación.
2. Base y versión acordadas, tablas InnoDB y definición original compatible con la auditoría. nombre debe existir en ambas tablas como VARCHAR(120) NOT NULL, sin DEFAULT y con charset/collation confirmados; nombres y apellidos deben estar ausentes.
3. COUNT(*) = 0 tanto en usuario como en conductor. Los conteos cero del diagnóstico del 2026-10-13 son históricos y deben repetirse. Si cualquiera tiene datos, detenerse antes del primer ALTER y presentar otra propuesta; no borrar datos ni dividir nombres completos automáticamente.
4. Respaldo completo y restaurable, con restauración ensayada en un entorno aislado y autorizado.
5. Revisión administrativa de dependencias SQL y de consumidores externos.
6. Suspensión efectiva de todos los escritores, incluidos procesos de fondo, importadores, pruebas y sesiones administrativas que pudieran insertar o modificar datos. Mantenerla hasta finalizar las verificaciones o la recuperación.

Ante cualquier discrepancia, falta de permiso, respaldo no verificado o escritor activo, no ejecutar DDL. Si una tabla ya muestra nombres/apellidos, puede existir una ejecución previa o parcial: inspeccionar su estado y no reejecutar UP a ciegas.

### Consultas de lectura para comprobar el estado

Estas consultas son una referencia de lectura del procedimiento original; **no se ejecutaron en esta revisión**. Para revisar el estado actual migrado, esperar nombres/apellidos y ausencia de nombre; usuario tiene siete columnas y conductor seis. Los conteos cero corresponden al estado anterior confirmado, no a un nuevo conteo posterior.

```sql
SELECT DATABASE() AS base_actual, VERSION() AS version;

SELECT 'usuario' AS tabla, COUNT(*) AS registros
FROM sfatza.usuario
UNION ALL
SELECT 'conductor', COUNT(*)
FROM sfatza.conductor;

SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE,
       IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME,
       COLLATION_NAME, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sfatza'
  AND TABLE_NAME IN ('usuario', 'conductor', 'rol')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX,
       COLUMN_NAME, SUB_PART, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'sfatza'
  AND TABLE_NAME IN ('usuario', 'conductor')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

SELECT k.TABLE_SCHEMA, k.TABLE_NAME, k.CONSTRAINT_NAME,
       k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
       r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS k
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
 AND r.TABLE_NAME = k.TABLE_NAME
WHERE (k.TABLE_SCHEMA = 'sfatza'
       AND k.TABLE_NAME IN ('usuario', 'conductor'))
   OR (k.REFERENCED_TABLE_SCHEMA = 'sfatza'
       AND k.REFERENCED_TABLE_NAME IN ('usuario', 'conductor'))
ORDER BY k.TABLE_SCHEMA, k.TABLE_NAME, k.CONSTRAINT_NAME,
         k.ORDINAL_POSITION;

SELECT TABLE_NAME, AUTO_INCREMENT, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'sfatza'
  AND TABLE_NAME IN ('usuario', 'conductor', 'rol')
ORDER BY TABLE_NAME;
```

El operador debe comprobar los resultados y detenerse si no cumplen las precondiciones. **Un SELECT previo no bloquea escrituras concurrentes y no hace que el cliente aborte el ALTER siguiente.** Una transacción que contenga el conteo no convierte estos DDL en una migración transaccional. La ausencia de carreras exige controlar todos los escritores fuera de estas consultas.

## Respaldo, restauración y dependencias administrativas

Antes de aplicar UP o DOWN, respaldar sfatza con su esquema, datos y objetos SQL, incluidos los necesarios para conservar relaciones y recuperar el estado. Aunque usuario y conductor estén vacías, otras tablas pueden contener información. Mantener los respaldos privados fuera de Git y del directorio público; no versionar datos personales, hashes, credenciales, definidores ni configuración privada.

Ensayar la restauración en un entorno aislado con autorización; comprobar su integridad y compatibilidad. No afirmar que un respaldo es restaurable solo porque se generó un archivo. El usuario confirmó que el respaldo privado ya se creó y su restauración se probó en sfatza_restore_test_20261013; allí se ensayaron también los ALTER. Esta revisión registra esa restaurabilidad comprobada según el usuario, sin reproducirla ni inspeccionar el archivo privado. No se aportaron aquí ruta, tamaño, SHA-256, manifiesto de objetos o fecha/hora exacta y no se inventan. La prueba de restauración del respaldo anterior no valida una eventual recuperación de datos registrados después de UP.

Con una cuenta administrativa autorizada, revisar vistas, triggers, procedimientos, funciones, eventos programados, columnas generadas, restricciones y consumidores SQL externos que puedan depender de nombre. La inspección previa de la cuenta de aplicación tuvo visibilidad limitada: cero objetos visibles no demuestra ausencia absoluta de objetos ocultos. Revisar también referencias con alias, NEW.nombre/OLD.nombre y SQL dinámico; una búsqueda literal no basta.

La auditoría previa encontró índices sobre identificadores, usuario, licencia e id_rol, pero ninguno sobre nombre. Las relaciones visibles que deben preservarse son:

| Restricción | Relación |
| --- | --- |
| fk_usuario_rol | usuario.id_rol → rol.id_rol |
| fk_reembolso_usuario | reembolso.id_usuario → usuario.id_usuario |
| fk_venta_cajero | venta.id_cajero → usuario.id_usuario |
| fk_venta_usuario_cancelacion | venta.id_usuario_cancelacion → usuario.id_usuario |
| fk_viaje_usuario_salida | viaje.id_usuario_salida → usuario.id_usuario |
| fk_viaje_usuario_llegada | viaje.id_usuario_llegada → usuario.id_usuario |
| fk_viaje_conductor | viaje.id_conductor → conductor.id_conductor |

Todas tenían RESTRICT en actualización y eliminación. Conservar su definición y comparar la evidencia previa/posterior. No desactivar claves foráneas ni cambiar rol u otras tablas para completar esta migración.

## Orden previsto de UP y verificaciones — referencia histórica

El usuario confirmó ensayo y aplicación manual exitosos. La lista siguiente conserva el procedimiento revisado; no se presenta cada punto como una comprobación reproducida por el agente ni como una instrucción de reejecución. No se proporciona un comando automático. Nuevas operaciones necesitan autorización independiente.

1. Revisar los archivos y obtener aprobación de la versión exacta. Registrar referencia Git y huellas de los archivos, operador, fecha, respaldo y estado previo, sin secretos.
2. Suspender escritores, verificar respaldo/dependencias y repetir todas las precondiciones para ambas tablas. Registrar metadatos de columnas, índices, restricciones, FK, conteos y AUTO_INCREMENT, además del estado de rol y demás tablas que debe conservarse.
3. Ejecutar únicamente el ALTER de usuario. Inspeccionar avisos/errores y verificar siete columnas: nombres y apellidos con la definición objetivo, nombre ausente, conteo cero, PK/UNIQUE/índice id_rol/FK/AUTO_INCREMENT y demás columnas conservadas.
4. Con escritores todavía suspendidos, comprobar que conductor sigue vacía y con su esquema original. Ante un resultado inesperado, detenerse; no aplicar su ALTER.
5. Ejecutar únicamente el ALTER de conductor. Verificar seis columnas y repetir los controles equivalentes, incluida su PK, UNIQUE de numero_licencia y FK entrante de viaje.
6. Repetir la comparación final de ambas tablas: conteos cero, tipos, NOT NULL, ausencia de DEFAULT, utf8mb4/utf8mb4_uca1400_ai_ci, ausencia de nombre y posición de apellidos después de nombres. Confirmar columnas ajenas, índices, claves, reglas referenciales y AUTO_INCREMENT conservados; rol mantiene id_rol/nombre y otras tablas no presentan cambios.
7. Registrar el resultado real en la evidencia del sprint y reanudar escritores solo cuando el estado sea coherente con sus consumidores autorizados. No iniciar CRUD ni registrar usuarios como parte de esta migración.

Estas verificaciones son de esquema y conservación. Pruebas de registro/edición necesitan implementación, autorización y datos ficticios en un ambiente aislado; no forman parte de la ejecución de UP.

## DDL, commits implícitos y fallos parciales

ALTER TABLE causa commits implícitos, incluso antes de ejecutar la sentencia; no mezclar estos DDL con escrituras funcionales pendientes. BEGIN/COMMIT/ROLLBACK no permiten deshacer conjuntamente los dos ALTER. La atomicidad DDL de MariaDB protege operaciones individuales frente a fallos; no proporciona un rollback solicitado por el usuario ni atomicidad conjunta entre ambas tablas.

ALTER puede esperar bloqueos de metadatos y realizar trabajo físico según el algoritmo escogido por MariaDB. No se afirma ejecución instantánea o ausencia de bloqueos; el procedimiento debe ensayarse en un entorno aislado compatible.

Ante error, pérdida de conexión o ejecución parcial:

1. Mantener escritores suspendidos y detener la secuencia; no continuar ni reejecutar los archivos completos automáticamente.
2. Inspeccionar por lecturas qué tabla conserva nombre y cuál tiene nombres/apellidos, si la sentencia terminó y si existen registros.
3. Si el estado es inequívoco y ambas tablas siguen vacías, revisar si procede completar UP o revertir únicamente la tabla ya migrada. Cada actuación necesita autorización; validar metadatos y dependencias antes.
4. Si hay datos o discrepancias, no usar DOWN. Preservar el estado actual y diseñar una recuperación específica. Un respaldo anterior no contiene los registros creados después.
5. Restaurar primero en aislamiento para comprobar la recuperación. Cualquier recuperación en el entorno real requiere un plan aprobado que preserve datos posteriores y las tablas ajenas; no restaurar toda la base automáticamente.

Referencia oficial: [commits implícitos](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit), [Atomic DDL](https://mariadb.com/docs/server/reference/sql-statements/data-definition/atomic-ddl) y [ALTER TABLE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table).

## Restricciones de DOWN

**DOWN es un procedimiento condicionado para tablas vacías, no una reversión automática. Ejecutarlo con datos puede eliminar permanentemente apellidos.**

Exige autorización explícita propia, respaldo restaurable, revisión administrativa de las dependencias de nombres/apellidos y suspensión de todos los escritores. COUNT(*) debe ser cero en ambas tablas, también si solo se pretende revertir una ejecución parcial.

La tabla que se vaya a revertir debe tener el contrato objetivo exacto: nombres/apellidos VARCHAR(120) NOT NULL sin DEFAULT, charset/collation acordados, nombre ausente y las demás columnas/claves conservadas. Detenerse ante dependencias o cambios posteriores, por ejemplo nuevos índices sobre apellidos; este DOWN no los reconstruye.

Para UP completo, aplicar las sentencias en orden inverso: conductor, verificar; usuario, verificar. Para UP parcial, considerar únicamente la sentencia de la tabla realmente migrada, comprobando también que ambas tablas están vacías; no ejecutar el archivo DOWN completo. Repetir precondiciones y validar el resultado entre tablas.

El resultado esperado devuelve usuario a seis columnas y conductor a cinco, con nombre VARCHAR(120) NOT NULL sin DEFAULT y charset/collation originales, nombres/apellidos ausentes y todas las claves y demás columnas conservadas. Confirmar conteos cero y ausencia de cambios en rol/otras tablas.

No borrar registros para habilitar DOWN. No convertir nombres/apellidos a un nombre completo automáticamente: la concatenación puede alcanzar 241 caracteres y exceder el límite anterior de 120. Si existen datos, mantenerlos y preparar otra migración/recuperación con autorización específica; no truncar información.

## Trazabilidad y autorización

El estado operativo se documenta en este archivo y en [database/install.sql](../install.sql), que incorpora el esquema objetivo para una base nueva. Las migraciones individuales se mantienen para instalaciones existentes y no deben repetirse sobre una base nueva que ya use el instalador.

Registrar en la documentación autorizada la versión exacta, qué sentencia se ejecutó, metadatos antes/después, resultado y límites. No crear una tabla de control de migraciones ni modificar otras tablas como parte de este cambio.

Estado operativo: **UP APLICADO Y VERIFICADO MANUALMENTE SEGÚN EL USUARIO**, con respaldo restaurado y ensayo previo en sfatza_restore_test_20261013. La revisión del inicializador fue estática; no ejecutó SQL. Esta migración no certifica las pruebas funcionales de login o CRUD, que se describen en las guías técnicas públicas.

Los scripts SQL no fueron modificados en esta revisión. No hay credenciales ni respaldos privados en los siete cambios revisados. Ejecutar DOWN, nuevos DDL/DML, respaldos/restauraciones o crear usuarios requiere autorización propia; tener una base restaurada existente no concede permiso para modificarla.

## Migración 20261013_002 — licencia de conductor obligatoria

Estado: **UP y DOWN preparados; no ejecutados**. Esta migración es independiente de 20261013_001. El código de la aplicación ya exige licencia al registrar y modificar conductores. El esquema inspeccionado permite NULL; UP lo cambia a NOT NULL conservando `VARCHAR(40)`, `utf8mb4`, `utf8mb4_uca1400_ai_ci` y el índice UNIQUE `numero_licencia`. DOWN vuelve a permitir NULL con `DEFAULT NULL`; no relaja la validación de la aplicación.

La inspección PDO de solo lectura del 2026-10-13 halló un conductor y cero licencias NULL, vacías/solo espacios o duplicadas según la collation. Es una instantánea, no garantía de estado futuro: repetir las consultas inmediatamente antes de aplicar. Si aparece cualquier fila incompatible, no ejecutar el DDL ni alterar datos automáticamente. El responsable debe identificar las filas por `id_conductor` en el entorno autorizado y decidir una licencia válida/unívoca con el titular del dato. No generar licencias ficticias, no borrar filas y no registrar valores personales en Git.

### Respaldo y ensayo aislado

1. Obtener autorización explícita para la ejecución y acordar responsable, ambiente y ventana. Suspender escrituras de la aplicación y de cualquier proceso externo; conservar la suspensión hasta completar la verificación.
2. Crear un respaldo completo de `sfatza` (esquema y datos, incluidos triggers, rutinas y eventos necesarios), guardarlo fuera del repositorio y del directorio público con acceso restringido. Usar el mecanismo operativo aprobado; no poner contraseñas en argumentos, historial de consola, scripts ni documentación versionada.
3. Calcular y registrar privadamente tamaño y SHA-256; verificar que la herramienta finalizó correctamente y que el archivo no está vacío. El checksum comprueba identidad, no restaurabilidad.
4. Restaurar el respaldo en una base aislada, con nombre diferente de `sfatza`, utilizando una cuenta autorizada. Verificar que la base restaurada abre, que `conductor` y sus claves están presentes y que se pueden leer filas y metadatos esperados. No reutilizar ni limpiar una base compartida sin permiso.
5. Ensayar UP sobre esa restauración aislada, verificar `IS_NULLABLE = 'NO'`, tipo/collation, UNIQUE conservado y datos intactos; ensayar DOWN únicamente si también está autorizado y comprobar `IS_NULLABLE = 'YES'`/`DEFAULT NULL`. Registrar salida y límites fuera de este repositorio. Ninguno de estos pasos de escritura está autorizado por Prompt 20 sin aprobación expresa.

### Precondiciones de UP y comprobación

Revisar la versión exacta de los scripts, base actual, permisos y ausencia de ejecución previa. Confirmar que `conductor.numero_licencia` aún es `VARCHAR(40) NULL DEFAULT NULL`, con charset/collation e índice UNIQUE previstos. Las siguientes consultas son solo lecturas y no se ejecutaron como parte de esta documentación:

```sql
SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'sfatza'
  AND TABLE_NAME = 'conductor'
  AND COLUMN_NAME = 'numero_licencia';

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'sfatza'
  AND TABLE_NAME = 'conductor'
  AND INDEX_NAME = 'numero_licencia'
ORDER BY SEQ_IN_INDEX;

SELECT id_conductor
FROM sfatza.conductor
WHERE numero_licencia IS NULL
   OR CHAR_LENGTH(TRIM(numero_licencia)) = 0;

SELECT c.id_conductor
FROM sfatza.conductor AS c
JOIN (
    SELECT numero_licencia
    FROM sfatza.conductor
    WHERE numero_licencia IS NOT NULL
    GROUP BY numero_licencia
    HAVING COUNT(*) > 1
) AS duplicadas ON duplicadas.numero_licencia = c.numero_licencia
ORDER BY c.id_conductor;

SELECT COUNT(*) AS conductores FROM sfatza.conductor;
```

La consulta agrupada usa la collation de la columna y las consultas devuelven identificadores, no las licencias. La detección de vacío mediante `TRIM` cubre espacios ASCII; verificar también espacios Unicode con una consulta compatible con la versión instalada o revisión aislada frente a la misma regla Unicode del servidor de aplicación. Si hay licencias nulas, vacías/solo espacios o duplicadas, detenerse y reportar únicamente los `id_conductor` afectados al responsable autorizado. No editar registros como parte de esta tarea. Si cualquier precondición falla, hay discrepancia de esquema o quedan escritores activos, no ejecutar UP.

Con autorización y tras completar el ensayo, aplicar una sola vez el archivo [UP 20261013_002](20261013_002_licencia_conductor_obligatoria.up.sql). `ALTER TABLE` puede esperar bloqueos de metadatos y causa commit implícito; no prometer atomicidad transaccional ni ejecución sin bloqueo. Revisar resultado/avisos y verificar inmediatamente el tipo, `IS_NULLABLE = 'NO'`, charset, collation e índice UNIQUE, así como que el conteo de conductores y los datos no cambiaron. Reanudar escritores solo si las comprobaciones pasan.

### Recuperación y DOWN

Ante fallo, desconexión o resultado incierto, mantener suspendidos los escritores. Consultar metadatos y datos sin volver a ejecutar UP automáticamente. Si UP no llegó a aplicarse, corregir la causa antes de decidir un nuevo intento; si el esquema quedó en estado inesperado, detener la operación y preparar un procedimiento revisado con el DBA. No restaurar el respaldo completo sobre la base activa: eso podría eliminar escrituras legítimas posteriores.

DOWN requiere autorización explícita independiente, ensayo/restauración comprobados y confirmación de que la aplicación y consumidores toleran NULL. Revisar cambios de esquema posteriores y suspender escritores; después aplicar una sola vez el [DOWN 20261013_002](20261013_002_licencia_conductor_obligatoria.down.sql) y verificar que la columna permite NULL/DEFAULT NULL y que charset, collation e índice UNIQUE siguen intactos. La validación de la aplicación sigue exigiendo licencia, aunque el DDL permita NULL.

Si se necesita recuperar desde respaldo, primero ensayar en aislamiento. Una recuperación en producción exige plan aprobado que preserve todas las escrituras posteriores y comparación de datos; el respaldo no es una orden para sobrescribir. No se han creado ni corregido licencias en la base real, y la inspección de solo lectura no equivale a aprobación de ejecución.


## Migración 20261027_003 — estados ACTIVO/INACTIVO de vehículos

**APLICADA Y VERIFICADA EN sfatza LOCAL DE DESARROLLO (2026-10-27).** No repetir UP en esta base; el estado de otros equipos debe comprobarse individualmente. Los encabezados de los SQL son históricos y se conservan idénticos a los artefactos ensayados. UP/DOWN corregidos pasaron 33 comprobaciones reales en MariaDB 12.0.2. Posteriormente se completaron el respaldo real, su restauración aislada y la aplicación única de UP, con verificaciones anteriores y posteriores descritas al final. DOWN en sfatza no se ejecutó y requiere autorización independiente.

- [UP](20261027_003_vehiculo_estados_activo_inactivo.up.sql): de ENUM('ACTIVO','MANTENIMIENTO','INACTIVO') a ENUM('ACTIVO','INACTIVO'), NOT NULL DEFAULT 'ACTIVO'.
- [DOWN](20261027_003_vehiculo_estados_activo_inactivo.down.sql): recupera exactamente el ENUM original y su default, sin reconstruir datos históricos.
- install.sql: únicamente la definición de estado cambia para instalaciones nuevas. No usar el inicializador para migrar la base existente. Las migraciones 001/002 son independientes; no ejecutarlas en cadena por esta autorización.

### Evidencia de partida y referencias

Inspección de solo lectura previa, 2026-10-27: sfatza en MariaDB 12.0.2, tablas InnoDB y utf8mb4_uca1400_ai_ci. Cero vehículos ACTIVO/MANTENIMIENTO/INACTIVO y cero filas de mantenimiento/viaje. Vehiculo coincide con el esquema anterior; FK fk_mant_vehiculo y fk_viaje_vehiculo referencian id_vehiculo con UPDATE/DELETE RESTRICT. Los metadatos visibles no mostraron triggers/vistas/rutinas/eventos relacionados, sin descartar objetos no visibles o consumidores externos. Es una instantánea histórica: repetir inmediatamente antes del DDL. En esta preparación no se consultó de nuevo MariaDB.

Búsqueda textual de referencias antes de preparar: sin uso del estado MANTENIMIENTO en PHP, rutas, configuración, JS propio o pruebas; la definición antigua estaba en install.sql. La tabla mantenimiento y fk_mant_vehiculo no son usos del valor del ENUM y deben conservarse. No hay semillas de vehículos que convertir. Documentación académica/diagramas y documentación privada pueden mencionarlo como estado anterior: no alterar entregables. La planificación privada distingue estado real anterior, objetivo y futura aplicación. Si aparecen otros consumidores, revisarlos antes de ejecutar, sin cambiarlos automáticamente.

### Guardas automáticas de UP/DOWN

El cliente MariaDB debe interpretar DELIMITER. Cada archivo contiene una sola sentencia compuesta BEGIN NOT ATOMIC con SIGNAL SQLSTATE '45000', sin procedimiento persistente, tabla auxiliar ni ejecutor PHP. El único ALTER se encuentra dentro de la rama segura; no ejecutar una selección parcial del archivo ni separar el ALTER de sus guardas. Una guarda fallida termina el bloque; no hay un ALTER independiente posterior. El operador debe detenerse ante cualquier error, incluso si usa un cliente que permite continuar. Prohibido --force o ignorar errores/avisos.

Comprobaciones automáticas:

1. Base seleccionada explícitamente: sfatza o nombre cuyo prefijo literal sea sfatza_s02_estados_ensayo_. No hay USE ni sfatza codificada en el ALTER; el nombre se comprueba mediante DATABASE(). Así se ensaya el MISMO archivo, sin sustituir nombres que puedan dirigirlo a la base real.
2. Servidor MariaDB, sesión dedicada autocommit=1 sin transacción abierta; modo STRICT_TRANS_TABLES o STRICT_ALL_TABLES. La versión objetivo es 12.0.2; otras versiones requieren revisión/ensayo propios, aunque la guarda de producto no fija una versión exacta.
3. foreign_key_checks=1 y check_constraint_checks=1. No desactivarlos para facilitar el DDL.
4. vehiculo existe como tabla base InnoDB.
5. Columna estado con el ENUM de origen exacto para esa dirección, NOT NULL, DEFAULT 'ACTIVO', utf8mb4/utf8mb4_uca1400_ai_ci y sin atributos extra/comentario que MODIFY pudiera perder. UP repetido o ejecutado tras install.sql nuevo falla deliberadamente; DOWN exige previamente el esquema de UP.
6. Ningún estado NULL ni distinto, por comparación binaria del texto, de ACTIVO/INACTIVO. UP bloquea MANTENIMIENTO, cadena vacía de error ENUM u otro incompatible; no convierte/elimina filas. DOWN también rechaza datos inválidos en vez de normalizarlos.

Estas guardas NO certifican respaldo, autorización, esquema completo ni ausencia de escritores, y no cierran la carrera entre el SELECT y el DDL. Suspender TODOS los escritores y cambios de esquema desde antes del preflight hasta después de verificar es obligatorio. Comparar manualmente índices, CHECK, FK entrantes y demás columnas con el manifiesto antes/después. El script no los modifica ni desactiva.

### Procedimiento de respaldo privado y ensayo aislado

Este procedimiento se completó para la aplicación local registrada al final. Se conserva como referencia para otros ambientes; no autoriza repetir operaciones en sfatza.

1. Obtener autorización para respaldo/restauración y ensayo, responsable y ventana; elegir una instancia aislada y una base nueva con prefijo sfatza_s02_estados_ensayo_, sin reutilizar ni limpiar bases existentes por suposición. Verificar host/puerto/base con el responsable y revisar credenciales privadas fuera de Git. La cuenta de la aplicación no debe convertirse en cuenta DDL.
2. Suspender aplicación, trabajos programados y otros escritores/DDL. Inventariar definición de vehiculo/mantenimiento/viaje, PK/UNIQUE/CHECK/FK, conteos, estados por texto y dependencias visibles. La visibilidad limitada del usuario de aplicación no sustituye una auditoría administrativa.
3. Usar mariadb-dump compatible con MariaDB 12.0.2 y una cuenta autorizada: esquema/datos de sfatza, --single-transaction, --routines, --events, --triggers y --hex-blob cuando corresponda. No --databases ni --all-databases para un volcado destinado a restauración con otro nombre. Revisar opciones de esa versión antes de usarlo. Introducir contraseña mediante prompt protegido o mecanismo privado aprobado, nunca en argumentos/documentación. Guardar con --result-file en directorio privado externo al repositorio y public/, sin redirección de texto PowerShell que recodifique el SQL. Usar herramientas compatibles ya instaladas; este procedimiento no requiere dependencias nuevas en el proyecto.
4. Registrar privadamente fecha, versión de cliente/servidor, alcance, código de salida, tamaño no nulo y SHA-256 del respaldo. Checksum prueba identidad, no restaurabilidad. Capturar también objetos/permisos necesarios para recuperar el servicio sin publicar secretos; el dump de una base no incluye automáticamente cuentas/permisos globales.
5. Revisar privadamente el dump por CREATE DATABASE/USE, referencias calificadas sfatza y objetos que podrían apuntar a la base real; restaurar preferentemente en instancia separada donde sfatza real no exista y la cuenta no tenga acceso a ella. Seleccionar base aislada no neutraliza nombres calificados. No ejecutar reemplazos ciegos ni importar un dump con destino no comprobado.
6. Con autorización separada para crear/restaurar ese ambiente, restaurar con cliente compatible, comprobar objetos/datos y poder recuperar también mantenimiento/viaje/FK. La restauración debe finalizar sin errores y concordar con el manifiesto. Una copia de esquema vacía no acredita restaurabilidad de un respaldo con datos.
7. En fixtures aislados y expresamente autorizados, ensayar: origen vacío; origen con ACTIVO e INACTIVO; origen con MANTENIMIENTO que DEBE fallar antes del ALTER; ausencia de base seleccionada; base no permitida; esquema/default distinto; controles desactivados o modo no estricto; repetición UP. Para cadena vacía/NULL, solo si un fixture negativo autorizado lo permite: nunca relajar la base real para fabricarlos.
8. Ejecutar el archivo UP completo solo contra el destino aislado validado, con cliente que entienda DELIMITER y sin --force. Revisar errores y avisos. Comparar IDs y etiquetas por fila, no solo conteos: INACTIVO cambia de ordinal interno 3 a 2, pero debe seguir siendo el texto INACTIVO. COPY evita solicitar una alteración solo de metadatos ante cambio de posiciones. No se acepta conversión silenciosa, pérdida de datos ni aviso no explicado.
9. Verificar ENUM/default/charset/collation, conteos, PK/UNIQUE/CHECK/FK, tabla mantenimiento intacta y rechazo de MANTENIMIENTO mediante operación sintética autorizada SOLO en la base aislada. Verificar que la creación de tablas nuevas desde install.sql, cuando se autorice ensayarla, nace con el objetivo y no requiere UP. No ejecutar install.sql en la restauración ni en sfatza existente.
10. Ensayar DOWN con permiso propio y comparar de nuevo etiquetas/IDs (INACTIVO vuelve a ordinal 3). Debe restaurar el tipo original y dejar intactas tablas/FK/datos. Probar una ida/vuelta controlada; ampliar el ENUM no crea mantenimientos ni cambia por sí solo el estado de una fila.

### Procedimiento de preflight y ejecución controlada

Para nuevas ejecuciones autorizadas, repetir metadatos y conteos con todos los escritores suspendidos. Las siguientes son consultas de referencia; la aplicación local y sus comprobaciones efectivas se registran al final:

```sql
SELECT DATABASE(), VERSION(), @@sql_mode, @@autocommit, @@in_transaction;
SELECT @@foreign_key_checks, @@check_constraint_checks;
SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME,
       COLLATION_NAME, EXTRA, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehiculo'
  AND COLUMN_NAME = 'estado';
SELECT estado, COUNT(*) AS cantidad FROM vehiculo GROUP BY estado;
SELECT COUNT(*) AS incompatibles FROM vehiculo
WHERE estado IS NULL
   OR BINARY CAST(estado AS CHAR) NOT IN (BINARY 'ACTIVO', BINARY 'INACTIVO');
SHOW CREATE TABLE vehiculo;
SHOW CREATE TABLE mantenimiento;
SHOW CREATE TABLE viaje;
```

No continuar si incompatibles es distinto de cero, falta respaldo ensayado, cambia el esquema completo o quedan escritores activos. Resolver filas incompatibles es decisión del responsable con autorización independiente; jamás UPDATE de sustitución incluido en la migración.

Con permiso de ejecución real, seleccionar explícitamente sfatza en cliente dedicado, confirmar destino y aplicar UNA dirección completa con guardas y DELIMITER. No ejecutar UP y DOWN como un solo lote. El bloque anónimo NO es transacción atómica: ALTER causa commits implícitos; no usar BEGIN/ROLLBACK como recuperación del DDL. Se propone ALGORITHM=COPY y LOCK=EXCLUSIVE, con espacio libre/tiempo dimensionados; bloqueará lecturas/escrituras durante fases relevantes. La cláusula WAIT no se utiliza dentro del bloque: MariaDB 12.0.2 la rechaza con error 1314. El operador debe fijar lock_wait_timeout=30 en la sesión dedicada antes de UP; limita espera de metadatos, no duración total ni garantiza ausencia de bloqueo. Una transacción lectora antigua puede impedir adquirir el bloqueo. No matar sesiones externas ni cambiar opciones para forzarlo sin autorización.

Tras finalizar, revisar estado y avisos, ENUM/default, conteos/etiquetas/IDs, resto de columnas/índices/CHECK y las FK de mantenimiento/viaje. Registrar evidencia privada y solo reabrir escritores con confirmación. El cambio no requiere eliminar tablas o FK ni modificar tipos de servicio/rutas/paradas.

### Recuperación y estado incierto

Ante error/desconexión/timeout, mantener escritores suspendidos y consultar definición/datos antes de decidir. Si sigue el origen, no asumir que se aplicó; si ya está el objetivo, no repetir UP; si existe estado inesperado, detener y revisar con responsable. El fallo de una guarda no convierte filas. El éxito del ALTER debe verificarse por separado, no por mensajes del cliente únicamente.

DOWN necesita autorización y preflight propio, respaldo reciente y revisión de cambios posteriores. Restaura la definición original, no registros borrados ni historial; no autoriza funcionalidades de mantenimiento. Si se necesita recuperación desde respaldo, ensayarla primero y preservar escrituras posteriores antes de una restauración aprobada. No sobreescribir base activa automáticamente ni deshacer migraciones ajenas.

### Fuentes técnicas consultadas y validación realizada

Se contrastó el diseño con documentación primaria de MariaDB: [bloques anónimos](https://mariadb.com/docs/server/reference/sql-statements/programmatic-compound-statements/using-compound-statements-outside-of-stored-programs), [SIGNAL](https://mariadb.com/docs/server/reference/sql-statements/programmatic-compound-statements/signal), [ALTER TABLE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table) y [ENUM](https://mariadb.com/docs/server/reference/data-types/string-data-types/enum). El ensayo real de los scripts corregidos ya se completó en MariaDB 12.0.2 aislada (evidencia privada EV-08). Se retiró WAIT del ALTER y se verificó lock_wait_timeout de sesión. La aplicación en este sfatza ya fue verificada tras respaldo real restaurado y preflight actualizado; futuras ejecuciones en otros ambientes requieren sus propias verificaciones y autorización.


### Intento previo detenido por permisos — 2026-10-27 (histórico, superado)

**Resultado de aquel intento: UP 003 NO EJECUTADO.** El preflight de lectura confirmó sfatza local en MariaDB 12.0.2, ENUM original y cero vehículos/mantenimientos/viajes. Los hashes de scripts coinciden con el ensayo aislado. El procedimiento se detuvo: la cuenta disponible solo tiene SELECT/INSERT/UPDATE/DELETE sobre sfatza, sin ALTER ni permisos administrativos para completar respaldo/verificación de objetos y exclusión de escritores. No se generó un respaldo parcial ni se aplicó DDL; restauración real y ventana quedaron pendientes en aquel momento. Esa limitación se superó mediante la autenticación administrativa interactiva y las verificaciones de la aplicación efectiva descrita a continuación. Los ensayos sintéticos por sí solos no acreditaban un respaldo de los datos reales.


### Aplicación efectiva de 003 — 2026-10-27

UP 003 aplicado una sola vez en sfatza de desarrollo, MariaDB 12.0.2 local, usando root con contraseña interactiva. Antes se verificaron privilegios, identidad, SHOW FULL PROCESSLIST/transacciones y ausencia de escritores externos; se protegió el respaldo con read_only temporal y bloqueo global de lectura. No se terminaron sesiones ajenas.

Respaldo completo privado de 20.068 bytes, SHA-256 comprobado y restauración en instancia separada sin TCP. Las 15 tablas, sus definiciones y todas las filas coincidieron con la fuente. El respaldo y su manifiesto permanecen fuera del repositorio, con ACL restringida; la ruta y huellas completas se conservan en la evidencia privada, no se publica contenido.

Un primer auxiliar de respaldo falló por --connect-timeout=10, opción no admitida por mariadb-dump 12.0.2; el archivo vacío no se utilizó. Se corrigió el auxiliar, se repitió preflight y se obtuvo/restauró el respaldo válido antes de ejecutar UP.

Verificación posterior: vehiculo.estado es ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO', mismo charset/collation. La comparación completa de las 15 definiciones mostró solo ese cambio de ENUM; todas las huellas de filas y conteos permanecieron iguales. mantenimiento, índices UNIQUE, CHECK y FK intactos. read_only volvió a OFF y event_scheduler permaneció OFF. No se ejecutaron DOWN, otras migraciones, inicializador ni DML de negocio. Instancia/base de restauración retiradas tras verificar identidad.

El rechazo anterior de la ventana no permite atribuir una conexión concreta: el registro antiguo no guardaba ID/contexto. En este procedimiento se registraron explícitamente las sesiones propias y no hubo conexiones externas. No se consideran automáticamente peligrosas las conexiones Sleep.

DOWN en sfatza sigue sin autorización. HU-02 aún no implementada. Los permisos de la cuenta de aplicación, root y sus contraseñas no se modificaron. No se reescribieron los scripts ensayados después de su aplicación.

## HU-04: migraciones 004 y 005 (APLICADAS EN DESARROLLO EL 2026-10-27)

La nueva decisión sustituye el catálogo de servicios independiente para la captura: cada alta/actualización de ruta exige un tipo activo existente. No se cambian DIRECTO (ID1/control1) ni PARADAS_CONTINUAS (ID2/control0), ni se propagan cambios a viaje/tarifa.

Inspección real de solo lectura con cuenta habitual: MariaDB12.0.2, sfatza; ruta sin id_tipo_servicio, parada sin índice único de nombre, dos rutas/siete asociaciones y cero viajes/tarifas. La consulta de duplicados normalizados no devolvió grupos en esa inspección; no asumir que esto seguirá siendo cierto. No se modificó ningún dato ni estructura.

### Antes de autorizar aplicación
1. Detener escrituras de aplicación y herramientas SQL durante respaldo, preflight y DDL; no matar sesiones ajenas automáticamente. Revisar SHOW FULL PROCESSLIST. Usar cuenta administrativa interactiva solo para administración, nunca cambiar la cuenta de aplicación.
2. Respaldar íntegramente sfatza fuera del repositorio (tablas/datos/FK/triggers/rutinas/eventos), comprobar salida, tamaño/SHA-256 y restauración aislada. Conservar SHOW CREATE TABLE y conteos/huellas por PK. Ninguna contraseña en argumentos, archivos o chat.
3. Ensayar estos scripts en una copia aislada autorizada antes de aplicar en sfatza; en esta etapa solo se prepararon y no fueron ejecutados ni certificados por ensayo DDL. Cliente MariaDB con DELIMITER, modo estricto y FK/CHECK habilitados; sesión dedicada, destino seleccionado explícitamente y lock_wait_timeout acotado. ALTER implica commit y posibles bloqueos, no es rollback transaccional.

### 004 — Relación de tipo de servicio (versión final aplicada en desarrollo)
El usuario autorizó retirar exclusivamente las dos rutas de prueba, sus siete asociaciones y cinco paradas identificadas, después de respaldo completo/restaurado, comprobación de ausencia de historia y ventana sin escritores. No se elimina la base ni se restablecen contadores. Usuarios, roles, credenciales, conductores, vehículos, métodos de pago y tipos se conservan. Si los objetivos o dependencias cambian, detenerse.
La versión final UP 004 exige ruta vacía y añade ruta.id_tipo_servicio TINYINT UNSIGNED NOT NULL más índice/FK RESTRICT. Esta versión sustituye la fase nullable propuesta anteriormente: ya no hace falta asignar tipos a datos antiguos ni ejecutar una fase NOT NULL posterior. No asigna un tipo por defecto, no modifica DIRECTO/PARADAS_CONTINUAS y no altera viajes/tarifas. El mismo SQL exacto debe ensayarse en la restauración aislada antes de aplicarse a sfatza; comparar SHA-256 del script entre ensayo y aplicación.
La limpieza autorizada elimina por PK exclusivamente asociaciones 1..7, rutas 1/2 con códigos TEST-HU04-001/002 y paradas 1/2/4/5/6 con marca PRUEBA. Revalidar filas completas y referencias bajo transacción, mantener FK habilitadas y borrar en orden hijo/padre. Cualquier viaje, tarifa, boleto, venta, reembolso, mantenimiento o dependencia inesperada detiene el proceso antes de borrar.
Verificar columna obligatoria/FK, rechazo SQL de NULL u omisión y tipos inexistentes, y conservación completa por huellas de las tablas protegidas. Nuevos datos ficticios se crean mediante servicios y cuenta habitual, nunca root. No habilitar operaciones reales si falla el respaldo, restauración o ensayo.
DOWN 004 se mantiene conservador: solo permite retirar la columna si no hay asignaciones guardadas; con NOT NULL esto implica tabla vacía. No borrar asignaciones ni ejecutar DOWN en sfatza para forzarlo.

### 005 — Nombre único de parada normalizado
UP 005 comprueba nombres vacíos y duplicados incluyendo inactivas y referencias distintas. Canonicaliza solo para comparar/indexar: UPPER(TRIM(REGEXP_REPLACE(nombre,'[[:space:]]+',' '))) con utf8mb4_uca1400_ai_ci. Esta collation también considera equivalentes ciertos acentos, por coherencia con el esquema existente. El nombre descriptivo conserva escritura; la aplicación recorta y reduce espacios Unicode al guardar nuevas capturas.
Si aparecen grupos duplicados, detenerse, informar sus IDs y pedir resolución explícita al usuario. No eliminar/fusionar/renombrar ni remapear asociaciones automáticamente. La migración se niega a instalar el índice hasta resolverlos; no usar IGNORE. Nuevas altas/ediciones se protegen en aplicación, serializadas por el bloqueo administrativo existente. La garantía para escritores SQL externos requiere aplicar 005.
005 añade únicamente columna generada PERSISTENT nombre_normalizado e índice UNIQUE nombre; no reescribe nombres/referencias/IDs ni modifica ruta_parada. Longitud360 evita truncar expansiones de mayúsculas; índice utf8mb4 dentro del límite de InnoDB. Verificar sintaxis/expresión/indexación en ensayo autorizado MariaDB antes de producción local.
Verificar generado/índice únicos, ausencia de grupos equivalentes y comparación por PK de todos los datos originales. DOWN005 retira solo índice/columna derivada, sin cambiar filas originales; suspende protección SQL y exige ventana controlada/autorización.

### Actualización de recorridos
La aplicación comprueba ausencia de viajes al abrir y, de nuevo, bajo bloqueo/transacción al guardar. No cambia el estado al editar. Si el recorrido no cambia, conserva todos los IDs de ruta_parada y distancias. Si cambia, solo se reemplazan sus asociaciones cuando no hay tarifas y las dependencias se verifican; se revierten todos los cambios ante fallo. Con tarifas, bloquea reemplazo para conservar historia y permite únicamente datos con recorrido intacto. No modifica el flujo existente de eliminación. El máximo de cinco puntos se aplica a nuevas capturas/actualizaciones, no mediante recorte o migración de rutas antiguas.

### Ejecución verificada — 2026-10-27
Aplicadas UP004 y UP005 en sfatza/MariaDB12.0.2 tras autorización explícita, respaldo completo privado de 21.651 bytes con SHA-256 bf23f681ba6f4fbad6b1abca4c558f21161a8aca642b1759eab12c109568c919, restauración idéntica de las 15 tablas/objetos y ensayo aislado satisfactorio. La evidencia y SQL de respaldo permanecen fuera del repositorio, con ACL restringida; no se publica su contenido.
Se ejecutaron exactamente los SQL ensayados: UP004 SHA-256 5c1862bffe68def647ed5f8c666530ac6bc23cede3543c886bf878fb2dea1726; UP005 SHA-256 6e1cb394c3c9de09da6c5e9ef7a3bd3f7024d609d3c45c137cbf84043a74bfd4. No se modificaron después; sus comentarios PREPARADA/NO EJECUTADA reflejan la etapa de preparación, y este registro es el estado vigente para desarrollo. No reejecutarlos sobre esta base ni aplicar DOWN sin autorización específica.
Limpieza transaccional por PK y comprobación de filas exactas: ruta_parada IDs1..7, rutas1/2 (TEST-HU04-001/002), paradas1/2/4/5/6 con marca PRUEBA. Sin FK desactivadas, TRUNCATE, reseteo de contadores, borrado de base o cambios en cuentas. Antes de limpiar se verificaron cero viajes/tarifas/ventas/boletos/reembolsos/mantenimientos y las dependencias esperadas.
Verificado: ruta.id_tipo_servicio TINYINT UNSIGNED NOT NULL con FK RESTRICT; parada.nombre_normalizado generada persistente e índice UNIQUE nombre. Ensayo:29 comprobaciones reales; principal:29 comprobaciones reales de servicios/repositorios MVC con cuenta habitual (root solo administración). Incluyeron alta/consulta/actualización de ruta y parada, tipo/recorrido, rechazo de datos inválidos/duplicados, SQL UNIQUE/FK/obligatoriedad, versión obsoleta, estados y eliminación física de temporales. No se probó login o navegación en navegador ni se crearon viajes/boletos.
Datos demostrativos conservados: ruta4 HU04-0F1E0C9F-RUTA, DIRECTO ID1, activa; recorrido paradas8→9→10 (ORIGEN/INTERMEDIA/DESTINO). Ruta temporal5 y parada temporal12 borradas por los servicios existentes; cero temporales residuales distintos del conjunto demostrativo. Los saltos de ID provienen de pruebas/rollback/borrado y no se restablecieron.
Estado final:15 tablas; ruta1, parada3 y ruta_parada3; todos los datos/esquemas de tablas protegidas coinciden por huellas con el respaldo (usuarios/roles/credenciales/conductores/vehículos/tipos/métodos de pago e historia). DIRECTO ID1/control1 y PARADAS_CONTINUAS ID2/control0 intactos. read_only volvió a OFF, FK/CHECK en1. Instancia aislada propia apagada y directorio retirado; no se registró un servicio de Windows ni se utilizó TCP en el ensayo.
Incidencias controladas previas a cualquier borrado: conexiones DBeaver ajenas detenían el preflight; lectura incorrecta de read_only como entero corregida en auxiliar privado al comprobar que MariaDB12 lo devuelve como ENUM ON/OFF. El respaldo restaurado se reutilizó solo tras revalidar su SHA, los SQL y las huellas completas de sfatza sin cambios. No se almacenaron contraseñas en archivos, comandos visibles ni evidencia; autenticación interactiva y credenciales transitorias únicamente en memoria. Word y planes privados conservados, sin commit/push.
