# Instalación local de SFATSA en Windows con XAMPP

Guía para preparar el código del Sprint 1 en una computadora de desarrollo. No incluye ni copia credenciales, datos personales o respaldos. Separa la instalación local del código de la creación/restauración del esquema MariaDB.

## Requisitos conocidos

- Windows, Git, XAMPP con PHP 8.2 o compatible con el proyecto, Apache y la extensión PDO MySQL (`pdo_mysql`).
- MariaDB accesible por TCP en `127.0.0.1:3306`. En el equipo de referencia es MariaDB 12.0.2 como servicio independiente de XAMPP. **No iniciar MySQL de XAMPP** si MariaDB independiente es el servicio configurado.
- Para una instalación nueva, una base MariaDB vacía y una cuenta local autorizada para crearla y crear sus tablas. La aplicación debe usar una cuenta separada, no `root`.
- Navegador moderno con JavaScript habilitado.

El inicializador [database/install.sql](../database/install.sql) contiene el esquema completo verificado para una base nueva. No existe Composer, `package.json` ni `composer.json`; no se requieren dependencias regenerables para ejecutar la aplicación.

## 1. Obtener el código

Desde PowerShell, clonar el repositorio compartido en la carpeta de trabajo acordada:

```powershell
git clone https://github.com/DieguitaRedZ488/SFATSA-SistemaDeGestionYVentaDeBoletos.git
Set-Location .\SFATSA-SistemaDeGestionYVentaDeBoletos
git status --short --branch
```

El repositorio remoto contendrá solo los cambios ya publicados; cambios locales no confirmados no son descargables. Antes de integrar trabajo de otra persona, acordar rama/revisión y revisar el estado local. No incluir `.env`, claves ni volcados de datos.

## 2. Preparar MariaDB y PHP

1. Instalar/iniciar el servicio MariaDB aprobado por el equipo y comprobar que escucha en `127.0.0.1:3306`. No cambiar el servicio SQL sin coordinarlo.
2. **Solo en una computadora nueva y si `sfatza` todavía no existe**, ejecutar `database/install.sql` con un cliente MariaDB local autorizado. El script crea la base, las 15 tablas y únicamente los catálogos de roles, métodos de pago y tipos de servicio. Desde la raíz local del repositorio, abrir el cliente interactivo con la cuenta administrativa local (el cliente solicitará su contraseña) y la opción que aborta `SOURCE` ante cualquier error:

   ```powershell
   mariadb --abort-source-on-error --user=CUENTA_ADMIN_LOCAL --password
   ```

   En el prompt de MariaDB, cargar el archivo por su ruta local:

   ```sql
   SOURCE database\install.sql;
   ```

   No usar opciones que fuercen continuar. El script empieza con `CREATE DATABASE sfatza` sin `IF NOT EXISTS` para rechazar una base ya existente.
   Las sentencias DDL no forman una sola transacción: si aparece un error, detenerse y revisar el estado antes de cualquier nueva ejecución. No borrar ni recrear la base a ciegas.
3. No ejecutar el inicializador sobre una base existente, compartida, restaurada o real. No crea usuarios SQL ni el administrador de la aplicación. Después de una instalación nueva, solicitar al responsable permisos mínimos para una cuenta de aplicación no-root y configurar esa cuenta en `.env`; no incluir contraseñas en comandos ni documentación.
4. El script inicial ya incorpora el resultado de `20261013_001` (nombres/apellidos separados) y el estado objetivo de `20261013_002` (licencia NOT NULL). **No ejecutar UP ni DOWN después de usarlo.** Para una base ya existente, no usar `install.sql`: verificar sus metadatos y seguir el procedimiento de migraciones, con autorización expresa para cualquier DDL. En `sfatza`, 001 está reportada aplicada y 002 continúa sin aplicar.
5. En el `php.ini` de la instalación XAMPP usada por Apache y PHP CLI, comprobar que PDO y el controlador `pdo_mysql` estén habilitados. No instalar paquetes Composer/npm: no hay manifiestos de dependencias ni se requieren para ejecutar el código fuente.

## 3. Configurar las variables privadas

En la raíz del repositorio, copiar `.env.example` a `.env` y editar `.env` localmente:

```powershell
Copy-Item .env.example .env
notepad .env
```

