# Verificación y trazabilidad del Sprint 1 — SFATSA

Fecha: 2026-10-12. Revisión documental: 2026-10-13. Estado: catálogo para revisión. Referencias: [spec.md](spec.md), [plan.md](plan.md) y [tasks.md](tasks.md).

Esta fase solo crea documentación. No ejecuta pruebas PHP, consultas de BD, solicitudes HTTP ni preparación/limpieza de datos. Las pruebas funcionales siguientes están planificadas, sin resultado ejecutado.

## 1. Evidencia histórica comprobada

Procedencia: auditorías previas de esta conversación del 2026-10-12, sobre la base de código e8a79c8. Este registro documental no es una nueva ejecución ni presupone la existencia de logs o informes externos.

| ID | Comprobación previa | Resultado comprobado y límite |
| --- | --- | --- |
| EV-01 | Estado inicial de Git, confirmado nuevamente antes de planificar | main limpio, commit e8a79c8b416f1120596eccd6d0999d4be54bd0f0. Posteriormente se creó la rama autorizada; no prueba funcionalidad. |
| EV-02 | PHP CLI y lint de los seis archivos PHP | PHP 8.2.12; seis comprobaciones de sintaxis con código 0. No acredita ejecución HTTP ni CRUD. |
| EV-03 | PDO CLI con componentes existentes y carga interna autorizada de configuración privada | Conexión exitosa; sfatza; servidor 12.0.2-MariaDB-log; proceso PHP código 0. No se expusieron secretos. |
| EV-04 | Metadatos de usuario, rol y conductor | 13 columnas coincidentes, PK/autoincrementos, tres UNIQUE completos, FK usuario.id_rol a rol.id_rol con RESTRICT/RESTRICT y siete índices BTREE. InnoDB y utf8mb4_uca1400_ai_ci. |
| EV-05 | Lecturas de roles y conteos | ADMINISTRADOR, CAJERO, CHECADORA, sin adicionales; usuario=0 y conductor=0. SELECT nombre en rol y COUNT(*) en las otras dos tablas exitosos. No se probaron escrituras ni se consultaron hashes/datos personales. |

EV-03 a EV-05 provienen de nueve SELECT ejecutados en la auditoría anterior. La evidencia cierra la incertidumbre sobre el esquema real, pero no completa login, contratos de aplicación ni operaciones de las HU. No acredita todas las columnas de datos o privilegios de escritura.

La guía Apache contiene comprobaciones HTTP históricas del sitio de preparación. No equivalen a integración HTTP con PDO ni se repitieron aquí.

## 2. Convenciones de trazabilidad y preparación

Los CU de HU-01 proceden del UML existente. CU-06/CU-07 fueron establecidos por el usuario para vincular los títulos de HU-03, cuya carpeta sigue siendo Sprint 2. Autenticación y controles comunes no tienen un CU académico identificado en los archivos revisados; se vinculan a Técnico T2 y no reciben un CU inventado.

Resultados permitidos al ejecutar posteriormente: PASÓ, FALLÓ o BLOQUEADA, con fecha, contexto y evidencia. PLANIFICADA significa que no se ejecutó. PLANIFICADA CONDICIONADA identifica una decisión/ambiente aún pendiente; no constituye un fallo ni un resultado aprobado.

Se conservan 46 IDs principales: son escenarios agrupados, no pruebas atómicas ni ejecuciones. Los subcasos descritos dentro de un ID se parametrizarán y registrarán por separado al ejecutarse, sin añadir referencias principales ni inventar ahora una cantidad de pruebas ejecutadas. Los subcasos condicionados no hacen pasar por aprobado el resto de una decisión.

Las pruebas autónomas de autenticación habilitan el inicio de gestión conforme a plan.md y tasks.md. Los subcasos integrados que requieren operaciones implementadas se ejecutan después y contribuyen al cierre final conjunto, sin bloquear el inicio de esas operaciones.

Antes de cualquier prueba funcional:

