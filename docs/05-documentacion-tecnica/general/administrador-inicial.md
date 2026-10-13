# Administrador inicial de SFATSA — CLI

Estado: código implementado y probado aisladamente el 2026-10-13 por autorización del Prompt 15. **No se ejecutó el alta real ni se abrió conexión a MariaDB.** DEC-01 y DEC-04 están aprobadas; login, sesiones, CRUD y cualquier escritura real requieren autorización posterior.

## Arquitectura y alcance

- [Entrada CLI](../../../scripts/crear-administrador-inicial.php): fuera de public/, HTTP rechazado con 404 antes de configuración, carga explícita de clases y fábrica de servicio diferida.
- [Comando](../../../app/Console/CrearAdministradorInicialCommand.php) y [terminal Windows](../../../app/Console/TerminalWindows.php): interacción, capacidad de entrada oculta y mensajes; el contrato Terminal permite dobles aislados.
- [Servicio](../../../app/Services/AdministradorInicialService.php): validación y coordinación del alta. ErrorAdministradorInicial usa mensajes de catálogo sin excepción original.
- [Repositorio PDO](../../../app/Repositories/PdoAdministradorInicialRepository.php): rol, usuarios, consultas preparadas, bloqueo y transacción mediante el contrato AdministradorInicialRepository.
- [Pruebas](../../../tests/administrador-inicial.php): memoria y dobles PDO sin conexión; procesos CLI/CGI para rechazos previos a configuración.

Se reutilizan Environment, config/database.php y ConnectionFactory existentes, sin duplicar credenciales. El CLI verifica argumentos y terminal antes de cargar .env internamente o crear la conexión exclusiva no persistente. La revisión no abrió el archivo privado. El script no ofrece roles, rutas HTTP, migraciones ni edición de cuentas.

## Contrato y validaciones

Nombres y apellidos se capturan separados, obligatorios, UTF-8 válido y máximo 120 caracteres cada uno. Usuario es obligatorio y máximo 50 caracteres. Se rechazan tipos inválidos, ausencia, vacío y valores solo de espacios Unicode. No se recortan valores ni imponen formatos o normalizaciones pendientes de DEC-07.

Contraseña y confirmación solo se leen desde una consola compatible, sin eco. No hay argumentos, variables de entorno, archivos de entrada o temporales para suministrarlas. Windows PowerShell existente usa Console.ReadKey(true); un pipe privado comunica el resultado al PHP en memoria, sin escribirlo a la terminal. Los errores del hijo se descartan. Terminales redirigidas, falta de PowerShell/proc_open o plataformas distintas de Windows abortan; no existe alternativa con contraseña visible.

Mínimo 12 caracteres UTF-8, algún carácter distinto de espacio, sin NUL y máximo 72 bytes por compatibilidad con PASSWORD_BCRYPT. Se compara la confirmación, se genera password_hash() con salt/coste nativos de PHP y se comprueba password_verify(). No se recorta ni trunca. El límite 72 es de bytes: caracteres multibyte pueden alcanzarlo antes. El hash se almacena en hash_contrasena; nunca se imprime.

Se busca ADMINISTRADOR por nombre en rol y se obtiene su identificador real; no se asume un ID fijo. El alta fija activo=1. Una cuenta ADMINISTRADOR existente, incluso inactiva, produce salida correcta sin crear, editar o reactivar nada. Si falta el rol, se aborta; el script no lo crea.

La consulta de duplicado respeta la collation de MariaDB; el índice UNIQUE sigue como respaldo. Un conflicto se comunica mediante un mensaje fijo sin SQL, datos privados o excepción PDO original.

## Exclusión, transacción y recuperación

La lectura inicial de rol/administrador evita pedir secretos innecesarios y no decide por sí sola si se puede insertar. Después de validar los datos:

1. Obtener GET_LOCK('sfatza.administrador-inicial.v1', 0) en la conexión exclusiva. Si está ocupado, abortar sin abrir transacción.
2. Iniciar transacción; reconsultar el rol con FOR UPDATE, comprobar cualquier administrador existente y el usuario duplicado.
3. Si ya existe un administrador, revertir la transacción de lectura y terminar sin cambios.
4. Si procede, INSERT preparado con nombres/apellidos, usuario, hash, id_rol resuelto y activo=1; confirmar solo si se insertó exactamente una fila.
5. En finally, intentar rollback de cualquier transacción pendiente y liberar GET_LOCK por separado, aunque un intento falle. Cerrar siempre la conexión; en esta composición no quedan otras referencias PDO.