Completar `DB_USERNAME` y `DB_PASSWORD` con la cuenta local entregada de forma privada. No pegar credenciales en comandos, chats, capturas, Git ni esta guía. Mantener los demás valores compatibles con el servicio acordado: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=sfatza`, `DB_CHARSET=utf8mb4`. `.env` está excluido de Git; `.env.example` es la plantilla versionable y deja secretos vacíos. La validación rechaza `root`.

El chequeo `database/check-connection.php` solo ejecuta `SELECT 1`, pero abre una conexión real; usarlo únicamente en la computadora propia una vez configuradas las credenciales y autorizado por el equipo:

```powershell
& 'C:\xampp\php\php.exe' '.\database\check-connection.php'
```

No sirve para crear tablas ni verificar permisos CRUD. Las pruebas automatizadas se ejecutan sin conexión y no leen `.env`.

## 4. Configurar Apache y `sfatsa.test`

Usar `public/` como único `DocumentRoot`; no publicar la raíz del repositorio. La guía detallada, las protecciones y la plantilla de VirtualHost están en [Configuración local de Apache](configuracion-apache-local.md). Adaptar las rutas a la ubicación real sin reemplazar configuraciones de otros proyectos.

En la plantilla de la guía, conservar `localhost` como primer VirtualHost de `*:80` y usar para SFATSA:

```apache
ServerName sfatsa.test
DocumentRoot "C:/xampp/htdocs/SFATSA-SistemaDeGestionYVentaDeBoletos/public"
```

Comprobar que el archivo `httpd.conf` incluye `conf/extra/httpd-vhosts.conf`. Como administrador, añadir a `C:\Windows\System32\drivers\etc\hosts`:

```text
127.0.0.1 sfatsa.test
```

No duplicar entradas ni sobrescribir la configuración de otros VirtualHosts. Validar y reiniciar Apache conforme a la guía:

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -t
& 'C:\xampp\apache\bin\httpd.exe' -S
```

`sfatsa.test` se sirve por HTTP local. No hay HTTPS propio configurado. Abrir [http://sfatsa.test/](http://sfatsa.test/).

## 5. Crear el administrador inicial e iniciar sesión

El programa CLI existente, [crear-administrador-inicial.php](../scripts/crear-administrador-inicial.php), crea una cuenta real en la base configurada. Requiere autorización del responsable, esquema y permisos preparados, una consola Windows interactiva y `.env` correcto. No ejecutarlo como prueba ni con credenciales compartidas en argumentos; las contraseñas se solicitan sin eco.

Solo tras recibir autorización expresa para el alta en la base local, desde PowerShell interactivo en la raíz:

```powershell
& 'C:\xampp\php\php.exe' '.\scripts\crear-administrador-inicial.php'
```

Proporcionar nombres, apellidos, usuario y contraseña directamente a la consola. No guardar ni publicar la contraseña. El proceso no crea tablas ni roles; si ya existe un administrador, no lo reemplaza. Consultar [Administrador inicial](administrador-inicial.md) para precondiciones y manejo de resultados inciertos.

Luego abrir `http://sfatsa.test/` e iniciar sesión con la cuenta activa autorizada:

- Gestión de usuarios: `http://sfatsa.test/?ruta=usuarios`
- Gestión de conductores: `http://sfatsa.test/?ruta=conductores`

Ambas gestiones requieren rol `ADMINISTRADOR`; CAJERO/CHECADORA no tienen acceso.

## 6. Comprobar CSS, JavaScript y pruebas locales

La interfaz sirve Bootstrap local y los recursos del proyecto desde `public/assets/`. Con la página abierta, las herramientas de desarrollador del navegador (pestaña Network) deben mostrar respuestas exitosas para:

- `/assets/bootstrap/bootstrap.min.css`
- `/assets/css/autenticacion.css`
- `/assets/css/usuarios.css`
- `/assets/js/autenticacion.js`
- `/assets/js/usuarios.js`

La hoja y el script de gestión se incluyen en las pantallas de usuarios y conductores. Comprobar errores 404 y la consola JavaScript. Esto es una verificación que debe realizar cada integrante en su navegador; no se afirma que ya se hizo en otra computadora.

Desde PowerShell, las pruebas aisladas no utilizan MariaDB:

```powershell
& 'C:\xampp\php\php.exe' '.\tests\conductores.php'
& 'C:\xampp\php\php.exe' '.\tests\usuarios.php'
& 'C:\xampp\php\php.exe' '.\tests\login-mvc.php'
& 'C:\xampp\php\php.exe' '.\tests\pdo-infrastructure.php'
& 'C:\xampp\php\php.exe' '.\tests\administrador-inicial.php'
```

Para sintaxis JavaScript, si Node.js está instalado, `node --check` acepta cada archivo fuente; no se requiere `node_modules`. Consulte [evidencia del Sprint 1](sprints/sprint-01/verificacion.md) para distinguir dobles aislados de comprobaciones manuales y de BD.

## Estado conocido de migraciones

- `20261013_001_separar_nombres_apellidos`: aplicación reportada y verificada manualmente por el usuario en `sfatza`; no volver a ejecutar a ciegas.
- `20261013_002_licencia_conductor_obligatoria`: UP y DOWN preparados; lectura de solo lectura en esta preparación confirmó que UP **no está aplicado** en `sfatza`. No ejecutarlo sin autorización expresa, respaldo probado en aislamiento, suspensión de escritores y repetición de precondiciones.

Para una base nueva, los efectos de ambas migraciones están incorporados directamente en [database/install.sql](../database/install.sql), por lo que no se vuelven a ejecutar. Para una instalación existente, revisar el [README de migraciones](../database/migrations/README.md) y distinguir su estado real antes de actuar. Esta preparación no ejecutó el inicializador, una migración ni escrituras en MariaDB.

Ver [README de migraciones](../database/migrations/README.md) y [guía de Gestión de conductores](gestion-conductores.md). No se realizan escrituras o migraciones automáticamente al iniciar la aplicación ni al ejecutar las suites aisladas.
