# Sprint Backlog técnico — Sprint 1 de SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Estado: planificación para revisión.

Fuente: [Sprint_Backlog.docx](../../../Documentos/Sprint_Backlog.docx), filas de Sprint 01. Se conservan exactamente las 14 descripciones, los responsables y las estimaciones académicas. Los IDs inequívocos son referencias nuevas de seguimiento; no renumeran los originales.

El documento académico indica Pendiente en todas las filas. El estado técnico inicial autorizado por el usuario es Técnico T1 PARCIAL y las otras 13 PENDIENTES. Las auditorías posteriores del esquema se registran como evidencia sin cambiar esos estados ni declarar tareas completadas.

## 1. Tareas originales y estado inicial

| ID inequívoco | Identificador académico | Descripción exacta | Responsable académico | Estimación | Estado técnico |
| --- | --- | --- | --- | --- | --- |
| S01-TEC-T1 | Técnico T1 | Creación y configuración inicial de la base de datos | Enc. Base de Datos | 8 h | PARCIAL |
| S01-TEC-T2 | Técnico T2 | Implementación del login, autenticación y control de acceso por rol | Programador | 8 h | PENDIENTE |
| S01-HU01-T1 | HU-01 T1 | Diseño del formulario de gestión de usuarios | Diseño | 8 h | PENDIENTE |
| S01-HU01-T2 | HU-01 T2 | Preparación de la estructura de usuarios y roles | Enc. Base de Datos | 8 h | PENDIENTE |
| S01-HU01-T3 | HU-01 T3 | Programación del registro y validación de usuario duplicado | Programador | 8 h | PENDIENTE |
| S01-HU01-T4 | HU-01 T4 | Programación de modificación y carga de datos de usuarios | Programador | 8 h | PENDIENTE |
| S01-HU01-T5 | HU-01 T5 | Asignación y actualización de roles y privilegios | Programador | 8 h | PENDIENTE |
| S01-HU01-T6 | HU-01 T6 | Desactivación, confirmación y restricción de acceso | Programador | 8 h | PENDIENTE |
| S01-HU01-T7 | HU-01 T7 | Pruebas funcionales y correcciones de gestión de usuarios | Análisis | 8 h | PENDIENTE |
| S01-HU03-T1 | HU-03 T1 | Diseño del formulario de gestión de conductores | Diseño | 8 h | PENDIENTE |
| S01-HU03-T2 | HU-03 T2 | Preparación de la estructura de datos de conductores | Enc. Base de Datos | 8 h | PENDIENTE |
| S01-HU03-T3 | HU-03 T3 | Programación del registro y validación de conductores | Programador | 8 h | PENDIENTE |
| S01-HU03-T4 | HU-03 T4 | Modificación, activación y desactivación de conductores | Programador | 8 h | PENDIENTE |
| S01-HU03-T5 | HU-03 T5 | Pruebas funcionales y correcciones | Análisis | 8 h | PENDIENTE |


Total académico: **14 tareas × 8 h = 112 h = 14 días de 8 horas**. El responsable global de una HU en otro documento no sustituye al responsable de cada tarea aquí.

## 2. Objetivos y criterios de finalización

Los criterios CA, controles SEG, decisiones DEC y evidencias EV se describen en [spec.md](spec.md) y [verificacion.md](verificacion.md). Las dependencias siguientes son propuestas técnicas, no modificaciones de las dependencias académicas.

Una dependencia de inicio exige que esté disponible el entregable necesario, no que se haya cerrado toda la tarea precedente. Para gestión de usuarios/conductores se requiere la base funcional de S01-TEC-T2: autenticación HTTP, sesión/cierre y autorización vigentes, controles de futuras escrituras y errores seguros, verificables autónomamente. El cierre integrado de esa tarea se realiza después, con las operaciones de ambas HU disponibles.

Las pruebas funcionales comunes pueden contribuir al cierre final de varias tareas. P-SEG-10 y otros subcasos que necesitan gestión implementada no bloquean su inicio. De igual modo, los contratos de datos pueden estar disponibles antes del login/CRUD; sus pruebas funcionales posteriores contribuyen al cierre final sin exigir esas operaciones para comenzar el contrato. Las referencias de evidencia son contribuciones al cierre, no prerrequisitos de implementación de todas las pruebas mencionadas.

### S01-TEC-T1

- Objetivo: disponer de la configuración inicial de la base existente y de evidencia reproducible de su utilización.
- Finalización: CA-TEC-01; configuración y cuenta de aplicación verificadas, contrato de las tres tablas y catálogo documentados, y alcance de permisos necesarios acordado/verificado con autorización posterior. No recrear ni alterar tablas.
- Dependencias: revisión de esta planificación y autorización separada para verificaciones adicionales que impliquen escrituras.
- Evidencia esperada: EV-03, EV-04 y EV-05 como antecedentes; registro seguro de comprobaciones adicionales, límites y revisión de permisos sin credenciales.
- Estado: PARCIAL. PDO CLI, esquema y lecturas ya están comprobados; la evidencia no autoriza cierre automático ni prueba de escrituras.

