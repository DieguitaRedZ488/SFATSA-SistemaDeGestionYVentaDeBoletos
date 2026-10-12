# Apache local en Windows con XAMPP

Esta guía reproduce el entorno de desarrollo de SFATSA por HTTP. No implementa funcionalidades, crea tablas ni configura una conexión PDO. MariaDB 12.0 fue identificada como servidor independiente en el equipo de referencia; no iniciar MySQL desde el panel de XAMPP.

## Estado confirmado en el equipo de referencia

El usuario confirmó que Apache fue reiniciado, el VirtualHost está activo y el archivo hosts contiene `127.0.0.1 sfatsa.test`. PHP 8.2.12 y Apache 2.4.58 son las versiones locales informadas, sin establecer mínimos para el proyecto. No hay HTTPS propio de SFATSA ni una conexión PDO probada desde la aplicación.

Validaciones ejecutadas por el agente: `httpd.exe -t` devolvió `Syntax OK` y código 0; `httpd.exe -S` devolvió código 0, con `localhost` predeterminado y `sfatsa.test` en puerto 80. El puerto 443 conserva el sitio SSL existente de XAMPP.

Pruebas HTTP realizadas en este equipo y confirmadas por el usuario:

| Comprobación | Resultado confirmado |
| --- | --- |
| `http://sfatsa.test/` en Chrome normal | Funciona. |
| `http://sfatsa.test/` con curl | HTTP 200 y «SFATSA está en preparación.». |
| `http://sfatsa.test/phpmyadmin/` | HTTP 403. |
| `AGENTS.md` y `MEMORY.md` en las rutas comprobadas | Archivos internos protegidos; no se especifica aquí un código observado para cada ruta. |
| `http://localhost/` | Conserva la redirección habitual a `/dashboard/`. |
| `http://sfatsa.test/icons.svg` | HTTP 404, sin bloqueo indebido. |

No se verificaron todas las rutas posibles ni todas las variantes de seguridad. Las pruebas recomendadas más adelante deben ejecutarse en cada equipo; sus resultados esperados no constituyen evidencia de ejecución.

## Rutas y revisión previa

Los ejemplos suponen XAMPP en `C:\xampp` y el repositorio en `C:\xampp\htdocs\SFATSA-SistemaDeGestionYVentaDeBoletos`. Sustituir esas rutas por las de cada equipo en todos los bloques y comandos. Apache utiliza barras `/` en sus rutas de configuración.

Comprobar que existe `public/index.php`. `DocumentRoot` debe apuntar exclusivamente a `public/`, donde está la entrada web. Apuntarlo a la raíz del proyecto podría exponer documentos y archivos internos si los permisos no estuvieran correctamente configurados. Con nuestras reglas actuales de denegación, los accesos a los archivos internos están bloqueados; esto no sustituye mantener la raíz web en `public/`.

Antes de editar, revisar `httpd.conf` y sus archivos incluidos: `Listen`, `VirtualHost`, `Directory`, `Files`, `Location`, `LocationMatch`, `If`, `Alias`, `ScriptAlias` y `ProxyPass`. Confirmar que está incluido `conf/extra/httpd-vhosts.conf`, que el puerto 80 es el previsto y que los módulos usados están cargados. En este equipo hay Alias de XAMPP, handlers administrativos y un proxy AJP `/examples`; no asumir que otras instalaciones son idénticas.

Para conocer los archivos incluidos, ejecutar en PowerShell:

```powershell
& C:\xampp\apache\bin\httpd.exe -t -D DUMP_INCLUDES
```

## Respaldo y configuración del VirtualHost

Crear un respaldo antes de editar. Este comando guarda una copia con marca temporal junto al archivo original, fuera del repositorio; puede requerir permisos administrativos:

```powershell
$vhostsPath = 'C:\xampp\apache\conf\extra\httpd-vhosts.conf'
$backupPath = $vhostsPath + '.bak-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff')
Copy-Item -LiteralPath $vhostsPath -Destination $backupPath
Write-Output $backupPath
```

