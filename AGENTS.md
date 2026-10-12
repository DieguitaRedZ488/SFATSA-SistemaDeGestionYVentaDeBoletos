# Instrucciones para agentes — SFATSA

## Proyecto y stack

SFATSA es el Sistema de Gestión y Venta de Boletos. Su implementación debe responder a los requisitos aprobados del proyecto.

El stack establecido es PHP, MariaDB, PDO, HTML5, CSS3, Bootstrap 5 y JavaScript.

## Arquitectura y consulta previa

- Utilizar una arquitectura de tres capas: presentación, lógica de negocio y acceso a datos.
- Aplicar el patrón MVC: vistas para la presentación, controladores para coordinar solicitudes y modelos para representar el dominio.
- Encapsular las reglas de negocio en servicios y el acceso a datos en repositorios mediante PDO.
- Antes de implementar, consultar `docs/constitution.md` y la documentación del sprint correspondiente en `Documentos/`, incluidas historias, casos de uso y backlogs. Si existen contradicciones, solicitar aclaración antes de implementar el comportamiento afectado.

## Convenciones

- Clases: `PascalCase`.
- Métodos y variables: `camelCase`.
- Constantes: `UPPER_SNAKE_CASE`.
- Tablas y columnas: `snake_case`.
- Documentación e interfaz: español.

## Seguridad

- Utilizar PDO con consultas preparadas y parámetros vinculados para los datos de entrada. No concatenar entradas del usuario en SQL.
- Almacenar contraseñas mediante `password_hash()` y comprobarlas con `password_verify()`.
- Validar los datos del lado del servidor; la validación del cliente es complementaria.
- Comprobar la autorización por roles en el servidor antes de permitir cada operación protegida.

## Flujo de trabajo y límites

- Desarrollar en ramas, con cambios pequeños y revisables, y ejecutar las pruebas pertinentes antes de integrar.
- Composer está permitido únicamente cuando exista una necesidad justificada y aprobada.
- No modificar `Documentos/`, modelos de datos ni especificaciones aprobadas sin autorización del usuario.
- No instalar herramientas ni dependencias sin autorización del usuario.
- No realizar commits, push ni merges sin autorización del usuario.