### S01-TEC-T2

- Objetivo: autenticar y aplicar RBAC por petición.
- Finalización: CA-TEC-02 a CA-TEC-05 y SEG-01 a SEG-07; login, sesión, cierre, cuenta activa y matriz RBAC comprobados por HTTP sin exposición de información sensible. La entrega inicial habilita gestión; el cierre final incluye la verificación integrada posterior.
- Dependencias de inicio: configuración/PDO disponibles de S01-TEC-T1, contrato de usuarios/roles disponible de S01-HU01-T2 y decisiones aplicables DEC-01/DEC-04. No depende de que estén implementadas las operaciones de las HU; sí requiere las cuentas necesarias, preparadas por un procedimiento autorizado. La verificación integrada requiere esas operaciones después, como condición de cierre y no de inicio.
- Evidencia esperada: entrega inicial con P-TEC-02 a P-TEC-04 y subcasos autónomos aplicables de P-SEG-01, P-SEG-02, P-SEG-05 y P-SEG-07; P-TEC-01 aporta evidencia de infraestructura. Tras implementar gestión, P-SEG-01 a P-SEG-10 y las pruebas pertinentes de rol/desactivación contribuyen al cierre integrado. Flujo HTTP y rechazos registrados con datos ficticios; ninguna ejecución se presume realizada.
- Estado: PENDIENTE.

### S01-HU01-T1

- Objetivo: diseñar los formularios y navegación de gestión de usuarios conforme a los CU.
- Finalización: campos obligatorios, rol, búsqueda/carga, edición y confirmación/cancelación de desactivación revisados; mensajes previstos; ninguna contraseña/hash precargada.
- Dependencias: especificación revisada, DEC-02 y DEC-03; Figma es opcional.
- Evidencia esperada: diseño o capturas del futuro formulario y P-UI-01 vinculados a CA-01-01, CA-01-04, CA-01-05 y CA-01-07.
- Estado: PENDIENTE.

### S01-HU01-T2

- Objetivo: preparar el contrato de usuarios y roles usando las tablas confirmadas.
- Finalización: modelo y mapeo de repositorios compatibles con columnas, longitudes, PK, UNIQUE y FK; catálogo resuelto sin IDs fijos ni tablas de privilegios.
- Dependencias: S01-TEC-T1.
- Evidencia esperada: EV-04/EV-05 como base histórica; contrato de acceso y validaciones R-DAT-01, R-DAT-03 a R-DAT-08, comprobados por P-01-02, P-01-03 y P-01-05.
- Estado: PENDIENTE. El esquema confirmado no acredita por sí solo el contrato de aplicación.

### S01-HU01-T3

- Objetivo: registrar usuarios válidos y rechazar duplicados.
- Finalización: CA-01-01, CA-01-02, CA-01-03 y CA-01-09; hash generado, rol validado, UNIQUE protegido ante concurrencia y errores sin éxito falso.
- Dependencias de inicio: diseño/contrato disponibles de S01-HU01-T1 y S01-HU01-T2, base funcional de S01-TEC-T2 y política aplicable de DEC-04. No requiere cierre integrado de S01-TEC-T2.
- Evidencia esperada: P-01-01 a P-01-05, P-01-14 y P-SEG-10; revisión de consulta parametrizada y ausencia de contraseñas en salida/logs.
- Estado: PENDIENTE.

### S01-HU01-T4

- Objetivo: buscar, cargar y modificar usuarios existentes.
- Finalización: CA-01-02 a CA-01-05 y CA-01-09; carga sin secretos, inexistentes controlados, edición válida y distinción del propio identificador frente a duplicados ajenos.
- Dependencias: S01-HU01-T3, DEC-02 y DEC-03.
- Evidencia esperada: P-01-06 a P-01-09 y P-01-14; datos ficticios antes/después sin hashes.
- Estado: PENDIENTE.

### S01-HU01-T5

- Objetivo: asignar y actualizar roles y sus permisos derivados en código.
- Finalización: CA-01-06 y CA-TEC-04; FK válida, matriz RBAC aplicada y cambios efectivos en peticiones siguientes de sesiones existentes; sin permisos individuales.
- Dependencias de inicio: base funcional de S01-TEC-T2 y registro disponible de S01-HU01-T3; actualización integrada con S01-HU01-T4. La prueba conjunta de permisos contribuye al cierre integrado posterior.
- Evidencia esperada: P-01-05, P-01-10 y P-SEG-01; comprobación de ausencia de persistencia de privilegios adicionales.
- Estado: PENDIENTE.

### S01-HU01-T6

- Objetivo: desactivar cuentas confirmando la operación y restringir su acceso.
- Finalización: CA-01-07, CA-01-08 y CA-01-09; cancelación sin cambios, conservación del registro y rechazo de login/peticiones con sesión previa.
- Dependencias de inicio: búsqueda/carga disponibles de S01-HU01-T4 y base funcional de S01-TEC-T2. DEC-05 requiere decisión antes de imponer vetos adicionales, que no se consideran parte aprobada. La prueba de sesión previa aporta evidencia al cierre integrado.
- Evidencia esperada: P-01-07 y P-01-11 a P-01-14; estado y conservación con datos ficticios; propuestas DEC-05 separadas en P-DEC-02.
- Estado: PENDIENTE.

