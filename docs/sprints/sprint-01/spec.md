# Especificación del Sprint 1 — SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Estado: borrador para revisión. Rama: docs/sprint-01-planificacion. Base: e8a79c8b416f1120596eccd6d0999d4be54bd0f0.

La autorización actual permite crear estos cuatro documentos y la rama local. No autoriza implementar, cambiar datos o esquema, instalar dependencias ni realizar commits, push o PR. Las decisiones aprobadas se distinguen de las propuestas pendientes.

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

CU-06 Registrar conductor y CU-07 Actualizar conductor son los identificadores indicados por el usuario para esta planificación. SRC-08 muestra los títulos sin esa numeración visible; no se modificó el modelo para añadirla. Los identificadores de requisitos, criterios, decisiones y pruebas de estos Markdown son referencias de planificación nuevas, no identificadores atribuidos a los originales.

## 2. Alcance y exclusiones

| Elemento | Alcance aprobado |
| --- | --- |
| Técnico T1 | Creación y configuración inicial de BD. La base ya existe y su estructura fue verificada; preparar su uso y evidencia sin recrear tablas. |
| Técnico T2 | Login, autenticación, sesiones y control de acceso por rol. |
| HU-01 | Registrar usuarios, buscar/cargar y modificar datos, asignar/actualizar rol y desactivar con confirmación, conservando registros y restringiendo acceso. |
| HU-03 | Registrar conductores, buscar/cargar y modificar datos, activar/desactivar y conservar su información. |

Quedan fuera de esta fase documental todas las implementaciones y operaciones de BD. El alcance funcional del Sprint 1 excluye eliminación física, permisos individuales, tablas nuevas de privilegios, administración del catálogo de roles, reactivación de usuarios, recuperación de contraseña y módulos de otros sprints. El cambio de contraseña durante la edición queda sujeto a DEC-03.

El criterio académico de conductores activos para asignación se conserva; su verificación en Sprint 1 y su integración futura con viajes se delimitan en DEC-06. No se incorpora gestión de viajes a este sprint.

## 3. Esquema y reglas de datos

Evidencia histórica: conexión PDO a sfatza, MariaDB 12.0.2, tres tablas InnoDB y collation utf8mb4_uca1400_ai_ci. Las 13 columnas y las restricciones siguientes coinciden con el esquema solicitado.

| Tabla | Columna | Tipo y restricciones |
| --- | --- | --- |
| usuario | id_usuario | BIGINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| usuario | id_rol | SMALLINT UNSIGNED, NOT NULL, FK a rol.id_rol |
| usuario | nombre | VARCHAR(120), NOT NULL |
| usuario | usuario | VARCHAR(50), NOT NULL, UNIQUE |
| usuario | hash_contrasena | VARCHAR(255), NOT NULL |
| usuario | activo | TINYINT(1), NOT NULL, DEFAULT 1 |
| rol | id_rol | SMALLINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| rol | nombre | VARCHAR(30), NOT NULL, UNIQUE |
| conductor | id_conductor | BIGINT UNSIGNED, NOT NULL, PK, AUTO_INCREMENT |
| conductor | nombre | VARCHAR(120), NOT NULL |
| conductor | numero_licencia | VARCHAR(40), NULL, DEFAULT NULL, UNIQUE |
| conductor | telefono | VARCHAR(25), NULL, DEFAULT NULL |
| conductor | activo | TINYINT(1), NOT NULL, DEFAULT 1 |

La FK fk_usuario_rol tiene ON UPDATE RESTRICT y ON DELETE RESTRICT. Se verificaron siete índices BTREE, sin prefijos: tres PK, tres UNIQUE y el índice no único de usuario.id_rol.

En el momento de la auditoría había cero usuarios, cero conductores y exactamente ADMINISTRADOR, CAJERO y CHECADORA en rol. Es una instantánea histórica; estos documentos no consultaron nuevamente MariaDB.

