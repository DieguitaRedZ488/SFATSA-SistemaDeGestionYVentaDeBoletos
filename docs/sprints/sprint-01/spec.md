# Especificación del Sprint 1 — SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Estado: borrador para revisión. Rama: docs/sprint-01-planificacion. Base: e8a79c8b416f1120596eccd6d0999d4be54bd0f0.

**Avance actual — Prompt 19:** login y HU-01 disponibles; el usuario confirmó manualmente registro, consulta/búsqueda, modificación de nombres, desactivación y rechazo de login de una cuenta inactiva. HU-03 incorpora listado/búsqueda, registro, edición y confirmación de desactivación/reactivación. HU-01 añade reactivación autorizada por el equipo y protege también el cambio de rol del último administrador activo. Las nuevas pruebas manuales contra MariaDB siguen pendientes; el agente no altera registros reales. [Guías de usuarios](../../gestion-usuarios.md) y [conductores](../../gestion-conductores.md), SRC-16 y EV-13.

Antecedente: Prompt 15 (SRC-13) autorizó únicamente el mecanismo CLI del primer administrador, pruebas aisladas y documentación técnica. En aquella entrega no se ejecutó el alta, abrió conexión o implementó login/CRUD. Los incrementos posteriores se delimitan en SRC-14/SRC-15/SRC-16; no autorizan al agente a modificar registros reales, ejecutar migraciones, alterar Figma/Documentos/ o integrar Git.

Migración 20261013_001: **APLICADA Y VERIFICADA MANUALMENTE, SEGÚN CONFIRMACIÓN DEL USUARIO**. usuario y conductor tienen nombres y apellidos VARCHAR(120) NOT NULL, sin la columna antigua nombre; la base original conserva 22 claves foráneas. El respaldo privado fue restaurado y los ALTER ensayados en sfatza_restore_test_20261013 antes de aplicarlos a sfatza. No se repitieron esas operaciones ni las consultas de metadatos en esta revisión; ver SRC-12 y EV-08.

## 1. Objetivo y fuentes

Preparar autenticación y acceso por rol, gestión de usuarios (HU-01) y gestión de conductores (HU-03), con trazabilidad a las fuentes académicas y al esquema real confirmado.