Editar `httpd-vhosts.conf` conservando otros sitios. El ejemplo siguiente es una plantilla sin secretos basada en las reglas revisadas, no una copia completa de configuración privada. Evitar duplicar VirtualHosts: `localhost` debe ser el primero del puerto 80. Si ya existen otros hosts, revisar su orden antes de adaptar el bloque.

```apache
# Proteger los directorios internos mediante reglas globales.
<Directory "C:/xampp/htdocs/SFATSA-SistemaDeGestionYVentaDeBoletos">
    Options None
    AllowOverride None
    Require all denied
</Directory>

<Directory "C:/xampp/htdocs/SFATSA-SistemaDeGestionYVentaDeBoletos/public">
    Options None
    AllowOverride None
    Require local
</Directory>

# Denegar el nombre SFATSA por HTTPS mientras no exista HTTPS propio.
<If "%{HTTP_HOST} =~ m#(?i)^sfatsa[.]test[.]?(?::[0-9]+)?$# && %{HTTPS} == 'on'">
    Require all denied
</If>

<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"
</VirtualHost>

<VirtualHost *:80>
    ServerName sfatsa.test
    DocumentRoot "C:/xampp/htdocs/SFATSA-SistemaDeGestionYVentaDeBoletos/public"

    ProxyPass /examples !
    <LocationMatch "(?i)^/+(?:cgi-bin|php-cgi|icons|licenses|phpmyadmin|webalizer|server-status|server-info|examples)(?:/|$)">
        Require all denied
        ErrorDocument 403 "Acceso denegado en SFATSA."
    </LocationMatch>
</VirtualHost>
```

## Seguridad y alcance de las reglas

Un `DocumentRoot` aislado no elimina Alias globales: estos pueden apuntar fuera de `public`. El bloqueo de rutas administrativas y la exclusión del proxy son explícitos y específicos de SFATSA. Nuevos Alias, proxies o reglas globales requieren una nueva revisión.

Apache combina `Directory` y `.htaccess`, después reglas de directorio con expresiones regulares, `Files`, `Location`/`LocationMatch` y finalmente `If`. Sin `AuthMerging` explícito, una nueva autorización sustituye la anterior: el bloqueo del proyecto reemplaza el permiso general de `htdocs`, y `Require local` habilita `public`. No agregar `AuthMerging And` a ese permiso porque conservaría la denegación del padre. Las reglas posteriores pueden alterar la autorización, por lo que deben revisarse todas las configuraciones incluidas.

`Options None` evita listados y deshabilita opciones como seguimiento de enlaces, CGI e inclusiones en SFATSA. `AllowOverride None` impide cambios mediante `.htaccess` dentro del proyecto. Las reglas del proyecto son globales para proteger también su acceso mediante `localhost`; las reglas de los demás proyectos se conservan y deben probarse.

El patrón `(?:/|$)` delimita los nombres administrativos y evita bloquear `/icons.svg` por compartir un prefijo. No convierte el conjunto de pruebas en una auditoría exhaustiva.

La regla HTTPS deniega solicitudes cuyo encabezado Host identifica `sfatsa.test`, después de la negociación TLS. No proporciona un certificado válido para SFATSA ni protege el sitio SSL existente cuando se accede con otros nombres. Usar explícitamente `http://sfatsa.test/` para este entorno. `Listen 80` y `Listen 443` existentes no equivalen a escuchar exclusivamente en loopback; esta guía no cambia su alcance ni el acceso a otros proyectos.

## Hosts de Windows

Abrir un editor como administrador y editar `C:\Windows\System32\drivers\etc\hosts`. Conservar las entradas existentes y agregar, sin duplicados contradictorios:

```text
127.0.0.1 sfatsa.test
```

Guardar como `hosts`, sin extensión `.txt`. No agregar una entrada IPv6 sin comprobar que Apache atiende esa dirección. La entrada solo resuelve el nombre; no crea el VirtualHost ni inicia Apache.

## Validación y activación

Ejecutar antes de reiniciar Apache:

```powershell
& C:\xampp\apache\bin\httpd.exe -t
$LASTEXITCODE
& C:\xampp\apache\bin\httpd.exe -S
$LASTEXITCODE
```

