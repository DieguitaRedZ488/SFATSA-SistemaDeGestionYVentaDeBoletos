# Plan técnico del Sprint 1 — SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Estado: propuesta para revisión. Alcance autorizado ahora: documentación y rama local.

Referencias: [especificación](spec.md), [tareas](tasks.md), [verificación](verificacion.md), [constitución](../../constitution.md), [guía PDO](../../conexion-pdo-local.md) y [guía Apache](../../configuracion-apache-local.md). Las fuentes y decisiones se identifican en spec.md.

## 1. Punto de partida y arquitectura

El repositorio tiene public/index.php con un mensaje de preparación, las clases de configuración y ConnectionFactory, un comprobador CLI y una suite de infraestructura. Controllers, Models, Services, Views, routes y assets tienen únicamente marcadores. No hay login, CRUD ni repositorios de entidades implementados.

El esquema real ya coincide con lo esperado. La planificación utiliza las tablas existentes sin migraciones, nuevas tablas de permisos ni cambios de columnas. Las ubicaciones y clases siguientes son propuestas de implementación futura; no describen código existente.

| Capa / componente | Responsabilidad propuesta | Ubicación compatible |
| --- | --- | --- |
| Presentación / entrada | Inicialización segura, enrutamiento permitido y composición de dependencias. | public/index.php y routes/ |
| Presentación / controladores | Coordinar solicitudes, autenticación/autorización y respuesta; delegar reglas al servicio. | app/Controllers/: AutenticacionController, UsuarioController, ConductorController |
| Presentación / vistas | Formularios, búsqueda, datos y mensajes; escape contextual y CSRF; sin SQL. | app/Views/ y public/assets/ |
| Negocio / modelos | Representar Usuario, Rol y Conductor conforme al esquema; no serializar hashes hacia la presentación. | app/Models/ |
| Negocio / servicios | Validaciones, autenticación, permisos por rol, usuarios y conductores; políticas aprobadas. | app/Services/: AutenticacionService, AutorizacionService, UsuarioService, ConductorService |
| Acceso a datos | Consultas parametrizadas y mapeo de entidades; recibir PDO mediante inyección. | app/Repositories/: UsuarioRepository, RolRepository, ConductorRepository |
| Infraestructura existente | Carga interna de configuración, validación y creación de PDO. | config/Environment.php, config/database.php, app/Repositories/ConnectionFactory.php |

Los controladores no contienen SQL y los repositorios no deciden permisos de negocio. El servicio de autorización verifica estado y rol actuales para cada petición protegida. Las operaciones permitidas se definen en código por nombre de rol, sin asumir valores fijos de id_rol.

No se presupone Composer ni un framework. La forma concreta de carga de clases se resolverá dentro del stack y del alcance aprobado.

## 2. Integración HTTP con PDO

Propuesta técnica compatible con la configuración documentada:

1. Conservar public/ como raíz web y configurar el manejo de errores de la petición antes de cargar configuración o abrir conexiones.
2. Usar rutas explícitas con método y operación permitidos. Un despachador desde index.php puede funcionar sin reescrituras; la guía documenta AllowOverride None, por lo que no se depende de un .htaccess nuevo.
3. Utilizar Environment, el validador de database.php y ConnectionFactory internamente, sin volcar configuración ni crear archivos privados automáticamente.
4. Crear PDO solo cuando la petición lo necesite y reutilizar esa instancia en los repositorios de la petición mediante inyección; sin singleton ni persistencia.
5. Distinguir falta de autenticación, falta de permiso, validación, inexistencia, duplicados y fallos internos. Usar respuestas y mensajes controlados; los códigos HTTP concretos se verificarán en el diseño técnico.
6. Revalidar acceso antes de leer recursos protegidos o persistir. Aplicar CSRF antes de cualquier cambio de estado.
7. Registrar diagnósticos por categorías, sin argumentos sensibles, mensajes crudos de PDO ni datos personales innecesarios.

La conexión CLI comprobada no demuestra la carga de configuración bajo Apache. La futura validación HTTP debe comprobar el entorno efectivo sin publicar páginas de diagnóstico con secretos ni cambiar PHP/Apache automáticamente.

