<?php

declare(strict_types=1);

use SFATSA\Controllers\AutenticacionController;
use SFATSA\Controllers\ConductorController;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Repositories\PdoConductorRepository;
use SFATSA\Services\AutenticacionService;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\ConductorService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\ErrorGestionConductores;
use SFATSA\Services\OperacionAdministrativaService;
use SFATSA\Services\SesionService;

ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/ConductoresFicticios.php';

final class PdoConductorFicticio extends PDO
{
    public array $consultas = [];
    public array $resultados = [];
    public int $cambios = 1;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new SentenciaConductorFicticia($this, $query, array_shift($this->resultados) ?? []); }
}
final class SentenciaConductorFicticia extends PDOStatement
{
    public function __construct(private PdoConductorFicticio $pdo, private string $sql, private array $filas) {}
    public function execute(?array $params = null): bool { $this->pdo->consultas[] = ['sql' => $this->sql, 'parametros' => $params]; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->filas) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->filas; }
    public function rowCount(): int { return $this->pdo->cambios; }
}

$checks = 0; $caso = 'inicio';
$assert = static function (bool $condicion) use (&$checks): void {
    if (!$condicion) {
        $linea = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0;
        throw new RuntimeException('Comprobación fallida en línea ' . $linea . '.');
    }
    $checks++;
};
$errorEsperado = static function (Closure $operacion, int $estado, ?string $campo = null) use ($assert): void {
    try { $operacion(); throw new RuntimeException('Se esperaba rechazo.'); }
    catch (ErrorGestionConductores $error) {
        $assert($error->estado === $estado);
        if ($campo !== null) { $assert(isset($error->errores[$campo])); }
        $assert(!str_contains($error->getMessage(), 'FICTICIO_INTERNO_SENSIBLE'));
    }
};
$valido = static fn (): array => ['nombres' => '  José  María ', 'apellidos' => ' Álvarez López ', 'numero_licencia' => 'licen000000001', 'telefono' => '9513440979'];
$fixture = static function (): array {
    $usuarios = new GestionUsuariosFicticia(); $conductores = new ConductoresFicticios();
    $admin = new AdministracionConductoresFicticia($usuarios, $conductores);
    return [new ConductorService($conductores, new OperacionAdministrativaService($admin)), $conductores, $usuarios, $admin];
};
$crearController = static function (array $fixture, ?int $idActor = 41): array {
    [$servicio, $conductores, $usuarios] = $fixture;
    $store = new SesionGestionFicticia(); $sesion = new SesionService($store); $csrf = new CsrfService($store);
    $auth = new AutenticacionController($sesion, $csrf, static fn () => new AutenticacionService($usuarios), new Vista(), new AutorizacionService());
    if ($idActor !== null) {
        $actor = $usuarios->buscar($idActor);
        $sesion->iniciar(new UsuarioAutenticado($idActor, $actor['nombres'], $actor['apellidos'], $actor['usuario'], $actor['rol']));
    }
    $contador = new stdClass(); $contador->fabricas = 0;
    $crear = static function () use ($servicio, $contador): ConductorService { $contador->fabricas++; return $servicio; };
    return [new ConductorController(static fn () => $auth->usuarioActual(), $crear, $csrf, $store, new Vista(), new AutorizacionService()), $csrf, $store, $contador];
};

