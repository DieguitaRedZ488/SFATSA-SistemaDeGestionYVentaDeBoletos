# Plan técnico del Sprint 1 — SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Plan incremental con avance implementado: bootstrap (Prompt 15), login (Prompt 17), HU-01 (Prompt 18), HU-03/reactivación de usuarios/protección de roles (Prompt 19), licencia obligatoria/migración preparada sin ejecutar DDL (Prompt 20) y formato de licencia de 14 caracteres ASCII alfanuméricos en mayúsculas (Prompt 23). Las pruebas manuales nuevas permanecen pendientes.

Referencias: [especificación](spec.md), [tareas](tasks.md), [verificación](verificacion.md), [constitución](../../constitution.md), [guía PDO](../../conexion-pdo-local.md) y [guía Apache](../../configuracion-apache-local.md). Las fuentes y decisiones se identifican en spec.md.

## 1. Punto de partida y arquitectura

public/index.php compone login, HU-01 y HU-03 con rutas explícitas. Reutiliza una conexión PDO diferida por petición, Environment y ConnectionFactory; no contiene credenciales. AutenticacionController proporciona la identidad activa/rol vigente a UsuarioController y ConductorController; se comparten sesiones, CSRF y autorización. El bootstrap CLI existente queda separado y no se vuelve a ejecutar.

HU-01 implementada: UsuarioController, UsuarioService, GestionUsuarioRepository/PdoGestionUsuarioRepository, vistas de listado/formulario/confirmación y assets propios. El repositorio de autenticación conserva su contrato de lectura; el de gestión no devuelve hashes y concentra SELECT/INSERT/UPDATE parametrizados. Edición conserva contraseña y estado; desactivación/reactivación solo cambian activo. La reactivación es la ampliación de equipo SRC-16. Sin DELETE, nuevos roles/esquema o permisos individuales. [Rutas, operación y pruebas](../../gestion-usuarios.md).

HU-03 implementada: ConductorController, ConductorService, ErrorGestionConductores y ConductorRepository/PdoConductorRepository; vistas de listado, formulario y estado. Reutiliza partials/gestion-inicio.php y gestion-fin.php y los assets de gestión existentes. Registro activo, edición que conserva estado y confirmación independiente para activación/desactivación. Prompt 23 añade validación de 14 caracteres ASCII alfanuméricos, conversión a mayúsculas y rechazo de espacios/símbolos; aplica en registro y edición. La columna se mantiene VARCHAR(40), UNIQUE; migración reversible para NOT NULL preparada, sin ejecutar. La inspección read-only anterior de Prompt 20 halló un conductor válido a esa regla vieja; Prompt 23 encontró dos registros incompatibles con el nuevo formato (longitud/minúsculas), sin mostrar valores ni modificarlos. Ver README de migraciones y SRC-18–SRC-20. [Rutas y guía manual](../../gestion-conductores.md).

Cada escritura valida datos y CSRF y reutiliza OperacionAdministrativaService: transacción InnoDB, bloqueo inicial de la fila ADMINISTRADOR del catálogo con SELECT ... FOR UPDATE y revalidación/bloqueo del actor antes del objetivo. Repositorios de usuarios y conductores comparten la misma PDO durante la acción. Este orden serializa sus escrituras; antes de desactivar o retirar el rol de un administrador activo se cuenta mediante lectura bloqueante actual y se rechaza si quedaría cero, incluido el cambio propio. UNIQUE/FK siguen como respaldo. Commit solo al terminar; rollback ante fallo y errores seguros. Concurrencia, collation y permisos reales quedan pendientes en ambiente autorizado. Escritores externos que no respetan el protocolo quedan fuera de su garantía.

La migración 20261013_001 está **APLICADA Y VERIFICADA MANUALMENTE, SEGÚN CONFIRMACIÓN DEL USUARIO**: nombres/apellidos VARCHAR(120) NOT NULL, nombre antiguo ausente en usuario/conductor y 22 FK conservadas en sfatza. El usuario confirmó respaldo privado restaurado y ensayo de ALTER en sfatza_restore_test_20261013, con tablas vacías antes de UP. No se repitieron operaciones o consultas de BD aquí. El [README de migraciones](../../../database/migrations/README.md) y EV-08 registran procedencia y límites; UP/DOWN se conservan idénticos, con comentarios históricos de preparación. La tabla conserva el diseño de tres capas; login y ambas HU reutilizan el contrato existente.