1. Obtener autorización de implementación y, para integraciones con escrituras, del ambiente de pruebas y de su preparación/limpieza.
2. Usar un ambiente local aislado y datos ficticios: administradores, cajero, checadora, cuenta inactiva y conductores con/sin licencia. No preparar esas cuentas ahora.
3. Elegir valores únicos por caso, registrar precondiciones y comprobar efectos después. Las contraseñas se suministran privadamente; no se guardan credenciales ni hashes reales en Git.
4. Simular fallos con dobles de repositorio o recursos aislados autorizados; no apagar el servicio compartido, alterar permisos o cambiar tablas para provocar errores.
5. Mantener las pruebas de SQL injection y XSS no destructivas y acotadas a datos ficticios. No se incluyen instrucciones de explotación destructiva.
6. No ejecutar tests/pdo-infrastructure.php en esta fase: crea y elimina un archivo temporal. Su ejecución futura necesita un alcance que permita esos efectos.

## 3. Matriz de pruebas técnicas

| HU / ámbito | Caso de uso | Criterio | Tarea | Prueba y resultado esperado | Resultado real |
| --- | --- | --- | --- | --- | --- |
| Técnico | Sin CU identificado | CA-TEC-01 | S01-TEC-T1 | P-TEC-01: reproducir configuración/esquema/accesos acordados en ambiente autorizado; salida sanitizada y sin alteraciones de esquema. Antecedentes EV-03 a EV-05. | PLANIFICADA; antecedentes ejecutados separados |
| Técnico | Sin CU identificado | CA-TEC-02, SEG-01, SEG-02 | S01-TEC-T2 | P-TEC-02: login válido de cada rol activo; sesión autenticada con rol correcto y regeneración de ID. | PLANIFICADA |
| Técnico | Sin CU identificado | CA-TEC-03 | S01-TEC-T2 | P-TEC-03: contraseña incorrecta, cuenta inexistente y cuenta inactiva; ninguna sesión autenticada y mensaje general. | PLANIFICADA |
| Técnico | Sin CU identificado | CA-TEC-05 | S01-TEC-T2 | P-TEC-04: cerrar sesión y volver a usar la sesión anterior; acceso protegido rechazado y cookie/sesión invalidadas. | PLANIFICADA |

## 4. Matriz HU-01