### S01-HU01-T7

- Objetivo: ejecutar pruebas funcionales de usuarios y corregir fallos.
- Finalización: criterios aprobados de HU-01 y controles pertinentes verificados; decisiones que afecten al alcance resueltas, defectos corregidos y resultados/limitaciones registrados, sin afirmar cobertura total.
- Dependencias de ejecución: operaciones disponibles de S01-HU01-T3 a S01-HU01-T6, base funcional de S01-TEC-T2 y autorización del ambiente/pruebas con escrituras. Sus resultados integrados contribuyen al cierre de S01-TEC-T2; no exigen ese cierre antes de ejecutarse.
- Evidencia esperada: resultados reales del catálogo P-01, P-UI-01 y P-SEG, con contexto, revisión y comprobaciones de correcciones.
- Estado: PENDIENTE.

### S01-HU03-T1

- Objetivo: diseñar formularios y navegación de conductores.
- Finalización: nombre obligatorio, licencia/teléfono opcionales, búsqueda, carga, edición y controles de estado compatibles con CU-06/CU-07; mensajes de errores y confirmación de cambios.
- Dependencias: especificación revisada, DEC-02 y DEC-07; Figma opcional.
- Evidencia esperada: diseño o capturas posteriores y P-UI-03; relación con CA-03-01, CA-03-04 a CA-03-06.
- Estado: PENDIENTE.

### S01-HU03-T2

- Objetivo: preparar el contrato de datos de conductores.
- Finalización: modelo y mapeo compatibles con tipos, longitudes, NULL, UNIQUE y estado; licencia opcional sin identificador inventado; no alterar tablas.
- Dependencias: S01-TEC-T1 y definición aplicable de DEC-07.
- Evidencia esperada: EV-04 como antecedente; contrato R-DAT-02, R-DAT-03, R-DAT-05 a R-DAT-08 y pruebas P-03-02 a P-03-06.
- Estado: PENDIENTE. La tabla existente no acredita la implementación del contrato.

### S01-HU03-T3

- Objetivo: registrar conductores y validar los datos/licencia.
- Finalización: CA-03-01 a CA-03-03 y CA-03-06; altas con/sin opcionales, duplicados y fallos controlados; consultas parametrizadas.
- Dependencias de inicio: diseño/contrato disponibles de S01-HU03-T1 y S01-HU03-T2 y base funcional de S01-TEC-T2. No requiere cierre integrado de S01-TEC-T2.
- Evidencia esperada: P-03-01 a P-03-05, P-03-13 y P-SEG-10, con datos ficticios.
- Estado: PENDIENTE.

### S01-HU03-T4

- Objetivo: buscar, cargar, editar y cambiar el estado de conductores.
- Finalización: CA-03-02 a CA-03-06; actualización válida, duplicados en edición, inexistentes, activación/desactivación y conservación. CA-03-07 se delimita mediante DEC-06 como contrato de selección, sin implementar ni simular asignación real a viajes.
- Dependencias: S01-HU03-T3, DEC-02 y DEC-06 para el criterio de asignación.
- Evidencia esperada: P-03-06 a P-03-14; registros ficticios antes/después y límites de la integración futura.
- Estado: PENDIENTE.

### S01-HU03-T5

- Objetivo: ejecutar pruebas funcionales de conductores y corregir fallos.
- Finalización: criterios aprobados de HU-03 y controles pertinentes verificados; licencia opcional, límites, estados y errores; acuerdo explícito sobre CA-03-07 e integración futura.
- Dependencias de ejecución: operaciones disponibles de S01-HU03-T3/S01-HU03-T4, base funcional de S01-TEC-T2, DEC-06 y autorización del ambiente/pruebas con escrituras. Sus resultados integrados contribuyen al cierre de S01-TEC-T2; no exigen ese cierre antes de ejecutarse.
- Evidencia esperada: resultados reales de P-03, P-UI-03 y P-SEG; defectos/correcciones y verificación de contrato acordada, sin declarar probada la asignación a viajes.
- Estado: PENDIENTE.

## 3. Discrepancias y actualización de estados

- Sprint Backlog: 112 h/14 días. Product Backlog: 13 días para Sprint 1.
- Descripciones: HU-03 5 días; Product Backlog: HU-03 6 días.
- Carpetas UML y tarea técnica de conductores en Sprint 2 difieren de la asignación actual; ver DIS-01, DIS-04 y DIS-05 en spec.md.
- No se cambian responsables, horas ni documentos originales. Los IDs y dependencias técnicas se mantienen separados de las columnas académicas.
- Ninguna fila está COMPLETADA. Para cambiar un estado se requiere evidencia del resultado de la tarea y revisión; la creación de documentación o la existencia de tablas no equivale a funcionalidad terminada.