Migración 20261013_002 para `conductor.numero_licencia NOT NULL` está preparada, **no ejecutada**. Una consulta de solo lectura encontró tipo `VARCHAR(40)`, NULL permitido, DEFAULT NULL, collation `utf8mb4_uca1400_ai_ci`, índice UNIQUE intacto, un conductor y cero licencias nulas, vacías/solo espacios o duplicadas. La aplicación ya exige licencia en alta/edición. UP/DOWN, respaldo, recuperación y precondiciones están documentados en el README; ninguna escritura/DDL se realizó.

| Capa / componente | Responsabilidad propuesta | Ubicación compatible |
| --- | --- | --- |
| Presentación / entrada | Inicialización segura, enrutamiento permitido y composición de dependencias. | public/index.php y routes/ |
| Presentación / controladores | Coordinar solicitudes, autenticación/autorización y respuesta; delegar reglas al servicio. | app/Controllers/: AutenticacionController, UsuarioController, ConductorController |
| Presentación / vistas | Formularios, búsqueda, datos y mensajes; escape contextual y CSRF; sin SQL. | app/Views/ y public/assets/ |
| Negocio / modelos | Representar Usuario, Rol y Conductor conforme al esquema; no serializar hashes hacia la presentación. | app/Models/ |
| Negocio / servicios | Validaciones, autenticación, permisos por rol, usuarios y conductores; políticas aprobadas. | app/Services/: AutenticacionService, AutorizacionService, UsuarioService, ConductorService |
| Acceso a datos | Consultas parametrizadas y mapeo de datos; PDO por inyección, sin repositorio adicional de roles innecesario. | app/Repositories/: UsuarioRepository/PdoUsuarioRepository; GestionUsuarioRepository/PdoGestionUsuarioRepository; ConductorRepository/PdoConductorRepository |
| Infraestructura existente | Carga interna de configuración, validación y creación de PDO. | config/Environment.php, config/database.php, app/Repositories/ConnectionFactory.php |

HU-01 representa nombres/apellidos independientemente; sus vistas capturan dos obligatorios con validación en servidor, hasta 120 caracteres UTF-8 y rechazo de vacío/solo espacios. Recorta únicamente espacios exteriores; carga/guardado mantienen ambos campos separados. El listado compone el nombre completo sin columna adicional y busca parcialmente en nombres, apellidos o usuario. HU-03 aplica el mismo contrato personal y busca parcialmente por nombres, apellidos o licencia; mantiene DEC-06 para selección/asignación futura.

Los controladores no contienen SQL y los repositorios no deciden permisos de negocio. El servicio de autorización verifica estado y rol actuales para cada petición protegida. Las operaciones permitidas se definen en código por nombre de rol, sin asumir valores fijos de id_rol.

La carga explícita en app/bootstrap-web.php no abre conexiones/sesiones al incluirse. Se reutiliza Bootstrap CSS local y JavaScript propio, sin Composer, paquetes nuevos o framework PHP.

## 2. Integración HTTP con PDO

Propuesta técnica compatible con la configuración documentada:

1. Conservar public/ como raíz web y configurar el manejo de errores de la petición antes de cargar configuración o abrir conexiones.
2. Usar rutas explícitas con método y operación permitidos. Un despachador desde index.php puede funcionar sin reescrituras; la guía documenta AllowOverride None, por lo que no se depende de un .htaccess nuevo.
3. Utilizar Environment, el validador de database.php y ConnectionFactory internamente, sin volcar configuración ni crear archivos privados automáticamente.
4. Crear PDO solo cuando la petición lo necesite y reutilizar esa instancia en los repositorios de la petición mediante inyección; sin singleton ni persistencia.
5. Distinguir falta de autenticación, falta de permiso, validación, inexistencia, duplicados y fallos internos. Usar respuestas y mensajes controlados; los códigos HTTP concretos se verificarán en el diseño técnico.
6. Revalidar acceso antes de leer recursos protegidos o persistir. Aplicar CSRF antes de cualquier cambio de estado.
7. Registrar diagnósticos por categorías, sin argumentos sensibles, mensajes crudos de PDO ni datos personales innecesarios.

La conexión CLI por sí sola no demuestra el entorno Apache. Login y operaciones HU-01 reportadas por el usuario aportan evidencia HTTP real; no prueban las nuevas operaciones HU-03/reactivación o todos los permisos y bloqueos. No publicar diagnósticos con secretos ni cambiar PHP/Apache automáticamente.

