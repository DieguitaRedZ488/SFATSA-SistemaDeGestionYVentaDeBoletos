# SFATSA-SistemaDeGestionYVentaDeBoletos

Repositorio de colaboradores del proyecto

## Descripción y alcance

SFATSA es el Sistema de Gestión y Venta de Boletos. La documentación contempla la gestión de usuarios y roles, vehículos y mantenimientos, conductores, rutas y paradas, tarifas, operación de viajes, disponibilidad de lugares, venta y cobro de boletos, cancelaciones autorizadas y consultas de ventas y viajes.

## Estado actual

El código implementa la infraestructura MVC del Sprint 1, autenticación/login, gestión de usuarios (HU-01) y gestión de conductores (HU-03). La aplicación usa la base existente `sfatza`; no incluye un volcado ni un script completo para crear todas las tablas desde una base vacía. El aprovisionamiento/restauración inicial del esquema debe coordinarse con el responsable de base de datos.

La migración 20261013_001 de nombres/apellidos se reportó aplicada y verificada manualmente por el usuario. La migración 20261013_002 para `conductor.numero_licencia NOT NULL` está preparada y **no aplicada**; no ejecutar sin autorización, respaldo y precondiciones. Véase el [README de migraciones](database/migrations/README.md).

La implementación del Sprint 1 y sus límites/pruebas se describen en la documentación técnica del [Sprint 1](docs/sprints/sprint-01/plan.md). Para configurar una computadora nueva, comenzar por la [guía de instalación Windows/XAMPP](docs/instalacion-windows-xampp.md). También están disponibles las guías de [Apache](docs/configuracion-apache-local.md), [PDO](docs/conexion-pdo-local.md), [administrador inicial](docs/administrador-inicial.md), [usuarios](docs/gestion-usuarios.md), [conductores](docs/gestion-conductores.md) y [login](docs/login-mvc.md).

## Tecnologías y arquitectura acordadas

- PHP, MariaDB y PDO.
- HTML5, CSS3, Bootstrap 5 y JavaScript.
- Arquitectura de tres capas: presentación, lógica de negocio y acceso a datos.
- Patrón MVC, con servicios para las reglas de negocio y repositorios para el acceso a datos mediante PDO.

Estas decisiones están recogidas en [AGENTS.md](AGENTS.md) y [docs/constitution.md](docs/constitution.md). Composer solo podrá utilizarse si existe una necesidad justificada y aprobada; no hay dependencias instaladas como parte de esta configuración.

## Organización documental

| Ubicación | Contenido |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Instrucciones de trabajo, convenciones y límites para agentes. |
| [docs/constitution.md](docs/constitution.md) | Ocho principios que rigen el desarrollo. |
| [docs/configuracion-apache-local.md](docs/configuracion-apache-local.md) | Configuración de Windows y XAMPP, seguridad, validación y pruebas locales. |
| [docs/conexion-pdo-local.md](docs/conexion-pdo-local.md) | Variables privadas, fábrica PDO y comprobación CLI de solo lectura. |
| [docs/instalacion-windows-xampp.md](docs/instalacion-windows-xampp.md) | Preparación en otra computadora, límites del esquema disponible y acceso a la aplicación. |
| [Documentos/Historias_Usuario.docx](Documentos/Historias_Usuario.docx) | Once historias de usuario y sus prioridades. |
| [Documentos/Historias_Descripciones.docx](Documentos/Historias_Descripciones.docx) | Descripciones, requisitos y criterios de aceptación. |
| [Documentos/Product_Backlog.docx](Documentos/Product_Backlog.docx) | Planificación, dependencias y estimaciones de las historias. |
| [Documentos/Sprint_Backlog.docx](Documentos/Sprint_Backlog.docx) | Tareas, responsables y estimaciones por sprint. |
| [Documentos/Casos_Uso/](Documentos/Casos_Uso/) | Modelos UML `.mdj` organizados en carpetas de Sprint 1 a Sprint 6. |

Antes de implementar, consultar la constitución y la documentación del sprint correspondiente. Las diferencias de denominación, asignación a sprints o estimaciones entre documentos deben aclararse antes de implementar el comportamiento afectado. No modificar `Documentos/`, modelos de datos ni especificaciones aprobadas sin autorización.

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
3. Consultar las instrucciones, la constitución y los documentos del sprint; mantener trazabilidad con historias, casos de uso, tareas y pruebas.
4. Preparar cambios pequeños y revisables, siguiendo las convenciones del proyecto y utilizando español en documentación e interfaz.
5. Verificar el formato y ejecutar las pruebas pertinentes antes de integrar; presentar resultados y limitaciones para revisión.
6. Solicitar autorización antes de instalar herramientas o dependencias y antes de realizar commits, push o merges. No versionar secretos.