| HU | Caso de uso | Criterio | Tarea | Prueba y resultado esperado | Resultado real |
| --- | --- | --- | --- | --- | --- |
| HU-01 | CU-01, CU-02, CU-03 | CA-01-01, CA-01-04, CA-01-05, CA-01-07 | S01-HU01-T1; S01-HU01-T7 | P-UI-01: revisar formulario, roles, carga y confirmación/cancelación; sin campos precargados de contraseña/hash ni permisos individuales. | PLANIFICADA |
| HU-01 | CU-01 | CA-01-01, CA-01-06 | S01-HU01-T3; S01-HU01-T5; S01-HU01-T7 | P-01-01: (a) alta válida de cuenta ficticia con una cuenta nueva, rol correcto, hash nativo y confirmación; (b) en memoria y ambiente autorizado, password_verify() sobre el hash persistido acepta la contraseña esperada y rechaza una distinta. No imprimir ni registrar contraseñas o hashes. | PLANIFICADA |
| HU-01 | CU-01, CU-02 | CA-01-02, R-DAT-01 | S01-HU01-T2; S01-HU01-T3; S01-HU01-T4; S01-HU01-T7 | P-01-02: por cada obligatorio de registro y editable obligatorio, probar (a) campo omitido, (b) NULL y (c) campo presente con cadena vacía. En los tres subcasos, rechazo en servidor sin persistencia. Cadenas solo con espacios pendientes de DEC-07; contraseña en edición pendiente de DEC-03. | PLANIFICADA; subcasos DEC-03/DEC-07 condicionados |
| HU-01 | CU-01, CU-02 | CA-01-02, R-DAT-03 | S01-HU01-T2; S01-HU01-T3; S01-HU01-T4; S01-HU01-T7 | P-01-03: nombre 120 y usuario 50 caracteres válidos; 121/51 rechazados, incluidos casos multibyte; sin truncamiento. Límites de contraseña dependen de DEC-04. | PLANIFICADA |
| HU-01 | CU-01 | CA-01-03 | S01-HU01-T3; S01-HU01-T7 | P-01-04: registrar identificador ya existente; error de duplicado y ninguna segunda cuenta. Comparación conforme a collation. | PLANIFICADA |
| HU-01 | CU-01, CU-02 | CA-01-02, CA-01-06, R-DAT-04 | S01-HU01-T2; S01-HU01-T3; S01-HU01-T5; S01-HU01-T7 | P-01-05: rol válido frente a ID inexistente/valor manipulado; aceptar solo catálogo permitido y conservar FK válida. | PLANIFICADA |
| HU-01 | CU-02, CU-03 | CA-01-04 | S01-HU01-T4; S01-HU01-T6; S01-HU01-T7 | P-01-06: buscar y cargar una cuenta existente; datos correctos sin contraseña/hash, con ID validado. | PLANIFICADA; búsqueda sujeta a DEC-02 |
| HU-01 | CU-02, CU-03 | CA-01-04 | S01-HU01-T4; S01-HU01-T6; S01-HU01-T7 | P-01-07: búsqueda sin resultados o ID inexistente; mensaje controlado y ninguna modificación/desactivación. | PLANIFICADA; búsqueda sujeta a DEC-02 |
| HU-01 | CU-02 | CA-01-05 | S01-HU01-T4; S01-HU01-T7 | P-01-08: editar campos autorizados con datos válidos; persistencia y confirmación. Cambiar contraseña se evalúa solo tras DEC-03. | PLANIFICADA |
| HU-01 | CU-02 | CA-01-03, CA-01-05 | S01-HU01-T4; S01-HU01-T7 | P-01-09: conservar el propio usuario es válido; usar identificador de otra cuenta se rechaza y conserva ambas. | PLANIFICADA |
| HU-01 | CU-02 | CA-01-06, CA-TEC-04, SEG-03 | S01-HU01-T5; S01-TEC-T2; S01-HU01-T7 | P-01-10: otro administrador cambia el rol de una cuenta con sesión abierta: (a) al retirar ADMINISTRADOR, su siguiente petición pierde gestión; (b) al conceder ADMINISTRADOR a una cuenta activa de otro rol, su siguiente petición permite la gestión autorizada. Revalidar rol y aplicar la matriz vigente en ambos subcasos, sin aprobar políticas de DEC-05. | PLANIFICADA |
| HU-01 | CU-03 | CA-01-07, CA-01-08 | S01-HU01-T6; S01-HU01-T7 | P-01-11: visualizar y confirmar desactivación; activo=0, registro conservado y confirmación de resultado. | PLANIFICADA |
| HU-01 | CU-03 | CA-01-07 | S01-HU01-T6; S01-HU01-T7 | P-01-12: cancelar la confirmación; estado y cantidad de cuentas sin cambios. | PLANIFICADA |
| HU-01 | CU-03 | CA-01-08, CA-TEC-03, CA-TEC-04 | S01-HU01-T6; S01-TEC-T2; S01-HU01-T7 | P-01-13: login de cuenta desactivada y petición con su sesión anterior; ambos rechazados antes de la operación protegida. | PLANIFICADA |
| HU-01 | CU-01, CU-02, CU-03 | CA-01-09, SEG-07 | S01-HU01-T3; S01-HU01-T4; S01-HU01-T6; S01-HU01-T7 | P-01-14: fallo de persistencia simulado; sin éxito falso, trazas o secretos; sin efectos parciales cuando corresponda. | PLANIFICADA |

## 5. Matriz HU-03