## 3. Orden incremental

| Incremento | Resultado revisable | Tareas principales |
| --- | --- | --- |
| 0 | Revisar especificación y resolver decisiones que afecten al incremento; mantener las demás como pendientes. | Todas; DEC-01 a DEC-09 según dependencia |
| 1 | Mantener antecedentes del esquema anterior y registrar el contrato migrado de nombres/apellidos según EV-08; preparar contratos PHP bajo autorización de implementación. No reejecutar UP ni confundir el cambio de esquema con CRUD o autenticación implementados. | S01-TEC-T1, S01-HU01-T2, S01-HU03-T2 |
| 2 | Diseño de formularios y búsquedas conforme a datos y CU; revisión visual. | S01-HU01-T1, S01-HU03-T1; DEC-02, DEC-03, DEC-07 |
| 3 | Base funcional de autenticación: composición HTTP, sesiones, login, cierre y autorización por petición verificados de forma autónoma; bootstrap implementado y alta/login reales reportados por el usuario. Habilita iniciar las operaciones de gestión sin exigir el cierre integrado de Técnico T2. | S01-TEC-T2, entrega inicial; DEC-01 y DEC-04 según comportamiento |
| 4 | Registro de usuarios con rol, hash y duplicados; primera prueba funcional del flujo protegido. | S01-HU01-T3 y parte de S01-HU01-T5 |
| 5 | Búsqueda/carga, modificación, actualización de rol y aplicación del cambio a sesiones existentes. | S01-HU01-T4, S01-HU01-T5 |
| 6 | Desactivación confirmada y conservación. SRC-16 añade reactivación y protección del último administrador ante desactivación/cambio de rol. | S01-HU01-T5, S01-HU01-T6, S01-HU01-T7 |
| 7 | Registro de conductores con licencia obligatoria/única y teléfono opcional, límites reales y duplicados. | S01-HU03-T3 |
| 8 | Búsqueda, actualización, activación/desactivación y contrato de selección de conductores activos sujeto a DEC-06; sin implementación ni simulación de asignación real a viajes. | S01-HU03-T4, S01-HU03-T5; DEC-06 |
| 9 | Verificación integrada final de autenticación, RBAC y operaciones de usuarios/conductores; correcciones y evidencia de cierre para revisión antes de cualquier integración Git. | S01-HU01-T7, S01-HU03-T5 y cierre integrado de S01-TEC-T2 |

Las tareas de pruebas acompañan a los incrementos; su cierre requiere resultados verificables. Esta secuencia no sustituye las estimaciones ni los responsables académicos.

### Disponibilidad inicial y cierre integrado

La base funcional de S01-TEC-T2 debe estar disponible antes de iniciar las operaciones de gestión: login correcto/incorrecto/inactivo, sesión y cierre, autorización con estado/rol vigente, controles para las futuras escrituras y errores HTTP controlados. Su evidencia inicial comprende P-TEC-02 a P-TEC-04 y los subcasos autónomos aplicables de P-SEG-01, P-SEG-02, P-SEG-05 y P-SEG-07. Puede verificarse mediante contratos y dobles de prueba autorizados, sin necesitar altas o ediciones de las HU ya implementadas. Las políticas que afecten al comportamiento permanecen sujetas a las decisiones pendientes.

El cierre integrado de S01-TEC-T2 llega después de disponer de las operaciones de HU-01 y HU-03. Entonces se aplican las pruebas comunes a las rutas reales, incluidos cambios de rol, desactivación, CSRF de gestión y concurrencia P-SEG-10. Estos resultados también contribuyen al cierre de S01-HU01-T7 y S01-HU03-T5.

Las dependencias de inicio exigen disponibilidad del entregable necesario, no la finalización completa de toda la tarea de origen. La evidencia integrada posterior contribuye a los cierres finales; no bloquea el inicio de una tarea dependiente ni establece un ciclo de ejecución. La distinción no crea tareas nuevas ni cambia estados, responsables o estimaciones.

## 4. Primer administrador — DEC-01 implementada

Prompt 15 aprobó DEC-01 y DEC-04 y autorizó exclusivamente este mecanismo, sus pruebas aisladas y documentación. No se ejecutó un alta real ni se abrió conexión a MariaDB.