| Fuente | Referencia y uso |
| --- | --- |
| SRC-00 | Instrucciones y autorizaciones del usuario en esta conversación: alcance, RBAC, esquema confirmado y estados iniciales. |
| SRC-01 | [AGENTS.md](../../../AGENTS.md): arquitectura, convenciones y límites. |
| SRC-02 | [Constitución](../../constitution.md): requisitos, seguridad, integridad y verificación. |
| SRC-03 | [Historias de usuario](../../../Documentos/Historias_Usuario.docx): HU-01 y HU-03 y actor Administrador. |
| SRC-04 | [Descripciones](../../../Documentos/Historias_Descripciones.docx): historias números 1 y 3, criterios y conservación de registros. |
| SRC-05 | [Product Backlog](../../../Documentos/Product_Backlog.docx): Sprint 1 con HU-01 y HU-03. |
| SRC-06 | [Sprint Backlog](../../../Documentos/Sprint_Backlog.docx): 14 tareas, responsables y estimaciones. |
| SRC-07 | [UML HU-01](<../../../Documentos/Casos_Uso/Sprint 1/HU-01.mdj>): CU-01, CU-02 y CU-03. |
| SRC-08 | [UML HU-03](<../../../Documentos/Casos_Uso/Sprint 2/HU-03.mdj>): registrar y actualizar conductor. |
| SRC-09 | [Guía PDO](../../conexion-pdo-local.md) y [guía Apache](../../configuracion-apache-local.md): infraestructura existente y límites de las comprobaciones documentadas. |
| SRC-10 | Auditorías previas de esta conversación, 2026-10-12, resumidas en [verificacion.md](verificacion.md): evidencia histórica, sin nuevas consultas en esta fase. |
| SRC-11 | Diagnóstico de solo lectura del 2026-10-13 y autorización de preparación: migración 20261013_001, nombres/apellidos separados y obligatorios, rechazo de vacío/solo espacios y máximo de 120 caracteres cada uno. Esa fase no autorizaba ejecución SQL ni implementación. [Procedimiento de migración](../../../database/migrations/README.md). |
| SRC-12 | Confirmación del usuario recibida en esta revisión: UP aplicado manualmente en sfatza, columnas nuevas y ausencia de nombre verificadas, 22 FK conservadas, tablas vacías antes del cambio, respaldo privado restaurado y ALTER ensayados en sfatza_restore_test_20261013; Figma original sin cambios. La evidencia se atribuye al usuario, no a nuevas ejecuciones del agente; ver EV-08. |
| SRC-13 | Prompt 15 del usuario: aprobación de DEC-01 y DEC-04 e implementación exclusiva del bootstrap CLI seguro con pruebas aisladas; alta real y login/CRUD pendientes de autorización. [Procedimiento implementado](../../administrador-inicial.md); EV-10. |
| SRC-14 | Prompt 17 y confirmación posterior del usuario: bootstrap ejecutado manualmente y login MVC implementado; acceso real ADMINISTRADOR, bienvenida y rechazo de credenciales incorrectas comprobados por el usuario. [Evidencia del login](../../login-mvc.md), EV-11. |
| SRC-15 | Prompt 18: autoriza HU-01 completa y pruebas aisladas; búsqueda por nombres/apellidos/usuario, edición de esos tres campos y rol sin cambio de contraseña, normalización prudente, protección CSRF, autodesactivación prohibida y protección provisional del último administrador activo. Prohíbe operaciones reales del agente, cambios de esquema, HU-03, Figma, Documentos/ e integración Git. EV-12. |
| SRC-16 | Prompt 19: autoriza HU-03 con búsqueda por nombres/apellidos/licencia, registro activo, edición y cambios de estado confirmados; licencia/teléfono opcionales según el contrato real. Autoriza reactivar usuarios como ampliación del equipo y exige conservar al menos un ADMINISTRADOR activo ante desactivaciones/cambios de rol, con concurrencia. El usuario confirma las pruebas manuales HU-01 indicadas en EV-13. Mantiene la prohibición de escrituras reales del agente, DDL, bootstrap, cambios académicos/Figma e integración Git. |
| SRC-17 | Prompt 20: cambia la regla de negocio para hacer obligatoria y única la licencia del conductor, conserva nombres/apellidos obligatorios y teléfono opcional, y autoriza preparar una migración reversible y documentación de respaldo/recuperación, pero prohíbe ejecutarla o escribir datos reales sin autorización explícita. Se autoriza implementar y probar validaciones con datos ficticios y revisar el estado de licencia de MariaDB solo mediante lecturas. |
| SRC-18 | Inspección de solo lectura del agente, 2026-10-13: `sfatza.conductor.numero_licencia` es `VARCHAR(40) NULL DEFAULT NULL`, `utf8mb4_uca1400_ai_ci`, índice UNIQUE `numero_licencia`; un conductor, cero NULL, cero valores vacíos/solo espacios y cero grupos duplicados según la collation. No se mostraron valores de licencia ni se realizaron escrituras. |
| SRC-19 | Prompt 23, 2026-10-13: licencia exactamente 14 caracteres ASCII A-Z/0-9, convertir minúsculas a mayúsculas, rechazar espacios/símbolos, validar alta y edición, permitir conservar la licencia propia. Mantener columna `VARCHAR(40)`; prohíbe cambios de esquema/registros, ejecutar migración 002 y operaciones de Git. Autoriza revisar datos existentes solo mediante lectura y actualizar pruebas/documentación técnica. |
| SRC-20 | Inspección de solo lectura del agente, 2026-10-13: en `sfatza.conductor` se contaron dos registros; ambos incumplen el nuevo formato por longitud distinta de 14 y letras minúsculas. No se encontraron NULL, caracteres fuera del conjunto ASCII alfanumérico ni grupos duplicados al comparar sin distinguir mayúsculas. No se mostraron licencias ni se modificaron datos/esquema. |

CU-06 Registrar conductor y CU-07 Actualizar conductor son los identificadores indicados por el usuario para esta planificación. SRC-08 muestra los títulos sin esa numeración visible; no se modificó el modelo para añadirla. Los identificadores de requisitos, criterios, decisiones y pruebas de estos Markdown son referencias de planificación nuevas, no identificadores atribuidos a los originales.

## 2. Alcance y exclusiones

| Elemento | Alcance aprobado |
| --- | --- |
| Técnico T1 | Creación y configuración inicial de BD. La base ya existe y su estructura fue verificada; preparar su uso y evidencia sin recrear tablas. |
| Técnico T2 | Login, autenticación, sesiones y control de acceso por rol. |
| HU-01 | Registrar usuarios, buscar/cargar y modificar datos, asignar/actualizar rol y desactivar con confirmación, conservando registros y restringiendo acceso. |
| HU-03 | Registrar conductores, buscar/cargar y modificar datos, activar/desactivar y conservar su información. |

