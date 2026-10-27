<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

try {
    require_once dirname(__DIR__) . '/app/bootstrap-web.php';
    set_error_handler(static function (): never {
        throw new RuntimeException('No se pudo procesar la solicitud.');
    });
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $store = new SFATSA\Security\SesionPhp($https);
    $sesion = new SFATSA\Services\SesionService($store);
    $csrf = new SFATSA\Services\CsrfService($store);
    $conexion = null;
    $crearConexion = static function () use (&$conexion): PDO {
        if ($conexion === null) {
            require_once dirname(__DIR__) . '/config/Environment.php';
            $validarConfiguracion = require dirname(__DIR__) . '/config/database.php';
            $configuracion = $validarConfiguracion(new SFATSA\Config\Environment(dirname(__DIR__) . '/.env'));
            if ($configuracion['database'] !== 'sfatza') {
                throw new RuntimeException('Configuración no disponible.');
            }
            $conexion = (new SFATSA\Repositories\ConnectionFactory($configuracion))->create();
        }
        return $conexion;
    };
    $autenticacion = null;
    $crearAutenticacion = static function () use (&$autenticacion, $crearConexion): SFATSA\Services\AutenticacionService {
        $autenticacion ??= new SFATSA\Services\AutenticacionService(new SFATSA\Repositories\PdoUsuarioRepository($crearConexion()));
        return $autenticacion;
    };
    $controller = new SFATSA\Controllers\AutenticacionController(
        $sesion, $csrf, $crearAutenticacion, new SFATSA\Http\Vista(), new SFATSA\Services\AutorizacionService()
    );
    $routes = require dirname(__DIR__) . '/routes/web.php';
    $plantillasVehiculos = ['listado', 'formulario', 'confirmacion', 'estado', 'eliminar'];
    foreach ($plantillasVehiculos as $plantilla) {
        if (!is_file(dirname(__DIR__) . '/app/Views/vehiculos/' . $plantilla . '.php')) {
            foreach ([
                'vehiculos', 'vehiculos.registrar', 'vehiculos.revisar', 'vehiculos.confirmar',
                'vehiculos.cancelar', 'vehiculos.editar', 'vehiculos.estado', 'vehiculos.eliminar',
            ] as $rutaVehiculos) {
                unset($routes[$rutaVehiculos]);
            }
            break;
        }
    }
    $ruta = $_GET['ruta'] ?? 'login';
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (is_string($ruta) && SFATSA\Http\IntegracionHU04::esRuta($ruta)) {
        $respuesta = SFATSA\Http\IntegracionHU04::atender($routes, $ruta, $metodo, $_GET, $_POST,
            static fn (string $modulo) => SFATSA\Http\IntegracionHU04::controlador(
                $modulo, static fn () => $controller->usuarioActual(), $crearConexion, $csrf, $store,
                new SFATSA\Http\Vista(), new SFATSA\Services\AutorizacionService()
            )
        );
    } elseif (!is_string($ruta) || !isset($routes[$ruta])) {
        $respuesta = $controller->mensaje('Página no encontrada', 'La página solicitada no está disponible.', 404);
    } elseif (!isset($routes[$ruta][$metodo])) {
        $respuesta = $controller->mensaje(
            'Método no permitido', 'La solicitud no utiliza el método permitido.', 405,
            ['Allow' => implode(', ', array_keys($routes[$ruta]))]
        );
    } else {
        $accion = $routes[$ruta][$metodo];
        if (is_array($accion)) {
            $servicioUsuarios = null;
            $crearUsuarios = static function () use (&$servicioUsuarios, $crearConexion): SFATSA\Services\UsuarioService {
                $servicioUsuarios ??= new SFATSA\Services\UsuarioService(new SFATSA\Repositories\PdoGestionUsuarioRepository($crearConexion()));
                return $servicioUsuarios;
            };
            if ($accion[0] === 'conductores') {
                $servicioConductores = null;
                $crearConductores = static function () use (&$servicioConductores, $crearConexion): SFATSA\Services\ConductorService {
                    if ($servicioConductores === null) {
                        $pdo = $crearConexion();
                        $servicioConductores = new SFATSA\Services\ConductorService(
                            new SFATSA\Repositories\PdoConductorRepository($pdo),
                            new SFATSA\Services\OperacionAdministrativaService(new SFATSA\Repositories\PdoGestionUsuarioRepository($pdo))
                        );
                    }
                    return $servicioConductores;
                };
                $gestionController = new SFATSA\Controllers\ConductorController(
                    static fn () => $controller->usuarioActual(), $crearConductores, $csrf, $store,
                    new SFATSA\Http\Vista(), new SFATSA\Services\AutorizacionService()
                );
            } elseif ($accion[0] === 'vehiculos') {
                $servicioVehiculos = null;
                $crearVehiculos = static function () use (&$servicioVehiculos, $crearConexion): SFATSA\Services\VehiculoService {
                    if ($servicioVehiculos === null) {
                        $pdo = $crearConexion();
                        $servicioVehiculos = new SFATSA\Services\VehiculoService(
                            new SFATSA\Repositories\PdoVehiculoRepository($pdo),
                            new SFATSA\Services\OperacionAdministrativaService(
                                new SFATSA\Repositories\PdoGestionUsuarioRepository($pdo)
                            )
                        );
                    }
                    return $servicioVehiculos;
                };
                $crearRegistroVehiculo = static function () use ($crearVehiculos, $store): SFATSA\Services\RegistroVehiculoService {
                    return new SFATSA\Services\RegistroVehiculoService($crearVehiculos(), $store);
                };
                $gestionController = new SFATSA\Controllers\VehiculoController(
                    static fn () => $controller->usuarioActual(), $crearVehiculos, $crearRegistroVehiculo,
                    $csrf, $store, new SFATSA\Http\Vista(), new SFATSA\Services\AutorizacionService()
                );
            } elseif ($accion[0] === 'usuarios') {
                $gestionController = new SFATSA\Controllers\UsuarioController(
                    static fn () => $controller->usuarioActual(), $crearUsuarios, $csrf, $store,
                    new SFATSA\Http\Vista(), new SFATSA\Services\AutorizacionService()
                );
            } else {
                $respuesta = $controller->mensaje('Página no encontrada', 'La página solicitada no está disponible.', 404);
            }
            if (isset($gestionController)) {
                $metodoGestion = $accion[1];
                $respuesta = $metodo === 'POST'
                    ? $gestionController->$metodoGestion($_POST, $_GET) : $gestionController->$metodoGestion($_GET);
            }
        } else {
            $respuesta = $metodo === 'POST' ? $controller->$accion($_POST) : $controller->$accion();
        }
    }
    $respuesta->enviar();
} catch (Throwable) {
    http_response_code(503);
    // No registrar la excepción original: puede incluir configuración o argumentos.
    try {
        echo (new SFATSA\Http\Vista())->renderizar('mensaje', [
            'titulo' => 'Acceso temporalmente no disponible',
            'mensaje' => 'No se pudo procesar la solicitud. Intente nuevamente más tarde.',
        ]);
    } catch (Throwable) {
        echo 'SFATSA: servicio temporalmente no disponible.';
    }
} finally {
    unset($_POST['contrasena'], $_POST['confirmacion']);
}
