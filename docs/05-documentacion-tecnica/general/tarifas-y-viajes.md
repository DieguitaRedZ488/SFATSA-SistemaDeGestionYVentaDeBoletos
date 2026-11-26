# Tarifas y control de viajes

Implementación de tarifas y registro de salida/llegada sobre la arquitectura MVC existente. No añade programación de viajes, venta de boletos, cancelación de viajes ni migraciones.

## Acceso y rutas

ADMINISTRADOR puede consultar, registrar y actualizar tarifas. CHECADORA puede consultar viajes, registrar salidas y finalizar viajes. ADMINISTRADOR no obtiene implícitamente permiso de operación de viajes; CHECADORA no administra catálogos ni tarifas. La identidad se revalida en cada solicitud y nuevamente bajo transacción al escribir. CAJERO no tiene acceso a estos módulos.

| Ruta | Métodos |
| --- | --- |
| `tarifas` | GET |
| `tarifas.registrar` | GET, POST |
| `tarifas.editar&id=…` | GET, POST |
| `viajes` | GET |
| `viajes.salida` | GET, POST |
| `viajes.detalle&id=…` | GET |
| `viajes.llegada&id=…` | GET, POST |

Todas usan el parámetro `?ruta=` del punto de entrada existente. Las escrituras exigen CSRF y listas explícitas de campos. No se aceptan fecha/hora de operación, estado, capacidad o usuario responsable proporcionados por el navegador. Las confirmaciones siguen POST/Redirect/GET hacia destinos locales permitidos.

## Tarifas

- Moneda única MXN. Importe entre 0.01 y 99999999.99; máximo dos decimales. Se valida y normaliza como cadena, sin redondeo ni cálculos con float. El punto es el separador decimal.
- Captura de fecha inicial/final obligatorias. Se almacenan `00:00:00` y `23:59:59`, respectivamente, con límites inclusivos. Un solo día es válido. Fechas imposibles o periodos invertidos se rechazan.
- Origen/destino son IDs de `ruta_parada`, pertenecen a la misma ruta y deben cumplir `orden_origen < orden_destino`. Ruta, servicio y puntos se revalidan bajo bloqueo. El servicio debe coincidir con el de la ruta.
- Para igual servicio/origen/destino, hay solapamiento cuando `existente.desde <= nueva.hasta` y `existente.hasta >= nueva.desde`. Los intervalos heredados con fin NULL se consideran abiertos. Se rechazan solapamientos aunque una tarifa existente esté inactiva; no se ignora historia para permitir otra tarifa.
- Solo se editan tarifas sin referencias desde `boleto`. Cualquier boleto, incluso CANCELADO, protege todos los datos de la tarifa. No se reasignan referencias ni se altera la vigencia histórica. Para un precio nuevo debe crearse una tarifa con periodo compatible.
- La edición comprueba una huella de versión bajo bloqueo; el formulario obsoleto conserva su versión y requiere volver a consultar. La huella no sustituye CSRF o autorización.
- `TarifaService::vigente` devuelve la tarifa activa aplicable al instante del reloj o NULL; ante múltiples candidatas heredadas devuelve conflicto. No implementa venta ni asigna precios a boletos.

## Viajes

La salida crea el viaje EN_CURSO, con ruta/tipo compatibles, vehículo ACTIVO y conductor habilitado. Los formularios muestran únicamente recursos disponibles, pero esta lectura es orientativa: la disponibilidad efectiva se verifica otra vez dentro de la transacción. La capacidad se copia desde el vehículo bloqueado, sin cambiar su estado administrativo.

El servidor captura salida después de adquirir los bloqueos y validar exclusividad. La llegada bloquea el viaje, exige EN_CURSO con llegada/actor aún NULL, captura la hora y actualiza llegada, actor y FINALIZADO atómicamente. Una segunda llegada, un viaje inexistente, CANCELADO o FINALIZADO se rechaza. Si el reloj retrocede respecto a la salida se devuelve conflicto sin registrar llegada; no se fabrica una hora alternativa. Llegada igual a salida es válida por la precisión de segundos.

El listado sin filtros incluye viajes en curso de días anteriores; el enlace «Todos los viajes en curso» permite acceder a recorridos nocturnos. CANCELADO se consulta como estado existente, pero no se proporciona una acción para cancelar.

## Tiempo

`RelojOperacion` utiliza explícitamente America/Mexico_City, con fuente DateTimeImmutable inyectable y precisión de segundos. Los DATETIME se escriben como hora local explícita; no se invocan NOW/CURRENT_TIMESTAMP ni se depende de la zona SQL. Lectura y presentación conservan la misma convención. La validación de fechas también usa esa zona. No se cambia globalmente el php.ini, Apache ni el comportamiento de otros módulos.