El incremento actual completa las operaciones web autorizadas de HU-03 y añade reactivación de usuarios a HU-01. La reactivación de usuarios es una ampliación funcional autorizada por el equipo en SRC-16, no un criterio atribuido retroactivamente a la HU-01 académica. Cambia únicamente activo de 0 a 1, conserva identidad, datos y contraseña y rechaza repeticiones. Las pruebas de escritura reales corresponden al usuario; el agente utiliza dobles. Se excluyen eliminación física, permisos individuales, tablas nuevas de privilegios, administración de roles, recuperación/cambio de contraseña y módulos de otros sprints.

El criterio académico de conductores activos para asignación se conserva; su verificación en Sprint 1 y su integración futura con viajes se delimitan en DEC-06. No se incorpora gestión de viajes a este sprint.

## 3. Esquema y reglas de datos

Esquema actualizado según SRC-12: nombres y apellidos VARCHAR(120) NOT NULL en usuario y conductor; nombre ya no existe en ellas. El contrato siguiente tiene 15 columnas entre usuario (7), conductor (6) y rol (2). Los demás tipos y restricciones proceden del esquema previamente auditado y del alcance de UP, que no los modifica. MariaDB 12.0.2, InnoDB, utf8mb4 y utf8mb4_uca1400_ai_ci son la configuración acordada y explícita en UP; esta revisión no realizó una nueva consulta a BD para contrastar cada atributo.

| Tabla | Columna | Tipo y restricciones |
| --- | --- | --- |
| usuario | id_usuario | BIGINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| usuario | id_rol | SMALLINT UNSIGNED, NOT NULL, FK a rol.id_rol |
| usuario | nombres | VARCHAR(120), NOT NULL |
| usuario | apellidos | VARCHAR(120), NOT NULL |
| usuario | usuario | VARCHAR(50), NOT NULL, UNIQUE |
| usuario | hash_contrasena | VARCHAR(255), NOT NULL |
| usuario | activo | TINYINT(1), NOT NULL, DEFAULT 1 |
| rol | id_rol | SMALLINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| rol | nombre | VARCHAR(30), NOT NULL, UNIQUE |
| conductor | id_conductor | BIGINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| conductor | nombres | VARCHAR(120), NOT NULL |
| conductor | apellidos | VARCHAR(120), NOT NULL |
| conductor | numero_licencia | Estado observado en SRC-18/SRC-20: VARCHAR(40), NULL, DEFAULT NULL, UNIQUE; aplicación exige 14 caracteres ASCII A-Z/0-9 en mayúsculas. El DDL se mantiene VARCHAR(40); migración 20261013_002 preparada, sin ejecutar. |
| conductor | telefono | VARCHAR(25), NULL, DEFAULT NULL |
| conductor | activo | TINYINT(1), NOT NULL, DEFAULT 1 |

La auditoría anterior documentó fk_usuario_rol con RESTRICT/RESTRICT y siete índices BTREE sin prefijos en estas tres tablas: tres PK, tres UNIQUE e índice no único de usuario.id_rol. SRC-12 confirma que la base completa conserva 22 FK después de UP. Ese conteo global no equivale a una nueva comparación individual de índices, acciones referenciales o AUTO_INCREMENT por parte del agente.

En el momento de la auditoría inicial había cero usuarios, cero conductores y exactamente ADMINISTRADOR, CAJERO y CHECADORA en rol. El diagnóstico de solo lectura del 2026-10-13 volvió a confirmar cero usuarios y cero conductores, sin repetir la lectura del catálogo de roles. Son instantáneas históricas; esta preparación no consultó nuevamente MariaDB.

### Cambio aprobado y aplicado según la confirmación manual

| Tabla | Columna anterior, histórica | Columnas aplicadas según SRC-12 |
| --- | --- | --- |
| usuario | nombre VARCHAR(120) NOT NULL | nombres VARCHAR(120) NOT NULL y apellidos VARCHAR(120) NOT NULL |
| conductor | nombre VARCHAR(120) NOT NULL | nombres VARCHAR(120) NOT NULL y apellidos VARCHAR(120) NOT NULL |