Exigir código de salida 0 y `Syntax OK` para `-t`. En `-S`, comprobar `localhost` como predeterminado en `*:80` y `sfatsa.test` en puerto 80. Que el sitio SSL existente aparezca en 443 no significa que SFATSA tenga HTTPS configurado. Estos comandos no prueban las respuestas HTTP ni activan los cambios.

Si la validación falla, restaurar el respaldo de `httpd-vhosts.conf`, repetir la validación y detener la activación. Usar la ruta exacta del respaldo creado; no sobrescribir cambios posteriores de otros integrantes.

Para restaurar después de una modificación futura que provoque errores, mantener Apache detenido o efectuar la restauración antes de reiniciarlo, si sigue funcionando con la configuración anterior. No reiniciarlo con la configuración fallida. Seleccionar un respaldo conocido como válido y revisar que su restauración no elimine cambios posteriores de otros integrantes.

En PowerShell, adaptar las rutas y sustituir `MARCA-TEMPORAL` por la marca del respaldo existente; estos comandos son instrucciones de recuperación, no se ejecutaron al documentarlas:

```powershell
$vhostsPath = 'C:\xampp\apache\conf\extra\httpd-vhosts.conf'
$backupPath = 'C:\xampp\apache\conf\extra\httpd-vhosts.conf.bak-MARCA-TEMPORAL'
if (!(Test-Path -LiteralPath $backupPath -PathType Leaf)) {
    throw 'No existe el respaldo seleccionado; no se restauró ningún archivo.'
}
Copy-Item -LiteralPath $backupPath -Destination $vhostsPath -Force -ErrorAction Stop
& C:\xampp\apache\bin\httpd.exe -t
$LASTEXITCODE
& C:\xampp\apache\bin\httpd.exe -S
$LASTEXITCODE
```

Consultar cada código inmediatamente después de su comando, antes de ejecutar el siguiente. Solo iniciar o reiniciar Apache cuando `-t` muestre `Syntax OK`, ambos códigos sean 0 y `-S` refleje los VirtualHosts esperados. Si la validación del respaldo también falla, detener la recuperación y revisar los errores sin activar esa configuración.

Después de validar y acordar la interrupción de otros proyectos locales, reiniciar Apache desde el panel de XAMPP; si estaba detenido, iniciarlo. Iniciar únicamente Apache: MariaDB se administra mediante su servicio independiente y no debe iniciarse MySQL de XAMPP. Confirmar en el panel que Apache permanece iniciado y ejecutar las pruebas siguientes.

## Pruebas recomendadas para cada integrante

Usar `curl.exe` explícitamente en PowerShell. `--noproxy "*"` evita proxies configurados para estas pruebas locales y `-i` muestra código, encabezados y cuerpo. Primero probar la resolución normal y luego, si hace falta aislar un problema de hosts, usar `--resolve`:

```powershell
curl.exe --noproxy "*" -i http://sfatsa.test/
curl.exe --noproxy "*" --resolve sfatsa.test:80:127.0.0.1 -i http://sfatsa.test/
curl.exe --noproxy "*" -i http://sfatsa.test/AGENTS.md
curl.exe --noproxy "*" -i http://sfatsa.test/MEMORY.md
curl.exe --noproxy "*" -i http://localhost/SFATSA-SistemaDeGestionYVentaDeBoletos/AGENTS.md
curl.exe --noproxy "*" -i http://localhost/SFATSA-SistemaDeGestionYVentaDeBoletos/MEMORY.md
curl.exe --noproxy "*" -i http://sfatsa.test/phpmyadmin/
curl.exe --noproxy "*" -i http://sfatsa.test/icons.svg
curl.exe --noproxy "*" -i http://localhost/
```

Adaptar también el nombre de carpeta en las URL de localhost si el repositorio fue clonado con otro nombre. Para cada ruta de la tabla que no tenga comando individual, sustituir la URL en el mismo comando de curl.

