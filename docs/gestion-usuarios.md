# HU-01 — Gestión de usuarios de SFATSA

Implementada el 2026-10-13 por autorización del Prompt 18, sobre el login MVC existente, en docs/sprint-01-planificacion / HEAD ed791dbaa06f54ed6588268f1a301acb87617d33, sin commit. El usuario confirmó previamente el login real ADMINISTRADOR, la bienvenida y el rechazo de credenciales incorrectas. En Prompt 18 las operaciones se probaron con repositorios/PDO ficticios. Antes de Prompt 19 el usuario confirmó manualmente registro, consulta/búsqueda, edición de nombres, desactivación y rechazo de login de una cuenta inactiva contra MariaDB. La evidencia se atribuye al usuario; el agente no creó, modificó, activó o desactivó registros reales.

Actualización Prompt 19: reactivación de usuarios autorizada como ampliación funcional del equipo, protección del último administrador también frente a cambios de rol e integración con [Gestión de conductores](gestion-conductores.md). Ninguna de estas ampliaciones se atribuye retroactivamente a la HU-01 académica; originales sin cambios.

## Abrir y utilizar

1. Abrir [http://sfatsa.test/](http://sfatsa.test/) e iniciar sesión con la cuenta ADMINISTRADOR existente.
2. En la bienvenida, seleccionar **Abrir Gestión de usuarios**. Acceso directo: [http://sfatsa.test/?ruta=usuarios](http://sfatsa.test/?ruta=usuarios).
3. El listado consulta las cuentas reales, incluida la propia, y muestra nombre completo, usuario, rol, estado y acciones. Buscar parcialmente por nombres, apellidos o usuario; Limpiar búsqueda devuelve el listado completo.
4. Registrar usuario solicita Nombre(s), Apellidos, Usuario, Rol, Contraseña y Confirmación; guarda activo=1. Todos son obligatorios. Nombres/apellidos: máximo 120 caracteres cada uno; usuario: 50. Se recortan solo espacios exteriores de los tres textos, manteniendo acentos, mayúsculas y espacios internos. Contraseña literal: mínimo 12 caracteres, máximo técnico bcrypt 72 bytes, sin NUL; no se trunca. Caracteres multibyte cuentan como caracteres para los textos y como bytes para el límite bcrypt.
5. Modificar precarga nombres, apellidos, usuario y rol. Conserva contraseña y estado; no ofrece recuperación o cambio de contraseña. Activar/desactivar son acciones independientes con confirmación. Guardar los mismos datos es válido; usuario ajeno duplicado se rechaza.
6. Desactivar muestra claramente la cuenta y requiere marcar la confirmación y enviar. Cancelar no escribe. Solo cambia activo=0 y conserva el registro. La cuenta propia y el último administrador activo están protegidos incluso ante POST manipulado.
7. Para una cuenta inactiva, **Activar** abre la confirmación. Confirmar cambia únicamente activo=1, conserva datos/contraseña y vuelve al listado. Una cuenta ya activa no presenta esa acción y un envío redundante se rechaza.
8. Al modificar el rol, el servidor rechaza retirar ADMINISTRADOR de la última cuenta activa con ese rol, incluida la propia. Con otro administrador activo, un cambio de rol propio puede retirar el acceso de gestión en la siguiente petición; no se modifican roles automáticamente.

Los únicos roles seleccionables son ADMINISTRADOR, CAJERO y CHECADORA del catálogo real, sin IDs numéricos fijos. CAJERO/CHECADORA reciben 403 aunque escriban una URL o manipulen formularios; anónimos/cuentas inactivas vuelven al login. Cada petición protegida consulta el estado/rol vigente, incluyendo sesiones anteriores al cambio. Conductores tiene acceso funcional desde el panel y la navegación; gestión exclusiva de ADMINISTRADOR.

## Rutas

Apache conserva su configuración; se usan query routes sin reescritura o .htaccess.

| Método | URL | Acción |
| --- | --- | --- |
| GET | /?ruta=usuarios&q=texto | Listado y búsqueda. |
| GET | /?ruta=usuarios.registrar | Formulario de alta. |
| POST | /?ruta=usuarios.registrar | Validar y registrar. |
| GET | /?ruta=usuarios.editar&id=ID | Cargar formulario de edición. |
| POST | /?ruta=usuarios.editar&id=ID | Validar y guardar datos/rol. |
| GET | /?ruta=usuarios.desactivar&id=ID | Mostrar cuenta y confirmación; sin escribir. |
| POST | /?ruta=usuarios.desactivar&id=ID | Confirmar y desactivar lógicamente. |
| GET | /?ruta=usuarios.activar&id=ID | Mostrar cuenta inactiva y confirmación; sin escribir. |
| POST | /?ruta=usuarios.activar&id=ID | Confirmar y reactivar la misma cuenta. |

ID debe ser un entero positivo representable por PHP; inválidos responden 400, inexistentes 404. Validación: 422; duplicidad/conflicto/protecciones: 409; CSRF/autorización: 403; fallos de persistencia: mensaje controlado 503. Métodos ajenos a la ruta: 405 con Allow. Tras éxito se redirige con 303 al listado y se muestra un mensaje de sesión una sola vez, evitando reenvío por recarga. Destinos de redirección fijos, sin URLs del navegador.

## Arquitectura y seguridad

Inventario histórico del incremento original Prompt 18: **15 archivos nuevos y 13 existentes ampliados**, conservando los 43 pendientes de entonces. Los otros 30 archivos previos mantienen su hash inicial. No hay modificaciones fuera del alcance.

| Estado | Archivos |
| --- | --- |
| Nuevo, controlador | app/Controllers/UsuarioController.php |
| Nuevos, repositorios | app/Repositories/GestionUsuarioRepository.php; app/Repositories/PdoGestionUsuarioRepository.php |
| Nuevos, servicios | app/Services/UsuarioService.php; app/Services/ErrorGestionUsuarios.php |
| Nuevas, vistas | app/Views/usuarios/listado.php; formulario.php; desactivar.php; layout-inicio.php; layout-fin.php |
| Nuevos, recursos | public/assets/css/usuarios.css; public/assets/js/usuarios.js |
| Nuevos, pruebas | tests/usuarios.php; tests/Support/GestionUsuariosFicticia.php |
| Nueva, guía | docs/gestion-usuarios.md |
| Ampliados, entrada/rutas | public/index.php; app/bootstrap-web.php; routes/web.php |
| Ampliados, integración | app/Controllers/AutenticacionController.php; app/Http/Respuesta.php; app/Http/Vista.php; app/Views/inicio.php; app/Views/partials/cabecera.php |
| Actualizados, documentos técnicos | docs/login-mvc.md; docs/sprints/sprint-01/spec.md; plan.md; tasks.md; verificacion.md |

Las filas de vistas/documentos con nombres abreviados mantienen el directorio indicado en su primer archivo. Al terminar Prompt 18 Git contenía 58 archivos pendientes (43 anteriores + 15 nuevos); varios archivos ampliados ya eran no rastreados. El incremento posterior amplía estos archivos y añade HU-03; el estado final actual se comunica en la entrega. No se añadió nada al índice ni se creó commit.

- UsuarioController coordina acceso, CSRF, vistas y respuestas; reutiliza la identidad vigente de AutenticacionController, AutorizacionService y SessionStore existentes.
- UsuarioService valida UTF-8, tipos, obligatoriedad, longitudes, roles, duplicados y protecciones; normaliza solo espacios exteriores de textos. No conserva contraseñas en respuesta/sesión ni recibe hashes del formulario.
- GestionUsuarioRepository/PdoGestionUsuarioRepository usan PDO inyectado y columnas reales de usuario/rol. Sus lecturas de gestión excluyen hash_contrasena. Edición no modifica hash ni activo; activación/desactivación no borran filas ni modifican contraseñas. Búsqueda parametrizada con marcadores distintos y escape explícito de %, _ y ! como texto literal; equivalencia/acentos conforme a la collation existente.
- Una sola conexión diferida por petición reutiliza Environment, config/database.php y ConnectionFactory, sin duplicar credenciales. La inclusión del bootstrap y los accesos anónimos no abren PDO.
- Escrituras POST/CSRF reutilizan OperacionAdministrativaService con transacción InnoDB, bloqueo uniforme inicial de rol ADMINISTRADOR, revalidación/bloqueo del actor y cuenta objetivo, duplicidad previa más UNIQUE y manejo de FK/errores. Ambos módulos usan la misma conexión durante cada acción. El conteo actual del último administrador usa SELECT ... FOR UPDATE antes de desactivar o retirar su rol; la segunda solicitud concurrente se revalida después de obtener el bloqueo. Commit solo al completar; fallo intenta rollback, sin éxito falso ni detalles internos. Ante un resultado de commit/rollback incierto, revisar el listado antes de repetir. No se certifica concurrencia real mediante dobles.
- Vistas PHP escapan textos/atributos; no hay contraseñas precargadas ni interpolación de entradas en JS/CSS. Cabeceras de seguridad y sesión del login se conservan. La validación JavaScript es complementaria; el servidor valida aun si se omite.

## Diseño observado y adaptación

Se leyeron por MCP únicamente src/App.tsx y src/index.css del [Figma Make oficial](https://www.figma.com/make/mw4xDH3lehv8ydwYJC1Kjk/Wireframes-para-SFATSA). Observado: AppShell, listado/tabla, registro/edición y DisableScreen; encabezado oscuro #2d3032, navegación lateral de 188 px, fondo #f5f5f3, sidebar #e9e9e6, tarjetas blancas, bordes grises, títulos Georgia, texto Inter/system-ui, etiquetas de estado redondeadas y confirmación con datos de la cuenta.

Adaptaciones al alcance aprobado: PHP/HTML y Bootstrap CSS 5.3.8 existente, CSS/JS propios, roles reales, mínimo 12 caracteres y confirmación de contraseña solo en registro; edición sin contraseña. La búsqueda/selección se realiza en el listado, sin controles decorativos de selección/paginación. La confirmación se presenta como sección accesible de página, inspirada en la tarjeta del prototipo y utilizable sin JS, sin una superposición que bloquee el teclado. Controles de 44 px, etiquetas, ayudas, mensajes anunciados, foco de error/teclado, enlace al contenido y móvil; tabla desplazable dentro de su contenedor. No se incorporan datos ficticios a MariaDB ni dependencias React/Tailwind.

Make no admite las herramientas de captura/metadatos de Design, según la comprobación anterior; no se repitieron llamadas incompatibles. La revisión visual usa vistas locales con datos ficticios. Figma original no se modificó.

## Pruebas ejecutadas y límites

Las cantidades y revisiones siguientes corresponden a Prompt 18 (EV-12) y conservan su contexto histórico. La confirmación manual se registra en EV-13 y las nuevas comprobaciones de Prompt 19 en EV-14; no se presumen realizadas a partir de estos antecedentes.

- tests/usuarios.php: **520 comprobaciones correctas** con repositorios, sesiones y PDO simulados, sin conexiones MariaDB. Registro/hash, campos inválidos/solo espacios, límites ASCII/á/emoji separados y simultáneos, duplicados y FK simuladas, búsqueda, edición independiente, roles sin IDs fijos, conservación de contraseña/estado, inexistentes, desactivación, autodesactivación/último administrador, permisos concedidos/revocados, CSRF propio/ajeno, XSS, parámetros SQL y rollback/errores.
- Sintaxis: 47 archivos PHP y dos JavaScript correctos.
- Regresión: login MVC 75, infraestructura PDO 33 y bootstrap 170 comprobaciones correctas; bootstrap real no ejecutado.
- Chrome aislado: listado/registro/edición/desactivación a 1440 px y 390 px, error de registro a 390 px; sin desbordamiento horizontal de la página, etiquetas presentes, contraseñas vacías, ausencia de hashes y foco en error. Validación cliente de 120/121 caracteres emoji, contraseña/confirmación, espera y restauración del botón; envíos interceptados, sin solicitudes de escritura.
- Apache: GET anónimos de las cuatro rutas protegidas responden 303 al login; POST al listado responde 405 con Allow: GET y CSS/JS locales responden 200. No se usaron credenciales reales ni se abrieron cuentas reales en las capturas.

Repetir pruebas aisladas:

```powershell
& 'C:\xampp\php\php.exe' '.\tests\usuarios.php'
& 'C:\xampp\php\php.exe' '.\tests\login-mvc.php'
& 'C:\xampp\php\php.exe' '.\tests\pdo-infrastructure.php'
& 'C:\xampp\php\php.exe' '.\tests\administrador-inicial.php'
```

Confirmado por el usuario antes de Prompt 19: registro, consulta/búsqueda, edición de nombres, desactivación y rechazo del login inactivo en MariaDB. Pendiente: reactivación manual, edición de roles/otros datos, casos negativos y de límites no reportados, equivalencia de duplicados bajo collation y concurrencia con sesiones reales en ambiente autorizado. Los dobles no prueban sintaxis/bloqueos en servidor MariaDB. No se configura HTTPS o políticas no aprobadas de bloqueo de intentos/MFA.

## Guía manual breve — evidencia previa y nuevos pasos

Los pasos siguientes son una guía para el usuario. El agente no ejecuta escrituras; los resultados HU-01 reportados antes de Prompt 19 no demuestran que se haya completado toda esta secuencia ni la nueva reactivación.

1. Con ADMINISTRADOR, comprobar listado de la propia cuenta, búsqueda y navegación/Cancelación, que no deben escribir.
2. Cuando el usuario decida probar escrituras, registrar una cuenta con datos elegidos y rol real; comprobar el mensaje y el estado Activo. Probar omisiones, espacios, longitudes y duplicado; entradas inválidas no deben producir nuevas cuentas. No publicar contraseñas ni hashes en evidencia.
3. Editar esa cuenta: nombres/apellidos por separado, usuario y rol. Comprobar conservación del estado y acceso con su contraseña original; la contraseña no aparece en edición.
4. Con una sesión de CAJERO/CHECADORA, comprobar 403 en todas las rutas de gestión. Cambiar el rol desde otra cuenta ADMINISTRADOR y comprobar permisos en la siguiente petición de la sesión anterior.
5. Abrir desactivación, cancelar y comprobar que continúa activa. Luego confirmar una cuenta distinta de la propia y comprobar estado Inactivo, conservación y rechazo de login/sesión anterior. La acción Activar ahora permite recuperarla mediante una confirmación independiente, sin restablecer la contraseña.
6. Intentar desactivar la propia cuenta por URL/formulario manipulado: rechazo sin cambios. Comprobar la protección del último administrador únicamente en un ambiente controlado, sin arriesgar el acceso del sistema original. Intentar retirar el rol del único administrador debe rechazarse sin cambios. Probar concurrencia entre desactivaciones/cambios de rol solo en ambiente controlado; mantener acceso administrativo alterno cuando corresponda.

## Reactivar a axelita69 desde la interfaz — pendiente de ejecución manual

1. Entrar en [http://sfatsa.test/](http://sfatsa.test/) con una cuenta ADMINISTRADOR activa; no ejecutar SQL o bootstrap.
2. Abrir **Gestión de usuarios** y buscar `axelita69` en el buscador.
3. Verificar que la fila corresponde a la cuenta deseada y muestra **Inactivo**. No se ha consultado o cambiado su estado por el agente. Si aparece Activo, no necesita reactivación y no tendrá la acción Activar.
4. Seleccionar **Activar** en esa fila, comprobar los datos de la confirmación, marcar la confirmación y enviar. **Cancelar** regresa sin modificarla.
5. Comprobar el mensaje de éxito y estado **Activo** en el listado. La cuenta y contraseña son las existentes; no se crea otra cuenta ni se restablece la contraseña.
6. Si el usuario decide comprobar su login, usar su contraseña privada original en otra sesión. No publicarla en evidencias. Si se desconoce, este módulo no implementa recuperación.

## Decisiones y seguimiento

DEC-05: Prompt 18 prohíbe autodesactivación. Prompt 19 autoriza y exige mantener al menos un ADMINISTRADOR activo tanto al desactivar como al cambiar el rol, incluso el propio cuando es el único. Se implementa en servidor bajo el mismo protocolo de bloqueo/transacción, no con JavaScript ni consultas previas aisladas. Es una decisión técnica autorizada, pendiente de ratificación académica; los originales no se modifican.

Reactivación: ampliación del equipo autorizada en Prompt 19, mediante POST/CSRF y confirmación, conservando identidad, datos y contraseña. No es un nuevo CU atribuido al UML académico.

Se conservan 14 tareas originales, responsables, estimaciones y estados iniciales, y 46 IDs principales en el catálogo; se añade evidencia a sus subcasos sin cerrar pruebas manuales. HU-03 se implementa en su guía separada. Eliminación y contraseña en edición siguen fuera de alcance; reactivación está autorizada en SRC-16. MEMORY.md mantiene estado anterior: se propone actualizarlo por separado; no se modificó automáticamente. Migraciones, bootstrap, archivos académicos y Figma permanecen intactos.