UP define ambas columnas sin DEFAULT, con CHARACTER SET utf8mb4 y COLLATE utf8mb4_uca1400_ai_ci; renombra nombre a nombres y añade apellidos después de nombres. Ese cambio es coherente con las columnas confirmadas por el usuario. El contrato aplicado tiene siete columnas en usuario y seis en conductor; rol conserva su contrato de dos columnas, incluida rol.nombre. Los scripts no modifican PK, AUTO_INCREMENT, UNIQUE, FK ni las demás columnas, ni crean una columna persistida de nombre completo. No se dispone del texto exacto de los comandos manuales ni de una nueva comparación de todos los atributos; la procedencia y límites están en EV-08.

Los formularios de registro y edición capturan **Nombre(s)** y **Apellidos** por separado. Cada valor debe ser UTF-8 válido, no vacío/solo espacios y de hasta 120 caracteres; la obligatoriedad se valida en servidor. HU-01 recorta únicamente espacios exteriores de nombres, apellidos y usuario según SRC-15. HU-03 recorta nombres/apellidos; la licencia no se recorta. Nombres/apellidos/licencia son obligatorios. Teléfono sigue opcional, hasta 25 caracteres; ausencia o vacío se guarda como NULL. Desde SRC-19, la licencia es exactamente 14 caracteres ASCII alfanuméricos (`[A-Z0-9]`); las minúsculas ASCII se normalizan en servidor y navegador, mientras espacios, símbolos y otros caracteres se rechazan. La columna conserva `VARCHAR(40)` y UNIQUE; SRC-19 sustituye la regla de formato abierta de SRC-17 sin cambiar retrospectivamente el registro histórico.

La migración ya fue aplicada manualmente según SRC-12; no reejecutar UP en sfatza. Las precondiciones y el procedimiento del README quedan como referencia histórica y para una recuperación expresamente autorizada. El usuario confirmó tablas vacías antes del cambio y restauración/ensayo previos; no se infiere un conteo actual ni se certifican aquí otras sesiones o dependencias SQL. DOWN requiere nueva autorización y verificar que ambas tablas continúan vacías: con registros puede perder apellidos y no es un rollback transaccional. No se informó un ensayo de DOWN. Los comentarios PREPARADA, NO EJECUTADA de los SQL corresponden a su preparación histórica y se conservan sin alterar los artefactos.

Las reglas siguientes describen el contrato migrado confirmado por el usuario. Bootstrap, login, HU-01 y HU-03 están disponibles. La evidencia manual de HU-01 se limita a las operaciones reportadas en SRC-16/EV-13; no acredita pruebas manuales de HU-03, reactivación o concurrencia. NOT NULL por sí solo no acredita rechazo funcional de vacío o solo espacios.

| Regla | Definición y fundamento |
| --- | --- |
| R-DAT-01 | Registro de usuario: nombres y apellidos separados, identificador de usuario, contraseña y rol obligatorios; hash_contrasena se obtiene en servidor. Nombres y apellidos también son obligatorios al editar y rechazan vacío o solo espacios. SRC-00, SRC-04 y SRC-11. |
| R-DAT-02 | Nombres, apellidos y licencia del conductor son obligatorios al registrar y editar; se rechazan ausencia, NULL, vacío o solo espacios. Solo el teléfono es opcional y puede representarse mediante NULL. SRC-00, SRC-17 y contrato observado/objetivo. |
| R-DAT-03 | Respetar límites de caracteres UTF-8: nombres 120 y apellidos 120 de forma independiente, usuario 50, licencia 40 y teléfono 25. Validar en servidor sin truncar silenciosamente; comprobar límites en registro y edición, con entradas multibyte. El límite 255 corresponde al hash almacenado, no a la contraseña original. |
| R-DAT-04 | Roles seleccionables únicamente del catálogo confirmado y de la lista permitida en código. Resolver id_rol en servidor; no asumir IDs numéricos fijos. |
| R-DAT-05 | Usuario único y licencia única cuando exista. Una edición que conserva su propio valor no debe confundirse con el registro de otra entidad. La comprobación previa se complementa con UNIQUE y tratamiento de conflictos. |
| R-DAT-06 | Conservar registros al desactivar. Estado funcional activo/inactivo, validado como 1/0; no hay CHECK de estado en el esquema auditado. |
| R-DAT-07 | Las altas utilizan el estado inicial DEFAULT 1. Identificadores y hash no se editan directamente desde formularios; los IDs identifican registros existentes y se validan en servidor. |
| R-DAT-08 | Duplicados conforme a la collation real y UNIQUE, sin cambiarla. Recorte exterior prudente para HU-01/HU-03 según SRC-15/SRC-16; conservar acentos, caso y espacios internos. El teléfono ausente/vacío de HU-03 se representa como NULL; la licencia es obligatoria y no recibe formato inventado. |

