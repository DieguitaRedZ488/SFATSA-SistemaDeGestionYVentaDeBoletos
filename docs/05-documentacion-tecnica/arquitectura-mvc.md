# Arquitectura MVC

La aplicación web está organizada en capas con responsabilidades separadas. El punto de entrada público compone las rutas y los controladores; los controladores coordinan la solicitud, los servicios aplican reglas de negocio y los repositorios acceden a MariaDB mediante PDO.

## Flujo de solicitud

```text
Navegador
  -> public/index.php y routes/web.php
  -> controlador
  -> servicio de negocio
  -> repositorio PDO
  -> MariaDB
  <- vista HTML
```

La aplicación utiliza rutas por parámetro `?ruta=...`, sin depender de reglas de reescritura de Apache. `app/bootstrap-web.php` carga las clases de la aplicación; la conexión se crea de forma diferida cuando una operación necesita persistencia. La configuración y la fábrica de conexiones están documentadas en [Conexión PDO local](general/conexion-pdo-local.md).

## Responsabilidades por capa

| Capa | Ubicación | Responsabilidad |
| --- | --- | --- |
| Presentación | `app/Controllers/`, `app/Views/`, `app/Http/` | Recibir y coordinar solicitudes, seleccionar vistas y producir respuestas. |
| Negocio | `app/Services/`, `app/Models/` | Validar entradas, aplicar reglas y autorización, y coordinar operaciones. |
| Acceso a datos | `app/Repositories/` | Consultar y modificar datos con PDO y consultas preparadas. |
| Composición web | `public/index.php`, `routes/web.php`, `app/bootstrap-web.php` | Resolver rutas y conectar los componentes de la aplicación. |

Las vistas no contienen consultas SQL. Los controladores no sustituyen las validaciones de los servicios, y los repositorios reciben la conexión PDO por inyección. Para el detalle operativo, consulte las guías de [login](general/login-mvc.md), [gestión de usuarios](general/gestion-usuarios.md) y [gestión de conductores](general/gestion-conductores.md).

## Autenticación y escrituras administrativas

Las rutas protegidas vuelven a comprobar la identidad, el estado activo y el rol en el servidor. Los formularios que modifican datos usan solicitudes POST y tokens CSRF verificados en servidor; la validación del navegador es complementaria. Las vistas escapan el texto que renderizan.

Las escrituras administrativas de usuarios y conductores comparten `OperacionAdministrativaService` y una única transacción PDO por operación. El servicio bloquea la fila del rol `ADMINISTRADOR` y revalida al actor antes de ejecutar la operación; el módulo de usuarios también comprueba el total de administradores activos antes de retirar el último rol o desactivar la última cuenta. Los repositorios de la operación deben utilizar la misma conexión. El uso de bloqueos coordina las acciones que siguen este protocolo; no sustituye pruebas de integración de concurrencia con MariaDB ni cubre escritores externos que no lo utilicen.

## Referencias de implementación

- Enrutamiento: `public/index.php` y `routes/web.php`.
- Composición de clases: `app/bootstrap-web.php`.
- Autorización: `app/Services/AutorizacionService.php`.
- Transacciones administrativas: `app/Services/OperacionAdministrativaService.php`.
- Persistencia de conductores: `app/Repositories/PdoConductorRepository.php`.
- Persistencia de usuarios: `app/Repositories/PdoGestionUsuarioRepository.php`.