DATETIME no guarda zona. La aplicación no reinterpreta ni convierte automáticamente registros heredados; su procedencia horaria requiere revisión independiente si se importan datos de otro sistema.

## Protocolo transaccional y alcance

`OperacionSprint03Service` comparte una única PDO con todos los repositorios de la operación. Primero inicia transacción, bloquea la fila de rol ADMINISTRADOR usando el mismo cerrojo que las escrituras anteriores y revalida al actor y su rol requerido bajo bloqueo. Utilizar este cerrojo no concede permisos administrativos a CHECADORA.

Después, la salida bloquea ruta, servicio, vehículo y conductor en orden fijo. Con los recursos retenidos consulta viajes EN_CURSO mediante lecturas bloqueantes actuales, que no reutilizan una instantánea anterior. Solo entonces captura el reloj e inserta. Dos solicitudes con igual vehículo o conductor se serializan y la perdedora detecta el viaje ya creado. La llegada comparte el mismo cerrojo y realiza una actualización condicional del viaje bloqueado.

Las tarifas comparten este protocolo antes de comprobar solapamientos. La edición bloquea la tarifa y comprueba boletos mediante lectura bloqueante. Las FK y el bloqueo de la fila padre coordinan nuevas referencias mientras la edición está abierta.

La garantía cubre escritores que siguen estos servicios/protocolo. El esquema existente no tiene restricciones de exclusión de intervalos ni unicidad de recursos por estado. Un escritor SQL externo que ignore el protocolo puede violar esas reglas. No se añadieron restricciones nuevas ni se afirma protección universal contra escritores externos. El cerrojo común prioriza consistencia y coordinación con módulos anteriores; serializa las escrituras de estos módulos.

La salida añade un token de formulario aleatorio, por sesión/actor, con vencimiento de 15 minutos y máximo diez formularios. Se consume antes de llamar al servicio y también ante resultado de commit incierto. Evita reenviar el mismo formulario después de finalizar un viaje. No es una llave de idempotencia durable entre sesiones o reinicios. CSRF tiene una finalidad independiente. Los errores inciertos indican consultar el listado antes de repetir.

## Esquema

Requiere las tablas y relaciones versionadas y `ruta.id_tipo_servicio` de la migración 004. La instalación nueva basada exclusivamente en install.sql no incorpora por sí sola 004/005; seguir el historial y el procedimiento de instalación existente con autorización del responsable. No ejecutar install.sql sobre una base existente ni repetir migraciones aplicadas.

No se modifican columnas, ENUM, índices o datos operativos. El CHECK de importe permite cero en SQL: el requisito >0 se aplica en servicio, igual que orden del trayecto y exclusividad.

## Verificación segura

Sin base de datos:

```powershell
php tests/sprint03.php
php tests/sprint03-vistas.php
php tests/sprint03-http-cgi.php
node tests/operacion-captura.js
```

`sprint03-http-cgi.php` utiliza PHP CGI de Windows, sesiones temporales y `-n`, sin PDO_MYSQL. Las otras pruebas PHP usan dobles y renderizado de las vistas reales. La exportación opcional de vistas ficticias usa SFATSA_UI_PREVIEW_DIR; no dirigirla a public ni incluir datos operativos.

En Windows/XAMPP y MariaDB 12.0 instalado, el ensayo de persistencia se ejecuta explícitamente con:

```powershell
./tests/sprint03-mariadb.ps1
```

Se pueden especificar -MariaDbBin y -Php. Crea una instancia temporal con identidad aleatoria, pipe exclusivo y sin TCP ni servicio Windows. El PHP comprueba datadir, pipe, base, puerto cero, FK/CHECK habilitadas y ausencia de conexión a sfatza. Construye un esquema ficticio y la cuenta de prueba solo tiene SELECT/INSERT/UPDATE/DELETE. No carga .env ni copia datos reales.

Comprueba importes, periodos, trayectos, actualización, boleto cancelado, permisos, salida/llegada y carreras de procesos independientes. El padre retiene el cerrojo hasta observar las dos solicitudes preparadas esperando ejecutarlo; luego verifica un éxito y un conflicto y comprueba las filas resultantes. Finalmente retira base/cuenta, apaga la instancia tras verificar identidad y elimina exclusivamente su temporal comprobado. Un fallo de identidad impide el apagado o borrado automático.

La regresión anterior conserva fallos conocidos de trazabilidad/fixtures. La evidencia privada distingue sus resultados de los tests de este módulo; no se cambiaron reglas anteriores para hacerlos pasar.