| HU | Caso de uso | Criterio | Tarea | Prueba y resultado esperado | Resultado real |
| --- | --- | --- | --- | --- | --- |
| HU-03 | CU-06, CU-07 | CA-03-01, CA-03-04 a CA-03-06 | S01-HU03-T1; S01-HU03-T5 | P-UI-03: revisar campos opcionales, búsqueda, carga, edición, estado y mensajes; no exigir licencia ni introducir eliminación física. | PLANIFICADA |
| HU-03 | CU-06 | CA-03-01 | S01-HU03-T3; S01-HU03-T5 | P-03-01: registrar nombre, licencia y teléfono ficticios válidos; una alta y confirmación. | PLANIFICADA |
| HU-03 | CU-06 | CA-03-01, R-DAT-02 | S01-HU03-T2; S01-HU03-T3; S01-HU03-T5 | P-03-02: altas sin licencia/teléfono, incluyendo varias licencias ausentes; NULL permitido y ningún duplicado ficticio causado por ausencia. Conversión de vacíos según DEC-07. | PLANIFICADA |
| HU-03 | CU-06, CU-07 | CA-03-02 | S01-HU03-T2; S01-HU03-T3; S01-HU03-T4; S01-HU03-T5 | P-03-03: nombre obligatorio (a) omitido, (b) NULL y (c) presente con cadena vacía; cada subcaso se rechaza en servidor sin persistencia. Probar también tipos/estado inválidos; NULL permitido solo en opcionales. Cadenas solo con espacios permanecen pendientes de DEC-07. | PLANIFICADA; subcasos de espacios condicionados a DEC-07 |
| HU-03 | CU-06, CU-07 | CA-03-02, R-DAT-03 | S01-HU03-T2; S01-HU03-T3; S01-HU03-T4; S01-HU03-T5 | P-03-04: aceptar nombre 120/licencia 40/teléfono 25; rechazar 121/41/26, incluyendo multibyte; sin truncamiento. | PLANIFICADA |
| HU-03 | CU-06 | CA-03-03 | S01-HU03-T3; S01-HU03-T5 | P-03-05: licencia existente proporcionada; rechazo sin segunda alta ni sobrescritura del conductor original. | PLANIFICADA |
| HU-03 | CU-07 | CA-03-03 | S01-HU03-T2; S01-HU03-T4; S01-HU03-T5 | P-03-06: mantener licencia propia en edición es válido; adoptar la de otro conductor se rechaza sin cambios. | PLANIFICADA |
| HU-03 | CU-07 | CA-03-04 | S01-HU03-T4; S01-HU03-T5 | P-03-07: búsqueda y carga de conductor existente; datos y estado correctos. | PLANIFICADA; búsqueda sujeta a DEC-02 |
| HU-03 | CU-07 | CA-03-04 | S01-HU03-T4; S01-HU03-T5 | P-03-08: búsqueda sin resultados o ID inexistente; mensaje controlado y sin actualización. | PLANIFICADA; búsqueda sujeta a DEC-02 |
| HU-03 | CU-07 | CA-03-05, CA-03-06 | S01-HU03-T4; S01-HU03-T5 | P-03-09: editar datos y retirar un opcional conforme a DEC-07; valores válidos persistidos y confirmados. | PLANIFICADA |
| HU-03 | CU-07 | CA-03-05 | S01-HU03-T4; S01-HU03-T5 | P-03-10: desactivar conductor activo; activo=0 y registro conservado. | PLANIFICADA |
| HU-03 | CU-07 | CA-03-05 | S01-HU03-T4; S01-HU03-T5 | P-03-11: activar conductor inactivo; activo=1 y mismo registro. | PLANIFICADA |
| HU-03 | CU-07 | CA-03-06 | S01-HU03-T4; S01-HU03-T5 | P-03-12: abandonar/cancelar edición antes de guardar; datos intactos. No presupone diálogo previo obligatorio de estado. | PLANIFICADA |
| HU-03 | CU-06, CU-07 | CA-03-06, SEG-07 | S01-HU03-T3; S01-HU03-T4; S01-HU03-T5 | P-03-13: fallo de persistencia simulado; error general sin confirmación falsa, secretos o efectos parciales. | PLANIFICADA |
| HU-03 | Sin CU académico de selección en Sprint 1; CU-07 aporta el estado | CA-03-07, DEC-06 | S01-HU03-T4; S01-HU03-T5 | P-03-14: verificar únicamente el contrato de selección con candidatos ficticios activos/inactivos; devuelve solo los activos. No implementar ni simular asignación real a viajes. La integración con viajes queda para su sprint posterior y no se ejecuta aquí. | PLANIFICADA CONDICIONADA a DEC-06 |

## 6. Matriz de seguridad común y propuestas

Las pruebas comunes se aplican a todas las rutas/métodos protegidos que finalmente existan; registrar su listado real al implementar. Esta tabla no afirma cobertura de rutas inexistentes.