| Regla | Definición y fundamento |
| --- | --- |
| R-DAT-01 | Registro de usuario: nombre, identificador de usuario, contraseña y rol obligatorios; hash_contrasena se obtiene en servidor. SRC-00, SRC-04 y esquema. |
| R-DAT-02 | Nombre de conductor obligatorio. Licencia y teléfono opcionales; ausencia representable mediante NULL. SRC-00 y esquema. |
| R-DAT-03 | Respetar límites de caracteres: nombres 120, usuario 50, licencia 40 y teléfono 25. Validar en servidor, sin truncar silenciosamente. El límite 255 corresponde al hash almacenado, no a la contraseña original. |
| R-DAT-04 | Roles seleccionables únicamente del catálogo confirmado y de la lista permitida en código. Resolver id_rol en servidor; no asumir IDs numéricos fijos. |
| R-DAT-05 | Usuario único y licencia única cuando exista. Una edición que conserva su propio valor no debe confundirse con el registro de otra entidad. La comprobación previa se complementa con UNIQUE y tratamiento de conflictos. |
| R-DAT-06 | Conservar registros al desactivar. Estado funcional activo/inactivo, validado como 1/0; no hay CHECK de estado en el esquema auditado. |
| R-DAT-07 | Las altas utilizan el estado inicial DEFAULT 1. Identificadores y hash no se editan directamente desde formularios; los IDs identifican registros existentes y se validan en servidor. |
| R-DAT-08 | La equivalencia de duplicados debe respetar la collation real. Reglas adicionales de normalización, espacios y formatos quedan en DEC-07. No inventar expresiones regulares de licencia o teléfono. |

## 4. Matriz RBAC aprobada

R-AUT-01: permisos por rol definidos en código, sin permisos individuales ni nuevas tablas. R-AUT-02: comprobar autenticación, usuario activo y rol vigente en cada petición protegida.

| Operación | ADMINISTRADOR activo | CAJERO activo | CHECADORA activa | Sin sesión válida o usuario inactivo |
| --- | --- | --- | --- | --- |
| Buscar y visualizar usuarios | Permitido | Denegado | Denegado | Denegado |
| Registrar usuarios | Permitido | Denegado | Denegado | Denegado |
| Modificar usuarios y su rol | Permitido | Denegado | Denegado | Denegado |
| Desactivar usuarios | Permitido | Denegado | Denegado | Denegado |
| Buscar y visualizar conductores | Permitido | Denegado | Denegado | Denegado |
| Registrar y modificar conductores | Permitido | Denegado | Denegado | Denegado |
| Activar/desactivar conductores | Permitido | Denegado | Denegado | Denegado |

Los tres roles pueden autenticarse si la cuenta está activa y las credenciales son válidas. Autenticarse no concede gestión a CAJERO o CHECADORA; sus módulos se definirán en sus sprints. Denegar roles desconocidos y operaciones sin permiso. Ocultar controles de interfaz es complementario.

## 5. Autenticación, sesiones y seguridad

Estas medidas técnicas derivan de SRC-01, SRC-02, las habilidades del repositorio y el alcance solicitado. No representan funcionalidad ya implementada.

| Control | Resultado requerido al implementar |
| --- | --- |
| SEG-01 | Generar hashes con password_hash() y verificar con password_verify(). No conservar ni mostrar contraseñas originales; no cargar hashes en las vistas. Política concreta en DEC-04. |
| SEG-02 | Configurar sesiones con modo estricto, cookies exclusivas, HttpOnly y SameSite apropiado; Secure bajo HTTPS. Regenerar ID al autenticar y al detectar cambios de privilegios en la sesión. Invalidar sesión/cookie al salir. Caducidad en DEC-04. |
| SEG-03 | Revalidar usuario activo y rol actual por petición. Una sesión anterior no conserva permisos revocados ni permite seguir operando tras desactivar la cuenta. |
| SEG-04 | Proteger las operaciones de gestión que cambian estado mediante token CSRF impredecible, asociado a la sesión y verificado en servidor antes de persistir. No efectuar cambios mediante GET. El tratamiento CSRF de login y logout se concretará como decisión técnica pendiente en DEC-04; no se presume resuelto aquí. |
| SEG-05 | SQL preparado con parámetros para valores, validación en servidor y listas permitidas para identificadores/ordenación. |
| SEG-06 | Escapar salidas por contexto; para texto HTML usar htmlspecialchars con ENT_QUOTES, ENT_SUBSTITUTE y UTF-8. Evitar interpolación de entradas en JavaScript. |
| SEG-07 | Errores controlados sin SQL, DSN, credenciales, hashes, tokens ni trazas. No confirmar éxito cuando falla la persistencia. Mantener atomicidad cuando una operación requiera varias escrituras. |

