# Memoria del proyecto SFATSA

## Estado actual

- Sistema de Gestión y Venta de Boletos.
- Sprint 1 dispone de login MVC, administrador inicial por CLI, gestión de usuarios HU-01 y gestión de conductores HU-03; las guías técnicas describen sus rutas y límites. El código está pendiente de integración y las pruebas manuales adicionales indicadas en la documentación siguen siendo responsabilidad del equipo.
- `public/index.php` es la entrada web y enlaza las pantallas implementadas del Sprint 1.
- VirtualHost `sfatsa.test` activo por HTTP; Apache fue reiniciado y hosts contiene `127.0.0.1 sfatsa.test`, según confirmación del usuario.
- HTTPS propio de SFATSA no está configurado.
- En la inspección de solo lectura de Prompt 22, MariaDB reportó versión `12.0.2-MariaDB-log`; `sfatza` tiene 15 tablas base InnoDB, charset/collation `utf8mb4`/`utf8mb4_uca1400_ai_ci` y 22 columnas de clave foránea. Se consultaron `SHOW CREATE TABLE`, metadatos y solamente los nombres/estados de los catálogos `rol`, `metodo_pago` y `tipo_servicio`; no se leyeron filas de `usuario` ni `conductor`.
- El esquema existente refleja nombres y apellidos separados en `usuario` y `conductor`. `conductor.numero_licencia` sigue `VARCHAR(40) NULL DEFAULT NULL` con índice UNIQUE; la migración 20261013_002 permanece preparada y sin aplicar.
- `database/install.sql` prepara la creación desde cero de las 15 tablas y solo los catálogos comprobados. Incorpora el resultado de 20261013_001 y el objetivo NOT NULL de 20261013_002 para una base nueva. El archivo no fue ejecutado; no se hicieron escrituras ni cambios de esquema.
- La conexión PHP–PDO a `sfatza` permitió esta inspección de solo lectura mediante `ConnectionFactory`; no se comprobaron en esta tarea permisos efectivos de escritura ni flujos HTTP.
- Ya existen instrucciones, constitución y configuración básica del repositorio.

## Entorno y decisiones

- Entorno local informado: PHP 8.2.12 y Apache 2.4.58.
- MariaDB 12.0.2 funciona como servicio independiente de XAMPP en `127.0.0.1:3306`. No iniciar MySQL de XAMPP.
- Acceso a datos acordado: PDO y controlador `pdo_mysql`.
- Interfaz: HTML5, CSS3, Bootstrap 5 y JavaScript.
- Versión específica de Bootstrap 5 pendiente de definir.
- Arquitectura de tres capas: presentación, negocio y acceso a datos.
- Patrón MVC, con servicios para el negocio y repositorios para los datos.
- Composer permitido únicamente con necesidad justificada y aprobada.
- Documentación e interfaz en español.
- Colaboración mediante ramas y Pull Requests.

## Documentación y revisión

- Documentación académica en `Documentos/`: 11 historias y seis sprints.
- Incluye historias, descripciones, Product Backlog, Sprint Backlog y casos de uso UML.
- Existen discrepancias pendientes entre backlogs y diagramas.
- También hay diferencias de denominación y estimaciones entre documentos.
- No se ha decidido unilateralmente cuál documento es correcto.
- Antes de implementar cada sprint, revisar requisitos y criterios de aceptación.
- Revisar también casos de uso, modelo de datos y wireframes del sprint.
- Confirmar los artefactos disponibles; no inventar los que falten.
- Aclarar contradicciones con el usuario y mantener trazabilidad HU, CU, tareas y pruebas.

## Estado de Git

- Rama de trabajo observada: `docs/sprint-01-planificacion`, HEAD `ed791dbaa06f54ed6588268f1a301acb87617d33`. Hay cambios locales pendientes; no se han preparado en el índice ni confirmado en Git durante esta tarea.

## Infraestructura PDO

