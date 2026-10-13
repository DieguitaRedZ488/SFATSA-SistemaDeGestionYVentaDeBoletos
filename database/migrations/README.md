# Migraciones de SFATSA

## Estado y alcance autorizado

Migración **20261013_001: APLICADA Y VERIFICADA MANUALMENTE, SEGÚN CONFIRMACIÓN DEL USUARIO**. usuario y conductor tienen nombres y apellidos VARCHAR(120) NOT NULL, sin la columna antigua nombre, y sfatza conserva 22 claves foráneas. Ambas tablas estaban vacías antes del cambio. El usuario confirmó respaldo privado creado, restauración exitosa y ensayo de ALTER en sfatza_restore_test_20261013 antes de aplicarlos a sfatza; Figma original no fue modificado.

El estado de 20261013_001 indicado aquí corresponde a la confirmación del responsable del proyecto. El inicializador para instalaciones nuevas y el estado de 20261013_002 se describen más adelante en «Esquema inicial para instalaciones nuevas».

Archivos:

- [UP](20261013_001_separar_nombres_apellidos.up.sql): renombrar nombre a nombres y agregar apellidos en usuario y conductor.
- [DOWN condicionado](20261013_001_separar_nombres_apellidos.down.sql): regresar al esquema anterior únicamente si ambas tablas siguen vacías y se cumplen todas sus condiciones.

**No reejecutar UP en sfatza: el cambio ya está aplicado.** Los scripts UP/DOWN se mantienen idénticos a la preparación revisada; sus comentarios PREPARADA, NO EJECUTADA son históricos y no representan el estado operativo de esta revisión. El estado de aplicación se registra en este README y los documentos del sprint, sin editar los artefactos SQL después de su aplicación reportada.

Cualquier nueva ejecución, incluida una eventual reversión, necesita autorización explícita propia. Los archivos contienen DDL y comentarios, no un ejecutor ni verificaciones automáticas. No ejecutarlos como lotes desatendidos, al iniciar la aplicación o al correr pruebas. No se informó que DOWN hubiera sido ensayado o aplicado.

## Propósito y contrato

Destino acordado: sfatza, MariaDB 12.0.2, InnoDB, charset utf8mb4 y collation utf8mb4_uca1400_ai_ci. UP contiene explícitamente charset/collation y ambos VARCHAR(120) NOT NULL; la inspección de solo lectura del Prompt 22 confirmó esas columnas en `usuario` y `conductor`, junto con los metadatos completos usados para el inicializador. Los detalles y la procedencia de la aplicación manual de 001 siguen siendo los registrados por el usuario en EV-08.

## Esquema inicial para instalaciones nuevas

`../install.sql` es un inicializador separado de las migraciones históricas. La lectura de `sfatza` mediante `SHOW CREATE TABLE` e `information_schema` confirmó 15 tablas InnoDB, charset/collation `utf8mb4`/`utf8mb4_uca1400_ai_ci` y 22 columnas de clave foránea con reglas `RESTRICT` para UPDATE y DELETE. El archivo inicial reproduce esas tablas, índices, claves, checks y tipos; elimina los valores incidentales de `AUTO_INCREMENT` observados en la base existente.

Para que una instalación vacía nazca con el contrato actual, el inicializador incluye el resultado de 20261013_001 (`usuario` y `conductor` con `nombres` y `apellidos`) y el objetivo de 20261013_002 (`conductor.numero_licencia VARCHAR(40) NOT NULL`, conservando el índice UNIQUE). También inserta únicamente los catálogos consultados por nombre/estado: roles, métodos de pago y tipos de servicio. No inserta usuarios, hashes, conductores ni datos operativos. En una instalación nueva no se ejecutan estas migraciones después del inicializador.

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
