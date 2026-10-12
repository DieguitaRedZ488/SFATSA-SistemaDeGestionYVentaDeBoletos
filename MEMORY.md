# Memoria del proyecto SFATSA

## Estado actual

- Sistema de Gestión y Venta de Boletos.
- Fase 04: configuración inicial, sin implementación funcional.
- `Index.html` contiene una página de prueba.
- `public/index.php` es la entrada web local y muestra «SFATSA está en preparación.».
- VirtualHost `sfatsa.test` activo por HTTP; Apache fue reiniciado y hosts contiene `127.0.0.1 sfatsa.test`, según confirmación del usuario.
- HTTPS propio de SFATSA no está configurado.
- No hay funcionalidades implementadas ni tablas creadas para la aplicación; conexión PDO desde SFATSA pendiente de probar.
- Ya existen instrucciones, constitución y configuración básica del repositorio.

## Entorno y decisiones

- Entorno local informado: PHP 8.2.12 y Apache 2.4.58.
- MariaDB independiente 12.0 identificada previamente, según confirmación del usuario; puerto informado: 3306; cliente 12.0.2. No iniciar MySQL de XAMPP.
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

- Rama de trabajo actual: `chore/configuracion-apache-local`.
- Referencia histórica de la configuración inicial: rama `chore/configuracion-inicial`, publicada con seguimiento de `origin/chore/configuracion-inicial`; commit `ae0f065`.
- Integración a `main` pendiente; los cambios requieren revisión mediante Pull Request.

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
- Esta fase no crea código funcional, estructura MVC ni especificaciones de sprint.

## Próximos pasos

1. Revisar la documentación de Apache y reproducir las verificaciones en los equipos del grupo.
2. Preparar la estructura técnica y probar PDO con autorización; HTTPS propio sigue pendiente.
3. Aclarar el alcance y las discrepancias del Sprint 1.
4. Aplicar SDD por sprint y HU, con aprobación antes de implementar.