## 4. Matriz RBAC aprobada

R-AUT-01: permisos por rol definidos en código, sin permisos individuales ni nuevas tablas. R-AUT-02: comprobar autenticación, usuario activo y rol vigente en cada petición protegida.

| Operación | ADMINISTRADOR activo | CAJERO activo | CHECADORA activa | Sin sesión válida o usuario inactivo |
| --- | --- | --- | --- | --- |
| Buscar y visualizar usuarios | Permitido | Denegado | Denegado | Denegado |
| Registrar usuarios | Permitido | Denegado | Denegado | Denegado |
| Modificar usuarios y su rol | Permitido | Denegado | Denegado | Denegado |
| Desactivar usuarios | Permitido | Denegado | Denegado | Denegado |
| Reactivar usuarios, ampliación SRC-16 | Permitido | Denegado | Denegado | Denegado |
| Buscar y visualizar conductores | Permitido | Denegado | Denegado | Denegado |
| Registrar y modificar conductores | Permitido | Denegado | Denegado | Denegado |
| Activar/desactivar conductores | Permitido | Denegado | Denegado | Denegado |

Los tres roles pueden autenticarse si la cuenta está activa y las credenciales son válidas. Autenticarse no concede gestión a CAJERO o CHECADORA; sus módulos se definirán en sus sprints. Denegar roles desconocidos y operaciones sin permiso. Ocultar controles de interfaz es complementario.

## 5. Autenticación, sesiones y seguridad

Estas medidas técnicas derivan de SRC-01, SRC-02, las habilidades del repositorio y SRC-13/SRC-15/SRC-16. Ambos módulos reutilizan sesión/CSRF, revalidan cuenta activa y rol por petición y dentro de las transacciones de escritura; usan PDO preparado, HTML escapado y mensajes seguros.

| Control | Resultado requerido al implementar |
| --- | --- |
| SEG-01 | Mínimo 12 caracteres; password_hash()/password_verify(), sin originales ni hashes en vistas/salida. Bootstrap usa PASSWORD_BCRYPT con coste nativo de PHP y rechaza más de 72 bytes o NUL antes de hashear, sin recortar ni truncar. DEC-04 aprobada en SRC-13. |
| SEG-02 | Sesiones web implementadas: modo estricto, cookies exclusivas, HttpOnly, SameSite=Lax y Secure bajo HTTPS; regeneración al autenticar/cambiar privilegios, cierre e inactividad de 30 minutos. No aplica al CLI. |
| SEG-03 | Revalidar usuario activo y rol actual por petición. Una sesión anterior no conserva permisos revocados ni permite seguir operando tras desactivar la cuenta. |
| SEG-04 | Login/logout y operaciones HU-01/HU-03 usan POST y CSRF impredecible de sesión, validado antes de persistir. GET solo consulta formularios o datos; no aplica al CLI. |
| SEG-05 | SQL preparado con parámetros para valores, validación en servidor y listas permitidas para identificadores/ordenación. |
| SEG-06 | Escapar salidas por contexto; para texto HTML usar htmlspecialchars con ENT_QUOTES, ENT_SUBSTITUTE y UTF-8. Evitar interpolación de entradas en JavaScript. |
| SEG-07 | Errores controlados sin SQL, DSN, credenciales, hashes, tokens ni trazas. No confirmar éxito cuando falla la persistencia. Mantener atomicidad cuando una operación requiera varias escrituras. |

Los cambios de estado muestran el registro y requieren confirmación explícita; cancelar conserva datos y estado. Desactivar cambia únicamente activo a 0 y reactivar únicamente activo a 1; se rechazan estados redundantes. SRC-15 conserva la prohibición de autodesactivación autenticada. SRC-16 exige mantener al menos un ADMINISTRADOR activo ante desactivación o cambio de rol, incluida la edición propia si es el único. Esta política está autorizada técnicamente e implementada en servidor bajo el protocolo transaccional común de usuarios; su ratificación académica sigue pendiente en DEC-05. Se revalida bajo bloqueo antes de escribir, sin cambiar automáticamente cuentas reales.

HU-03 permite activar y desactivar conservando el registro. SRC-16 exige una confirmación previa que identifique conductor y acción, además del mensaje posterior al guardado. Ese detalle deriva del incremento técnico autorizado y no se atribuye como texto literal de los originales.

### Contrato del administrador inicial — SRC-13, DEC-01

