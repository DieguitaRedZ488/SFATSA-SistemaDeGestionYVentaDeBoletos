# Memoria del proyecto SFATSA

## Estado actual

- Sistema de Gestión y Venta de Boletos.
- Fase 04: configuración inicial, sin implementación funcional.
- `Index.html` contiene una página de prueba.
- Ya existen instrucciones, constitución y configuración básica del repositorio.

## Entorno y decisiones

- Entorno local informado: PHP 8.2.12 y Apache 2.4.58.
- MariaDB independiente: servicio activo en el puerto 3306; cliente 12.0.2. Versión exacta del servidor pendiente de consulta.
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

- Rama de trabajo: `chore/configuracion-inicial`.
- Rama publicada, con seguimiento de `origin/chore/configuracion-inicial`.
- Commit de referencia: `ae0f065`.
- Integración a `main` pendiente; los cambios requieren revisión mediante Pull Request.

## Límites de trabajo

- Consultar `AGENTS.md` y `docs/constitution.md` antes de implementar.
- No modificar documentación académica, modelos ni especificaciones aprobadas sin autorización.
- No instalar herramientas, dependencias ni MCP sin autorización.
- No ejecutar commits, push ni merges sin autorización.
- Esta fase no crea código funcional, estructura MVC ni especificaciones de sprint.

## Próximos pasos

1. Terminar la configuración de Codex.
2. Preparar la estructura técnica con autorización.
3. Aclarar el alcance y las discrepancias del Sprint 1.
4. Aplicar SDD por sprint y HU, con aprobación antes de implementar.