| HU / ámbito | Caso de uso | Criterio / control | Tarea | Prueba y resultado esperado | Resultado real |
| --- | --- | --- | --- | --- | --- |
| HU-01 / HU-03 | CU-01, CU-02, CU-03, CU-06, CU-07 | CA-TEC-04, R-AUT-01, R-AUT-02 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-01: admin activo permitido; CAJERO, CHECADORA, anónimo, cuenta inactiva y rol desconocido denegados, incluyendo URL directa, método y datos manipulados; sin efectos. | PLANIFICADA |
| Técnico / HU-01 / HU-03 | Login/autenticación sin CU académico identificado; CU-01, CU-02, CU-03, CU-06, CU-07 | SEG-05, CA-TEC-03 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-02: subcasos de SQL injection no destructiva en (a) login/autenticación, (b) búsqueda y (c) gestión, con entradas ficticias y caracteres SQL. Valores tratados como datos, sin autenticación indebida, acceso extra ni SQL concatenado. El subcaso de login es autónomo; búsqueda/gestión se verifican después al estar implementadas. | PLANIFICADA |
| HU-01 / HU-03 | Todos los CU del sprint | SEG-06 | S01-HU01-T7; S01-HU03-T5 | P-SEG-03: texto ficticio con caracteres HTML en nombre/búsqueda/mensajes; escape contextual y ausencia de ejecución o alteración de marcado. | PLANIFICADA |
| Técnico / HU-01 / HU-03 | Login/logout sin CU académico identificado; CU-01, CU-02, CU-03, CU-06, CU-07 | SEG-04, DEC-04 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-04: (a) en operaciones de gestión que cambian estado, token ausente, incorrecto o de otra sesión causa rechazo antes de persistir; válido permite solo una operación autorizada; (b) concretar y probar el tratamiento CSRF de login/logout solo tras resolver la decisión técnica DEC-04, sin asumir aquí una política aprobada. | PLANIFICADA; login/logout condicionados a DEC-04 |
| Técnico / ambas HU | Sin CU de autenticación; CU protegidos | SEG-02, SEG-03 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-05: ID previo al login no conserva autenticación; cookies con atributos acordados, estado/rol revalidados y regeneración al detectar cambio de privilegios. | PLANIFICADA |
| Técnico | Sin CU identificado | CA-TEC-05, DEC-04 | S01-TEC-T2 | P-SEG-06: caducidad con el tiempo aprobado y rechazo posterior; no asignar minutos arbitrarios. | PLANIFICADA CONDICIONADA a DEC-04 |
| Técnico / ambas HU | Sin CU técnico; CU protegidos | SEG-07 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-07: configuración/conexión/persistencia fallida simulada; mensaje general y ausencia de SQL, DSN, credenciales, hashes, tokens o trazas en salida/diagnóstico. | PLANIFICADA |
| HU-01 / HU-03 | CU-01, CU-02, CU-03, CU-06, CU-07 | SEG-04 | S01-TEC-T2; S01-HU01-T7; S01-HU03-T5 | P-SEG-08: solicitar cambios mediante GET; ninguna modificación, aunque existan sesión y parámetros. | PLANIFICADA |
| HU-01 / HU-03 | Todos los CU del sprint | SEG-05, R-DAT-01 a R-DAT-08 | S01-HU01-T7; S01-HU03-T5 | P-SEG-09: omitir validación cliente y enviar arrays, IDs inválidos, rol/estado manipulados; servidor rechaza sin cambios. | PLANIFICADA |
| HU-01 / HU-03 | CU-01, CU-06 | CA-01-03, CA-03-03, SEG-07 | S01-HU01-T3; S01-HU03-T3; S01-HU01-T7; S01-HU03-T5 | P-SEG-10: solicitudes concurrentes controladas con usuario/licencia ficticios iguales; una alta válida, otra rechazada y ninguna duplicación. | PLANIFICADA; requiere ambiente autorizado |
| Técnico | Sin CU identificado | DEC-01, SEG-01 | S01-TEC-T2 | P-DEC-01: evaluar procedimiento inicial con contraseña privada y ejecución repetida conforme a la decisión aprobada; sin secretos en Git/salida. | PROPUESTA; pendiente de aprobación DEC-01 |
| HU-01 | CU-02, CU-03 | DEC-05 | S01-HU01-T5; S01-HU01-T6; S01-HU01-T7 | P-DEC-02: evaluar autodesactivación/último administrador y retirada de su rol solo según política que se apruebe; no asumir un veto obligatorio. | PROPUESTA; pendiente de aprobación DEC-05 |

## 7. Registro posterior de resultados y límites

Al ejecutar una prueba autorizada, actualizar su resultado con fecha, commit, ambiente, precondiciones ficticias, pasos controlados, resultado observado y evidencia sanitizada. Relacionar fallos con criterio/tarea, registrar la corrección y repetir solo las comprobaciones pertinentes.

No guardar contraseñas, hashes reales, DSN, tokens, IDs de sesión o datos personales en evidencia. No atribuir a una prueba de sintaxis, metadatos o conteos la validación de login, formularios o permisos de escritura.

El catálogo cubre las categorías solicitadas, pero no demuestra cobertura total ni resultados funcionales. Las decisiones condicionadas, la integración futura de asignación y las rutas aún inexistentes permanecen explícitas. Las tareas conservan los estados iniciales de tasks.md.
