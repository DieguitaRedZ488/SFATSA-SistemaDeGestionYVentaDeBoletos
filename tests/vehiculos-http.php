<?php

declare(strict_types=1);

use SFATSA\Controllers\AutenticacionController;
use SFATSA\Controllers\VehiculoController;
use SFATSA\Http\Respuesta;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\AutenticacionService;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\OperacionAdministrativaService;
use SFATSA\Services\RegistroVehiculoService;
use SFATSA\Services\SesionService;
use SFATSA\Services\VehiculoService;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/VehiculosFicticios.php';

$checks = 0;
$caso = 'inicio';
$assert = static function (bool $condicion, string $mensaje = 'Comprobación fallida.') use (&$checks): void {
    if (!$condicion) {
        $linea = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0;
        throw new RuntimeException($mensaje . ' Línea ' . $linea . '.');
    }
    $checks++;
};
$crear = static function (?int $idActor = 41) use (&$checks): array {
    $usuarios = new GestionUsuariosFicticia();
    $vehiculos = new VehiculosFicticios();
    $administracion = new AdministracionVehiculosFicticia($usuarios, $vehiculos);
    $servicio = new VehiculoService($vehiculos, new OperacionAdministrativaService($administracion));
    $store = new SesionGestionFicticia();
    $sesion = new SesionService($store, static fn (): int => 10000);
    $csrf = new CsrfService($store);
    $autorizacion = new AutorizacionService();
    $autenticacion = new AutenticacionController(
        $sesion,
        $csrf,
        static fn (): AutenticacionService => new AutenticacionService($usuarios),
        new Vista(),
        $autorizacion
    );
    if ($idActor !== null) {
        $fila = $usuarios->buscar($idActor);
        $sesion->iniciar(new UsuarioAutenticado(
            $idActor, $fila['nombres'], $fila['apellidos'], $fila['usuario'], $fila['rol']
        ));
    }
    $flujo = new RegistroVehiculoService($servicio, $store, static fn (): int => 10000);
    $fabrica = (object) ['llamadas' => 0, 'flujo' => 0];
    $vistas = (object) ['items' => []];
    $renderizar = static function (string $plantilla, array $datos) use ($vistas): string {
        $vistas->items[] = ['plantilla' => $plantilla, 'datos' => $datos];
        return 'VISTA_FICTICIA:' . $plantilla;
    };
    $controlador = new VehiculoController(
        static fn () => $autenticacion->usuarioActual(),
        static function () use ($servicio, $fabrica): VehiculoService {
            $fabrica->llamadas++;
            return $servicio;
        },
        static function () use ($flujo, $fabrica): RegistroVehiculoService {
            $fabrica->flujo++;
            return $flujo;
        },
        $csrf,
        $store,
        new Vista(),
        $autorizacion,
        $renderizar
    );
    return [$controlador, $vehiculos, $usuarios, $csrf, $store, $fabrica, $vistas];
};
$valido = static fn (): array => [
    'numero_economico' => ' eco-21 ', 'placa' => ' a-00021-a ',
    'descripcion' => ' Autobús sintético ', 'capacidad' => '42',
];
$respuestaError = static function (Respuesta $respuesta, int $estado, string $noDebeContener) use ($assert): void {
    $assert($respuesta->estado === $estado, 'Estado HTTP esperado ' . $estado . ', recibido ' . $respuesta->estado);
    $assert(!str_contains($respuesta->cuerpo, $noDebeContener));
};

