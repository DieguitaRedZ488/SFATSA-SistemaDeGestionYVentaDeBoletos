# Login MVC de SFATSA — incremento funcional del Prompt 17

Implementado el 2026-10-13 en docs/sprint-01-planificacion, sin commit. El usuario confirmó que creó correctamente el administrador mediante el bootstrap; no se repitió ese alta, se consultaron sus credenciales o crearon otras cuentas.

Actualización posterior (Prompts 18/19): el usuario confirmó login real ADMINISTRADOR y operaciones HU-01 de registro, consulta/búsqueda, edición de nombres, desactivación y rechazo de login inactivo. [Usuarios](gestion-usuarios.md) y [conductores](gestion-conductores.md) están implementados y accesibles desde bienvenida/navegación para ADMINISTRADOR. Reactivación de usuarios y protección del último administrador ante cambios de rol son ampliaciones autorizadas en Prompt 19; sus pruebas manuales siguen pendientes. La evidencia y lista de archivos originales de esta guía conservan el contexto del incremento del login.

La autorización original del Prompt 17 cubrió login, autenticación, sesión, cierre, roles e interfaz PHP/HTML/Bootstrap/CSS/JavaScript. En aquel incremento quedaron fuera CRUD, migraciones y cambios de registros/Figma/documentos académicos, y se conservaron byte a byte los 17 archivos previos, incluidos los cuatro documentos del sprint. Prompt 18 añade HU-01 y Prompt 19 HU-03/reactivación/protección del último rol. Conservan las restricciones sobre escrituras reales del agente, migraciones y originales.

## Consultar el módulo

Apache/XAMPP ya tiene el VirtualHost documentado con raíz public/. MariaDB es el servicio independiente existente; no iniciar otro servidor MySQL ni reinstalar componentes.