| ID | Requisito del incremento implementado |
| --- | --- |
| R-INI-01 | scripts/crear-administrador-inicial.php acepta solo PHP CLI y ningún argumento; fuera de public/, sin ruta web. Rechaza HTTP con 404 antes de cargar configuración. |
| R-INI-02 | Reutilizar Environment, config/database.php y ConnectionFactory para sfatza con cuenta de aplicación y PDO no persistente; no duplicar credenciales ni modificar esquema. |
| R-INI-03 | Nombres/apellidos obligatorios de hasta 120 caracteres UTF-8 y usuario obligatorio de hasta 50; rechazar inválidos, vacíos y solo espacios. Conservar valores sin normalizaciones adicionales de DEC-07. |
| R-INI-04 | Contraseña/confirmación sin eco en consola Windows; abortar si es incompatible. No aceptar secretos por argumentos, entorno o temporales. Mínimo 12 caracteres y máximo técnico bcrypt de 72 bytes, sin NUL/truncamiento; verificar coincidencia y almacenar solo hash nativo. |
| R-INI-05 | Resolver exactamente ADMINISTRADOR desde rol sin IDs fijos; insertar con activo=1, sin elección de rol. Cualquier administrador existente, incluso inactivo, impide otra alta. No actualizar cuentas ni crear roles. |
| R-INI-06 | GET_LOCK compartido por las ejecuciones del CLI, antes de abrir transacción; revalidar rol, administradores y duplicado dentro de ella. INSERT preparado, UNIQUE como respaldo y commit solo ante éxito. |
| R-INI-07 | Revertir transacción pendiente y liberar bloqueo en intentos separados; cerrar conexión exclusiva como respaldo. Mensajes fijos sin excepción original, SQL, secretos o trazas. Ante commit/liberación incierto, revisar estado antes de repetir. |
| R-INI-08 | Pruebas con repositorios/PDO/terminal ficticios, sin conectar o insertar en sfatza. Entrada oculta interactiva, permisos/collation y concurrencia real MariaDB quedan pendientes; alta real requiere autorización propia. |

[Arquitectura, operación y límites del bootstrap](../../administrador-inicial.md). No se volvió a ejecutar. Login, HU-01 y HU-03 se documentan por separado.

## 6. Criterios de aceptación verificables

Los criterios siguientes concretan SRC-00, SRC-04, SRC-07 y SRC-08. Las decisiones pendientes no se convierten en condiciones aprobadas por incluirse aquí.

### Técnico

| ID | Criterio |
| --- | --- |
| CA-TEC-01 | La configuración permite utilizar la base existente mediante la cuenta de aplicación y verificar estructura y accesos acordados sin modificar tablas. Evidencia histórica parcial disponible. |
| CA-TEC-02 | Cuando una cuenta activa presenta credenciales válidas, el sistema establece una sesión autenticada y resuelve su rol. |
| CA-TEC-03 | Credenciales inválidas, cuenta inexistente o inactiva no producen sesión autenticada y reciben un mensaje general. |
| CA-TEC-04 | Cada petición protegida aplica la matriz RBAC con el estado y rol actuales, incluso usando una sesión anterior. |
| CA-TEC-05 | Tras cerrar sesión, sus credenciales anteriores no autorizan nuevas peticiones. Expira tras 30 minutos de inactividad; implementación y comprobaciones aisladas disponibles, cierre integrado de Sprint pendiente. |

### HU-01 — Gestión de usuarios

| ID | Caso de uso | Criterio |
| --- | --- | --- |
| CA-01-01 | CU-01 | Un administrador registra una cuenta con nombres y apellidos separados válidos, demás datos obligatorios y rol existente; ambos campos se conservan por separado, se almacena un hash y se confirma el guardado. |
| CA-01-02 | CU-01, CU-02 | Obligatorios omitidos/NULL/vacíos, solo espacios, longitudes excedidas, tipos/UTF-8 inválidos y rol inválido se rechazan sin persistir. Nombres/apellidos independientes; recorte exterior prudente según SRC-15. |
| CA-01-03 | CU-01, CU-02 | Un identificador perteneciente a otro usuario produce error de duplicado sin sobrescribirlo ni crear una segunda cuenta. |
| CA-01-04 | CU-02, CU-03 | Búsqueda parcial por nombres, apellidos o usuario, con selección mediante acciones del listado y carga por ID. Sin contraseña/hash; sin resultados/inexistentes, mensaje y ausencia de cambios. DEC-02 definida para HU-01 por SRC-15. |
| CA-01-05 | CU-02 | Editar nombres y apellidos independientes o juntos, usuario y rol, conservando contraseña y estado. Nombres/apellidos obligatorios de hasta 120 caracteres; guardar confirma el resultado. Sin restablecimiento de contraseña. SRC-15/DEC-03. |
| CA-01-06 | CU-01, CU-02 | Asignar o actualizar el rol guarda una FK válida y los permisos derivados se aplican en las siguientes peticiones protegidas. |
| CA-01-07 | CU-03 | Antes de desactivar se muestra la cuenta y se solicita confirmación; cancelar no modifica su estado. |
| CA-01-08 | CU-03 | Confirmar la desactivación conserva la cuenta con activo=0 e impide login y operaciones protegidas, incluidas las de sesiones anteriores. |
| CA-01-09 | CU-01, CU-02, CU-03 | Un fallo de persistencia devuelve error controlado, sin éxito falso, secretos ni cambios parciales de una operación compuesta. |