El mutex serializa ejecuciones de este script; **no detiene escrituras externas que no cooperen**. La fila del rol se bloquea durante la transacción para impedir que cambie mientras se usa. Mantener coordinadas las altas externas durante la operación.

Commit no libera GET_LOCK. Una desconexión libera el bloqueo y revierte una transacción todavía abierta. Si commit o liberación devuelven un fallo, el mensaje exige revisar el estado: un commit pudo completarse antes de perder su respuesta. No repetir a ciegas ni afirmar que un error implica cero cambios. No hay reintentos automáticos, alteraciones de esquema o DDL.

## Ejecución manual futura — requiere autorización de alta real

Este comando está **propuesto, no ejecutado**. Ejecutarlo completo permite escribir una cuenta en sfatza; la aprobación del código no autoriza esa escritura.

Antes de ejecutarlo:

1. Obtener autorización explícita del alta real y revisar este código y sus pruebas.
2. Confirmar el esquema migrado y tablas InnoDB; ADMINISTRADOR existente en rol; permisos SELECT en rol/usuario, INSERT en usuario y disponibilidad del bloqueo con la cuenta de aplicación. No ampliar permisos ni usar root automáticamente.
3. Usar la configuración privada existente, con DB_DATABASE=sfatza; no copiarla a Git, argumentos o documentación.
4. Usar una consola Windows local interactiva con Windows PowerShell en su ubicación estándar. Configurar la consola para entrada UTF-8, especialmente al capturar nombres con acentos. Evitar redirección, tuberías y transcripciones de la operación.
5. Coordinar cualquier otro proceso que pueda crear cuentas o modificar el catálogo de roles.

Desde la raíz del repositorio, en PowerShell:

```powershell
& 'C:\xampp\php\php.exe' '.\scripts\crear-administrador-inicial.php'
```

Responder a Nombres, Apellidos y Usuario; luego contraseña/confirmación sin eco. No añadir argumentos. Si se rechaza un dato, se cancela esta ejecución; no se reintenta automáticamente.

Salida 0 significa alta confirmada o administrador ya existente; el mensaje distingue ambos. Salida 1 indica rechazo/fallo. Ante resultado incierto, verificar el estado mediante un procedimiento autorizado antes de repetir. Cualquier recuperación de una cuenta inactiva requiere un alcance separado.

## Evidencia ejecutada y pendientes

En PHP 8.2.12, pasaron los 15 lint PHP, las 33 comprobaciones previas de infraestructura y 170 comprobaciones nuevas:

- Obligatorios, blancos Unicode, tipos, UTF-8 inválido y límites 120/120/50; conservación de nombres/apellidos separados.
- Contraseña corta, confirmación distinta, NUL y límites bcrypt ASCII/multibyte; hash nativo y password_verify() sin imprimirlos.
- Rol real resuelto sin ID fijo, administrador existente activo/inactivo, duplicado y repetición sin cambios.
- Intercalación de dos servicios compartiendo mutex ficticio, fallos y liberación/desconexión; no es concurrencia probada con el servidor.
- Repositorio de producción sobre dobles PDO: valores parametrizados, rol bloqueado y activo=1, sin constructor PDO real.
- Comando con terminal ficticia; procesos reales que rechazan argumentos/entrada redirigida; petición sintética CGI devuelve 404 antes de cargar configuración. No se usó Apache ni abrió conexión.

Comandos de prueba seguros, sin alta real:

```powershell
& 'C:\xampp\php\php.exe' '.\tests\pdo-infrastructure.php'
& 'C:\xampp\php\php.exe' '.\tests\administrador-inicial.php'
```

Pendientes: comprobar entrada sin eco en la consola interactiva del operador; permisos/collation y concurrencia/rollback/liberación con sesiones MariaDB reales en un ambiente autorizado; alta real; login MVC, sesiones de 30 minutos, CSRF web y CRUD. La suite requiere el ejecutable CGI que acompaña al PHP utilizado y falla si falta; no instala nada.

Las pruebas automatizadas de este mecanismo usan un entorno aislado. No equivalen a comprobar la entrada sin eco en la consola, los permisos/collation o la concurrencia y el rollback frente a un servidor MariaDB real; esas comprobaciones requieren un entorno autorizado.

Referencias primarias: [GET_LOCK de MariaDB](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock), [password_hash de PHP](https://www.php.net/manual/en/function.password-hash.php), [proc_open de PHP](https://www.php.net/manual/en/function.proc-open.php) y [Console.ReadKey de Microsoft](https://learn.microsoft.com/en-us/dotnet/api/system.console.readkey).
