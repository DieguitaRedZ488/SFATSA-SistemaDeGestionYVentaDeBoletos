# SFATSA-SistemaDeGestionYVentaDeBoletos

Repositorio de colaboradores del proyecto

## Descripción y alcance

SFATSA es el Sistema de Gestión y Venta de Boletos. La documentación contempla la gestión de usuarios y roles, vehículos y mantenimientos, conductores, rutas y paradas, tarifas, operación de viajes, disponibilidad de lugares, venta y cobro de boletos, cancelaciones autorizadas y consultas de ventas y viajes.

## Estado actual

El código implementa la infraestructura MVC del Sprint 1, autenticación/login, gestión de usuarios (HU-01) y gestión de conductores (HU-03). La aplicación local debe configurarse de acuerdo con la guía de instalación y con autorización del responsable de base de datos. `database/install.sql` prepara el esquema completo para una instalación nueva; no es una migración y no debe ejecutarse sobre una base existente.

La migración 20261013_001 de nombres/apellidos se reportó aplicada y verificada manualmente por el usuario. La migración 20261013_002 para `conductor.numero_licencia NOT NULL` está preparada y **no aplicada**; no ejecutar sin autorización, respaldo y precondiciones. Véase el [README de migraciones](database/migrations/README.md).

La implementación vigente y las guías para desarrolladores están en la [documentación técnica](docs/05-documentacion-tecnica/README.md). Para configurar una computadora nueva, comenzar por la [guía de instalación Windows/XAMPP](docs/05-documentacion-tecnica/general/instalacion-windows-xampp.md). También están disponibles las guías de [Apache](docs/05-documentacion-tecnica/general/configuracion-apache-local.md), [PDO](docs/05-documentacion-tecnica/general/conexion-pdo-local.md), [administrador inicial](docs/05-documentacion-tecnica/general/administrador-inicial.md), [usuarios](docs/05-documentacion-tecnica/general/gestion-usuarios.md), [conductores](docs/05-documentacion-tecnica/general/gestion-conductores.md) y [login](docs/05-documentacion-tecnica/general/login-mvc.md).

## Tecnologías y arquitectura acordadas

- PHP, MariaDB y PDO.
- HTML5, CSS3, Bootstrap 5 y JavaScript.
- Arquitectura de tres capas: presentación, lógica de negocio y acceso a datos.
- Patrón MVC, con servicios para las reglas de negocio y repositorios para el acceso a datos mediante PDO.

Composer solo podrá utilizarse si existe una necesidad justificada y aprobada; no hay dependencias instaladas como parte de esta configuración.

## Organización documental

La documentación del proyecto está agrupada por propósito:

| Ubicación | Contenido |
| --- | --- |
| [01 — Gestión del proyecto](docs/01-gestion-del-proyecto/) | Product Backlog y Sprint Backlog general, copiados de los documentos fuente indicados por el equipo. |
| [02 — Documentación por sprint](docs/02-documentacion-por-sprint/) | Entregables por sprint: backlog, historia, casos de uso, diagramas de secuencia y wireframes disponibles. La distribución de HU-02 a HU-11 sigue la asignación definitiva solicitada. |
| [03 — Documentación general](docs/03-documentacion-general/) | Reporte final del proyecto. |
| [04 — Análisis y diseño global](docs/04-analisis-y-diseno-global/) | Diagramas globales de clases, componentes y modelo relacional. |
| [05 — Documentación técnica](docs/05-documentacion-tecnica/README.md) | Instalación, guías operativas y documentación técnica del Sprint 1; no sustituye los entregables académicos. |
Los diagramas StarUML `.mdj` se conservan en formato editable. Las copias académicas anteriores fueron retiradas del repositorio por estar desactualizadas; los entregables vigentes están en `docs/01-gestion-del-proyecto/` y `docs/02-documentacion-por-sprint/`. Los documentos técnicos de implementación se mantienen separados de los backlogs académicos. La distribución documental no altera la planificación ni la especificación del proyecto.

## Entorno y versiones pendientes

| Componente | Requisito conocido | Pendiente de verificar |
| --- | --- | --- |
| PHP | Versión local 8.2.12; conexión PDO por CLI comprobada. | Versión objetivo e integración PDO con solicitudes HTTP. |
| MariaDB | Servicio independiente 12.0.2; `sfatza` con 15 tablas, incluida `reembolso`, verificadas previamente con el cliente según confirmación del usuario. | Permisos efectivos sobre tablas mediante PDO. |
| Servidor web | Apache de XAMPP; versión local informada: 2.4.58; `sfatsa.test` activo por HTTP. | Reproducir y validar en cada equipo; HTTPS propio pendiente. |
| Bootstrap | 5.3.8, archivo local `public/assets/bootstrap/bootstrap.min.css`. | Validar los recursos HTTP localmente en cada equipo. |
| Navegador | Soporte de HTML5, CSS3 y JavaScript. | Navegadores y versiones que se validarán. |

Los datos anteriores describen el equipo de referencia y no establecen versiones mínimas para el proyecto. La guía distingue los resultados confirmados de las pruebas recomendadas para cada integrante.

## Configuración básica

- `.gitignore` excluye entornos con secretos, respaldos, dependencias, temporales, logs, cachés, llaves privadas y archivos locales del sistema/editor, y conserva `.env.example` y los SQL fuente versionables.
- `.editorconfig` declara UTF-8, LF, salto de línea final, eliminación de espacios al final y sangría de 4 espacios para PHP y 2 para HTML, CSS, JavaScript, JSON, YAML y Markdown. Sus reglas dependen del soporte del editor y no se han aplicado automáticamente a archivos existentes.
- `.env.example` contiene la URL local y la base `sfatza`; usuario y contraseña permanecen vacíos. El lector estricto está en `config/Environment.php`. No se crea `.env` automáticamente, ni una base de datos. Ejecutar `tests/pdo-infrastructure.php` para validar con datos ficticios; reservar `database/check-connection.php` para una prueba autorizada después de configurar las credenciales localmente.

## Colaboración con Git y ramas

1. Comprobar la rama activa y el estado del repositorio antes de editar; revisar y respetar los cambios existentes.
2. Trabajar en una rama acordada. La infraestructura PDO se prepara en `chore/conexion-pdo-mariadb`; la documentación de Apache fue integrada mediante el PR #3.
3. Consultar los entregables del sprint y la documentación técnica correspondiente; mantener trazabilidad con historias, casos de uso, tareas y pruebas.
4. Preparar cambios pequeños y revisables, siguiendo las convenciones del proyecto y utilizando español en documentación e interfaz.
5. Verificar el formato y ejecutar las pruebas pertinentes antes de integrar; presentar resultados y limitaciones para revisión.
6. Solicitar autorización antes de instalar herramientas o dependencias y antes de realizar commits, push o merges. No versionar secretos.
