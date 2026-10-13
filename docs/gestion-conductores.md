# HU-03 — Gestión de conductores de SFATSA

Implementación autorizada por Prompt 19, el 2026-10-13, sobre el login y HU-01 existentes, en docs/sprint-01-planificacion / HEAD ed791dbaa06f54ed6588268f1a301acb87617d33, sin commit. El agente no inserta, modifica, activa o desactiva registros reales. Las nuevas pruebas manuales contra MariaDB corresponden al usuario y permanecen pendientes.

Fuentes: HU-03 de Historias_Usuario.docx y Historias_Descripciones.docx, Product_Backlog.docx, Sprint_Backlog.docx y UML Documentos/Casos_Uso/Sprint 2/HU-03.mdj, consultados sin modificación. Backlogs/alcance autorizado sitúan HU-03 en Sprint 1 aunque su carpeta UML conserve Sprint 2. CU-06/CU-07 son las referencias establecidas por el usuario para registrar/actualizar conductor; no se añadieron IDs a los originales. Ver [especificación](sprints/sprint-01/spec.md) y SRC-16.

## Abrir y utilizar

1. Abrir [http://sfatsa.test/](http://sfatsa.test/) e iniciar sesión con una cuenta ADMINISTRADOR activa existente. No ejecutar bootstrap o migraciones.
2. Elegir **Gestión de conductores** desde la bienvenida o la navegación. Acceso directo: [http://sfatsa.test/?ruta=conductores](http://sfatsa.test/?ruta=conductores).
3. El listado consulta conductores reales, muestra folio/identificador interno, nombre completo, licencia, teléfono, estado y acciones. Si no hay conductores, muestra el estado vacío y permite registrar desde el botón funcional.
4. Buscar parcialmente por nombres, apellidos o número de licencia. Limpiar búsqueda vuelve al listado; buscar/cancelar/abrir formularios no escribe datos.
5. Registrar solicita Nombre(s), Apellidos, Número de licencia y Teléfono. Nombres, apellidos y licencia son obligatorios; los dos primeros admiten hasta 120 caracteres y el teléfono es opcional hasta 25. La licencia debe tener exactamente 14 caracteres A-Z o 0-9; las minúsculas se convierten a mayúsculas mientras se escribe y también en el servidor. No se permiten espacios, símbolos ni caracteres acentuados. El nuevo conductor se guarda activo=1.
6. Modificar precarga esos cuatro campos y conserva el estado. Mantener la propia licencia es válido; usar la de otro conductor se rechaza sin sobrescribirlo.
7. Para un conductor activo aparece **Desactivar**; para uno inactivo, **Activar**. Abrir la acción muestra los datos y el cambio solicitado. Confirmar mediante POST/CSRF cambia únicamente activo; Cancelar regresa sin cambios. Una repetición se rechaza con mensaje comprensible.

Solo ADMINISTRADOR puede consultar o escribir. CAJERO/CHECADORA reciben 403 incluso por URL directa/formulario manipulado; anónimos/cuentas inactivas vuelven al login. El estado y rol actuales se consultan por petición y se revalidan antes de persistir; no se duplica autenticación.

## Contrato real y validación

Se reutiliza la PDO existente a sfatza y la tabla conductor sin DDL:

| Columna | Contrato utilizado |
| --- | --- |
| id_conductor | BIGINT UNSIGNED, PK, AUTO_INCREMENT; no editable. |
| nombres | VARCHAR(120), NOT NULL; obligatorio. |
| apellidos | VARCHAR(120), NOT NULL; obligatorio. |
| numero_licencia | VARCHAR(40), actualmente NULL, DEFAULT NULL y UNIQUE; la aplicación exige 14 caracteres ASCII alfanuméricos en mayúsculas. Migración 20261013_002 preparada para NOT NULL, no ejecutada. |
| telefono | VARCHAR(25), NULL, DEFAULT NULL; opcional. |
| activo | TINYINT(1), NOT NULL, DEFAULT 1; cambios permitidos únicamente 0/1. |

Tipos y nulos proceden del contrato auditado/documentado; la migración de nombres/apellidos fue aplicada manualmente y confirmada por el usuario. No se cambia el esquema ni se infiere un conteo actual desde las instantáneas históricas de cero conductores.

La validación del servidor comprueba tipo escalar, UTF-8 válido, ausencia de NUL y longitudes en caracteres, incluidos multibyte, sin truncar. Recorta únicamente espacios exteriores, preservando espacios internos, mayúsculas y acentos. Nombres/apellidos/licencia vacíos o solo espacios se rechazan. Solo el teléfono puede omitirse, ser NULL o quedar vacío después del recorte; esos casos se guardan como NULL.

Prompt 23 establece la regla vigente para registro y edición: exactamente 14 caracteres ASCII alfanuméricos `[A-Z0-9]`. El navegador transforma letras ASCII minúsculas a mayúsculas al escribir y ofrece `minlength`, `maxlength` y `pattern`; el servidor aplica la misma normalización y valida el valor aunque JavaScript esté deshabilitado o se manipule la solicitud. Rechaza valores ausentes/vacíos, longitud incorrecta, espacios (incluidos exteriores), símbolos, letras acentuadas y otros caracteres. La regla se verifica antes de consultar duplicados o persistir. Mantener la propia licencia al editar es válido; una licencia ajena duplicada se rechaza y el índice UNIQUE sigue protegiendo ante una carrera. La columna MariaDB permanece `VARCHAR(40)` temporalmente: no se modifica el esquema ni se ejecuta 20261013_002.

El teléfono continúa opcional, hasta 25 caracteres, y vacío se representa como NULL. Nombres y apellidos siguen obligatorios con máximo de 120 caracteres.

**Estado de MariaDB y migración:** una consulta de solo lectura al `sfatza` local encontró `conductor.numero_licencia` como `VARCHAR(40) NULL DEFAULT NULL`, `utf8mb4_uca1400_ai_ci`, con índice único `numero_licencia`. Había un conductor y cero licencias NULL, vacías/solo espacios o duplicadas (cero grupos duplicados bajo la collation); no se muestran licencias ni otros datos personales. La aplicación valida licencia obligatoria desde ya. Los scripts [UP](../database/migrations/20261013_002_licencia_conductor_obligatoria.up.sql) y [DOWN](../database/migrations/20261013_002_licencia_conductor_obligatoria.down.sql) están preparados, **no ejecutados**. El procedimiento de respaldo, precondiciones, recuperación y comprobación está en [README de migraciones](../database/migrations/README.md).

## Rutas

Apache conserva su configuración; se utilizan query routes desde public/index.php.

| Método | URL | Acción |
| --- | --- | --- |
| GET | /?ruta=conductores&q=texto | Listado y búsqueda. |
| GET | /?ruta=conductores.registrar | Formulario de registro. |
| POST | /?ruta=conductores.registrar | Validar y registrar activo. |
| GET | /?ruta=conductores.editar&id=ID | Cargar formulario de edición. |
| POST | /?ruta=conductores.editar&id=ID | Validar y guardar los cuatro datos. |
| GET | /?ruta=conductores.estado&id=ID&estado=0 | Mostrar confirmación de desactivación; no escribe. |
| POST | /?ruta=conductores.estado&id=ID&estado=0 | Confirmar y desactivar. |
| GET | /?ruta=conductores.estado&id=ID&estado=1 | Mostrar confirmación de activación; no escribe. |
| POST | /?ruta=conductores.estado&id=ID&estado=1 | Confirmar y reactivar. |

ID debe ser un entero positivo representable por PHP; estado se valida como 0/1 en servidor. Tras una escritura exitosa se usa redirección 303 a un destino fijo del listado, con mensaje de sesión una sola vez. Métodos ajenos a la ruta reciben 405; CSRF/roles denegados no llegan a la persistencia. Los errores muestran mensajes controlados sin SQL, credenciales o trazas.

## Arquitectura y seguridad

| Componente | Archivos y función |
| --- | --- |
| Controlador | app/Controllers/ConductorController.php: acceso, CSRF, vistas y respuestas. |
| Negocio | app/Services/ConductorService.php y ErrorGestionConductores.php: validación, duplicados, existencia/estado y errores comprensibles. |
| Datos | app/Repositories/ConductorRepository.php y PdoConductorRepository.php: columnas reales y SELECT/INSERT/UPDATE preparados. |
| Escrituras comunes | app/Services/OperacionAdministrativaService.php: protocolo de transacción y autorización vigente compartido con usuarios. |
| Vistas | app/Views/conductores/listado.php, formulario.php y estado.php. |
| Presentación común | app/Views/partials/gestion-inicio.php y gestion-fin.php, estilos usuarios.css y script usuarios.js existentes reutilizados. |
| Integración | public/index.php, routes/web.php y app/bootstrap-web.php; panel inicial y navegación existentes. |
| Pruebas | tests/conductores.php y tests/Support/ConductoresFicticios.php; regresiones existentes sin registros reales. |

No hay SQL en vistas ni reglas duplicadas en controladores. Todas las escrituras usan POST y CSRF de sesión; GET solo consulta. HTML se escapa con htmlspecialchars, ENT_QUOTES/ENT_SUBSTITUTE y UTF-8. Las confirmaciones no confían en campos ocultos, estado del botón o JavaScript para autorizar.

OperacionAdministrativaService inicia la transacción, bloquea primero la fila ADMINISTRADOR del catálogo y después revalida/bloquea la cuenta del actor. La acción de conductor comparte esa misma PDO, bloquea/revalida el objetivo cuando corresponde y confirma solo al terminar; un fallo intenta rollback y no anuncia éxito. El orden común con HU-01 limita carreras administrativas y evita aplicar una escritura autorizada solo por un rol antiguo. Escritores externos que no siguen este protocolo y bloqueos reales del servidor no se certifican mediante dobles. Ante resultado incierto de commit/rollback, revisar el listado antes de repetir.

## Referencia visual y adaptación

Se consultó por Figma MCP de solo lectura el código de [Make oficial](https://www.figma.com/make/mw4xDH3lehv8ydwYJC1Kjk/Wireframes-para-SFATSA): AppShell, listado de conductores y formularios driver-create/driver-edit. Se reutilizan del diseño encabezado oscuro, navegación lateral, tarjetas claras, títulos Georgia, texto Inter/system-ui, tabla/buscador y estados redondeados, con los estilos ya adaptados en HU-01.

El prototipo contiene un selector Estado en el formulario. La implementación registra activo y cambia estado mediante confirmación independiente conforme al Prompt 19. Make no mostró una pantalla específica de confirmación de conductor: se adapta la tarjeta de HU-01 para activar/desactivar conductores y activar usuarios, sin presentar esas vistas como pantallas observadas en Figma. La licencia aparece ahora como obligatoria por la regla aprobada en Prompt 20; nombres/apellidos también son obligatorios y el teléfono sigue opcional.

Se implementa PHP/HTML, Bootstrap CSS 5.3.8 local y CSS/JavaScript existentes, sin React/Tailwind o nuevas dependencias. Se conservan etiquetas, mensajes anunciados, foco visible, enlace al contenido, formularios utilizables por teclado/sin JavaScript y presentación móvil. La tabla tiene su propio desplazamiento cuando lo necesita, sin desbordar la página. Sin datos de demostración insertados en MariaDB ni controles ficticios de módulos futuros.

## Comprobaciones y guía manual pendiente

Los resultados aislados y de sintaxis del Prompt 20 se registran en EV-15 de [verificacion.md](sprints/sprint-01/verificacion.md). Dobles cubren registro/búsqueda/edición, teléfono opcional NULL, licencia obligatoria/duplicada, límites UTF-8, estados/confirmación, roles, CSRF, XSS y fallos. No sustituyen pruebas de persistencia, collation, permisos y concurrencia MariaDB reales.

```powershell
& 'C:\xampp\php\php.exe' '.\tests\conductores.php'
& 'C:\xampp\php\php.exe' '.\tests\usuarios.php'
& 'C:\xampp\php\php.exe' '.\tests\login-mvc.php'
& 'C:\xampp\php\php.exe' '.\tests\pdo-infrastructure.php'
& 'C:\xampp\php\php.exe' '.\tests\administrador-inicial.php'
```

Estos comandos son suites aisladas; no son instrucciones para ejecutar el alta real, preparar cuentas o escribir en sfatza.

1. Con ADMINISTRADOR, revisar listado y navegación y buscar por nombres/apellidos/licencia. Sin coincidencias debe aparecer un mensaje, sin crear conductores automáticamente.
2. Si se decide probar una escritura, registrar un conductor con una licencia ficticia de prueba de 14 caracteres A-Z/0-9; comprobar que las minúsculas se presentan y guardan en mayúsculas. El teléfono puede quedar vacío.
3. Intentar registrar y editar licencias de 13/15 caracteres, con espacios iniciales/finales/internos, símbolos, letras acentuadas o valor vacío: el servidor debe rechazarlas, marcar el campo y conservar los demás valores para corregirlos. También probar nombres/apellidos obligatorios y límites 120/121, teléfono 25/26 y caracteres multibyte; no debe truncarse ningún valor.
4. Modificar independientemente nombres/apellidos y conservar la misma licencia del conductor: debe aceptarse. Una licencia válida de otro conductor debe rechazarse. Dejar vacío el teléfono representa ausencia, sin afectar otras entidades.
5. Abrir Desactivar y cancelar: estado intacto. Volver a abrir, confirmar y comprobar Inactivo/datos conservados. Abrir Activar, cancelar y luego confirmar si se desea: mismo folio/datos, Activo. Repetir una acción obsoleta debe mostrar conflicto sin otro cambio.
6. Con CAJERO/CHECADORA, comprobar rechazo de todas las rutas, incluso acceso directo. Comprobar CSRF y entradas manipuladas en ambiente controlado, sin provocar fallos modificando permisos/esquema.
7. Para reactivar al usuario solicitado, seguir [Reactivar a axelita69](gestion-usuarios.md#reactivar-a-axelita69-desde-la-interfaz--pendiente-de-ejecución-manual). El agente no realiza esa operación.

## Decisiones y límites

DEC-02/DEC-07 de conductores mantienen la búsqueda, recorte de nombres/apellidos y teléfono opcional. Prompt 20 hizo obligatoria la licencia; la regla posterior y aprobada de Prompt 23 añade formato ASCII alfanumérico exacto de 14 caracteres y conversión a mayúsculas. La columna continúa `VARCHAR(40)`; la migración para fijar NOT NULL sigue pendiente y no se ejecuta como parte de esta validación.

Solo gestión de conductores/usuarios del Sprint 1: no hay eliminación física, asignación de viajes, ventas o otros sprints. El criterio académico de activos para nuevas asignaciones se conserva para su integración futura; DEC-06/P-03-14 mantienen pendiente la selección para viajes y no se declara probada. Se preservan 14 tareas originales y 46 IDs principales, sin marcar completas pruebas manuales no realizadas. Documentos/, Figma, esquema/migraciones/bootstrap, secretos y MEMORY.md permanecen fuera de las modificaciones.
