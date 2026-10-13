<?php

declare(strict_types=1);

use SFATSA\Controllers\AutenticacionController;
use SFATSA\Controllers\UsuarioController;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Repositories\PdoGestionUsuarioRepository;
use SFATSA\Services\AutenticacionService;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\ErrorGestionUsuarios;
use SFATSA\Services\SesionService;
use SFATSA\Services\UsuarioService;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';

final class PdoGestionFicticio extends PDO
{
    public array $consultas = [];
    public array $resultados = [];
    public bool $transaccion = false;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new SentenciaGestionFicticia($this, $query, array_shift($this->resultados) ?? []);
    }
    public function beginTransaction(): bool { return $this->transaccion = true; }
    public function inTransaction(): bool { return $this->transaccion; }
    public function commit(): bool { $this->transaccion = false; return true; }
    public function rollBack(): bool { $this->transaccion = false; return true; }
}
final class SentenciaGestionFicticia extends PDOStatement
{
    public function __construct(private PdoGestionFicticio $pdo, private string $sql, private array $filas) {}
    public function execute(?array $params = null): bool { $this->pdo->consultas[] = ['sql' => $this->sql, 'parametros' => $params]; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->filas) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->filas; }
    public function rowCount(): int { return 1; }
}

$checks = 0;
$caso = 'inicio';
$assert = static function (bool $condicion) use (&$checks): void {
    if (!$condicion) {
        $linea = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0;
        throw new RuntimeException('Comprobación fallida en línea ' . $linea . '.');
    }
    $checks++;
};
$errorEsperado = static function (Closure $operacion, int $estado, ?string $campo = null) use ($assert): void {
    try { $operacion(); throw new RuntimeException('Se esperaba un rechazo.'); }
    catch (ErrorGestionUsuarios $error) {
        $assert($error->estado === $estado);
        if ($campo !== null) { $assert(isset($error->errores[$campo])); }
        $assert(!str_contains($error->getMessage(), 'FICTICIO_INTERNO_SENSIBLE'));
    }
};
$valido = static fn (): array => [
    'nombres' => '  José  María  ', 'apellidos' => ' Álvarez López ', 'usuario' => ' nuevo.ficticio ',
    'id_rol' => '13', 'contrasena' => 'FICTICIO-Seguro-123', 'confirmacion' => 'FICTICIO-Seguro-123',
];
$crearController = static function (GestionUsuariosFicticia $repo, ?int $id = 41): array {
    $store = new SesionGestionFicticia();
    $sesion = new SesionService($store);
    $csrf = new CsrfService($store);
    $auth = new AutenticacionController($sesion, $csrf, static fn () => new AutenticacionService($repo), new Vista(), new AutorizacionService());
    if ($id !== null) {
        $fila = $repo->buscar($id);
        $sesion->iniciar(new UsuarioAutenticado($id, $fila['nombres'], $fila['apellidos'], $fila['usuario'], $fila['rol']));
    }
    $contador = new stdClass(); $contador->fabricas = 0;
    $factory = static function () use ($repo, $contador): UsuarioService { $contador->fabricas++; return new UsuarioService($repo); };
    $controller = new UsuarioController(static fn () => $auth->usuarioActual(), $factory, $csrf, $store, new Vista(), new AutorizacionService());
    return [$controller, $csrf, $store, $contador, $auth];
};

