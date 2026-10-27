<?php

declare(strict_types=1);

use SFATSA\Repositories\PdoVehiculoRepository;
use SFATSA\Services\ErrorGestionVehiculos;
use SFATSA\Services\OperacionAdministrativaService;
use SFATSA\Services\RegistroVehiculoService;
use SFATSA\Services\VehiculoService;

ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/VehiculosFicticios.php';

/** No llama al constructor PDO: contratos SQL simulados, sin conexión real. */
final class PdoVehiculoFicticio extends PDO
{
    public array $consultas = [];
    public array $resultados = [];
    public int $cambios = 1;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new SentenciaVehiculoFicticia($this, $query, array_shift($this->resultados) ?? []);
    }
}
final class SentenciaVehiculoFicticia extends PDOStatement
{
    public function __construct(private PdoVehiculoFicticio $pdo, private string $sql, private array $filas) {}
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
$errorEsperado = static function (Closure $operacion, int $estado, ?string $campo = null) use ($assert): ErrorGestionVehiculos {
    try { $operacion(); throw new RuntimeException('Se esperaba rechazo.'); }
    catch (ErrorGestionVehiculos $error) {
        $assert($error->estado === $estado);
        if ($campo !== null) { $assert(isset($error->errores[$campo])); }
        $assert(!str_contains(serialize([$error->getMessage(), $error->errores]), 'FICTICIO_INTERNO_SENSIBLE'));
        $assert($error->getPrevious() === null);
        return $error;
    }
};
$valido = static fn (): array => ['numero_economico' => '  eco-13 ', 'placa' => ' a-00013-a ', 'descripcion' => ' Autobús de prueba ', 'capacidad' => '40'];
$fixture = static function (): array {
    $usuarios = new GestionUsuariosFicticia(); $vehiculos = new VehiculosFicticios();
    $admin = new AdministracionVehiculosFicticia($usuarios, $vehiculos);
    return [new VehiculoService($vehiculos, new OperacionAdministrativaService($admin)), $vehiculos, $usuarios, $admin];
};

try {
    $caso = 'normalización, revisión sin escritura y alta confirmada';
    [$s, $r, $u] = $fixture(); $datos = $valido();
    $resumen = $s->prepararRegistro($datos);
    $assert($resumen === ['numero_economico' => 'ECO-13', 'placa' => 'A-00013-A', 'descripcion' => 'Autobús de prueba', 'capacidad' => 40, 'estado' => 'ACTIVO']);
    $assert(count($r->filas) === 2 && $u->eventos === []);
    foreach ([null, '', '0', 1, true, []] as $confirmacion) { $errorEsperado(fn () => $s->registrar(41, $datos, $confirmacion), 422); }
    $assert(count($r->filas) === 2);
    $s->registrar(41, $datos, '1');
    $assert($r->filas[13] === $resumen + ['id_vehiculo' => 13]);
    $assert($u->eventos === ['iniciar', 'bloquear', 'buscar_bloqueado', 'confirmar']);
    $datos['numero_economico'] = "\u{00A0}éco-14\u{00A0}"; $datos['placa'] = 'abc-14'; $datos['estado'] = 'INACTIVO';
    $s->registrar(41, $datos, '1');
    $assert($r->filas[14]['numero_economico'] === 'ÉCO-14' && $r->filas[14]['estado'] === 'INACTIVO');

    $caso = 'límites de texto y caracteres, sin formato legal obligatorio';
    foreach (['placa' => 20, 'numero_economico' => 30, 'descripcion' => 100] as $campo => $limite) {
        [$s] = $fixture(); $datos = $valido(); $datos[$campo] = str_repeat('A', $limite);
        $assert($s->prepararRegistro($datos)[$campo] === $datos[$campo]);
        $datos[$campo] .= 'A'; $errorEsperado(fn () => $s->prepararRegistro($datos), 422, $campo);
        foreach ([null, '', ' ', "\u{00A0}", [], false, 22, "\xFF", "A\0B", "A\nB", "\tAB", "AB\r", "A\u{200B}B"] as $invalido) {
            $datos = $valido(); $datos[$campo] = $invalido;
            $errorEsperado(fn () => $s->prepararRegistro($datos), 422, $campo);
            $errorEsperado(fn () => $s->modificar(41, 11, $datos), 422, $campo);
        }
        $datos = $valido(); unset($datos[$campo]); $errorEsperado(fn () => $s->prepararRegistro($datos), 422, $campo);
    }
    [$s] = $fixture();
    foreach (['abc123', 'A-00000-A', '12345', 'A', 'a--12-b'] as $placa) {
        $datos = $valido(); $datos['placa'] = $placa;
        $assert($s->prepararRegistro($datos)['placa'] === strtoupper($placa));
    }
    foreach (['-ABC', 'ABC-', 'AB C', 'ÁBC', 'ß', 'AB_C', 'AB.C', 'A/B', "A'B", '<script>', 'ＡＢＣ'] as $placa) {
        $datos = $valido(); $datos['placa'] = $placa; $errorEsperado(fn () => $s->prepararRegistro($datos), 422, 'placa');
    }
    $datos = $valido(); $datos['descripcion'] = str_repeat('á', 100);
    $assert($s->prepararRegistro($datos)['descripcion'] === $datos['descripcion']);
    $datos['descripcion'] .= 'á'; $errorEsperado(fn () => $s->prepararRegistro($datos), 422, 'descripcion');
    $datos = $valido(); $datos['numero_economico'] = str_repeat('ß', 16);
    $errorEsperado(fn () => $s->prepararRegistro($datos), 422, 'numero_economico');

    $caso = 'capacidad y estados estrictos';
    foreach ([1 => 1, 65535 => 65535, '00040' => 40] as $valor => $esperado) {
        $datos = $valido(); $datos['capacidad'] = $valor; $assert($s->prepararRegistro($datos)['capacidad'] === $esperado);
    }
    foreach ([null, '', ' ', 0, '000', -1, 65536, '65536', str_repeat('9', 100), '1.0', 1.5, 1.0, '1e2', true, [], '+1', ' 40'] as $capacidad) {
        $datos = $valido(); $datos['capacidad'] = $capacidad;
        $errorEsperado(fn () => $s->prepararRegistro($datos), 422, 'capacidad');
        $errorEsperado(fn () => $s->modificar(41, 11, $datos), 422, 'capacidad');
    }
    foreach ([null, '', 'activo', 'MANTENIMIENTO', 1, [], false] as $estado) {
        $datos = $valido(); $datos['estado'] = $estado; $errorEsperado(fn () => $s->prepararRegistro($datos), 422, 'estado');
        $errorEsperado(fn () => $s->cambiarEstado(41, 11, $estado, '1'), 400, 'estado');
    }

    $caso = 'unicidad, exclusión propia y conservación de estado al editar';
    foreach (['placa' => 'a-00011-a', 'numero_economico' => 'eco-11'] as $campo => $valor) {
        [$s, $r] = $fixture(); $antes = $r->filas; $datos = $valido(); $datos[$campo] = $valor;
        $errorEsperado(fn () => $s->prepararRegistro($datos), 409, $campo);
        $errorEsperado(fn () => $s->registrar(41, $datos, '1'), 409, $campo);
        $errorEsperado(fn () => $s->modificar(41, 12, $datos), 409, $campo);
        $assert($r->filas === $antes);
    }
    [$s, $r] = $fixture(); $datos = $r->filas[12]; unset($datos['estado']); $datos['descripcion'] = 'Descripción modificada';
    $s->modificar(41, 12, $datos); $s->modificar(41, 12, $datos);
    $assert($r->filas[12]['estado'] === 'INACTIVO' && $r->filas[12]['descripcion'] === 'Descripción modificada');
    $datos['estado'] = 'ACTIVO'; $errorEsperado(fn () => $s->modificar(41, 12, $datos), 422, 'estado');
    $s->cambiarEstado(41, 12, 'ACTIVO', '1'); $assert($r->filas[12]['estado'] === 'ACTIVO');
    $errorEsperado(fn () => $s->cambiarEstado(41, 12, 'ACTIVO', '1'), 409);
    $errorEsperado(fn () => $s->cambiarEstado(41, 12, 'INACTIVO', null), 422);
    $s->cambiarEstado(41, 12, 'INACTIVO', '1'); $assert($r->filas[12]['estado'] === 'INACTIVO');
    $r->filas[12]['estado'] = 'MANTENIMIENTO'; $antes = $r->filas;
    $errorEsperado(fn () => $s->modificar(41, 12, $valido()), 400, 'estado');
    $errorEsperado(fn () => $s->cambiarEstado(41, 12, 'ACTIVO', '1'), 400, 'estado');
    $assert($r->filas === $antes);

    $caso = 'consulta parcial, exacta, literal y errores de lectura';
    [$s, $r] = $fixture();
    foreach (['a-00011-a', '00011', 'eco-11', 'co-11'] as $q) { $assert($s->listar(' ' . $q . ' ')['vehiculos'][0]['id_vehiculo'] === 11); }
    $assert(count($s->listar()['vehiculos']) === 2 && $s->listar('no-existe')['vehiculos'] === []);
    $assert($s->listar('%_!')['vehiculos'] === [] && $s->obtener('11')['id_vehiculo'] === 11);
    foreach ([null, [], '0', 0, -1, '01', '1.1', '1 OR 1=1', str_repeat('9', 30)] as $id) { $errorEsperado(fn () => $s->obtener($id), 400); }
    foreach ([[], false, "\xFF", "A\n", str_repeat('a', 31)] as $q) { $errorEsperado(fn () => $s->listar($q), 400); }
    $assert($s->listar(str_repeat('a', 30))['vehiculos'] === []);
    $errorEsperado(fn () => $s->obtener(999), 404);
    $errorEsperado(fn () => $s->modificar(41, 999, $valido()), 404);
    $errorEsperado(fn () => $s->cambiarEstado(41, 999, 'INACTIVO', '1'), 404);
    foreach (['listar', 'buscar', 'existe'] as $fallo) {
        $r->fallar = $fallo;
        $errorEsperado(match ($fallo) { 'listar' => fn () => $s->listar(), 'buscar' => fn () => $s->obtener(11), default => fn () => $s->prepararRegistro($valido()) }, 503);
    }

    $caso = 'autorización, revocación, rollback y carreras UNIQUE simuladas';
    foreach ([42, 43, 44, 999] as $actor) {
        [$s, $r] = $fixture(); $antes = $r->filas;
        $errorEsperado(fn () => $s->registrar($actor, $valido(), '1'), 403);
        $errorEsperado(fn () => $s->modificar($actor, 11, $valido()), 403);
        $errorEsperado(fn () => $s->cambiarEstado($actor, 11, 'INACTIVO', '1'), 403);
        $assert($r->filas === $antes);
    }
    [$s, $r, $u, $admin] = $fixture();
    $admin->trasBloqueo = static function () use ($u): void { $u->filas[41]['id_rol'] = 13; };
    $errorEsperado(fn () => $s->registrar(41, $valido(), '1'), 403); $assert(count($r->filas) === 2);
    foreach (['insertar', 'actualizar', 'estado', 'existe', 'buscar_bloqueado'] as $fallo) {
        [$s, $r, $u] = $fixture(); $antes = $r->filas; $r->fallar = $fallo;
        $operacion = match ($fallo) { 'actualizar', 'buscar_bloqueado' => fn () => $s->modificar(41, 11, $valido()), 'estado' => fn () => $s->cambiarEstado(41, 11, 'INACTIVO', '1'), default => fn () => $s->registrar(41, $valido(), '1') };
        $errorEsperado($operacion, 503); $assert($r->filas === $antes && !$u->enTransaccion());
    }
    foreach (['placa', 'numero_economico', 'vehiculo.placa', 'indice_desconocido'] as $indice) {
        [$s, $r] = $fixture(); $r->fallar = 'duplicado:' . $indice;
        $error = $errorEsperado(fn () => $s->registrar(41, $valido(), '1'), 409);
        $assert(isset($error->errores[$indice === 'numero_economico' ? 'numero_economico' : 'placa']));
        $assert(count($error->errores) === ($indice === 'indice_desconocido' ? 2 : 1));
        $assert(count($r->filas) === 2 && !str_contains($error->getMessage(), 'Duplicate entry'));
    }
    [$s, $r, $u] = $fixture(); $antes = $r->filas; $u->fallar = 'confirmar';
    $errorEsperado(fn () => $s->registrar(41, $valido(), '1'), 503);
    $assert($r->filas === $antes && in_array('revertir', $u->eventos, true));
    foreach (['modificar', 'estado'] as $operacion) {
        [$s, $r, $u] = $fixture(); $antes = $r->filas; $u->fallar = 'confirmar';
        $errorEsperado($operacion === 'modificar' ? fn () => $s->modificar(41, 11, $valido())
            : fn () => $s->cambiarEstado(41, 11, 'INACTIVO', '1'), 503);
        $assert($r->filas === $antes && !$u->enTransaccion());
    }
    [$s, $r, $u, $admin] = $fixture(); $admin->iniciarTransaccion();
    $errorEsperado(fn () => $s->registrar(41, $valido(), '1'), 503);
    $assert($u->enTransaccion() && !in_array('revertir', $u->eventos, true)); $admin->revertirTransaccion();
    [$s, $r, $u] = $fixture(); $r->fallar = 'insertar'; $u->fallar = 'revertir';
    $errorEsperado(fn () => $s->registrar(41, $valido(), '1'), 503);

    $caso = 'resumen privado, cancelación, confirmación única y caducidad';
    [$s, $r, $u] = $fixture(); $sesion = new SesionGestionFicticia(); $ahora = 1000;
    $flujo = new RegistroVehiculoService($s, $sesion, static function () use (&$ahora): int { return $ahora; });
    $a = $flujo->preparar(41, $valido()); $b = $flujo->preparar(41, $valido());
    $assert($a['token'] !== $b['token'] && strlen($a['token']) === 64 && count($r->filas) === 2 && $u->eventos === []);
    $errorEsperado(fn () => $flujo->confirmar(42, $a['token']), 403);
    $errorEsperado(fn () => $flujo->cancelar(42, $a['token']), 403);
    $flujo->cancelar(41, $a['token']); $assert(count($r->filas) === 2 && $u->eventos === []);
    $errorEsperado(fn () => $flujo->confirmar(41, $a['token']), 409);
    $flujo->confirmar(41, $b['token']); $assert(count($r->filas) === 3 && $r->filas[13]['placa'] === 'A-00013-A');
    $errorEsperado(fn () => $flujo->confirmar(41, $b['token']), 409);
    foreach ([null, [], '', str_repeat('f', 63)] as $token) { $errorEsperado(fn () => $flujo->confirmar(41, $token), 400); }
    $errorEsperado(fn () => $flujo->confirmar(41, str_repeat('f', 64)), 409);
    $datos = $valido(); $datos['placa'] = 'OTRA'; $datos['numero_economico'] = 'OTRO';
    $c = $flujo->preparar(41, $datos); $ahora += 899; $assert($flujo->obtenerResumen(41, $c['token'])['placa'] === 'OTRA');
    $ahora++; $errorEsperado(fn () => $flujo->confirmar(41, $c['token']), 409);
    $tokens = []; for ($i = 0; $i < 6; $i++) { $tokens[] = $flujo->preparar(41, $datos)['token']; }
    $errorEsperado(fn () => $flujo->obtenerResumen(41, $tokens[0]), 409);
    $assert($flujo->obtenerResumen(41, $tokens[5])['placa'] === 'OTRA');
    $errorEsperado(fn () => $flujo->preparar(0, $datos), 403);

    $caso = 'revalidación final y no reintentar confirmación fallida';
    foreach (['duplicado', 'revocado', 'persistencia', 'datos_manipulados'] as $fallo) {
        [$s, $r, $u] = $fixture(); $sesion = new SesionGestionFicticia(); $flujo = new RegistroVehiculoService($s, $sesion);
        $a = $flujo->preparar(41, $valido());
        if ($fallo === 'duplicado') { $r->filas[12]['placa'] = $a['datos']['placa']; }
        if ($fallo === 'revocado') { $u->filas[41]['activo'] = 0; }
        if ($fallo === 'persistencia') { $r->fallar = 'insertar'; }
        if ($fallo === 'datos_manipulados') { $sesion->datos['vehiculos_registros'][$a['token']]['datos']['estado'] = 'MANTENIMIENTO'; }
        $errorEsperado(fn () => $flujo->confirmar(41, $a['token']), match ($fallo) { 'duplicado' => 409, 'revocado' => 403, 'persistencia' => 503, default => 422 });
        $errorEsperado(fn () => $flujo->confirmar(41, $a['token']), 409); $assert(count($r->filas) === 2);
    }

    $caso = 'contratos PDO y parámetros sin interpolar entrada';
    $pdo = new PdoVehiculoFicticio(); $repo = new PdoVehiculoRepository($pdo); $entrada = "%_!' OR 1=1 --";
    $repo->listar($entrada); $repo->listar(''); $assert($repo->buscar(11) === null); $repo->buscar(11, true);
    $assert(!$repo->existePlaca($entrada)); $repo->existePlaca($entrada, 11); $repo->existeNumeroEconomico($entrada, 11);
    $datos = ['numero_economico' => 'ECO-99', 'placa' => 'PLACA-99', 'descripcion' => "Autobús ' sintético", 'capacidad' => 40, 'estado' => 'ACTIVO', 'id_vehiculo' => 999, 'intruso' => 'no'];
    $repo->insertar($datos); $repo->actualizar(11, $datos); $repo->cambiarEstado(11, 'INACTIVO', 'ACTIVO');
    foreach ($pdo->consultas as $consulta) {
        $assert(!str_contains($consulta['sql'], $entrada) && !str_contains($consulta['sql'], $datos['descripcion']));
        $assert(preg_match('/\b(DELETE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $consulta['sql']) === 0);
        preg_match_all('/:([a-z_]+)/', $consulta['sql'], $marcadores);
        $nombres = $marcadores[1]; sort($nombres); $claves = array_keys($consulta['parametros']); sort($claves);
        $assert($nombres === $claves && count($nombres) === count(array_unique($nombres)));
    }
    $assert($pdo->consultas[0]['parametros'] === ['placa' => "%!%!_!!' OR 1=1 --%", 'economico' => "%!%!_!!' OR 1=1 --%"]);
    $assert(!str_contains($pdo->consultas[1]['sql'], 'WHERE') && str_contains($pdo->consultas[3]['sql'], 'FOR UPDATE'));
    $assert(!isset($pdo->consultas[7]['parametros']['id_vehiculo']) && !isset($pdo->consultas[7]['parametros']['intruso']) && count($pdo->consultas[7]['parametros']) === 5);
    $assert(!str_contains($pdo->consultas[8]['sql'], 'estado =') && !isset($pdo->consultas[8]['parametros']['estado']));
    $assert(str_contains($pdo->consultas[9]['sql'], 'AND estado = :anterior'));
    $pdo->resultados = [[['id_vehiculo' => 11]]]; $assert($repo->existePlaca('PLACA-99'));
    $pdo->resultados = [[['id_vehiculo' => 11, 'estado' => 'ACTIVO']]]; $assert($repo->buscar(11)['estado'] === 'ACTIVO');
    $pdo->cambios = 0; $repo->actualizar(11, $datos);
    foreach ([fn () => $repo->cambiarEstado(11, 'INACTIVO', 'ACTIVO'), fn () => $repo->insertar($datos)] as $operacion) {
        try { $operacion(); throw new LogicException('Se esperaba conflicto.'); }
        catch (RuntimeException $error) { $assert(!($error instanceof LogicException)); }
    }
    echo "HU-02 vehículos: $checks comprobaciones correctas. Memoria/sesión/PDO simulados; sin conexión a MariaDB.\n";
} catch (Throwable $error) {
    $linea = preg_match('/^Comprobación fallida en línea [0-9]+\.$/D', $error->getMessage()) === 1 ? ' ' . $error->getMessage() : '';
    fwrite(STDERR, "Falló HU-02 en: $caso.$linea " . get_class($error) . ' (' . basename($error->getFile()) . ':' . $error->getLine() . "). Sin detalles privados.\n");
    exit(1);
}
