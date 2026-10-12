---
name: php-security
description: "Usar al diseñar, implementar o revisar autenticación, autorización, formularios, sesiones, consultas SQL y operaciones de datos en PHP para SFATSA."
---

# Seguridad PHP para SFATSA

Consultar `AGENTS.md`, `MEMORY.md`, `docs/constitution.md` y los requisitos aprobados de la tarea. Aplicar los controles pertinentes dentro del alcance autorizado, respetando las tres capas, MVC, servicios y repositorios.

## Controles

- **PDO y SQL:** utilizar `prepare()` y parámetros vinculados para todos los valores de entrada. No concatenarlos en SQL. Los identificadores y la ordenación dinámicos requieren listas permitidas basadas en el modelo aprobado; no se parametrizan como valores.
- **Entradas:** validar en servidor tipo, longitud, formato, rango y reglas de negocio aprobadas. No confiar en validación JavaScript, campos ocultos ni identificadores enviados por el cliente.
- **Salidas:** para texto HTML y atributos de texto entre comillas, utilizar `htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` al renderizar. Validar URLs y sus esquemas y codificar según contexto; evitar interpolar datos no confiables en JavaScript, CSS o atributos de eventos.
- **Contraseñas:** almacenar únicamente hashes mediante `password_hash()` y comprobarlos con `password_verify()`. No almacenar ni registrar contraseñas en texto claro, ni usar MD5 o SHA como sustitutos.
- **Sesiones:** configurar modo estricto, uso exclusivo de cookies, `HttpOnly` y `SameSite` adecuado antes de `session_start()`; utilizar `Secure` bajo HTTPS y HTTPS en producción. Regenerar el identificador mediante `session_regenerate_id()` tras autenticar o cambiar privilegios. Invalidar sesión y cookie al salir; aplicar la caducidad acordada.
- **CSRF:** proteger las solicitudes que modifican datos con tokens impredecibles generados con `random_bytes()`, asociados a la sesión y comprobados en servidor con `hash_equals()`. Rechazar tokens ausentes o inválidos antes de cualquier escritura. No modificar datos mediante GET; `SameSite` es una protección complementaria.
- **Autorización:** comprobar en servidor el usuario activo, su rol y el permiso sobre la operación y el recurso en cada acceso protegido. Denegar accesos sin permiso; ocultar botones no es un control de autorización.
- **Ventas y lugares:** utilizar transacciones PDO en un motor transaccional, con `commit()` al completar y `rollBack()` ante fallos. Revalidar disponibilidad dentro de la transacción y aplicar bloqueo de filas, como `SELECT ... FOR UPDATE`, o escrituras condicionales atómicas según el modelo aprobado. Comprobar el resultado de las escrituras; una consulta previa aislada no evita una doble venta.
- **Errores y secretos:** mostrar mensajes generales al usuario y registrar detalles sin contraseñas, tokens ni identificadores de sesión. No exponer trazas, SQL o credenciales. Mantener secretos fuera del repositorio y del acceso web; desactivar la exposición de errores en producción y validar la configuración requerida.

## Comprobaciones antes de terminar

- [ ] Entradas inválidas, valores SQL y salidas maliciosas se manejan correctamente.
- [ ] Contraseñas y sesiones están protegidas; cierre de sesión y regeneración funcionan cuando aplican.
- [ ] CSRF ausente o inválido y accesos directos sin permiso se rechazan sin modificar datos.
- [ ] Ventas simultáneas sobre un mismo lugar y fallos intermedios no producen doble asignación ni escrituras parciales cuando aplica.
- [ ] Errores, logs y archivos accesibles no revelan secretos; registrar pruebas realizadas, resultados y pendientes.

## Límites

No introducir frameworks, librerías ni dependencias sin autorización. No inventar permisos ni modificar modelos o especificaciones aprobadas. Esta skill no autoriza implementar funcionalidades, instalar herramientas ni ejecutar operaciones Git fuera del alcance aprobado.

Referencias: [PDO](https://www.php.net/manual/en/pdo.prepare.php), [sesiones PHP](https://www.php.net/manual/en/session.security.ini.php), [XSS](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html), [CSRF](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html) y [bloqueos MariaDB](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).