La desactivación de usuario exige búsqueda, visualización y confirmación previa; cancelar conserva el estado. La desactivación efectiva cambia activo a 0 y conserva la cuenta. Las prohibiciones de autodesactivación o de desactivar al último administrador no se consideran aprobadas: DEC-05.

HU-03 permite activar y desactivar conservando el registro. Los originales exigen confirmar los cambios realizados, pero no especifican un diálogo previo obligatorio para cada cambio de estado de conductor; no se impone uno como requisito aprobado.

## 6. Criterios de aceptación verificables

Los criterios siguientes concretan SRC-00, SRC-04, SRC-07 y SRC-08. Las decisiones pendientes no se convierten en condiciones aprobadas por incluirse aquí.

### Técnico

| ID | Criterio |
| --- | --- |
| CA-TEC-01 | La configuración permite utilizar la base existente mediante la cuenta de aplicación y verificar estructura y accesos acordados sin modificar tablas. Evidencia histórica parcial disponible. |
| CA-TEC-02 | Cuando una cuenta activa presenta credenciales válidas, el sistema establece una sesión autenticada y resuelve su rol. |
| CA-TEC-03 | Credenciales inválidas, cuenta inexistente o inactiva no producen sesión autenticada y reciben un mensaje general. |
| CA-TEC-04 | Cada petición protegida aplica la matriz RBAC con el estado y rol actuales, incluso usando una sesión anterior. |
| CA-TEC-05 | Tras cerrar sesión, sus credenciales de sesión anteriores no autorizan nuevas peticiones. El tiempo de caducidad se verifica solo después de aprobar DEC-04. |

### HU-01 — Gestión de usuarios

| ID | Caso de uso | Criterio |
| --- | --- | --- |
| CA-01-01 | CU-01 | Un administrador registra una cuenta con datos obligatorios válidos y rol existente; se conserva un hash y se confirma el guardado. |
| CA-01-02 | CU-01, CU-02 | Campos obligatorios omitidos, con NULL o presentes con cadena vacía, longitudes excedidas o rol inválido se rechazan en servidor sin persistir cambios. El tratamiento de cadenas solo con espacios permanece en DEC-07. |
| CA-01-03 | CU-01, CU-02 | Un identificador perteneciente a otro usuario produce error de duplicado sin sobrescribirlo ni crear una segunda cuenta. |
| CA-01-04 | CU-02, CU-03 | La búsqueda carga los datos autorizados de un usuario existente sin contraseña/hash; sin resultados informa que no se encontró y no permite operar sobre un registro inexistente. Criterios de búsqueda en DEC-02. |
| CA-01-05 | CU-02 | Guardar cambios válidos de un usuario existente actualiza los campos de gestión autorizados y confirma el resultado. Tratamiento de contraseña en DEC-03. |
| CA-01-06 | CU-01, CU-02 | Asignar o actualizar el rol guarda una FK válida y los permisos derivados se aplican en las siguientes peticiones protegidas. |
| CA-01-07 | CU-03 | Antes de desactivar se muestra la cuenta y se solicita confirmación; cancelar no modifica su estado. |
| CA-01-08 | CU-03 | Confirmar la desactivación conserva la cuenta con activo=0 e impide login y operaciones protegidas, incluidas las de sesiones anteriores. |
| CA-01-09 | CU-01, CU-02, CU-03 | Un fallo de persistencia devuelve error controlado, sin éxito falso, secretos ni cambios parciales de una operación compuesta. |

### HU-03 — Gestión de conductores

| ID | Caso de uso | Criterio |
| --- | --- | --- |
| CA-03-01 | CU-06 | Un administrador registra un conductor válido con nombre y con o sin licencia/teléfono; se guarda y se confirma el resultado. |
| CA-03-02 | CU-06, CU-07 | Nombre obligatorio omitido, con NULL o presente con cadena vacía, longitudes excedidas o estado inválido se rechazan en servidor sin persistencia. El tratamiento de cadenas solo con espacios permanece en DEC-07. |
| CA-03-03 | CU-06, CU-07 | Una licencia proporcionada que pertenece a otro conductor produce error de duplicado; conservar la propia licencia en edición es válido. |
| CA-03-04 | CU-07 | La búsqueda carga un conductor existente; sin coincidencias informa que no se encontró y no realiza cambios. Criterios de búsqueda en DEC-02. |
| CA-03-05 | CU-07 | Guardar datos válidos y cambiar estado entre activo/inactivo actualiza el conductor y conserva su registro. |
| CA-03-06 | CU-06, CU-07 | El sistema confirma las persistencias exitosas y comunica los fallos sin éxito falso ni cambios parciales. Abandonar una edición antes de guardar no escribe datos. |
| CA-03-07 | Dependencia de asignación futura | Solo conductores activos estarán disponibles para nuevas asignaciones. Conserva el criterio académico; DEC-06 propone verificar en Sprint 1 un contrato de selección de candidatos activos, sin implementar ni simular asignación real a viajes. La integración posterior permanece pendiente. |

