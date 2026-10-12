# SFATSA-SistemaDeGestionYVentaDeBoletos

Repositorio de colaboradores del proyecto

## Descripción y alcance

SFATSA es el Sistema de Gestión y Venta de Boletos. La documentación contempla la gestión de usuarios y roles, vehículos y mantenimientos, conductores, rutas y paradas, tarifas, operación de viajes, disponibilidad de lugares, venta y cobro de boletos, cancelaciones autorizadas y consultas de ventas y viajes.

## Estado actual

El proyecto se encuentra en planificación y configuración inicial, sin funcionalidades ni tablas creadas para la aplicación. `Index.html` conserva una página de prueba; la entrada web local es `public/index.php`, que muestra «SFATSA está en preparación.».

El VirtualHost `sfatsa.test` está activo en el equipo de referencia y funciona por HTTP en Chrome y curl. Se comprobaron el bloqueo de `/phpmyadmin/`, la protección de `AGENTS.md` y `MEMORY.md` en las rutas probadas, la redirección habitual de `localhost/` a `/dashboard/` y un 404 en `/icons.svg`. Estas comprobaciones no cubren todas las rutas ni variantes de seguridad. HTTPS propio de SFATSA no está configurado.

Consulta la [guía de configuración local de Apache](docs/configuracion-apache-local.md) para reproducir el entorno y realizar tus propias verificaciones. MariaDB 12.0 se ejecuta de forma independiente; no debe iniciarse MySQL de XAMPP. Todavía no se ha probado una conexión PDO desde SFATSA, ni existen migraciones o un cargador de variables de entorno.

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
| [Documentos/Historias_Usuario.docx](Documentos/Historias_Usuario.docx) | Once historias de usuario y sus prioridades. |
| [Documentos/Historias_Descripciones.docx](Documentos/Historias_Descripciones.docx) | Descripciones, requisitos y criterios de aceptación. |
| [Documentos/Product_Backlog.docx](Documentos/Product_Backlog.docx) | Planificación, dependencias y estimaciones de las historias. |
| [Documentos/Sprint_Backlog.docx](Documentos/Sprint_Backlog.docx) | Tareas, responsables y estimaciones por sprint. |
| [Documentos/Casos_Uso/](Documentos/Casos_Uso/) | Modelos UML `.mdj` organizados en carpetas de Sprint 1 a Sprint 6. |

Antes de implementar, consultar la constitución y la documentación del sprint correspondiente. Las diferencias de denominación, asignación a sprints o estimaciones entre documentos deben aclararse antes de implementar el comportamiento afectado. No modificar `Documentos/`, modelos de datos ni especificaciones aprobadas sin autorización.

## Entorno y versiones pendientes

| Componente | Requisito conocido | Pendiente de verificar |
| --- | --- | --- |
| PHP | Versión local informada: 8.2.12; PDO y `pdo_mysql` acordados. | Versión objetivo y conexión PDO desde SFATSA. |
| MariaDB | Servidor independiente 12.0 identificado; puerto local informado: 3306. | Versión objetivo y conexión desde la aplicación. |
| Servidor web | Apache de XAMPP; versión local informada: 2.4.58; `sfatsa.test` activo por HTTP. | Reproducir y validar en cada equipo; HTTPS propio pendiente. |
| Bootstrap | Versión principal 5. | Versión específica y forma de incorporación. |
| Navegador | Soporte de HTML5, CSS3 y JavaScript. | Navegadores y versiones que se validarán. |

Los datos anteriores describen el equipo de referencia y no establecen versiones mínimas para el proyecto. La guía distingue los resultados confirmados de las pruebas recomendadas para cada integrante.

## Configuración básica

- `.gitignore` excluye entornos con secretos, dependencias, temporales, logs y cachés, y conserva `.env.example` como archivo versionable.
- `.editorconfig` declara UTF-8, LF, salto de línea final, eliminación de espacios al final y sangría de 4 espacios para PHP y 2 para HTML, CSS, JavaScript, JSON, YAML y Markdown. Sus reglas dependen del soporte del editor y no se han aplicado automáticamente a archivos existentes.
- `.env.example` contiene valores ilustrativos para el entorno local, la aplicación y MariaDB. El puerto, la URL y el nombre de base de datos no confirman una configuración existente. Usuario y contraseña están vacíos. La plantilla no crea una base de datos ni establece una conexión; su carga todavía no está implementada.

## Colaboración con Git y ramas

1. Comprobar la rama activa y el estado del repositorio antes de editar; revisar y respetar los cambios existentes.
2. Trabajar en una rama acordada. La configuración local de Apache y su documentación se preparan en `chore/configuracion-apache-local`.
3. Consultar las instrucciones, la constitución y los documentos del sprint; mantener trazabilidad con historias, casos de uso, tareas y pruebas.
4. Preparar cambios pequeños y revisables, siguiendo las convenciones del proyecto y utilizando español en documentación e interfaz.
5. Verificar el formato y ejecutar las pruebas pertinentes antes de integrar; presentar resultados y limitaciones para revisión.
6. Solicitar autorización antes de instalar herramientas o dependencias y antes de realizar commits, push o merges. No versionar secretos.