try {
    $caso = 'alta válida y teléfono opcional';
    [$servicio, $repo, $usuarios] = $fixture(); $servicio->registrar(41, $valido());
    $assert($repo->filas[14]['nombres'] === 'José  María' && $repo->filas[14]['apellidos'] === 'Álvarez López');
    $assert($repo->filas[14]['numero_licencia'] === 'LICEN000000001' && $repo->filas[14]['telefono'] === '9513440979');
    $assert($repo->filas[14]['activo'] === 1 && $usuarios->eventos === ['iniciar', 'bloquear', 'buscar_bloqueado', 'confirmar']);
    $sufijoLicencia = 0;
    foreach ([null, ''] as $opcional) {
        $datos = $valido(); $datos['numero_licencia'] = 'TEST' . str_pad((string) ++$sufijoLicencia, 10, '0', STR_PAD_LEFT); $datos['telefono'] = $opcional;
        $servicio->registrar(41, $datos); $fila = $repo->filas[max(array_keys($repo->filas))];
        $assert($fila['numero_licencia'] === $datos['numero_licencia'] && $fila['telefono'] === null);
    }
    $datos = $valido(); $datos['numero_licencia'] = 'TEST0000000005'; unset($datos['telefono']);
    $servicio->registrar(41, $datos);
    $assert($repo->filas[max(array_keys($repo->filas))]['telefono'] === null);

    $caso = 'obligatorios y límites Unicode en alta y edición';
    foreach (['nombres' => 120, 'apellidos' => 120] as $campo => $limite) {
        $obligatorio = in_array($campo, ['nombres', 'apellidos', 'numero_licencia'], true);
        foreach ($obligatorio ? [null, '', ' ', "\u{00A0}", [], "\xFF", "x\0y"] : [[], "\xFF", "x\0y"] as $invalido) {
            [$servicio, $repo] = $fixture(); $datos = $valido(); $datos[$campo] = $invalido; $antes = $repo->filas;
            $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, $campo);
            $errorEsperado(fn () => $servicio->modificar(41, 11, $datos), 422, $campo);
            $assert($repo->filas === $antes);
        }
        if ($obligatorio) {
            $datos = $valido(); unset($datos[$campo]);
            $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, $campo);
        }
        foreach (['a', 'á', '🚍'] as $caracter) {
            [$servicio, $repo] = $fixture(); $datos = $valido(); $datos[$campo] = str_repeat($caracter, $limite);
            $servicio->registrar(41, $datos); $assert($repo->filas[14][$campo] === $datos[$campo]);
            $servicio->modificar(41, 14, $datos); $assert($repo->filas[14][$campo] === $datos[$campo]);
            $datos[$campo] .= $caracter;
            $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, $campo);
            $errorEsperado(fn () => $servicio->modificar(41, 14, $datos), 422, $campo);
        }
    }
    foreach (['a', 'á', '🚍'] as $caracter) {
        [$servicio, $repo] = $fixture(); $datos = $valido(); $datos['nombres'] = $datos['apellidos'] = str_repeat($caracter, 120);
        $servicio->registrar(41, $datos); $assert($repo->filas[14]['nombres'] === $datos['nombres'] && $repo->filas[14]['apellidos'] === $datos['apellidos']);
    }

    $caso = 'formato de licencia exacto en alta y edición';
    foreach ([null, '', ' ', str_repeat('A', 13), str_repeat('A', 15), 'LICEN00000001 ', 'LICEN 00000001', 'LICEN-00000001', 'LICÉN00000001', 'LICEN0000000!'] as $licenciaInvalida) {
        [$servicio, $repo] = $fixture(); $datos = $valido(); $datos['numero_licencia'] = $licenciaInvalida; $antes = $repo->filas;
        $errorEsperado(fn () => $servicio->registrar(41, $datos), 422, 'numero_licencia');
        $errorEsperado(fn () => $servicio->modificar(41, 11, $datos), 422, 'numero_licencia');
        $assert($repo->filas === $antes);
    }
    $datos = $valido(); unset($datos['numero_licencia']);
    $errorEsperado(fn () => $fixture()[0]->registrar(41, $datos), 422, 'numero_licencia');
    $errorEsperado(fn () => $fixture()[0]->modificar(41, 11, $datos), 422, 'numero_licencia');
    [$servicio, $repo] = $fixture(); $datos = $valido(); $servicio->registrar(41, $datos);
    $assert($repo->filas[14]['numero_licencia'] === 'LICEN000000001');

    $caso = 'licencia duplicada y edición preserva estado';
    [$servicio, $repo] = $fixture(); $datos = $valido(); $datos['numero_licencia'] = 'licen000000011';
    $errorEsperado(fn () => $servicio->registrar(41, $datos), 409, 'numero_licencia');
    $errorEsperado(fn () => $servicio->modificar(41, 13, $datos), 409, 'numero_licencia');
    $datos['activo'] = 0; $servicio->modificar(41, 11, $datos);
    $assert($repo->filas[11]['activo'] === 1 && $repo->filas[11]['numero_licencia'] === 'LICEN000000011');
    $datos['apellidos'] = $repo->filas[11]['apellidos']; $datos['nombres'] = 'Nombres modificados';
    $servicio->modificar(41, 11, $datos); $assert($repo->filas[11]['apellidos'] === 'Álvarez López');
    $datos['apellidos'] = 'Apellidos modificados'; $datos['nombres'] = $repo->filas[11]['nombres'];
    $datos['numero_licencia'] = 'licen000000011'; $datos['telefono'] = '';
    $servicio->modificar(41, 11, $datos);
    $assert($repo->filas[11]['nombres'] === 'Nombres modificados' && $repo->filas[11]['apellidos'] === 'Apellidos modificados');
    $assert($repo->filas[11]['numero_licencia'] === 'LICEN000000011' && $repo->filas[11]['telefono'] === null);
    $servicio->modificar(41, 11, $datos); $assert(count($repo->filas) === 3);

    $caso = 'búsqueda, IDs y estados inválidos';
    [$servicio] = $fixture();
    foreach (['ficticio', 'Álvarez', 'LICEN000000011'] as $q) { $assert(count($servicio->listar($q)['conductores']) > 0); }
    $assert($servicio->listar('no-existe')['conductores'] === [] && count($servicio->listar('')['conductores']) === 3);
    foreach ([null, [], '0', '-1', '1.2', '11 OR 1=1', str_repeat('9', 30)] as $id) { $errorEsperado(fn () => $servicio->obtener($id), 400); }
    $errorEsperado(fn () => $servicio->obtener(999), 404);
    $errorEsperado(fn () => $servicio->modificar(41, 999, $valido()), 404);
    foreach ([[], "\xFF", str_repeat('a', 242)] as $q) { $errorEsperado(fn () => $servicio->listar($q), 400); }
    foreach ([null, [], '2', '-1', 'activo', false] as $estado) { $errorEsperado(fn () => $servicio->cambiarEstado(41, 11, $estado, '1'), 400); }

    $caso = 'desactivar, activar y rechazos redundantes';
    [$servicio, $repo] = $fixture(); $original = $repo->filas[11];
    $servicio->cambiarEstado(41, 11, '0', '1'); $assert($repo->filas[11] === array_replace($original, ['activo' => 0]));
    $errorEsperado(fn () => $servicio->cambiarEstado(41, 11, '0', '1'), 409);
    $servicio->cambiarEstado(41, 11, '1', '1'); $assert($repo->filas[11] === $original);
    $errorEsperado(fn () => $servicio->cambiarEstado(41, 11, '1', '1'), 409);
    $errorEsperado(fn () => $servicio->cambiarEstado(41, 11, '0', null), 422);
    $errorEsperado(fn () => $servicio->cambiarEstado(41, 999, '0', '1'), 404);
    $assert(count($repo->filas) === 3);

    $caso = 'transacción compartida y revocación antes de guardar';
    foreach (['insertar', 'duplicado'] as $fallo) {
        [$servicio, $repo, $usuarios] = $fixture(); $antes = $repo->filas; $repo->fallar = $fallo;
        $errorEsperado(fn () => $servicio->registrar(41, $valido()), $fallo === 'duplicado' ? 409 : 503);
        $assert($repo->filas === $antes && !$usuarios->enTransaccion());
    }
    [$servicio, $repo, $usuarios] = $fixture(); $antes = $repo->filas; $usuarios->fallar = 'confirmar';
    $errorEsperado(fn () => $servicio->registrar(41, $valido()), 503);
    $assert($repo->filas === $antes && in_array('revertir', $usuarios->eventos, true));
    [$servicio, $repo, $usuarios, $admin] = $fixture();
    $admin->trasBloqueo = static function () use ($usuarios): void { $usuarios->filas[41]['id_rol'] = 13; };
    $errorEsperado(fn () => $servicio->registrar(41, $valido()), 403); $assert(count($repo->filas) === 3);
    foreach ([42, 43, 44, 999] as $actor) {
        [$servicio, $repo] = $fixture();
        $errorEsperado(fn () => $servicio->registrar($actor, $valido()), 403);
        $errorEsperado(fn () => $servicio->modificar($actor, 11, $valido()), 403);
        $errorEsperado(fn () => $servicio->cambiarEstado($actor, 11, '0', '1'), 403);
        $assert(count($repo->filas) === 3 && $repo->filas[11]['activo'] === 1);
    }

    $caso = 'acceso directo por rol y CSRF';
    foreach ([42, 43, 44, null] as $actor) {
        $f = $fixture(); [$controller, $csrf, $store, $contador] = $crearController($f, $actor);
        foreach (['listar', 'nuevo', 'editar', 'confirmarEstado'] as $metodo) {
            $assert($controller->$metodo(['id' => '11', 'estado' => '0'])->estado === (in_array($actor, [42, 43], true) ? 403 : 303));
        }
        foreach (['registrar', 'modificar', 'cambiarEstado'] as $metodo) {
            $assert($controller->$metodo($valido() + ['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '11', 'estado' => '0'])->estado === (in_array($actor, [42, 43], true) ? 403 : 303));
        }
        $assert($contador->fabricas === 0 && count($f[1]->filas) === 3);
    }
    $f = $fixture(); [$controller, $csrf, $store, $contador] = $crearController($f); $antes = $f[1]->filas;
    foreach (['listar', 'nuevo', 'editar', 'confirmarEstado'] as $metodo) { $assert($controller->$metodo(['id' => '11', 'estado' => '0'])->estado === 200); }
    $assert($f[1]->filas === $antes);
    $fabricasAntes = $contador->fabricas;
    foreach (['registrar', 'modificar', 'cambiarEstado'] as $metodo) {
        foreach ([null, [], str_repeat('f', 64), (new CsrfService(new SesionGestionFicticia()))->token()] as $token) {
            $assert($controller->$metodo($valido() + ['csrf' => $token], ['id' => '11', 'estado' => '0'])->estado === 403);
        }
    }
    $assert($contador->fabricas === $fabricasAntes && $f[1]->filas === $antes);
    $r = $controller->registrar($valido() + ['csrf' => $csrf->token()]);
    $assert($r->estado === 303 && $r->cabeceras['Location'] === '/?ruta=conductores');
    $assert(str_contains($controller->listar()->cuerpo, 'Conductor registrado correctamente'));
    $assert(!str_contains($controller->listar()->cuerpo, 'Conductor registrado correctamente'));
    $r = $controller->modificar($valido() + ['csrf' => $csrf->token()], ['id' => '14']); $assert($r->estado === 303);
    $r = $controller->cambiarEstado(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '14', 'estado' => '0']);
    $assert($r->estado === 303 && $f[1]->filas[14]['activo'] === 0);
    $r = $controller->cambiarEstado(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '14', 'estado' => '1']);
    $assert($r->estado === 303 && $f[1]->filas[14]['activo'] === 1);
    $assert($controller->cambiarEstado(['csrf' => $csrf->token(), 'confirmacion' => '1'], ['id' => '14', 'estado' => '1'])->estado === 409);
    $assert($controller->editar(['id' => '999'])->estado === 404);
    $assert($controller->confirmarEstado(['id' => '11', 'estado' => []])->estado === 400);
    $assert(!str_contains($controller->confirmarEstado(['id' => '11', 'estado' => '1'])->cuerpo, 'type="checkbox"'));

    $caso = 'XSS y valores opcionales en vistas';
    $f[1]->filas[11]['nombres'] = '<script>alert(1)</script>';
    $f[1]->filas[11]['numero_licencia'] = '\"><img src=x onerror=alert(1)>';
    foreach ([$controller->listar(['q' => '<script>']), $controller->editar(['id' => '11']), $controller->confirmarEstado(['id' => '11', 'estado' => '0'])] as $r) {
        $assert(!str_contains($r->cuerpo, '<script>alert(1)</script>') && str_contains($r->cuerpo, '&lt;script&gt;'));
        $assert(!str_contains($r->cuerpo, '<img src=x'));
    }
    $vistaFormulario = $controller->editar(['id' => '12'])->cuerpo;
    $assert(str_contains($vistaFormulario, 'id="numero_licencia" name="numero_licencia" type="text" value=""'));
    $assert(preg_match('/id="numero_licencia"[^>]*required/', $vistaFormulario) === 1);
    $assert(str_contains($vistaFormulario, 'minlength="14" maxlength="14" pattern="[A-Za-z0-9]{14}"'));
    $assert(str_contains($vistaFormulario, 'El teléfono es opcional.'));
    $datosSinLicencia = $valido(); unset($datosSinLicencia['numero_licencia']);
    $respuestaSinLicencia = $controller->registrar($datosSinLicencia + ['csrf' => $csrf->token()]);
    $assert($respuestaSinLicencia->estado === 422);
    $assert(str_contains($respuestaSinLicencia->cuerpo, 'Este campo es obligatorio.'));
    $assert(str_contains($respuestaSinLicencia->cuerpo, 'name="nombres" type="text" value="  José  María "'));
    $assert(str_contains($respuestaSinLicencia->cuerpo, 'name="numero_licencia" type="text" value="" required'));
    $datosLicenciaInvalida = $valido(); $datosLicenciaInvalida['numero_licencia'] = 'licen 00000001';
    $respuestaLicenciaInvalida = $controller->registrar($datosLicenciaInvalida + ['csrf' => $csrf->token()]);
    $assert($respuestaLicenciaInvalida->estado === 422);
    $assert(str_contains($respuestaLicenciaInvalida->cuerpo, 'licen 00000001'));
    $assert(str_contains($respuestaLicenciaInvalida->cuerpo, 'sin espacios ni símbolos.'));

    $caso = 'PDO simulado: contrato y parámetros';
    $pdo = new PdoConductorFicticio(); $repository = new PdoConductorRepository($pdo); $malicia = "%_!' OR 1=1 --";
    $repository->listar($malicia); $repository->listar(''); $repository->buscar(11); $repository->buscar(11, true);
    $repository->existeLicencia($malicia); $repository->existeLicencia($malicia, 11);
    $datos = ['nombres' => 'Ficticio', 'apellidos' => 'Prueba', 'numero_licencia' => 'TEST0000000001', 'telefono' => null];
    $repository->insertar($datos); $repository->actualizar(11, $datos); $repository->cambiarEstado(11, 0, 1);
    foreach ($pdo->consultas as $consulta) {
        $assert(!str_contains($consulta['sql'], $malicia));
        $assert(preg_match('/\b(DELETE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $consulta['sql']) === 0);
        preg_match_all('/:([a-z_]+)/', $consulta['sql'], $marcadores);
        $nombres = $marcadores[1]; sort($nombres); $claves = array_keys($consulta['parametros']); sort($claves);
        $assert($nombres === $claves && count($nombres) === count(array_unique($nombres)));
    }
    $assert($pdo->consultas[0]['parametros']['licencia'] === "%!%!_!!' OR 1=1 --%");
    $assert(str_contains($pdo->consultas[3]['sql'], 'FOR UPDATE'));
    $assert(!str_contains($pdo->consultas[7]['sql'], 'activo =') && $pdo->consultas[6]['parametros']['numero_licencia'] === 'TEST0000000001');
    $assert(str_contains($pdo->consultas[8]['sql'], 'AND activo = :anterior'));
    $pdo->cambios = 0;
    try { $repository->cambiarEstado(11, 0, 1); throw new LogicException('Se esperaba conflicto.'); } catch (RuntimeException $error) { $assert(!($error instanceof LogicException)); }
    $routes = require dirname(__DIR__) . '/routes/web.php'; $assert(array_keys($routes['conductores']) === ['GET']);
    foreach (['conductores.registrar', 'conductores.editar', 'conductores.estado'] as $ruta) { $assert(array_keys($routes[$ruta]) === ['GET', 'POST']); }
    $entrada = file_get_contents(dirname(__DIR__) . '/public/index.php');
    $assert(str_contains($entrada, 'PdoConductorRepository($pdo)') && str_contains($entrada, 'PdoGestionUsuarioRepository($pdo)'));
    echo "Gestión de conductores: $checks comprobaciones correctas. Memoria/PDO simulados; sin conexión ni cambios en sfatza.\n";
} catch (Throwable $error) {
    $linea = preg_match('/^Comprobación fallida en línea [0-9]+\.$/D', $error->getMessage()) === 1 ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, "Falló HU-03 en: $caso.$linea " . get_class($error) . ' (' . basename($error->getFile()) . ':' . $error->getLine() . "). Sin detalles privados.\n");
    exit(1);
}