## 3. Orden incremental

| Incremento | Resultado revisable | Tareas principales |
| --- | --- | --- |
| 0 | Revisar especificación y resolver decisiones que afecten al incremento; mantener las demás como pendientes. | Todas; DEC-01 a DEC-09 según dependencia |
| 1 | Contratos de datos y configuración de aplicación documentados a partir del esquema existente; revisar permisos necesarios con autorización posterior. | S01-TEC-T1, S01-HU01-T2, S01-HU03-T2 |
| 2 | Diseño de formularios y búsquedas conforme a datos y CU; revisión visual. | S01-HU01-T1, S01-HU03-T1; DEC-02, DEC-03, DEC-07 |
| 3 | Base funcional de autenticación: composición HTTP, sesiones, login, cierre y autorización por petición verificados de forma autónoma; procedimiento de primer administrador sujeto a aprobación. Habilita iniciar las operaciones de gestión sin exigir el cierre integrado de Técnico T2. | S01-TEC-T2, entrega inicial; DEC-01 y DEC-04 según comportamiento |
| 4 | Registro de usuarios con rol, hash y duplicados; primera prueba funcional del flujo protegido. | S01-HU01-T3 y parte de S01-HU01-T5 |
| 5 | Búsqueda/carga, modificación, actualización de rol y aplicación del cambio a sesiones existentes. | S01-HU01-T4, S01-HU01-T5 |
| 6 | Desactivación confirmada, conservación y restricción de acceso; cancelación sin cambios. | S01-HU01-T6, S01-HU01-T7 |
| 7 | Registro de conductores con licencia/teléfono opcionales y duplicados. | S01-HU03-T3 |
| 8 | Búsqueda, actualización, activación/desactivación y contrato de selección de conductores activos sujeto a DEC-06; sin implementación ni simulación de asignación real a viajes. | S01-HU03-T4, S01-HU03-T5; DEC-06 |
| 9 | Verificación integrada final de autenticación, RBAC y operaciones de usuarios/conductores; correcciones y evidencia de cierre para revisión antes de cualquier integración Git. | S01-HU01-T7, S01-HU03-T5 y cierre integrado de S01-TEC-T2 |

Las tareas de pruebas acompañan a los incrementos; su cierre requiere resultados verificables. Esta secuencia no sustituye las estimaciones ni los responsables académicos.

### Disponibilidad inicial y cierre integrado

La base funcional de S01-TEC-T2 debe estar disponible antes de iniciar las operaciones de gestión: login correcto/incorrecto/inactivo, sesión y cierre, autorización con estado/rol vigente, controles para las futuras escrituras y errores HTTP controlados. Su evidencia inicial comprende P-TEC-02 a P-TEC-04 y los subcasos autónomos aplicables de P-SEG-01, P-SEG-02, P-SEG-05 y P-SEG-07. Puede verificarse mediante contratos y dobles de prueba autorizados, sin necesitar altas o ediciones de las HU ya implementadas. Las políticas que afecten al comportamiento permanecen sujetas a las decisiones pendientes.

El cierre integrado de S01-TEC-T2 llega después de disponer de las operaciones de HU-01 y HU-03. Entonces se aplican las pruebas comunes a las rutas reales, incluidos cambios de rol, desactivación, CSRF de gestión y concurrencia P-SEG-10. Estos resultados también contribuyen al cierre de S01-HU01-T7 y S01-HU03-T5.

Las dependencias de inicio exigen disponibilidad del entregable necesario, no la finalización completa de toda la tarea de origen. La evidencia integrada posterior contribuye a los cierres finales; no bloquea el inicio de una tarea dependiente ni establece un ciclo de ejecución. La distinción no crea tareas nuevas ni cambia estados, responsables o estimaciones.

## 4. Primer administrador — propuesta DEC-01

Actualmente no hay usuarios, por lo que un login exitoso necesita preparar una cuenta inicial. Se propone un procedimiento CLI posterior, sin endpoint público de instalación:

- Usar la misma cuenta de aplicación y configuración existente; nunca recurrir a una cuenta administradora de MariaDB.
- Solicitar la contraseña mediante entrada privada, sin mostrarla ni pasarla en argumentos; no incluir contraseñas ni hashes reales en Git, documentación o fixtures.
- Resolver ADMINISTRADOR desde el catálogo, validar los datos y generar el hash en memoria con password_hash().
- Persistir mediante consulta preparada solo después de obtener autorización para implementar el procedimiento y escribir datos.
- Definir un control de repetición que evite crear una segunda cuenta por ejecutar accidentalmente otra vez el alta inicial; concretar su comportamiento en DEC-01.
- Informar únicamente éxito o categoría de error. No ampliar permisos ni cambiar tablas para completar el alta.

Se requiere decidir la política de contraseña antes de cerrar el procedimiento. Este documento no contiene un comando de alta ni autoriza ejecutarlo.

## 5. Seguridad, validaciones y persistencia

| Área | Estrategia técnica |
| --- | --- |
| SQL | prepare()/execute() y parámetros para valores. IDs y ordenación requieren validación/listas permitidas. Ningún SQL en vistas. |
| Validación | Verificar tipos, obligatorios, longitudes, IDs existentes, rol del catálogo y estado 0/1 en servidor; validar longitudes de caracteres coherentemente con UTF-8. Rechazar arrays donde se espera un valor escalar. |
| Datos opcionales | Permitir NULL en licencia/teléfono. Acordar la conversión de entradas vacías y la normalización en DEC-07; no convertir ausencia de licencia en un identificador ficticio. |
| Duplicados | Consulta previa compatible con collation y exclusión del propio ID en edición, más UNIQUE como garantía ante concurrencia. Distinguir duplicado de FK inválida sin mostrar SQL ni errores crudos. |
| Contraseñas | Hash/verificación nativos; no devolver hashes desde contratos destinados a vistas. En pruebas autorizadas verificar en memoria con password_verify() que el hash persistido acepta la contraseña esperada y rechaza una distinta, sin imprimir ni registrar contraseñas o hashes. Elegir algoritmo y límites sin truncamiento en DEC-04; definir edición en DEC-03. |
| XSS | Escape según contexto; texto/atributos HTML entre comillas con htmlspecialchars y opciones indicadas en spec.md. No insertar entradas directamente en scripts o atributos de eventos. |
| CSRF y métodos | Proteger operaciones de gestión que cambian estado con tokens aleatorios de sesión comprobados con hash_equals(); rechazar ausentes/incorrectos antes de persistir. GET no cambia estado. SameSite complementa el token. El tratamiento de login/logout es una decisión técnica pendiente en DEC-04; los subcasos correspondientes solo se concretarán al resolverla. |
| Sesiones | Modo estricto, cookies exclusivas, HttpOnly, SameSite apropiado, regeneración al autenticar y al detectar cambio de privilegios. Cierre e invalidación; caducidad acordada. |
| HTTP local | Secure se usa bajo HTTPS. El entorno local está documentado por HTTP; no se activa HTTPS ni se cambia Apache en esta fase. La preparación para producción requiere HTTPS. |
| Autorización | Sesión válida, cuenta activa y rol vigente antes de cada operación protegida. Cambios de rol/desactivación se aplican a sesiones previas mediante revalidación. |
| Errores | Mensajes controlados; ausencia de éxito ante fallo. Desactivar exposición de errores de la petición y sanitizar diagnósticos; nunca volcar configuración, excepciones o secretos. |
| Transacciones | Una escritura única ya es atómica en InnoDB. Si un caso requiere varias escrituras inseparables, compartir PDO, confirmar solo al terminar y revertir ante fallos; no mantener una transacción durante interacción del usuario. |
| Concurrencia | Probar carreras de duplicados y tratar conflictos UNIQUE. La política de edición simultánea requiere revisión si se propone cambiar el comportamiento funcional; no añadir columnas de versión sin autorización. |

La eliminación física no forma parte de las operaciones. Restricciones adicionales de autodesactivación, último administrador, bloqueo de intentos o requisitos extra de contraseña permanecen propuestas en spec.md.