try {
    $caso = 'registro, normalización y hash';
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $servicio->registrar(41, $valido());
    $nueva = $repo->filas[46];
    $assert($nueva['nombres'] === 'José  María' && $nueva['apellidos'] === 'Álvarez López');
    $assert($nueva['usuario'] === 'nuevo.ficticio' && $nueva['id_rol'] === 13 && $nueva['activo'] === 1);
    $assert(password_verify($valido()['contrasena'], $nueva['hash_contrasena']));
    $assert($repo->eventos === ['iniciar', 'bloquear', 'buscar_bloqueado', 'insertar', 'confirmar']);

    $caso = 'obligatorios, tipos, UTF-8 y longitudes';
    foreach (['nombres', 'apellidos', 'usuario'] as $campo) {
        foreach ([null, '', '   ', "\u{00A0}\t", [], "\xFF", "a\0b"] as $valor) {
            $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
            $datos = $valido(); $datos[$campo] = $valor;
            $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, $campo);
            $errorEsperado(fn () => $servicio->modificar(41, 42, $datos), 422, $campo);
            $assert(count($repo->filas) === 5 && !in_array('iniciar', $repo->eventos, true));
        }
        $limite = $campo === 'usuario' ? 50 : 120;
        $repo = new GestionUsuariosFicticia(); $datos = $valido(); unset($datos[$campo]);
        $errorEsperado(fn () => (new UsuarioService($repo))->registrar(41, $datos), 422, $campo);
        $errorEsperado(fn () => (new UsuarioService($repo))->modificar(41, 42, $datos), 422, $campo);
        foreach (['a', 'á', '🚍'] as $caracter) {
            $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
            $datos = $valido(); $datos[$campo] = str_repeat($caracter, $limite);
            $servicio->registrar(41, $datos); $assert($repo->filas[46][$campo] === $datos[$campo]);
            $servicio->modificar(41, 46, $datos); $assert($repo->filas[46][$campo] === $datos[$campo]);
            $datos[$campo] .= $caracter;
            $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, $campo);
            $errorEsperado(fn () => $servicio->modificar(41, 46, $datos), 422, $campo);
        }
    }
    foreach (['a', 'á', '🚍'] as $caracter) {
        $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo); $datos = $valido();
        $datos['nombres'] = $datos['apellidos'] = str_repeat($caracter, 120);
        $servicio->registrar(41, $datos);
        $assert($repo->filas[46]['nombres'] === $datos['nombres'] && $repo->filas[46]['apellidos'] === $datos['apellidos']);
        $servicio->modificar(41, 46, $datos);
        $assert($repo->filas[46]['nombres'] === $datos['nombres'] && $repo->filas[46]['apellidos'] === $datos['apellidos']);
    }
    $caso = 'contraseña y rol';
    foreach (['corta', str_repeat('a', 73), str_repeat('á', 37), str_repeat(' ', 12), "FICTICIO\0-seguro-123", "\xFF"] as $password) {
        $repo = new GestionUsuariosFicticia(); $datos = $valido();
        $datos['contrasena'] = $datos['confirmacion'] = $password;
        $errorEsperado(fn () => (new UsuarioService($repo))->registrar(41, $datos), 422, 'contrasena');
        $assert(count($repo->filas) === 5);
    }
    foreach ([str_repeat('a', 12), str_repeat('a', 72), str_repeat('á', 12), str_repeat('🚍', 12)] as $password) {
        $repo = new GestionUsuariosFicticia(); $datos = $valido(); $datos['contrasena'] = $datos['confirmacion'] = $password;
        (new UsuarioService($repo))->registrar(41, $datos);
        $assert(password_verify($password, $repo->filas[46]['hash_contrasena']));
    }
    $repo = new GestionUsuariosFicticia(); $datos = $valido(); $datos['confirmacion'] = 'FICTICIO-Distinta-123';
    $errorEsperado(fn () => (new UsuarioService($repo))->registrar(41, $datos), 422, 'confirmacion');
    foreach ([null, [], '999', '0', '7 OR 1=1', '13.0'] as $rol) {
        $datos = $valido(); $datos['id_rol'] = $rol;
        $errorEsperado(fn () => (new UsuarioService($repo))->registrar(41, $datos), 422, 'id_rol');
    }
    foreach ([7, 13, 29] as $rol) {
        $repo = new GestionUsuariosFicticia(); $datos = $valido(); $datos['id_rol'] = (string) $rol;
        (new UsuarioService($repo))->registrar(41, $datos); $assert($repo->filas[46]['id_rol'] === $rol);
    }

    $caso = 'duplicidad, edición e inexistentes';
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $datos = $valido(); $datos['usuario'] = 'FICTICIO42';
    $errorEsperado(fn () => $servicio->registrar(41, $datos), 409, 'usuario');
    $errorEsperado(fn () => $servicio->modificar(41, 43, $datos), 409, 'usuario');
    $datos['usuario'] = 'ficticio42'; $datos['apellidos'] = $repo->filas[42]['apellidos']; $datos['id_rol'] = '29';
    $datos['activo'] = 0; $datos['hash_contrasena'] = 'NO_GUARDAR'; $datos['contrasena'] = 'NO_GUARDAR';
    $servicio->modificar(41, 42, $datos);
    $assert($repo->filas[42]['nombres'] === 'José  María' && $repo->filas[42]['apellidos'] === 'Prueba Álvarez');
    $assert($repo->filas[42]['id_rol'] === 29 && $repo->filas[42]['activo'] === 1);
    $assert($repo->filas[42]['hash_contrasena'] === 'HASH_FICTICIO_NO_PUBLICABLE');
    $datos['apellidos'] = 'Apellido nuevo'; $datos['nombres'] = $repo->filas[42]['nombres'];
    $servicio->modificar(41, 42, $datos); $assert($repo->filas[42]['apellidos'] === 'Apellido nuevo');
    $servicio->modificar(41, 42, $datos); $assert(!$repo->enTransaccion());
    $errorEsperado(fn () => $servicio->obtener(999), 404);
    $errorEsperado(fn () => $servicio->modificar(41, 999, $valido()), 404);
    foreach ([null, [], '0', '-1', '1.2', '1 OR 1=1', str_repeat('9', 30)] as $id) {
        $errorEsperado(fn () => $servicio->obtener($id), 400);
    }

    $caso = 'consulta y búsqueda sin secretos';
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    foreach (['Persona', 'Álvarez', 'ficticio42'] as $q) { $assert(count($servicio->listar($q)['usuarios']) > 0); }
    $assert($servicio->listar('sin-resultados')['usuarios'] === []);
    $assert(count($servicio->listar('  ')['usuarios']) === 5);
    $assert(!isset($servicio->obtener(42)['hash_contrasena']));
    $assert(!isset($servicio->listar('')['usuarios'][0]['hash_contrasena']));
    foreach ([[], "\xFF", str_repeat('a', 242)] as $q) { $errorEsperado(fn () => $servicio->listar($q), 400); }

    $caso = 'desactivación y protecciones';
    $servicio->desactivar(41, 42, '1');
    $assert($repo->filas[42]['activo'] === 0 && count($repo->filas) === 5);
    $errorEsperado(fn () => $servicio->desactivar(41, 42, '1'), 409);
    $errorEsperado(fn () => $servicio->desactivar(41, 41, '1'), 409);
    $errorEsperado(fn () => $servicio->desactivar(41, 43, null), 422);
    $errorEsperado(fn () => $servicio->desactivar(41, 999, '1'), 404);
    $repo->conteoAdministradores = 1;
    $errorEsperado(fn () => $servicio->desactivar(41, 45, '1'), 409);
    $assert($repo->filas[45]['activo'] === 1 && !$repo->enTransaccion());
    $repo->conteoAdministradores = 2; $servicio->desactivar(41, 45, '1');
    $assert($repo->filas[45]['activo'] === 0 && in_array('administradores_bloqueados', $repo->eventos, true));

    $caso = 'activación autorizada: preservar cuenta y rechazar cambios redundantes';
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $original = $repo->filas;
    $servicio->activar(41, 44, '1');
    $esperado = $original; $esperado[44]['activo'] = 1;
    $assert($repo->filas === $esperado && count($repo->filas) === 5);
    $assert($repo->eventos === ['iniciar', 'bloquear', 'buscar_bloqueado', 'buscar_bloqueado', 'activar', 'confirmar']);
    $errorEsperado(fn () => $servicio->activar(41, 44, '1'), 409);
    $assert($repo->filas === $esperado && !$repo->enTransaccion());
    $errorEsperado(fn () => $servicio->activar(41, 999, '1'), 404);
    foreach ([null, [], '0', 1, false] as $confirmacion) {
        $repo = new GestionUsuariosFicticia(); $antes = $repo->filas;
        $errorEsperado(fn () => (new UsuarioService($repo))->activar(41, 44, $confirmacion), 422);
        $assert($repo->filas === $antes && $repo->eventos === []);
    }
    foreach ([null, [], '0', '-1', '44.0', '44 OR 1=1'] as $id) {
        $errorEsperado(fn () => (new UsuarioService($repo))->activar(41, $id, '1'), 400);
    }
    foreach (['activar', 'confirmar'] as $fallo) {
        $repo = new GestionUsuariosFicticia(); $repo->fallar = $fallo; $antes = $repo->filas;
        $errorEsperado(fn () => (new UsuarioService($repo))->activar(41, 44, '1'), 503);
        $assert($repo->filas === $antes && !$repo->enTransaccion());
    }
    $repo = new GestionUsuariosFicticia(); $repo->iniciarTransaccion(); $antes = $repo->filas;
    $errorEsperado(fn () => (new UsuarioService($repo))->activar(41, 44, '1'), 503);
    $assert($repo->enTransaccion() && $repo->filas === $antes && $repo->eventos === ['iniciar']);
    $repo->revertirTransaccion();

    $caso = 'último administrador: cambio de rol propio y secuencias competitivas';
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $servicio->desactivar(41, 45, '1');
    $datos = $valido(); $datos['usuario'] = 'ficticio41'; $datos['id_rol'] = '13';
    $antes = $repo->filas;
    $errorEsperado(fn () => $servicio->modificar(41, 41, $datos), 409, 'id_rol');
    $datos['id_rol'] = '29';
    $errorEsperado(fn () => $servicio->modificar(41, 41, $datos), 409, 'id_rol');
    $assert($repo->filas === $antes && !$repo->enTransaccion());
    $datos['id_rol'] = '7'; $servicio->modificar(41, 41, $datos);
    $assert($repo->filas[41]['id_rol'] === 7 && $repo->filas[41]['nombres'] === 'José  María');
    // Dos operaciones válidas individualmente bajo un conteo anterior: la segunda reevalúa el estado actual.
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $datos = $valido(); $datos['usuario'] = 'ficticio41'; $datos['id_rol'] = '13';
    $servicio->modificar(41, 41, $datos);
    $datos['usuario'] = 'ficticio45';
    $errorEsperado(fn () => $servicio->modificar(45, 45, $datos), 409, 'id_rol');
    $assert($repo->filas[41]['id_rol'] === 13 && $repo->filas[45]['id_rol'] === 7);
    $assert($repo->administradoresActivos() === 1 && !$repo->enTransaccion());
    $errorEsperado(fn () => $servicio->modificar(41, 45, $datos), 403);
    // Un administrador inactivo no cuenta; reactivarlo sí permite retirar después otro rol.
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $repo->filas[45]['activo'] = 0;
    $datos['usuario'] = 'ficticio41';
    $errorEsperado(fn () => $servicio->modificar(41, 41, $datos), 409, 'id_rol');
    $servicio->activar(41, 45, '1');
    $servicio->modificar(41, 41, $datos);
    $assert($repo->filas[41]['id_rol'] === 13 && $repo->filas[45]['activo'] === 1);
    $assert($repo->administradoresActivos() === 1);
    // Guardar la misma cuenta/rol e incluso editar un administrador inactivo conserva la política.
    $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
    $repo->filas[45]['activo'] = 0;
    $datos['usuario'] = 'ficticio45'; $servicio->modificar(41, 45, $datos);
    $assert($repo->filas[45]['id_rol'] === 13 && $repo->filas[45]['activo'] === 0 && $repo->administradoresActivos() === 1);

    $caso = 'errores, restricciones y reversión';
    foreach (['bloquear', 'insertar', 'confirmar', 'duplicado', 'fk'] as $fallo) {
        $repo = new GestionUsuariosFicticia(); $repo->fallar = $fallo;
        $errorEsperado(fn () => (new UsuarioService($repo))->registrar(41, $valido()), in_array($fallo, ['duplicado', 'fk'], true) ? 409 : 503);
        $assert(count($repo->filas) === 5 && !$repo->enTransaccion());
    }
    foreach ([42, 43, 44, 999] as $actor) {
        $repo = new GestionUsuariosFicticia(); $servicio = new UsuarioService($repo);
        $errorEsperado(fn () => $servicio->registrar($actor, $valido()), 403);
        $errorEsperado(fn () => $servicio->modificar($actor, 45, $valido()), 403);
        $errorEsperado(fn () => $servicio->desactivar($actor, 45, '1'), 403);
        $errorEsperado(fn () => $servicio->activar($actor, 44, '1'), 403);
        $assert(count($repo->filas) === 5 && $repo->filas[45]['activo'] === 1);
    }

    $caso = 'controlador y acceso directo';
    foreach ([42, 43, 44, null] as $actor) {
        $repo = new GestionUsuariosFicticia(); [$controller, $csrf, $store, $contador] = $crearController($repo, $actor);
        $datos = $valido() + ['csrf' => $csrf->token()];
        foreach (['listar', 'nuevo', 'editar', 'confirmarDesactivacion', 'confirmarActivacion'] as $metodo) {
            $respuesta = $controller->$metodo(['id' => '45']);
            $assert($respuesta->estado === (in_array($actor, [42, 43], true) ? 403 : 303));
        }
        foreach (['registrar', 'modificar', 'desactivar', 'activar'] as $metodo) {
            $respuesta = $controller->$metodo($datos, ['id' => '45']);
            $assert($respuesta->estado === (in_array($actor, [42, 43], true) ? 403 : 303));
        }
        $assert($contador->fabricas === 0 && count($repo->filas) === 5);
    }
    $repo = new GestionUsuariosFicticia(); [$controller, $csrf, $store, $contador, $auth] = $crearController($repo);
    $antesConsulta = $repo->filas;
    $assert($controller->listar()->estado === 200 && $controller->nuevo()->estado === 200);
    $assert($controller->editar(['id' => '42'])->estado === 200 && $controller->confirmarDesactivacion(['id' => '42'])->estado === 200);
    $assert($controller->confirmarActivacion(['id' => '44'])->estado === 200);
    $assert(str_contains($controller->listar()->cuerpo, 'usuarios.activar&amp;id=44'));
    $assert(!str_contains($controller->listar()->cuerpo, 'usuarios.activar&amp;id=42'));
    $assert($repo->filas === $antesConsulta);
    $fabricasAntes = $contador->fabricas;
    foreach (['registrar', 'modificar', 'desactivar', 'activar'] as $metodo) {
        $assert($controller->$metodo($valido(), ['id' => '42'])->estado === 403);
        $assert($controller->$metodo($valido() + ['csrf' => ['invalid']], ['id' => '42'])->estado === 403);
        $assert($controller->$metodo($valido() + ['csrf' => str_repeat('f', 64)], ['id' => '42'])->estado === 403);
        $csrfAjeno = (new CsrfService(new SesionGestionFicticia()))->token();
        $assert($controller->$metodo($valido() + ['csrf' => $csrfAjeno], ['id' => '42'])->estado === 403);
    }
    $assert($contador->fabricas === $fabricasAntes && count($repo->filas) === 5);
    $respuesta = $controller->registrar($valido() + ['csrf' => $csrf->token()]);
    $assert($respuesta->estado === 303 && $respuesta->cabeceras['Location'] === '/?ruta=usuarios');
    $assert(str_contains($controller->listar()->cuerpo, 'Usuario registrado correctamente'));
    $assert(!str_contains($controller->listar()->cuerpo, 'Usuario registrado correctamente'));
    $respuesta = $controller->modificar($valido() + ['csrf' => $csrf->token()], ['id' => '46']);
    $assert($respuesta->estado === 303 && $repo->filas[46]['activo'] === 1);
    $respuesta = $controller->desactivar(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '46']);
    $assert($respuesta->estado === 303 && $repo->filas[46]['activo'] === 0);
    $antes = $repo->filas;
    $assert($controller->activar(['csrf' => $csrf->token()], ['id' => '46'])->estado === 422);
    $assert($repo->filas === $antes);
    $respuesta = $controller->activar(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '46']);
    $assert($respuesta->estado === 303 && $respuesta->cabeceras['Location'] === '/?ruta=usuarios');
    $esperado = $antes; $esperado[46]['activo'] = 1;
    $assert($repo->filas === $esperado);
    $assert(str_contains($controller->listar()->cuerpo, 'Usuario activado correctamente'));
    $assert(!str_contains($controller->listar()->cuerpo, 'Usuario activado correctamente'));
    $assert($controller->activar(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '46'])->estado === 409);
    $assert($repo->filas === $esperado);
    $assert($controller->activar(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '999'])->estado === 404);
    $assert($controller->confirmarActivacion(['id' => '999'])->estado === 404);
    $assert($controller->confirmarActivacion(['id' => []])->estado === 400);
    $assert(!str_contains($controller->confirmarActivacion(['id' => '46'])->cuerpo, 'data-button-label>Confirmar activación'));
    $assert($controller->editar(['id' => '999'])->estado === 404);
    $assert($controller->confirmarDesactivacion(['id' => []])->estado === 400);
    $assert(!str_contains($controller->confirmarDesactivacion(['id' => '41'])->cuerpo, 'data-button-label>Confirmar desactivación'));

    $caso = 'XSS, ausencia de contraseña y cambios de permisos';
    $repo->filas[42]['nombres'] = '<script>alert(1)</script>';
    $repo->filas[42]['usuario'] = '\"><img src=x onerror=alert(1)>';
    foreach ([$controller->listar(['q' => '<script>']), $controller->editar(['id' => '42']), $controller->confirmarDesactivacion(['id' => '42']), $controller->confirmarActivacion(['id' => '42'])] as $respuesta) {
        $assert(!str_contains($respuesta->cuerpo, '<script>alert(1)</script>'));
        $assert(!str_contains($respuesta->cuerpo, 'HASH_FICTICIO_NO_PUBLICABLE'));
        $assert(str_contains($respuesta->cuerpo, '&lt;script&gt;'));
    }
    $assert(!str_contains($controller->editar(['id' => '42'])->cuerpo, 'type="password"'));
    $assert(!str_contains($controller->confirmarActivacion(['id' => '44'])->cuerpo, 'type="password"'));
    $datos = $valido(); $datos['usuario'] = 'ficticio43';
    $respuesta = $controller->registrar($datos + ['csrf' => $csrf->token()]);
    $assert($respuesta->estado === 409 && !str_contains($respuesta->cuerpo, $valido()['contrasena']));
    $assert(preg_match('/type="password"[^>]*value=/u', $respuesta->cuerpo) === 0);
    $repo->filas[41]['id_rol'] = 13;
    $assert($controller->listar()->estado === 403 && $store->regeneraciones >= 2);
    $repo->filas[41]['activo'] = 0;
    $assert($controller->listar()->estado === 303 && $store->obtener('autenticacion') === null);
    $repo = new GestionUsuariosFicticia(); [$controllerOtro, $csrfOtro, $storeOtro] = $crearController($repo, 42);
    $assert($controllerOtro->listar()->estado === 403);
    $datos = $valido(); $datos['usuario'] = 'ficticio42'; $datos['id_rol'] = '7';
    (new UsuarioService($repo))->modificar(41, 42, $datos);
    $assert($controllerOtro->listar()->estado === 200 && $storeOtro->regeneraciones >= 2);
    (new UsuarioService($repo))->desactivar(41, 42, '1');
    $assert($controllerOtro->listar()->estado === 303 && $storeOtro->obtener('autenticacion') === null);
    (new UsuarioService($repo))->activar(41, 42, '1');
    $assert($repo->filas[42]['activo'] === 1 && $storeOtro->obtener('autenticacion') === null);

    $caso = 'PDO simulado: parámetros, contrato y bloqueos';
    $pdo = new PdoGestionFicticio(); $repository = new PdoGestionUsuarioRepository($pdo);
    $malicia = "%_!' OR 1=1 --";
    $repository->listar($malicia); $repository->listar(''); $repository->roles();
    $repository->buscar(41); $repository->buscar(42, true); $repository->existeUsuario($malicia, 42);
    $repository->iniciarTransaccion(); $pdo->resultados = [[['id_rol' => 7]]]; $repository->bloquearAdministracion();
    $pdo->resultados = [[['id_usuario' => 41], ['id_usuario' => 45]]]; $assert($repository->administradoresActivos() === 2);
    $datos = ['nombres' => 'Ficticio', 'apellidos' => 'Prueba', 'usuario' => $malicia, 'id_rol' => 13];
    $repository->insertar($datos, 'HASH_SOLO_FICTICIO'); $repository->actualizar(42, $datos); $repository->desactivar(42); $repository->activar(44);
    $repository->confirmarTransaccion(); $assert(!$repository->enTransaccion());
    foreach ($pdo->consultas as $consulta) {
        $assert(!str_contains($consulta['sql'], $malicia));
        preg_match_all('/:([a-z_]+)/', $consulta['sql'], $marcadores);
        $nombres = $marcadores[1]; sort($nombres); $claves = array_keys($consulta['parametros']); sort($claves);
        $assert($nombres === $claves && count($nombres) === count(array_unique($nombres)));
        $assert(preg_match('/\b(DELETE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $consulta['sql']) === 0);
        if (str_starts_with($consulta['sql'], 'SELECT')) { $assert(!str_contains($consulta['sql'], 'hash_contrasena')); }
    }
    $assert($pdo->consultas[0]['parametros']['nombres'] === "%!%!_!!' OR 1=1 --%");
    $assert(str_contains($pdo->consultas[4]['sql'], 'FOR UPDATE') && str_contains($pdo->consultas[6]['sql'], 'FOR UPDATE'));
    $assert(!str_contains($pdo->consultas[9]['sql'], 'hash_contrasena') && !str_contains($pdo->consultas[9]['sql'], 'activo ='));
    $assert($pdo->consultas[11]['sql'] === 'UPDATE usuario SET activo = :activo WHERE id_usuario = :id_usuario AND activo = :inactivo');
    $assert($pdo->consultas[11]['parametros'] === ['activo' => 1, 'id_usuario' => 44, 'inactivo' => 0]);
    $routes = require dirname(__DIR__) . '/routes/web.php';
    $assert(array_keys($routes['usuarios']) === ['GET']);
    foreach (['usuarios.registrar', 'usuarios.editar', 'usuarios.desactivar', 'usuarios.activar'] as $ruta) { $assert(array_keys($routes[$ruta]) === ['GET', 'POST']); }

    echo "Gestión de usuarios: $checks comprobaciones correctas. Memoria/PDO simulados; sin conexión ni cambios en sfatza.\n";
} catch (Throwable $error) {
    $linea = preg_match('/^Comprobación fallida en línea [0-9]+\.$/D', $error->getMessage()) === 1 ? ' ' . $error->getMessage() : '';
    $ubicacion = basename($error->getFile()) . ':' . $error->getLine();
    fwrite(STDERR, "Falló la suite HU-01 en: $caso.$linea " . get_class($error) . " ($ubicacion). Sin detalles privados.\n");
    exit(1);
}