## 7. Discrepancias registradas

| ID | Fuentes y diferencia | Tratamiento en esta planificación |
| --- | --- | --- |
| DIS-01 | Backlogs: HU-01/HU-03 en Sprint 1. Carpetas UML: HU-01/HU-02 en Sprint 1 y HU-03 en Sprint 2. | Se usa el alcance explícito del usuario, coincidente con los backlogs; no se trasladan modelos. |
| DIS-02 | UML HU-01 incluye Asignar privilegios. | Se interpreta conforme al RBAC aprobado: permisos derivados del rol en código. No se habilitan privilegios individuales. |
| DIS-03 | Product Backlog: 13 días para Sprint 1; Sprint Backlog: 112 h/14 días. Descripciones: HU-01 7 días y HU-03 5; Product Backlog: HU-03 6. | Se conservan las 14 estimaciones de 8 h y sus responsables en tasks.md. No se recalculan los originales. |
| DIS-04 | Técnico T1 del Sprint 2 vuelve a mencionar tablas/relaciones para conductores, ya incluidas en HU-03 T2 del Sprint 1. | Aclarar preparación inicial frente a ajustes posteriores; no realizar cambios de esquema. |
| DIS-05 | SRC-08 no muestra CU-06/CU-07 en los títulos; el usuario los establece para esta matriz. | Mantener equivalencia por nombre y documentar procedencia de los IDs. |
| DIS-06 | MEMORY.md conserva rama y estado anteriores al PR #4; las guías no incluyen la última auditoría de lectura de tablas. Documentos usan SFATZA y SFATSA. | La evidencia actual se registra aquí y en verificacion.md; no se modifican los archivos originales. |

## 8. Propuestas y decisiones pendientes

| ID | Decisión o propuesta | Estado |
| --- | --- | --- |
| DEC-01 | Aprobar procedimiento CLI para primer administrador con entrada privada de contraseña, cuenta de aplicación y control de ejecución repetida. La creación del registro requiere autorización futura de escritura. | Pendiente de aprobación |
| DEC-02 | Definir campos de búsqueda, coincidencia exacta/parcial y selección entre resultados. No inventar búsqueda, paginación ni filtros obligatorios. | Pendiente de definición |
| DEC-03 | Definir campos editables y si CU-02 permite cambiar contraseña; no exigir una contraseña nueva para toda edición como regla inventada. | Pendiente de definición |
| DEC-04 | Definir política de contraseña (longitudes, algoritmo/coste y límites sin truncamiento), caducidad de sesión y eventual limitación de intentos. Concretar como decisión técnica el tratamiento CSRF de login y logout. Bloqueos, MFA o CAPTCHA son propuestas, no requisitos aprobados. | Pendiente de aprobación |
| DEC-05 | Evaluar protecciones frente a autodesactivación, pérdida del último administrador activo o retirada de su rol. No imponer vetos adicionales sin aprobación. | Pendiente de aprobación |
| DEC-06 | Acordar evidencia del filtro de conductores activos en Sprint 1; propuesta: prueba del contrato de selección, sin implementar ni simular asignación real a viajes, y prueba integrada con viajes en su sprint posterior. | Pendiente de aprobación |
| DEC-07 | Definir normalización de espacios/vacíos y formatos adicionales; respetar NULL opcional y collation sin imponer transformaciones no aprobadas. | Pendiente de definición |
| DEC-08 | Autorizar, si procede, reconciliación de ubicaciones UML, numeración, denominaciones y estimaciones; actualizar memoria/guías por separado. | Pendiente de autorización |
| DEC-09 | Evaluar Figma MCP y versión/incorporación de Bootstrap 5. Conexión, herramientas o dependencias requieren autorización; Figma no es obligatorio. | Pendiente de evaluación |

La revisión de estos documentos no autoriza automáticamente su implementación. Las propuestas que cambian reglas funcionales requieren aprobación explícita.
