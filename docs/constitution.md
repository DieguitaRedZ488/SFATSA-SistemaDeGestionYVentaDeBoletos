# Constitución del proyecto SFATSA

Estos ocho principios rigen el desarrollo del Sistema de Gestión y Venta de Boletos. El stack establecido es PHP, MariaDB, PDO, HTML5, CSS3, Bootstrap 5 y JavaScript.

## 1. Arquitectura de tres capas y MVC

Separar presentación, lógica de negocio y acceso a datos. Aplicar MVC con controladores que coordinen solicitudes, vistas que presenten información y modelos que representen el dominio. Los servicios deben encapsular las reglas de negocio y los repositorios deben concentrar el acceso a MariaDB mediante PDO, evitando SQL en las vistas y duplicación de reglas en los controladores.

## 2. Desarrollo guiado por requisitos aprobados

Consultar la documentación del sprint antes de implementar. Cada cambio funcional debe responder a historias y criterios de aceptación aprobados. No inventar requisitos ni modificar `Documentos/`, modelos de datos o especificaciones aprobadas sin autorización. Resolver las contradicciones documentales con el usuario antes de implementar el comportamiento afectado.

## 3. Trazabilidad entre historias, casos de uso, tareas y pruebas

Identificar las historias de usuario, los casos de uso y las tareas que justifican cada cambio. Relacionar las verificaciones y pruebas con sus criterios de aceptación, de modo que sea posible conocer qué requisito cubre cada implementación y qué evidencia confirma su cumplimiento.

## 4. Seguridad de la aplicación

Utilizar consultas preparadas con PDO y parámetros vinculados, sin concatenar entradas del usuario en SQL. Proteger las contraseñas con `password_hash()` y `password_verify()`. Validar las entradas del lado del servidor y verificar la autorización por roles en cada operación protegida. Escapar la salida según su contexto y mantener credenciales y secretos fuera del código versionado.

## 5. Integridad de datos y control de concurrencia cuando corresponda

Respetar los modelos de datos aprobados y sus restricciones. Utilizar transacciones para operaciones que deban completarse de forma atómica. Cuando varias solicitudes puedan competir por los mismos datos, aplicar el control de concurrencia pertinente para evitar inconsistencias, duplicaciones o asignaciones incompatibles. Verificar estas garantías mediante pruebas adecuadas al caso.

## 6. Simplicidad y dependencias justificadas

Preferir soluciones claras dentro del stack establecido y evitar complejidad innecesaria. Composer solo se permite cuando exista una necesidad justificada y aprobada. No instalar herramientas ni dependencias sin autorización del usuario; documentar la necesidad y el impacto de cualquier dependencia propuesta antes de solicitar su aprobación.

## 7. Verificación y pruebas

Verificar cada cambio antes de integrarlo mediante las comprobaciones y pruebas pertinentes a su alcance. Cubrir los criterios de aceptación, las validaciones, los permisos y la integridad de datos cuando apliquen. Registrar los resultados y comunicar las limitaciones o verificaciones pendientes; no declarar como comprobado un comportamiento que no se haya verificado.

## 8. Colaboración con Git y ramas

Trabajar en ramas con cambios pequeños y revisables. Comprobar la rama activa y el estado del repositorio antes de editar, y respetar los cambios existentes de otros colaboradores. Mantener documentación e interfaz en español y aplicar las convenciones de `AGENTS.md`. No realizar commits, push ni merges sin autorización del usuario. Presentar los cambios y los resultados de verificación para su revisión antes de integrar.