| Capa | Archivos del incremento | Responsabilidad |
| --- | --- | --- |
| Composición CLI | scripts/crear-administrador-inicial.php | Rechazo HTTP previo a configuración, errores seguros y fábrica diferida con infraestructura PDO existente. |
| Presentación CLI | app/Console/CrearAdministradorInicialCommand.php; Terminal.php; TerminalWindows.php | Captura interactiva, rechazo de argumentos/terminal incompatible, contraseña sin eco y mensajes fijos. |
| Negocio | app/Services/AdministradorInicialService.php; ErrorAdministradorInicial.php | Validación, rol exclusivo ADMINISTRADOR, idempotencia y coordinación de exclusión/transacción/recuperación. |
| Datos | app/Repositories/AdministradorInicialRepository.php; PdoAdministradorInicialRepository.php | Consultas preparadas sobre rol/usuario y GET_LOCK/RELEASE_LOCK; PDO exclusivo no persistente. |
| Pruebas | tests/administrador-inicial.php | Dobles compartidos en memoria y PDO ficticio; procesos sin configuración privada para rechazo CLI/HTTP. |

Se comprueba consola Windows con entrada oculta antes de crear PDO. Una lectura preliminar evita solicitar contraseña si ya existe un administrador o falta el rol; al guardar se revalida bajo GET_LOCK('sfatza.administrador-inicial.v1', 0), adquirido antes de comenzar transacción. La segunda ejecución aborta mientras el bloqueo esté ocupado, o detecta el administrador después; la comprobación/inserción queda serializada. La exclusión es cooperativa y no impide escrituras externas que no usen ese bloqueo.

Se conservan nombres/apellidos/usuario sin recortes; límites UTF-8 120/120/50. Contraseña/confirmación solo en memoria, mínimo 12 caracteres y máximo 72 bytes para bcrypt nativo; rechazar NUL/exceso sin truncar. Se inserta solo el id_rol resuelto desde ADMINISTRADOR y activo=1; UNIQUE cubre duplicados según la collation real.

Rollback y RELEASE_LOCK se intentan por separado y la desconexión es el respaldo ante errores. Commit no libera el bloqueo. Si commit o limpieza fallan no se imprime éxito: revisar estado antes de repetir. No se actualizan cuentas, roles ni esquema.

Entrada oculta con Windows PowerShell existente y Console.ReadKey(true), sin secretos en argumentos, entorno o temporales. No se instala nada; terminales redirigidas o plataformas incompatibles abortan. Prueba interactiva de consola pendiente, sin atribuirla a los dobles.

Consultar [guía operativa](../../administrador-inicial.md) antes de una ejecución futura; el comando allí mostrado no se ejecutó y requiere autorización específica del alta real.

## 5. Seguridad, validaciones y persistencia

| Área | Estrategia técnica |
| --- | --- |
| SQL | prepare()/execute() y parámetros para valores. IDs y ordenación requieren validación/listas permitidas. Ningún SQL en vistas. |
| Validación | Verificar tipos, obligatorios, longitudes, IDs existentes, rol y estado en servidor. Nombres y apellidos separados son obligatorios: rechazar omisión, NULL, vacío y solo espacios, y contar hasta 120 caracteres UTF-8 por campo, sin truncar. Rechazar arrays; recorte exterior según DEC-07, sin normalización interna ni patrones inventados. |
| Datos opcionales | Solo el teléfono, hasta 25 caracteres, puede omitirse, ser NULL o vacío tras recorte exterior y se representa como NULL. Licencia obligatoria en alta/edición: exactamente 14 caracteres ASCII A-Z/0-9, minúsculas normalizadas a mayúsculas y rechazo de espacios/símbolos (SRC-19). Persiste en `VARCHAR(40)` con UNIQUE, sin migración de esquema. |
| Duplicados | Consulta previa compatible con collation y exclusión del propio ID en edición, más UNIQUE como garantía ante concurrencia. Distinguir duplicado de FK inválida sin mostrar SQL ni errores crudos. |
| Contraseñas | Hash/verificación nativos, sin hashes en vistas; password_verify() comprobado en memoria sin imprimir secretos. Mínimo 12 caracteres, bcrypt máximo 72 bytes sin NUL/truncamiento. HU-01 conserva contraseña en edición según SRC-15/DEC-03. |
| XSS | Escape según contexto; texto/atributos HTML entre comillas con htmlspecialchars y opciones indicadas en spec.md. No insertar entradas directamente en scripts o atributos de eventos. |
| CSRF y métodos | Proteger operaciones de gestión que cambian estado con tokens aleatorios de sesión comprobados con hash_equals(); rechazar ausentes/incorrectos antes de persistir. GET no cambia estado. SameSite complementa el token. DEC-04 aprobó CSRF en formularios que cambian estado, incluido login/logout; implementado y comprobado con dobles, nuevas pruebas manuales pendientes. |
| Sesiones | Modo estricto, cookies exclusivas, HttpOnly, SameSite=Lax, regeneración al autenticar y al detectar cambio de privilegios. Cierre e invalidación; 30 minutos de inactividad aprobados. |
| HTTP local | Secure se usa bajo HTTPS. El entorno local está documentado por HTTP; no se activa HTTPS ni se cambia Apache en esta fase. La preparación para producción requiere HTTPS. |
| Autorización | Sesión válida, cuenta activa y rol vigente antes de cada operación protegida. Cambios de rol/desactivación se aplican a sesiones previas mediante revalidación. |
| Errores | Mensajes controlados; ausencia de éxito ante fallo. Desactivar exposición de errores de la petición y sanitizar diagnósticos; nunca volcar configuración, excepciones o secretos. |
| Transacciones | Para DML funcional, una escritura única es atómica en InnoDB; operaciones compuestas comparten PDO y confirman o revierten al terminar. Esto no cubre DDL: ALTER TABLE provoca commits implícitos y los dos ALTER de la migración no se revierten juntos con ROLLBACK. |
| Concurrencia | Probar carreras de duplicados y tratar conflictos UNIQUE. La política de edición simultánea requiere revisión si se propone cambiar el comportamiento funcional; no añadir columnas de versión sin autorización. |