1. Abrir [http://sfatsa.test/](http://sfatsa.test/) en el navegador.
2. Introducir el usuario y contraseña privados elegidos al crear el administrador.
3. Iniciar sesión: aparece bienvenida, usuario, rol y, para ADMINISTRADOR, accesos funcionales a Gestión de usuarios y Gestión de conductores.
4. Usar Cerrar sesión para invalidar sesión/cookie y volver al login.

No se necesita ejecutar el bootstrap o migraciones de nuevo. No hay credenciales de demostración ni contraseña precargada. El acceso real del administrador se deja al usuario; no se solicitó su contraseña.

## Rutas implementadas

Se usa index.php con query routes porque Apache tiene AllowOverride None. No requiere .htaccess o cambios de Apache.

| Método y URL | Comportamiento |
| --- | --- |
| GET / o /?ruta=login | Login; una sesión válida redirige a inicio. |
| POST /?ruta=login | Validar CSRF/credenciales y autenticar. |
| GET /?ruta=inicio | Bienvenida privada; revalidar cuenta/rol o redirigir al login. |
| POST /?ruta=logout | CSRF, destruir sesión y expirar cookie; redirigir al login. |
| GET /?ruta=usuarios | Listado/búsqueda HU-01: anónimo redirige, otros roles reciben 403 y ADMINISTRADOR accede al módulo. Formularios GET/POST en la guía de HU-01. |
| GET /?ruta=conductores | Listado/búsqueda HU-03: anónimo redirige, otros roles reciben 403 y ADMINISTRADOR accede. Formularios/estado GET/POST en la guía de conductores. |

Los métodos no permitidos devuelven 405 con Allow; ruta inexistente, 404. Las redirecciones admiten únicamente /, /?ruta=inicio y /?ruta=usuarios. Parámetros de destino, rol o identificador enviados por el navegador no conceden acceso.

## Archivos del incremento

Modificado: [public/index.php](../public/index.php). Los siguientes 25 archivos son nuevos; los 17 pendientes anteriores no se modificaron:

- [app/Controllers/AutenticacionController.php](../app/Controllers/AutenticacionController.php)
- [app/Http/Respuesta.php](../app/Http/Respuesta.php)
- [app/Http/Vista.php](../app/Http/Vista.php)
- [app/Models/UsuarioAutenticado.php](../app/Models/UsuarioAutenticado.php)
- [app/Repositories/PdoUsuarioRepository.php](../app/Repositories/PdoUsuarioRepository.php)
- [app/Repositories/UsuarioRepository.php](../app/Repositories/UsuarioRepository.php)
- [app/Security/SesionPhp.php](../app/Security/SesionPhp.php)
- [app/Security/SessionStore.php](../app/Security/SessionStore.php)
- [app/Services/AutenticacionService.php](../app/Services/AutenticacionService.php)
- [app/Services/AutorizacionService.php](../app/Services/AutorizacionService.php)
- [app/Services/CsrfService.php](../app/Services/CsrfService.php)
- [app/Services/SesionService.php](../app/Services/SesionService.php)
- [app/Views/inicio.php](../app/Views/inicio.php)
- [app/Views/login.php](../app/Views/login.php)
- [app/Views/mensaje.php](../app/Views/mensaje.php)
- [app/Views/partials/cabecera.php](../app/Views/partials/cabecera.php)
- [app/Views/partials/pie.php](../app/Views/partials/pie.php)
- [app/bootstrap-web.php](../app/bootstrap-web.php)
- [docs/login-mvc.md](../docs/login-mvc.md)
- [public/assets/bootstrap/LICENSE](../public/assets/bootstrap/LICENSE)
- [public/assets/bootstrap/bootstrap.min.css](../public/assets/bootstrap/bootstrap.min.css)
- [public/assets/css/autenticacion.css](../public/assets/css/autenticacion.css)
- [public/assets/js/autenticacion.js](../public/assets/js/autenticacion.js)
- [routes/web.php](../routes/web.php)
- [tests/login-mvc.php](../tests/login-mvc.php)

## Arquitectura y flujo

- public/index.php: composición HTTP, cabeceras/errores y despacho según routes/web.php. app/bootstrap-web.php carga clases sin abrir configuración o conexión.
- AutenticacionController: formulario, acceso, bienvenida, cierre y reservas protegidas; sin SQL.
- AutenticacionService y AutorizacionService: password_verify(), activo=1, catálogo permitido y gestión exclusiva de ADMINISTRADOR.
- UsuarioRepository/PdoUsuarioRepository: solo SELECT preparados con JOIN rol; columnas reales nombres, apellidos, usuario, hash_contrasena, id_usuario, activo y relación id_rol. No INSERT, UPDATE, DELETE, DDL o rehash persistido.
- UsuarioAutenticado: identidad/rol aptos para vistas, sin hash o contraseña.
- SessionStore/SesionPhp y SesionService: almacenamiento PHP, regeneración e inactividad; CsrfService protege los formularios.
- Vista y plantillas PHP: contenido dinámico escapado con htmlspecialchars; estilos/scripts públicos separados.

Environment, config/database.php y ConnectionFactory se reutilizan sin duplicar secretos. La fábrica PDO es diferida: un visitante anónimo puede ver login y rechazos CSRF sin cargar configuración privada o conectar. Un POST válido para comprobar credenciales y las peticiones autenticadas usan la cuenta de aplicación existente, únicamente para lectura.

El POST de login valida CSRF antes de obtener PDO. Busca el usuario con parámetros, verifica el hash y rechaza inexistentes, contraseña incorrecta, inactivos o roles desconocidos mediante el mismo mensaje genérico. No se recorta la contraseña; entradas UTF-8 y límites se validan en servidor. El límite técnico bcrypt de 72 bytes se rechaza sin truncar. Un hash ficticio exclusivamente técnico permite ejecutar password_verify cuando no hay una cuenta; no concede autenticación ni es una credencial de SFATSA.

El login elimina el estado previo, regenera ID y descarta CSRF anterior. La sesión guarda id_usuario, última actividad y rol anterior solo para detectar cambios de privilegios; el rol que concede acceso se consulta nuevamente en la BD por cada petición privada. Una desactivación o rol desconocido invalida la sesión; un cambio de rol renueva ID y CSRF.

Cookies exclusivas, modo estricto, HttpOnly, SameSite=Lax y Secure cuando HTTPS está activo. Expiración del servidor tras 1800 segundos de inactividad; actividad válida renueva el plazo. El cierre requiere POST/CSRF, elimina estado y sesión y expira cookie. No hay hash/contraseña en sesión.

Se envían cabeceras no-store, CSP para recursos propios, nosniff y política de referencia. El límite HTTP captura errores y responde 503 sin SQL, DSN, configuración, credenciales o trazas. No se registran excepciones originales ni contraseñas.

## Referencia visual efectivamente consultada

Figma MCP leyó [el archivo Make original](https://www.figma.com/make/mw4xDH3lehv8ydwYJC1Kjk/Wireframes-para-SFATSA), src/App.tsx (LoginScreen) y src/index.css, sin escrituras.

Observado: marca tipográfica SFATSA; columnas 42%/58%; panel #303335 con trama de 34 px; fondo claro #f7f7f5; texto #222426; botón #343739; títulos Georgia y declaración Inter/system-ui; formulario de ancho máximo 385 px. El login del prototipo incluye un ejemplo de error y datos ficticios; no se copiaron esas credenciales o un error permanente.

Adaptación implementada: plantilla PHP, controles Bootstrap con estilos del diseño, contraste/legibilidad, etiquetas explícitas, foco visible, error real anunciado y carga de envío. En móvil se apilan panel/formulario. Se sustituyó el texto de wireframe por un pie institucional y se omitió el navegador del catálogo de prototipos, que no pertenece al flujo del usuario. La bienvenida posterior es provisional autorizada, no una pantalla observada en Figma.

Make no admite get_metadata/get_screenshot de Design: esas llamadas devolvieron tipo de archivo no soportado. No se inventa una captura del diseño original; la fuente consultada y las capturas locales de la implementación sustentan esta adaptación. El LoginScreen no usa imágenes/SVG visibles: no se descargaron imágenes ajenas a esa pantalla.

Bootstrap CSS 5.3.8 se incorporó localmente, sin npm/Composer ni Bootstrap JS/Popper. Se conservó su licencia MIT y se comprobó SHA-384 contra el valor de la [documentación oficial](https://getbootstrap.com/docs/5.3/getting-started/introduction/). La interfaz no depende de CDN, React o Tailwind.

## Verificación ejecutada

Los conteos y comprobaciones de esta sección corresponden al incremento original Prompt 17, EV-11. Regresiones y cambios posteriores se registran en EV-12/EV-13/EV-14, sin atribuir a estos antecedentes ejecución de las nuevas operaciones.

- PHP 8.2.12: sintaxis correcta en los 35 archivos PHP.
- tests/login-mvc.php: 75 comprobaciones correctas con repositorio/PDO/sesión ficticios y sesiones CGI temporales propias eliminadas al terminar. Sin conexión MariaDB.
- Regresión: 33 comprobaciones de infraestructura y 170 del bootstrap correctas; no se ejecutó un alta real. Sintaxis JavaScript correcta con Node existente.
- Apache/XAMPP: GET / devuelve 200 y el formulario; CSS, JavaScript y Bootstrap locales devuelven 200, cookie HttpOnly/SameSite=Lax y cabeceras sin caché.
- Una petición HTTP con CSRF válido y usuario aleatorio ficticio inexistente devolvió el mensaje genérico esperado. Ese flujo abrió PDO y ejecutó únicamente SELECT, sin recuperar una cuenta real ni modificar registros.
- Chrome local aislado: vista de escritorio 1440 × 900 y emulación móvil 390 × 844; contenido sin desbordamiento horizontal (390 px), controles etiquetados, contraseña de tipo password, envío deshabilitado/aria-busy/indicador y restauración al regresar.
- Error CSRF en navegador: visible, foco en el aviso, contraseña vacía y ancho móvil correcto, sin abrir PDO.

Las 75 comprobaciones cubren credenciales correctas/incorrectas de los tres roles, inexistentes/inactivos/desconocidos, CSRF, regeneración, rol vigente, reservas protegidas, 30 minutos de inactividad, cierre, XSS, SQL parametrizado y cookies nativas HTTP/HTTPS mediante CGI. Trazabilidad: S01-TEC-T2, CA-TEC-02 a CA-TEC-05, SEG-01 a SEG-07 y subcasos autónomos de P-TEC-02/03/04 y P-SEG-01/02/03/04/05/06/07/08/09.

Para repetir comprobaciones aisladas:

```powershell
& 'C:\xampp\php\php.exe' '.\tests\login-mvc.php'
& 'C:\xampp\php\php.exe' '.\tests\pdo-infrastructure.php'
& 'C:\xampp\php\php.exe' '.\tests\administrador-inicial.php'
```

## Límites y siguiente trabajo

El usuario confirmó después el inicio exitoso real ADMINISTRADOR y el rechazo de credenciales incorrectas; el agente no pidió/utilizó su contraseña. Las pruebas de todos los roles y cambios de estado/rol del agente fueron con dobles. HTTPS no se configuró: Secure se comprobó con CGI simulado. HU-01 y HU-03 están implementadas en incrementos posteriores; algunas escrituras HU-01 fueron confirmadas por el usuario. HU-03, reactivación y protección del último rol mantienen pruebas manuales pendientes. No hay pruebas exhaustivas ni limitación de intentos, MFA o recuperación.

S01-TEC-T2 no se declara cerrada por este incremento: falta la verificación integrada futura con las HU. Los 14 estados de tareas y 46 IDs principales previos se conservaron. La reactivación y la protección del último rol están autorizadas técnicamente en Prompt 19; ratificación académica y selección/asignación futura de conductores siguen pendientes. MEMORY.md conserva estado anterior; se propone su actualización por separado, sin modificarlo automáticamente.