- Guía: [docs/conexion-pdo-local.md](docs/conexion-pdo-local.md).
- `config/Environment.php` prioriza variables del proceso y lee `.env` privado sin dependencias, interpolación ni ejecución. Conserva valores de contraseña literalmente.
- `config/database.php` valida configuración local, exige credenciales y rechaza root; `app/Repositories/ConnectionFactory.php` crea PDO sin persistencia ni singleton.
- `database/check-connection.php` solo acepta CLI, selecciona `sfatza`, ejecuta `SELECT 1` y devuelve mensajes generales sin secretos. La prueba real autorizada devolvió «Conexión PDO comprobada correctamente.» y código de salida 0.
- En la comprobación histórica de infraestructura: sintaxis de cinco archivos PHP correcta y 33 comprobaciones locales con datos ficticios aprobadas, sin conexión a MariaDB; `.env` ignorado por Git. El HTTP 200 con mensaje de preparación corresponde a antes de integrar login y las gestiones del Sprint 1.
- El usuario configuró manualmente `.env` privado; su existencia y exclusión de Git se comprobaron sin mostrar su contenido. No se crea automáticamente. Las credenciales reales permanecen fuera de Git; no mostrar DSN, configuración ni trazas por HTTP.

## Apache local y verificaciones

- Guía reproducible: [docs/configuracion-apache-local.md](docs/configuracion-apache-local.md).
- Configuración local en `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, fuera del repositorio, con respaldo temporal también externo.
- `localhost` es el VirtualHost predeterminado del puerto 80. `sfatsa.test` apunta exclusivamente a `public/`.
- El proyecto tiene acceso web denegado salvo `public`, permitido localmente. Se bloquean rutas de Alias y handlers globales de XAMPP y se excluye el proxy `/examples` en SFATSA.
- `LocationMatch` delimita las rutas con `(?:/|$)`; hay una regla global para denegar HTTPS con Host `sfatsa.test` mientras no exista HTTPS propio.
- Validaciones realizadas por el agente: `httpd.exe -t` devolvió `Syntax OK` y código 0; `httpd.exe -S` devolvió código 0 y mostró `localhost` predeterminado, `sfatsa.test` en puerto 80 y el VirtualHost SSL existente en 443.
- Pruebas realizadas en este equipo y confirmadas por el usuario: Chrome normal abrió `http://sfatsa.test/`; curl devolvió HTTP 200 y el mensaje de preparación; `/phpmyadmin/` devolvió 403; `AGENTS.md` y `MEMORY.md` quedaron protegidos en las rutas comprobadas; `localhost/` mantuvo la redirección a `/dashboard/`; `/icons.svg` devolvió 404.
- No se afirma que todas las rutas o variantes de seguridad hayan sido verificadas. La guía incluye pruebas recomendadas adicionales, sin presentarlas como realizadas.
- No versionar archivos reales de Apache, hosts, respaldos ni credenciales; mantener únicamente documentación y ejemplos sin secretos.

## Límites de trabajo

- Consultar `AGENTS.md` y `docs/constitution.md` antes de implementar.
- No modificar documentación académica, modelos ni especificaciones aprobadas sin autorización.
- No instalar herramientas, dependencias ni MCP sin autorización.
- No ejecutar commits, push ni merges sin autorización.
- Los inicializadores y migraciones SQL son artefactos para revisión y ejecución autorizada en una instalación local; no ejecutarlos sobre la base existente por defecto.
- No realizar escrituras, cambios de esquema, migraciones ni pruebas destructivas en `sfatza` sin autorización explícita.

## Próximos pasos

1. Revisar el inicializador SQL y probarlo únicamente en una base local desechable autorizada antes de compartirlo.
2. Mantener 20261013_002 como pendiente para `sfatza`; cualquier ejecución requiere autorización expresa, respaldo/ensayo y verificación de precondiciones.
3. Reproducir en otros equipos las comprobaciones de Apache y revisar los requisitos técnicos de Sprint 1.
4. HTTPS propio y discrepancias documentales del Sprint 1 siguen pendientes.