La eliminación física queda fuera de alcance. Autodesactivación prohibida y protección del último administrador al desactivar/cambiar rol están autorizadas técnicamente en SRC-15/SRC-16; esta última requiere ratificación académica. Bloqueo de intentos y requisitos extra de contraseña siguen pendientes.

## 6. Estrategia de pruebas

El catálogo detallado está en [verificacion.md](verificacion.md). Sus 46 IDs principales representan escenarios agrupados, con subcasos y combinaciones; no equivalen a 46 pruebas atómicas ni a ejecuciones realizadas. Planificar:

- Funcionales: altas con nombres/apellidos separados y carga de ambos; edición de solo nombres, solo apellidos y ambos juntos, conservando el campo no modificado; roles, desactivación de usuarios y cambios de estado de conductores.
- Negativas: omisión, NULL, vacío y solo espacios en cada campo de nombres/apellidos, tanto en registro como en edición, sin persistencia; demás obligatorios, tipos inválidos, inexistentes, rol inválido, credenciales incorrectas, cuenta inactiva y duplicados.
- Límites: nombres y apellidos de 120 caracteres cada uno válidos, también simultáneamente; rechazar 121 en cada campo con el otro válido. Repetir en registro y edición con ASCII y caracteres multibyte. Conservar límites 50/40/25 de usuario/licencia/teléfono; solo teléfono es opcional.
- Seguridad: RBAC por ruta y método, sesión anterior a cambio de rol/desactivación, fijación de sesión, cierre, caducidad acordada, CSRF, XSS y entradas simuladas de SQL injection no destructivas.
- Persistencia/fallos: repositorios dobles para fallos controlados y, cuando se autorice, integraciones en entorno aislado; comprobar ausencia de éxito falso y de efectos parciales.
- Concurrencia: dos solicitudes de prueba con el mismo identificador o licencia ficticios; verificar una sola alta válida y tratamiento de la otra.
- Interfaz: campos Nombre(s) y Apellidos separados y obligatorios, con mensajes propios y máximo de 120 caracteres por campo; correspondencia con el contrato objetivo y la referencia Figma de solo lectura. No precargar contraseña/hash.

Para integración con escrituras se necesita autorización específica y un ambiente local de pruebas aislado con datos ficticios y preparación/limpieza acordadas. No reutilizar datos personales ni modificar la base actual por ejecutar una prueba. No detener el servicio real ni provocar fallos alterando permisos o esquema.

Antecedente del Prompt 15: suite existente 33, bootstrap 170 y 15 lint PHP correctos, sin MariaDB/.env real; rechazo CGI previo a PDO. EV-10/EV-09 conservan esa ejecución. El login y HU-01 posteriores se verifican en EV-11/EV-12: pruebas aisladas ejecutadas y acceso ADMINISTRADOR reportado por el usuario. SRC-16/EV-13 recoge las pruebas manuales HU-01 reportadas; integración manual HU-03/reactivación, último rol y concurrencia/collation reales siguen pendientes.

