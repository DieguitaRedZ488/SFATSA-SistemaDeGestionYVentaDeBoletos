# Conexión PDO local

Infraestructura mínima para PHP 8.2 y MariaDB 12.0.2, que funciona como servicio independiente de XAMPP en `127.0.0.1:3306`. No instala dependencias ni crea tablas. Según confirmación del usuario, la base existente `sfatza` contiene 15 tablas, incluida `reembolso`, verificadas previamente mediante el cliente MariaDB. Las funcionalidades del Sprint 1 todavía no están implementadas.

## Resultados comprobados en este equipo

| Comprobación | Estado |
| --- | --- |
| Autenticación desde CMD de la cuenta de aplicación | Comprobada, según confirmación del usuario. |
| Conexión PHP–PDO–MariaDB por CLI | Comprobada mediante `database/check-connection.php`, seleccionando `sfatza` y ejecutando únicamente `SELECT 1`; mensaje de éxito y código de salida 0. |
| Sitio `http://sfatsa.test/` | HTTP 200 y mensaje de preparación, sin conexiones activadas desde la página. |
| Integración PDO con solicitudes HTTP | Pendiente. |
| Permisos efectivos sobre tablas mediante PDO | No comprobados; `SELECT 1` no accede a las tablas. |

El usuario configuró manualmente `.env` privado. Se comprobó que está ignorado y no versionado, sin mostrar su contenido. Los resultados anteriores corresponden a este equipo; cada integrante debe comprobar su entorno sin asumir que ya fue validado.

## Configuración privada

Crear manualmente `.env` en la raíz a partir de `.env.example`, solo cuando se disponga de las credenciales locales. No compartirlas por chat ni introducirlas como argumentos de consola. Completar `DB_USERNAME` con la cuenta de aplicación y `DB_PASSWORD` localmente. No utilizar root. El archivo está ignorado por Git y debe tener permisos de lectura restringidos; mantenerlo fuera de `public/` y no copiarlo a documentación, logs o respaldos versionados.

Valores no secretos acordados: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=sfatza`, `DB_CHARSET=utf8mb4`. La plantilla deja usuario y contraseña vacíos: no permite conectar hasta completarlos. La cuenta SQL se identifica por usuario y host; `DB_USERNAME` contiene únicamente el nombre de usuario, sin `@127.0.0.1`.

## Formato del lector

`config/Environment.php` carga `.env` al recibir su ruta. No busca rutas alternativas ni modifica variables del proceso. `getenv()` tiene prioridad por clave, incluso si devuelve una cadena vacía; una variable vacía no toma automáticamente el valor del archivo. Un archivo ausente permite usar variables del proceso; uno existente e ilegible o mal formado provoca un error controlado.

Formato estricto y pequeño, distinto de una implementación completa de dotenv:

- UTF-8 sin BOM; finales de línea LF o CRLF. Una asignación `CLAVE=valor` por línea, sin espacios alrededor de la clave o antes de `=`.
- Claves en mayúsculas, con dígitos y guion bajo; no se permiten duplicados ni `export`.
- Líneas vacías y comentarios que comienzan con `#`, opcionalmente indentados, se ignoran.
- Todo lo que sigue al primer `=` es literal, incluidos espacios, `#`, `$`, `=` y barras invertidas. No existen comentarios al final de una asignación.
- Si el primer carácter del valor es una comilla simple o doble, debe cerrar con la misma comilla al final. Solo se eliminan las comillas exteriores; esa misma comilla no puede aparecer dentro. Se puede utilizar la otra clase de comillas o un valor sin comillas cuando sea necesario.
- No hay interpolación, escapes, ejecución, valores multilínea ni caracteres de control. La contraseña no se recorta. Espacios exteriores a las comillas no se admiten como decoración.

Los errores contienen mensajes generales o números de línea, nunca valores ni rutas privadas. Variables del proceso y archivos locales pueden diferir entre Apache y CLI; una prueba CLI no valida automáticamente la integración HTTP.

## Configuración y fábrica

`config/database.php` devuelve una función validadora que recibe `Environment`. Valida loopback IPv4, puerto entre 1 y 65535, nombre de base con letras/dígitos/guion bajo y charset `utf8mb4`. Rechaza cuenta root y credenciales ausentes. Los valores predeterminados no secretos son los acordados; el usuario y la contraseña son obligatorios. No concatena valores no validados en el DSN.

`SFATSA\Repositories\ConnectionFactory` recibe exclusivamente ese resultado validado. `create()` abre una nueva conexión con excepciones, resultados asociativos, preparaciones nativas y sin persistencia. No utiliza singleton ni conecta al incluir archivos. Los futuros repositorios recibirán PDO mediante inyección; controladores y vistas no contendrán SQL.

Los errores de creación se sustituyen por un mensaje general sin encadenar la excepción original. No volcar objetos de configuración ni excepciones: sus trazas podrían contener datos internos. La futura entrada HTTP que utilice esta infraestructura deberá desactivar la visualización de errores antes de cargar archivos, capturar fallos y devolver una respuesta general. El sitio actual conserva su mensaje y no carga la conexión; no se añadió una integración HTTP con PDO ni se alteró el `php.ini` global.

## Pruebas sin credenciales

Desde la raíz, en PowerShell:

```powershell
& C:\xampp\php\php.exe tests/pdo-infrastructure.php
$LASTEXITCODE
git check-ignore .env
```

Las pruebas usan valores ficticios, restauran las variables que cambian en su proceso y eliminan su archivo temporal. No leen el `.env` real ni abren conexiones. Cubren formato inválido, duplicados, valores literales, prioridad y variables vacías, configuración inválida y construcción de la fábrica sin conexión.

## Reproducir la comprobación CLI

La comprobación ya fue exitosa en el equipo de referencia. Para reproducirla en otro equipo, ejecutar únicamente después de configurar `.env` localmente y autorizar la prueba:

```powershell
& C:\xampp\php\php.exe database/check-connection.php
$LASTEXITCODE
```

El script solo acepta CLI, selecciona `sfatza` y ejecuta exclusivamente `SELECT 1`. Éxito: «Conexión PDO comprobada correctamente.» y código 0. Fallo: mensaje general y código 1. No imprime DSN, rutas, contraseñas ni trazas; no lista tablas ni comprueba sus permisos. No es una prueba del modelo de datos ni de funcionalidades. No iniciar MySQL de XAMPP.

## Diagnóstico seguro

Ante un fallo, revisar localmente presencia de variables, formato y prioridad del proceso, servicio MariaDB, puerto y disponibilidad de `pdo_mysql`. No resolver errores cambiando a root o ampliando privilegios sin revisión. El registro de problemas debe usar categorías controladas, sin mensajes crudos de excepciones. La cuenta y los permisos efectivos sobre tablas requieren una revisión posterior; esta tarea no modifica ninguno.

Referencias: [PDO MySQL](https://www.php.net/manual/en/ref.pdo-mysql.php) y [errores PDO](https://www.php.net/manual/en/pdo.error-handling.php).