### HU-03 — Gestión de conductores

| ID | Caso de uso | Criterio |
| --- | --- | --- |
| CA-03-01 | CU-06 | Un administrador registra un conductor válido con nombres, apellidos y licencia obligatorios; nombres/apellidos tienen máximo 120 caracteres y la licencia contiene exactamente 14 caracteres ASCII A-Z/0-9, normalizando minúsculas a mayúsculas. Teléfono opcional, máximo 25. Los campos se guardan separadamente y se confirma el resultado. SRC-19. |
| CA-03-02 | CU-06, CU-07 | Nombres, apellidos y licencia omitidos/NULL/vacíos se rechazan en servidor; nombres/apellidos excedidos de 120 y licencia de longitud distinta a 14 también se rechazan sin persistencia en alta ni cambios en edición. Licencia con espacios, símbolos o caracteres fuera de A-Z/0-9 se rechaza. Teléfono vacío se representa como NULL; tipos/estado inválidos se rechazan; recorte exterior solo para nombres/apellidos. SRC-19 sustituye el formato libre previo. |
| CA-03-03 | CU-06, CU-07 | Una licencia duplicada produce error; conservar la licencia propia en edición es válido. La comparación y el índice UNIQUE preservan la regla de unicidad. SRC-19. |
| CA-03-04 | CU-07 | Búsqueda parcial por nombres, apellidos o número de licencia y carga por ID; sin coincidencias informa que no se encontró y no realiza cambios. SRC-16/DEC-02. |
| CA-03-05 | CU-07 | Guardar datos válidos permite modificar nombres y apellidos por separado o juntos, conservando ambos valores y el registro; cambiar estado entre activo/inactivo sigue permitido. Cada campo mantiene obligatoriedad y máximo de 120 caracteres. |
| CA-03-06 | CU-06, CU-07 | El sistema confirma las persistencias exitosas y comunica los fallos sin éxito falso ni cambios parciales. Abandonar una edición antes de guardar no escribe datos. |
| CA-03-07 | Dependencia de asignación futura | Solo conductores activos estarán disponibles para nuevas asignaciones. Conserva el criterio académico; DEC-06 propone verificar en Sprint 1 un contrato de selección de candidatos activos, sin implementar ni simular asignación real a viajes. La integración posterior permanece pendiente. |

## 7. Discrepancias registradas

| ID | Fuentes y diferencia | Tratamiento en esta planificación |
| --- | --- | --- |
| DIS-01 | Backlogs: HU-01/HU-03 en Sprint 1. Carpetas UML: HU-01/HU-02 en Sprint 1 y HU-03 en Sprint 2. | Se usa el alcance explícito del usuario, coincidente con los backlogs; no se trasladan modelos. |
| DIS-02 | UML HU-01 incluye Asignar privilegios. | Se interpreta conforme al RBAC aprobado: permisos derivados del rol en código. No se habilitan privilegios individuales. |
| DIS-03 | Product Backlog: 13 días para Sprint 1; Sprint Backlog: 112 h/14 días. Descripciones: HU-01 7 días y HU-03 5; Product Backlog: HU-03 6. | Se conservan las 14 estimaciones de 8 h y sus responsables en tasks.md. No se recalculan los originales. |
| DIS-04 | Técnico T1 del Sprint 2 vuelve a mencionar tablas/relaciones para conductores, ya incluidas en HU-03 T2 del Sprint 1. | Aclarar preparación inicial frente a ajustes posteriores; no extender el alcance de la migración aprobada para preparación ni ejecutarla en esta fase. |
| DIS-05 | SRC-08 no muestra CU-06/CU-07 en los títulos; el usuario los establece para esta matriz. | Mantener equivalencia por nombre y documentar procedencia de los IDs. |
| DIS-06 | MEMORY.md conserva rama y estado anteriores al PR #4; las guías no incluyen la última auditoría de lectura de tablas. Documentos usan SFATZA y SFATSA. | La evidencia actual se registra aquí y en verificacion.md; no se modifican los archivos originales. |