| URL o grupo de rutas | Resultado esperado, pendiente de comprobar en cada equipo |
| --- | --- |
| `http://sfatsa.test/` | 200 y «SFATSA está en preparación.». |
| `http://sfatsa.test/AGENTS.md`, `/MEMORY.md` | 404 y ningún contenido interno. |
| `http://localhost/SFATSA-SistemaDeGestionYVentaDeBoletos/AGENTS.md`, misma ruta con `MEMORY.md` | 403 y ningún contenido interno. |
| `http://sfatsa.test/phpmyadmin/`, `/licenses/`, `/webalizer/`, `/icons/` | 403; sin herramientas ni listados. |
| `http://sfatsa.test/php-cgi/php-cgi.exe`, `/cgi-bin/` | 403; sin ejecución CGI. |
| `http://sfatsa.test/server-info`, `/server-status`, `/examples/` | 403; sin datos administrativos ni contenido del proxy. |
| `http://sfatsa.test/icons.svg` | 404 con el contenido actual de `public`; no 403 por coincidencia de prefijo. |
| `http://localhost/` | Redirección habitual a `/dashboard/` en la instalación de referencia; comparar con el comportamiento previo de cada equipo. |
| Un proyecto local existente y `http://localhost/phpmyadmin/` | Conservar su comportamiento previo desde acceso local. |

Comprobar también variantes de mayúsculas, rutas codificadas y métodos relevantes sin asumir que ya fueron probados. Registrar URL, código y contenido observado. `curl.exe` puede terminar con código 0 aun cuando el servidor responde 403 o 404: leer el estado HTTP. No usar `-L` inicialmente al comprobar redirecciones.

Para HTTPS, una prueba normal puede detenerse por el certificado existente. Solo para diagnosticar el bloqueo HTTP, se puede ejecutar:

```powershell
curl.exe --noproxy "*" --resolve sfatsa.test:443:127.0.0.1 -k -i https://sfatsa.test/
```

Se espera 403 tras TLS; es una prueba recomendada, no un resultado confirmado. `-k` omite la verificación del certificado y no demuestra HTTPS válido; no usarlo con credenciales ni trasladarlo a producción.

## Solución de problemas

| Problema | Revisar |
| --- | --- |
| 403 en `/phpmyadmin/` o archivos internos por localhost | Bloqueo intencional; no cambiar a `Require all granted`. |
| 403 en `/` de SFATSA | Revisar `DocumentRoot`, existencia de `public/index.php`, `DirectoryIndex`, reglas combinadas, acceso local y permisos de lectura/recorrido de Windows. Consultar el log antes de ampliar permisos. |
| `ERR_CONNECTION_REFUSED` o curl no conecta | Apache detenido, error de arranque, dirección o puerto equivocados, otro proceso en el puerto o rechazo de la conexión. Revisar panel de XAMPP, `Listen`, validación y `C:\xampp\apache\logs\error.log`. |
| Nombre no resuelto o abre otro sitio | Revisar hosts sin `.txt`, entradas contradictorias, proxies y orden de VirtualHosts con `-S`; comparar con `--resolve`. |
| Navegador intenta HTTPS | Escribir `http://sfatsa.test/` explícitamente y revisar actualización automática a HTTPS; SFATSA aún no tiene HTTPS propio. |
| 404 en `/icons.svg` | Esperado mientras ese archivo no exista en `public`. |
| Cambios escritos sin efecto | Verificar el archivo realmente incluido y que Apache fue reiniciado después de una validación correcta. |

No compartir logs sin revisar si contienen información sensible. No sustituir la investigación de un 403 por desactivar controles de acceso.

## Archivos que no deben versionarse

Los archivos reales de Apache y hosts, sus respaldos, certificados privados, logs y credenciales deben permanecer fuera del repositorio. Versionar únicamente esta guía y ejemplos sin secretos. `.env.example` contiene valores ilustrativos; no confirma una base de datos existente ni carga variables en PHP. No copiar credenciales a la plantilla, README o MEMORY.

## Referencias

- [Apache en Windows](https://httpd.apache.org/docs/2.4/platform/windows.html).
- [VirtualHosts por nombre](https://httpd.apache.org/docs/2.4/vhosts/name-based.html).
- [Orden de combinación de secciones](https://httpd.apache.org/docs/2.4/sections.html).
- [AuthMerging](https://httpd.apache.org/docs/2.4/mod/mod_authz_core.html#authmerging).
- [Manual de curl](https://curl.se/docs/manpage.html).
