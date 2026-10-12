# SFATSA-SistemaDeGestionYVentaDeBoletos

Repositorio de colaboradores del proyecto

## Descripción y alcance

SFATSA es el Sistema de Gestión y Venta de Boletos. La documentación contempla la gestión de usuarios y roles, vehículos y mantenimientos, conductores, rutas y paradas, tarifas, operación de viajes, disponibilidad de lugares, venta y cobro de boletos, cancelaciones autorizadas y consultas de ventas y viajes.

## Estado actual

El proyecto se encuentra en planificación y configuración inicial; las funcionalidades del Sprint 1 todavía no están implementadas. Según confirmación del usuario, la base existente `sfatza` contiene 15 tablas, incluida `reembolso`, verificadas previamente mediante el cliente MariaDB. Esta infraestructura no crea ni altera su estructura. `Index.html` conserva una página de prueba; la entrada web local es `public/index.php`, que muestra «SFATSA está en preparación.».

El VirtualHost `sfatsa.test` está activo en el equipo de referencia y funciona por HTTP en Chrome y curl. Se comprobaron el bloqueo de `/phpmyadmin/`, la protección de `AGENTS.md` y `MEMORY.md` en las rutas probadas, la redirección habitual de `localhost/` a `/dashboard/` y un 404 en `/icons.svg`. Estas comprobaciones no cubren todas las rutas ni variantes de seguridad. HTTPS propio de SFATSA no está configurado.

Consulta la [guía de configuración local de Apache](docs/configuracion-apache-local.md) para reproducir el entorno. MariaDB 12.0.2 funciona como servicio independiente de XAMPP; no debe iniciarse MySQL de XAMPP. La autenticación desde CMD está comprobada. La conexión PHP–PDO–MariaDB también fue comprobada mediante `database/check-connection.php`: seleccionó `sfatza`, ejecutó únicamente `SELECT 1` y terminó con código de salida 0. El sitio conserva HTTP 200 y su mensaje de preparación, sin cargar conexiones desde la página. La integración PDO con solicitudes HTTP sigue pendiente y los permisos efectivos sobre tablas mediante PDO no están comprobados. La [guía PDO](docs/conexion-pdo-local.md) distingue estos resultados de las pruebas recomendadas para otros equipos. No hay migraciones implementadas.

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
| Bootstrap | Versión principal 5. | Versión específica y forma de incorporación. |
| Navegador | Soporte de HTML5, CSS3 y JavaScript. | Navegadores y versiones que se validarán. |

Los datos anteriores describen el equipo de referencia y no establecen versiones mínimas para el proyecto. La guía distingue los resultados confirmados de las pruebas recomendadas para cada integrante.

## Configuración básica

- `.gitignore` excluye entornos con secretos, dependencias, temporales, logs y cachés, y conserva `.env.example` como archivo versionable.
- `.editorconfig` declara UTF-8, LF, salto de línea final, eliminación de espacios al final y sangría de 4 espacios para PHP y 2 para HTML, CSS, JavaScript, JSON, YAML y Markdown. Sus reglas dependen del soporte del editor y no se han aplicado automáticamente a archivos existentes.
- `.env.example` contiene la URL local y la base `sfatza`; usuario y contraseña permanecen vacíos. El lector estricto está en `config/Environment.php`. No se crea `.env` automáticamente, ni una base de datos. Ejecutar `tests/pdo-infrastructure.php` para validar con datos ficticios; reservar `database/check-connection.php` para una prueba autorizada después de configurar las credenciales localmente.

## Colaboración con Git y ramas

1. Comprobar la rama activa y el estado del repositorio antes de editar; revisar y respetar los cambios existentes.
2. Trabajar en una rama acordada. La infraestructura PDO se prepara en `chore/conexion-pdo-mariadb`; la documentación de Apache fue integrada mediante el PR #3.
3. Consultar las instrucciones, la constitución y los documentos del sprint; mantener trazabilidad con historias, casos de uso, tareas y pruebas.
4. Preparar cambios pequeños y revisables, siguiendo las convenciones del proyecto y utilizando español en documentación e interfaz.
5. Verificar el formato y ejecutar las pruebas pertinentes antes de integrar; presentar resultados y limitaciones para revisión.
6. Solicitar autorización antes de instalar herramientas o dependencias y antes de realizar commits, push o merges. No versionar secretos.