## 8. Decisiones aprobadas y propuestas pendientes

| ID | Decisión o propuesta | Estado |
| --- | --- | --- |
| DEC-01 | Bootstrap manual implementado; alta real reportada por el usuario, sin nueva ejecución del agente. | Aprobada; evidencia manual del usuario, límites de integración en la guía CLI |
| DEC-02 | Búsqueda parcial de HU-01 en nombres/apellidos/usuario y HU-03 en nombres/apellidos/licencia; coincidencia conforme a collation, caracteres comodín tratados como texto, sin filtros extra ni paginación ficticia. Selección por acción del listado. | Definida para HU-01 por SRC-15 y HU-03 por SRC-16 |
| DEC-03 | HU-01 edita nombres, apellidos, usuario y rol; conserva contraseña/estado y no ofrece restablecimiento. | Aprobada e implementada según SRC-15 |
| DEC-04 | Mínimo 12 caracteres, bcrypt sin truncamiento máximo 72 bytes, hash/verificación nativos y PDO preparado; sesión/CSRF/RBAC según controles SEG. Bloqueos de intentos, MFA/CAPTCHA siguen sin aprobar. | Aprobada; login y ambas HU implementados, verificación integrada pendiente |
| DEC-05 | SRC-15 prohíbe autodesactivación. SRC-16 exige no dejar cero ADMINISTRADOR activos al desactivar o cambiar roles, incluido el propio cuando es el único; protección en servidor y transacción/bloqueos comunes. | Autorizada técnicamente e implementada; ratificación académica y concurrencia real pendientes |
| DEC-06 | Acordar evidencia del filtro de conductores activos en Sprint 1; propuesta: prueba del contrato de selección, sin implementar ni simular asignación real a viajes, y prueba integrada con viajes en su sprint posterior. | Pendiente de aprobación |
| DEC-07 | HU-01 recorta espacios exteriores de nombres/apellidos/usuario. HU-03 recorta nombres/apellidos/licencia/teléfono y convierte teléfono opcional ausente/vacío en NULL. Rechaza campos obligatorios vacíos, UTF-8 inválido, NUL y longitudes excedidas; conserva espacios internos, mayúsculas, acentos y contraseña literal. Sin patrones adicionales de licencia/teléfono; bootstrap conserva comportamiento. | Definida para HU-01 según SRC-15 y HU-03 según SRC-16; opcionalidad de licencia sustituida por DEC-10 |
| DEC-10 | HU-03 requiere licencia en alta y edición; vacía/solo espacios se rechaza y se mantiene UNIQUE. No se impone formato ni longitud mínima; máximo 40 caracteres conforme a la columna existente. Teléfono permanece opcional. Aplicación valida antes de cualquier DDL; migración 20261013_002 conserva tipo, charset/collation e índice, pasa a NOT NULL y tiene DOWN a NULL/DEFAULT NULL. No ejecutar DDL ni modificar datos sin autorización explícita. | Aprobada por Prompt 20; aplicación implementada. Esquema leído sin escrituras; UP/DOWN preparados y pendientes de autorización para ejecución. |
| DEC-08 | Autorizar, si procede, reconciliación de ubicaciones UML, numeración, denominaciones y estimaciones; actualizar memoria/guías por separado. | Pendiente de autorización |
| DEC-09 | Figma Make leído por MCP; listados/formularios/confirmación se adaptan a PHP, Bootstrap CSS 5.3.8 existente, CSS/JS propios y móvil. Sin React/Tailwind ni nuevas dependencias. | Aplicada al login y ambas HU; Figma sin modificar |

SRC-13/SRC-14/SRC-15/SRC-16 delimitan los incrementos CLI, login y ambas HU. La reactivación de usuarios pertenece a SRC-16 y no modifica retrospectivamente los CU académicos. La confirmación manual del usuario acredita solo las operaciones HU-01 enumeradas; nuevas pruebas manuales, concurrencia real, DEC-06 y reconciliaciones académicas conservan sus pendientes. El agente no ejecuta escrituras reales.