try {
    $caso = 'autorización por actor vigente y fábrica diferida';
    [$controller, , , , , $fabrica] = $crear(null);
    $assert($controller->listar()->cabeceras['Location'] === '/' && $fabrica->llamadas === 0);
    [$controller, , , , , $fabrica] = $crear(42);
    $respuestaError($controller->listar(), 403, 'FICTICIO_INTERNO_SENSIBLE');
    $assert($fabrica->llamadas === 0);
    [$controller, , $usuarios, , , $fabrica] = $crear();
    $usuarios->filas[41]['id_rol'] = 13;
    $respuestaError($controller->listar(), 403, 'FICTICIO_INTERNO_SENSIBLE');
    $assert($fabrica->llamadas === 0);
    [$controller, , , , , $fabrica] = $crear(44);
    $assert($controller->listar()->cabeceras['Location'] === '/');
    $assert($fabrica->llamadas === 0);

    $caso = 'listado, flash de una lectura y entradas de consulta inválidas';
    [$controller, , , , $store, , $vistas] = $crear();
    $store->guardar('vehiculos_exito', 'Operación completada.');
    $listado = $controller->listar(['q' => ' a-00011-a ']);
    $assert($listado->estado === 200 && $listado->cuerpo === 'VISTA_FICTICIA:vehiculos/listado');
    $assert($vistas->items[0]['datos']['busqueda'] === 'A-00011-A');
    $assert($vistas->items[0]['datos']['exito'] === 'Operación completada.' && $store->obtener('vehiculos_exito') === null);
    $assert($controller->listar(['q' => []])->estado === 400);
    $assert($controller->editar(['id' => '1 OR 1=1'])->estado === 400);
    $assert($controller->confirmarEstado(['id' => '11', 'estado' => 'MANTENIMIENTO'])->estado === 400);

    $caso = 'CSRF requerido antes de crear servicios en todos los POST';
    foreach ([
        ['revisar', [$valido()]],
        ['confirmar', [['token' => str_repeat('a', 64)]]],
        ['cancelar', [['token' => str_repeat('a', 64)]]],
        ['modificar', [$valido()], ['id' => '11']],
        ['cambiarEstado', [['confirmacion' => '1']], ['id' => '11', 'estado' => 'INACTIVO']],
    ] as $operacion) {
        [$controller, , , , , $fabrica] = $crear();
        $metodo = $operacion[0];
        $parametros = $operacion[1];
        $consulta = $operacion[2] ?? [];
        $respuesta = $metodo === 'revisar' || $metodo === 'confirmar' || $metodo === 'cancelar'
            ? $controller->$metodo($parametros[0])
            : $controller->$metodo($parametros[0], $consulta);
        $respuestaError($respuesta, 403, 'FICTICIO_INTERNO_SENSIBLE');
        $assert($fabrica->llamadas === 0 && $fabrica->flujo === 0);
    }
    [$controller, , , $csrf, , $fabrica] = $crear();
    $tokenIncorrecto = str_repeat('0', 64);
    $assert(!$csrf->validar($tokenIncorrecto));
    $respuestaError($controller->revisar($valido() + ['csrf' => $tokenIncorrecto]), 403, 'FICTICIO_INTERNO_SENSIBLE');
    $assert($fabrica->llamadas === 0 && $fabrica->flujo === 0);

    $caso = 'revisión no inserta y confirmación consume datos privados una sola vez';
    [$controller, $repo, , $csrf, , , $vistas] = $crear();
    $entrada = $valido();
    $revisar = $controller->revisar($entrada + ['csrf' => $csrf->token()]);
    $assert($revisar->estado === 200 && $revisar->cuerpo === 'VISTA_FICTICIA:vehiculos/confirmacion');
    $assert(count($repo->filas) === 2 && $repo->eventos === ['existe', 'existe']);
    $resumen = $vistas->items[0]['datos'];
    $assert($resumen['datos']['numero_economico'] === 'ECO-21' && $resumen['datos']['placa'] === 'A-00021-A');
    $assert(is_string($resumen['token']) && strlen($resumen['token']) === 64);
    $confirmar = $controller->confirmar([
        'csrf' => $csrf->token(), 'token' => $resumen['token'],
        'placa' => 'NO-CONFIAR', 'numero_economico' => 'NO-CONFIAR',
    ]);
    $assert($confirmar->estado === 303 && $confirmar->cabeceras['Location'] === '/?ruta=vehiculos');
    $assert($repo->filas[13]['placa'] === 'A-00021-A' && $repo->filas[13]['numero_economico'] === 'ECO-21');
    $antes = count($repo->filas);
    $repetido = $controller->confirmar(['csrf' => $csrf->token(), 'token' => $resumen['token']]);
    $assert($repetido->estado === 409 && count($repo->filas) === $antes);

    $caso = 'duplicado aparecido tras revisión no acepta valores reenviados';
    [$controller, $repo, , $csrf, , , $vistas] = $crear();
    $controller->revisar($valido() + ['csrf' => $csrf->token()]);
    $token = $vistas->items[0]['datos']['token'];
    $repo->filas[99] = [
        'id_vehiculo' => 99, 'numero_economico' => 'ECO-21', 'placa' => 'A-00021-A',
        'descripcion' => 'Escritor concurrente', 'capacidad' => 10, 'estado' => 'ACTIVO',
    ];
    $conflicto = $controller->confirmar([
        'csrf' => $csrf->token(), 'token' => $token, 'placa' => 'A-99999-A',
        'numero_economico' => 'ECO-99', 'descripcion' => 'Datos manipulados', 'capacidad' => '1',
    ]);
    $assert($conflicto->estado === 409 && count($repo->filas) === 3);
    $assert($vistas->items[1]['plantilla'] === 'vehiculos/formulario');
    $assert($vistas->items[1]['datos']['valores']['placa'] === 'A-00021-A');
    $assert($controller->confirmar(['csrf' => $csrf->token(), 'token' => $token])->estado === 409);

    $caso = 'cancelación descarta resumen sin insertar';
    [$controller, $repo, , $csrf, , , $vistas] = $crear();
    $controller->revisar($valido() + ['csrf' => $csrf->token()]);
    $token = $vistas->items[0]['datos']['token'];
    $cancelar = $controller->cancelar(['csrf' => $csrf->token(), 'token' => $token]);
    $assert($cancelar->estado === 303 && $cancelar->cabeceras['Location'] === '/?ruta=vehiculos');
    $assert(count($repo->filas) === 2 && $controller->confirmar(['csrf' => $csrf->token(), 'token' => $token])->estado === 409);

    $caso = 'edición conserva estado e identidad; cambio de estado independiente';
    [$controller, $repo, , $csrf, , , $vistas] = $crear();
    $editar = $controller->editar(['id' => '12']);
    $assert($editar->estado === 200 && $vistas->items[0]['datos']['valores']['estado'] === 'INACTIVO');
    $actualizar = $controller->modificar($valido() + ['csrf' => $csrf->token()], ['id' => '11']);
    $assert($actualizar->estado === 303 && $repo->filas[11]['estado'] === 'ACTIVO');
    $assert($repo->filas[11]['id_vehiculo'] === 11 && $repo->filas[11]['placa'] === 'A-00021-A');
    $assert($controller->modificar($valido() + ['csrf' => $csrf->token()], ['id' => '1 OR 1=1'])->estado === 400);
    $estado = $controller->confirmarEstado(['id' => '11', 'estado' => 'INACTIVO']);
    $assert($estado->estado === 200 && $vistas->items[array_key_last($vistas->items)]['plantilla'] === 'vehiculos/estado');
    $assert($repo->filas[11]['estado'] === 'ACTIVO');
    $cambiar = $controller->cambiarEstado(
        ['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '11', 'estado' => 'INACTIVO']
    );
    $assert($cambiar->estado === 303 && $repo->filas[11]['estado'] === 'INACTIVO');
    $repetido = $controller->cambiarEstado(
        ['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '11', 'estado' => 'INACTIVO']
    );
    $assert($repetido->estado === 409 && $repo->filas[11]['estado'] === 'INACTIVO');
    $assert($controller->modificar($valido() + ['csrf' => $csrf->token(), 'estado' => 'ACTIVO'], ['id' => '11'])->estado === 422);

    $caso = 'errores internos controlados y plantillas/rutas condicionadas';
    [$controller, $repo, , , , , $vistas] = $crear();
    $repo->fallar = 'listar';
    $respuestaError($controller->listar(), 503, 'FICTICIO_INTERNO_SENSIBLE');
    $rutas = require dirname(__DIR__) . '/routes/web.php';
    $esperadas = [
        'vehiculos' => ['GET'],
        'vehiculos.registrar' => ['GET'],
        'vehiculos.revisar' => ['POST'],
        'vehiculos.confirmar' => ['POST'],
        'vehiculos.cancelar' => ['POST'],
        'vehiculos.editar' => ['GET', 'POST'],
        'vehiculos.estado' => ['GET', 'POST'],
    ];
    foreach ($esperadas as $ruta => $metodos) {
        $assert(array_keys($rutas[$ruta]) === $metodos);
    }
    $assert(!isset($rutas['vehiculos.revisar']['GET']) && !isset($rutas['vehiculos.estado']['DELETE']));
    $vistasNecesarias = ['listado.php', 'formulario.php', 'confirmacion.php', 'estado.php'];
    $directorioVistas = dirname(__DIR__) . '/app/Views/vehiculos';
    $assert(count(array_filter($vistasNecesarias, static fn (string $vista): bool => is_file($directorioVistas . '/' . $vista))) === 4);
    try {
        Respuesta::redirigir('/?ruta=externa');
        $assert(false);
    } catch (InvalidArgumentException) {
        $assert(true);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $caso . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'OK vehiculos-http: ' . $checks . ' comprobaciones' . PHP_EOL);