## 6. Estrategia de pruebas

El catálogo detallado está en [verificacion.md](verificacion.md). Sus 46 IDs principales representan escenarios agrupados, con subcasos y combinaciones; no equivalen a 46 pruebas atómicas ni a ejecuciones realizadas. Planificar:

- Funcionales: altas, búsqueda/carga, edición, roles, desactivación de usuarios y cambios de estado de conductores.
- Negativas: obligatorios, tipos inválidos, inexistentes, rol inválido, credenciales incorrectas, cuenta inactiva y duplicados.
- Límites: exactamente 120/50/40/25 caracteres y un carácter adicional; entradas multibyte; nulos en campos obligatorios y opcionales.
- Seguridad: RBAC por ruta y método, sesión anterior a cambio de rol/desactivación, fijación de sesión, cierre, caducidad acordada, CSRF, XSS y entradas simuladas de SQL injection no destructivas.
- Persistencia/fallos: repositorios dobles para fallos controlados y, cuando se autorice, integraciones en entorno aislado; comprobar ausencia de éxito falso y de efectos parciales.
- Concurrencia: dos solicitudes de prueba con el mismo identificador o licencia ficticios; verificar una sola alta válida y tratamiento de la otra.
- Interfaz: correspondencia de formularios con esquema, roles y flujos UML; no precargar contraseña/hash.

Para integración con escrituras se necesita autorización específica y un ambiente local de pruebas aislado con datos ficticios y preparación/limpieza acordadas. No reutilizar datos personales ni modificar la base actual por ejecutar una prueba. No detener el servicio real ni provocar fallos alterando permisos o esquema.

La suite existente tests/pdo-infrastructure.php crea un temporal y no se ejecuta en esta fase documental. No hay pruebas funcionales implementadas. La evidencia histórica de infraestructura no se contabiliza como prueba de login o CRUD.

## 7. Figma MCP y dependencias

Figma MCP es una opción para diseñar y revisar formularios. Evaluar acceso disponible, permisos, utilidad y artefactos exportables; cualquier conexión o instalación requiere autorización. No es una dependencia obligatoria ni se invocó en esta fase.

El diseño puede revisarse con los artefactos del repositorio y el stack HTML5/CSS3/Bootstrap 5/JavaScript. La versión específica y la forma de incorporar Bootstrap permanecen en DEC-09; no se descargan dependencias ahora.

| Dependencia / riesgo | Estado y acción propuesta |
| --- | --- |
| PDO CLI y esquema | Confirmados en auditoría previa; conservar evidencia y no recrear tablas. |
| Permisos SQL | SELECT comprobado; permisos de escritura no probados. Revisarlos con autorización sin usar root ni ampliarlos automáticamente. |
| Entorno HTTP | VirtualHost documentado; PDO por HTTP pendiente. Comprobar diferencias de configuración Apache/CLI posteriormente. |
| Cuenta inicial | Cero usuarios; necesita DEC-01 y permiso de escritura posterior. |
| Políticas funcionales | DEC-02 a DEC-07; resolver antes de implementar el comportamiento dependiente. |
| Documentación | DIS-01 a DIS-06; registrar sin corregir fuentes académicas. |
| Reproducibilidad del esquema | No hay SQL versionado. Esta planificación registra el contrato; cualquier artefacto adicional requiere autorización. |
| Verificación de activos para viajes | Acordar prueba del contrato de selección en Sprint 1, sin implementar ni simular asignación real; mantener la integración futura explícita. |
| Estimaciones | Conservar 112 h/14 días del Sprint Backlog; no convertir la secuencia técnica en una nueva estimación. |

## 8. Condiciones para iniciar implementación

Revisión de los cuatro documentos, aprobación del alcance a implementar y resolución de las decisiones que afecten al incremento. Obtener por separado autorización para escrituras/pruebas en BD, preparación del primer administrador y cualquier dependencia.

Crear estos documentos no completa ninguna tarea ni autoriza commits, push, PR o cambios en archivos ajenos.