## 7. Figma MCP y dependencias

Figma Make es referencia de solo lectura consultada por MCP para login y ambas HU. Se observaron listado y formularios de conductores. Su selector Estado se adapta a una confirmación separada conforme a SRC-16; no se observó una pantalla específica de confirmación de conductor en Make. Esa vista y la activación de usuarios reutilizan la tarjeta HU-01 como adaptación, sin atribuirlas al original. PHP MVC, HTML5, CSS3, Bootstrap 5 y JavaScript son el stack implementado; React/Tailwind no se incorporan.

Se reutilizan Bootstrap CSS 5.3.8 local, CSS/JS y componentes de gestión existentes. No se descargan dependencias; colores/tipografías y límites de las capturas Make se describen en las guías.

| Dependencia / riesgo | Estado y acción propuesta |
| --- | --- |
| PDO CLI y esquema | PDO CLI como antecedente; migración aplicada y verificada manualmente según EV-08, con nombres/apellidos y 22 FK. Esta revisión no abrió conexiones ni reprodujo la verificación de BD. |
| Permisos SQL | El usuario reportó lecturas/altas/edición/desactivación HU-01 exitosas. No acredita todos los permisos HU-03 o concurrencia. No ampliar permisos automáticamente. |
| Entorno HTTP | Login y HU-01 reportados contra MariaDB por el usuario; operaciones reales HU-03/reactivación pendientes. |
| Cuenta inicial | Bootstrap y login ADMINISTRADOR reales reportados por el usuario; no se repiten ni se solicitan sus credenciales. |
| Políticas funcionales | SRC-16 define búsqueda/normalización HU-03, reactivación y protección del último rol. Ratificación académica DEC-05, DEC-06 y reconciliaciones siguen pendientes. |
| Documentación | DIS-01 a DIS-06; registrar sin corregir fuentes académicas. |
| Reproducibilidad del esquema | UP aplicado y probado sobre una restauración según EV-08. Scripts conservados sin cambios; no constituyen un volcado completo ni autorizan nuevas ejecuciones. No se informó un ensayo de DOWN. |
| Verificación de activos para viajes | Acordar prueba del contrato de selección en Sprint 1, sin implementar ni simular asignación real; mantener la integración futura explícita. |
| Estimaciones | Conservar 112 h/14 días del Sprint Backlog; no convertir la secuencia técnica en una nueva estimación. |

### Migración aplicada y recuperación condicionada

EV-08 registra respaldo privado, restauración en sfatza_restore_test_20261013, ensayo de ALTER y aplicación manual en sfatza reportados por el usuario. La incertidumbre anterior sobre restaurabilidad queda resuelta por esa confirmación, no por una prueba realizada aquí. Se conservaron los scripts y se comprobó su coherencia con el resultado informado; no se dispone del texto exacto de los comandos manuales, del manifiesto privado ni de una nueva revisión administrativa de objetos o sesiones.

No reejecutar UP. DOWN no es automático: exige autorización nueva, conteos actuales cero en ambas tablas, respaldo y revisión de dependencias. Con datos pierde apellidos; no se informó que su ensayo estuviera realizado. DDL mantiene commits implícitos y cada tabla se recupera de forma supervisada según el [README](../../../database/migrations/README.md). Este incremento no cambia esquema/datos/permisos ni crea un ejecutor de migraciones PHP; el bootstrap CLI es independiente de UP/DOWN.

## 8. Siguiente comprobación manual

El usuario probará [Gestión de conductores](../../gestion-conductores.md) y [reactivación de usuarios](../../gestion-usuarios.md) desde el login existente. Revisar listados y confirmaciones antes de enviar escrituras elegidas manualmente; cancelar no cambia registros. No repetir bootstrap o migración.

Registrar resultados manuales separados de los dobles, sin cerrar automáticamente los 14 estados académicos. Probar último administrador y concurrencia únicamente en ambiente controlado autorizado, sin arriesgar acceso al sistema original. MEMORY.md conserva estado anterior: se propone actualizarlo por separado, sin modificarlo automáticamente.

No reejecutar migraciones, crear usuarios, añadir dependencias o integrar Git automáticamente. Pruebas posteriores con BD requieren ambiente y preparación/limpieza expresamente autorizados.
