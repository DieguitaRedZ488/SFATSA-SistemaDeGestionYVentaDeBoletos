<?php

declare(strict_types=1);

use SFATSA\Console\CrearAdministradorInicialCommand;
use SFATSA\Console\Terminal;
use SFATSA\Repositories\AdministradorInicialRepository;
use SFATSA\Repositories\PdoAdministradorInicialRepository;
use SFATSA\Services\AdministradorInicialService;
use SFATSA\Services\ErrorAdministradorInicial;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$raiz = dirname(__DIR__);
foreach ([
    '/app/Services/ErrorAdministradorInicial.php',
    '/app/Repositories/AdministradorInicialRepository.php',
    '/app/Repositories/PdoAdministradorInicialRepository.php',
    '/app/Services/AdministradorInicialService.php',
    '/app/Console/Terminal.php',
    '/app/Console/TerminalWindows.php',
    '/app/Console/CrearAdministradorInicialCommand.php',
] as $archivo) {
    require_once $raiz . $archivo;
}

/** Almacén compartido enteramente en memoria, incluidas transacción y exclusión. */
final class DatosBootstrapFicticios
{
    public ?int $rol = 37;
    public ?object $propietario = null;
    public array $cuentas = [];
}

final class RepositorioBootstrapFicticio implements AdministradorInicialRepository
{
    public array $eventos = [];
    public ?string $fallar = null;
    public ?Closure $antesDeInsertar = null;
    private bool $transaccion = false;
    private ?array $pendiente = null;

    public function __construct(public readonly DatosBootstrapFicticios $datos)
    {
    }
    private function evento(string $evento): void
    {
        $this->eventos[] = $evento;
        if ($this->fallar === $evento) {
            throw new RuntimeException('FICTICIO_SENSIBLE');
        }
    }
    public function obtenerIdRolAdministrador(): ?int
    {
        $this->evento('rol');
        return $this->datos->rol;
    }
    public function existeAdministrador(int $idRol): bool
    {
        $this->evento('administrador');
        foreach ($this->datos->cuentas as $cuenta) {
            if ($cuenta['id_rol'] === $idRol) {
                return true;
            }
        }
        return false;
    }
    public function existeUsuario(string $usuario): bool
    {
        $this->evento('usuario');
        foreach ($this->datos->cuentas as $cuenta) {
            if ($cuenta['usuario'] === $usuario) {
                return true;
            }
        }
        return false;
    }
    public function adquirirBloqueo(): bool
    {
        $this->evento('bloquear');
        if ($this->datos->propietario !== null) {
            return false;
        }
        $this->datos->propietario = $this;
        return true;
    }
    public function liberarBloqueo(): void
    {
        $this->evento('liberar');
        $this->datos->propietario = null;
    }
    public function iniciarTransaccion(): void
    {
        $this->evento('iniciar');
        $this->transaccion = true;
    }
    public function confirmarTransaccion(): void
    {
        $this->evento('confirmar');
        if ($this->pendiente !== null) {
            $this->datos->cuentas[] = $this->pendiente;
        }
        $this->pendiente = null;
        $this->transaccion = false;
    }
    public function revertirTransaccion(): void
    {
        $this->evento('revertir');
        $this->pendiente = null;
        $this->transaccion = false;
    }
    public function enTransaccion(): bool
    {
        return $this->transaccion;
    }
    public function insertar(int $idRol, array $identidad, #[SensitiveParameter] string $hash): void
    {
        $this->evento('insertar');
        if ($this->antesDeInsertar !== null) {
            ($this->antesDeInsertar)();
        }
        if (!$this->transaccion || $this->datos->propietario !== $this) {
            throw new RuntimeException('Alta fuera de exclusión/transacción.');
        }
        $this->pendiente = $identidad + ['id_rol' => $idRol, 'hash_contrasena' => $hash, 'activo' => 1];
    }
    public function cerrar(): void
    {
        $this->eventos[] = 'cerrar';
        if ($this->datos->propietario === $this) {
            $this->datos->propietario = null;
        }
        $this->transaccion = false;
        $this->pendiente = null;
    }
}

final class TerminalBootstrapFicticia implements Terminal
{
    public bool $disponible = true;
    public int $lecturasSecretas = 0;
    public int $verificaciones = 0;
    public string $salida = '';
    public array $textos = ['Nombres ficticios', 'Apellidos ficticios', 'cuenta_ficticia'];
    public array $secretos = ['FICTICIO-seguro-123', 'FICTICIO-seguro-123'];
    public function verificarEntradaOculta(): void
    {
        $this->verificaciones++;
        if (!$this->disponible) {
            throw new ErrorAdministradorInicial('TERMINAL');
        }
    }
    public function leerTexto(string $pregunta): string
    {
        return array_shift($this->textos) ?? '';
    }
    public function leerSecreto(string $pregunta): string
    {
        $this->lecturasSecretas++;
        if ($this->secretos === []) {
            throw new RuntimeException('FICTICIO_SENSIBLE');
        }
        return array_shift($this->secretos);
    }
    public function escribir(string $mensaje, bool $error = false): void
    {
        $this->salida .= $mensaje;
    }
}

/** Dobles PDO sin constructor real ni conexiones: ejecutan el repositorio de producción. */
final class PdoBootstrapFicticio extends PDO
{
    public array $consultas = [];
    public array $eventos = [];
    public bool $transaccion = false;
    public function __construct(public array $respuestas)
    {
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->respuestas === []) {
            throw new RuntimeException('Consulta inesperada en doble PDO.');
        }
        $respuesta = array_shift($this->respuestas);
        if (!str_starts_with($query, $respuesta['sql'])) {
            throw new RuntimeException('Orden SQL inesperado.');
        }
        return new SentenciaBootstrapFicticia($this, $query, $respuesta);
    }
    public function inTransaction(): bool { return $this->transaccion; }
    public function beginTransaction(): bool { $this->eventos[] = 'iniciar'; return $this->transaccion = true; }
    public function commit(): bool { $this->eventos[] = 'confirmar'; $this->transaccion = false; return true; }
    public function rollBack(): bool { $this->eventos[] = 'revertir'; $this->transaccion = false; return true; }
}

final class SentenciaBootstrapFicticia extends PDOStatement
{
    public function __construct(private PdoBootstrapFicticio $pdo, private string $sql, private array $respuesta)
    {
    }
    public function execute(?array $params = null): bool
    {
        $this->pdo->consultas[] = ['sql' => $this->sql, 'parametros' => $params];
        if (isset($this->respuesta['error'])) {
            throw $this->respuesta['error'];
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->respuesta['fila'] ?? false;
    }
    public function fetchColumn(int $column = 0): mixed
    {
        return $this->respuesta['columna'] ?? false;
    }
    public function rowCount(): int { return $this->respuesta['filas'] ?? 1; }
}

$comprobaciones = 0;
$caso = 'inicio';
$assert = static function (bool $condicion) use (&$comprobaciones): void {
    if (!$condicion) {
        throw new RuntimeException('Comprobación fallida.');
    }
    $comprobaciones++;
};
$rechazar = static function (Closure $operacion) use ($assert): ErrorAdministradorInicial {
    try {
        $operacion();
    } catch (ErrorAdministradorInicial $error) {
        $assert(!str_contains($error->getMessage(), 'FICTICIO') && $error->getPrevious() === null);
        return $error;
    }
    throw new RuntimeException('Se aceptó una operación inválida.');
};
$identidad = ['nombres' => 'Nombres ficticios', 'apellidos' => 'Apellidos ficticios', 'usuario' => 'cuenta_ficticia'];
$contrasena = 'FICTICIO-seguro-123';
$nuevo = static function (): array {
    $datos = new DatosBootstrapFicticios();
    $repo = new RepositorioBootstrapFicticio($datos);
    return [$datos, $repo, new AdministradorInicialService($repo)];
};
$proceso = static function (array $comando, ?array $entorno = null): array {
    $pipes = [];
    $process = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $entorno, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo ejecutar el proceso de prueba.');
    }
    fclose($pipes[0]); // Entrada redirigida vacía: nunca es una terminal ni contiene secretos.
    $salida = stream_get_contents($pipes[1]);
    $errores = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $salida, $errores];
};

try {
    $caso = 'validación de identidad';
    [, , $service] = $nuevo();
    $assert($service->validarIdentidad($identidad) === $identidad);
    foreach (['nombres' => 120, 'apellidos' => 120, 'usuario' => 50] as $campo => $maximo) {
        foreach (['', '   ', "\t\n", "\u{00A0}\u{3000}", null, [], "\xFF", str_repeat('á', $maximo + 1)] as $invalido) {
            $datos = $identidad;
            $datos[$campo] = $invalido;
            $rechazar(static fn () => $service->validarIdentidad($datos));
        }
        $datos = $identidad;
        unset($datos[$campo]);
        $rechazar(static fn () => $service->validarIdentidad($datos));
        $datos = $identidad;
        $datos[$campo] = str_repeat('ñ', $maximo);
        $assert($service->validarIdentidad($datos) === $datos);
    }
    $datos = ['nombres' => '  José María  ', 'apellidos' => "O'Connor", 'usuario' => "ficticio' OR '1'='1"];
    $assert($service->validarIdentidad($datos) === $datos);
    $rechazar(static fn () => $service->validarIdentidad($identidad + ['id_rol' => 2]));

    $caso = 'contraseñas y límites de bytes';
    foreach (['', '            ', 'corta123456', str_repeat('a', 73), str_repeat('á', 37), "FICTICIO\0-seguro-123", "\xFFFICTICIO-seguro-123"] as $invalida) {
        [$datos, $repo, $service] = $nuevo();
        $rechazar(static fn () => $service->crear($identidad, $invalida, $invalida));
        $assert($datos->cuentas === [] && !in_array('bloquear', $repo->eventos, true));
    }
    [$datos, , $service] = $nuevo();
    $rechazar(static fn () => $service->crear($identidad, $contrasena, 'FICTICIO-no-coincide'));
    $assert($datos->cuentas === []);

    $caso = 'alta, hash, valores separados y estado';
    foreach ([str_repeat('á', 12), str_repeat('a', 72), str_repeat('ñ', 36), ' FICTICIO con espacios '] as $valida) {
        [$datos, $repo, $service] = $nuevo();
        $limites = ['nombres' => str_repeat('ñ', 120), 'apellidos' => str_repeat('á', 120), 'usuario' => str_repeat('u', 50)];
        $assert($service->crear($limites, $valida, $valida));
        $cuenta = $datos->cuentas[0];
        $assert($cuenta['nombres'] === $limites['nombres'] && $cuenta['apellidos'] === $limites['apellidos'] && $cuenta['usuario'] === $limites['usuario']);
        $assert($cuenta['id_rol'] === 37 && $cuenta['activo'] === 1);
        $assert($cuenta['hash_contrasena'] !== $valida && password_verify($valida, $cuenta['hash_contrasena']));
        $assert(!password_verify('FICTICIO-equivocada', $cuenta['hash_contrasena']));
        $assert($datos->propietario === null && !$repo->enTransaccion());
        $assert($repo->eventos === ['bloquear', 'iniciar', 'rol', 'administrador', 'usuario', 'insertar', 'confirmar', 'liberar', 'cerrar']);
    }

    $caso = 'repetición, administrador inactivo y rol ausente';
    foreach ([0, 1] as $activo) {
        [$datos, $repo, $service] = $nuevo();
        $datos->cuentas[] = ['id_rol' => 37, 'usuario' => 'otro_ficticio', 'activo' => $activo];
        $anteriores = $datos->cuentas;
        $assert(!$service->necesitaAlta());
        $assert(!$service->crear($identidad, $contrasena, $contrasena));
        $assert($datos->cuentas === $anteriores && $datos->propietario === null);
    }
    [$datos, $repo, $service] = $nuevo();
    $datos->rol = null;
    $rechazar(static fn () => $service->necesitaAlta());
    $rechazar(static fn () => $service->crear($identidad, $contrasena, $contrasena));
    $assert($datos->cuentas === [] && in_array('revertir', $repo->eventos, true) && in_array('liberar', $repo->eventos, true));
    [$datos, $repo, $service] = $nuevo();
    $datos->cuentas[] = ['id_rol' => 9, 'usuario' => 'cuenta_ficticia', 'activo' => 1];
    $rechazar(static fn () => $service->crear($identidad, $contrasena, $contrasena));
    $assert(count($datos->cuentas) === 1 && !in_array('insertar', $repo->eventos, true));

    $caso = 'errores y liberación de recursos';
    foreach (['bloquear', 'iniciar', 'rol', 'administrador', 'usuario', 'insertar', 'confirmar'] as $fallo) {
        [$datos, $repo, $service] = $nuevo();
        $repo->fallar = $fallo;
        $rechazar(static fn () => $service->crear($identidad, $contrasena, $contrasena));
        $assert($datos->cuentas === [] && $datos->propietario === null && !$repo->enTransaccion());
        $assert(in_array('cerrar', $repo->eventos, true));
        if ($fallo !== 'bloquear') {
            $assert(in_array('liberar', $repo->eventos, true));
        }
    }
    foreach (['revertir', 'liberar'] as $fallo) {
        [$datos, $repo, $service] = $nuevo();
        $datos->cuentas[] = ['id_rol' => 37, 'usuario' => 'existente', 'activo' => 1];
        $repo->fallar = $fallo;
        $rechazar(static fn () => $service->crear($identidad, $contrasena, $contrasena));
        $assert($datos->propietario === null && !$repo->enTransaccion());
        $assert(in_array('liberar', $repo->eventos, true) && in_array('cerrar', $repo->eventos, true));
    }

    $caso = 'intercalación concurrente y repetición';
    [$datos, $primero, $service] = $nuevo();
    $segundo = new RepositorioBootstrapFicticio($datos);
    $competidor = new AdministradorInicialService($segundo);
    $primero->antesDeInsertar = static function () use ($rechazar, $competidor, $identidad, $contrasena): void {
        $rechazar(static fn () => $competidor->crear($identidad, $contrasena, $contrasena));
    };
    $assert($service->crear($identidad, $contrasena, $contrasena));
    $assert(count($datos->cuentas) === 1 && !in_array('iniciar', $segundo->eventos, true));
    $repetidor = new AdministradorInicialService(new RepositorioBootstrapFicticio($datos));
    $assert(!$repetidor->crear($identidad, $contrasena, $contrasena));
    $assert(count($datos->cuentas) === 1 && $datos->propietario === null);

    $caso = 'repositorio PDO real sobre doble sin conexión';
    $pdo = new PdoBootstrapFicticio([
        ['sql' => 'SELECT GET_LOCK', 'columna' => 1],
        ['sql' => 'SELECT id_rol', 'fila' => ['id_rol' => '37', 'nombre' => 'ADMINISTRADOR']],
        ['sql' => 'SELECT 1 FROM usuario WHERE id_rol', 'columna' => false],
        ['sql' => 'SELECT 1 FROM usuario WHERE usuario', 'columna' => false],
        ['sql' => 'INSERT INTO usuario'],
        ['sql' => 'SELECT RELEASE_LOCK', 'columna' => 1],
    ]);
    $service = new AdministradorInicialService(new PdoAdministradorInicialRepository($pdo));
    $inyeccionFicticia = $identidad;
    $inyeccionFicticia['usuario'] = "ficticio' OR '1'='1";
    $assert($service->crear($inyeccionFicticia, $contrasena, $contrasena));
    $assert($pdo->respuestas === [] && $pdo->eventos === ['iniciar', 'confirmar']);
    $assert($pdo->consultas[0]['parametros']['nombre'] === $pdo->consultas[5]['parametros']['nombre']);
    $assert(str_ends_with($pdo->consultas[1]['sql'], ' FOR UPDATE') && $pdo->consultas[1]['parametros']['nombre'] === 'ADMINISTRADOR');
    $insert = $pdo->consultas[4];
    $assert(!str_contains($insert['sql'], $inyeccionFicticia['usuario']) && !str_contains($insert['sql'], $contrasena));
    $assert($insert['parametros']['usuario'] === $inyeccionFicticia['usuario'] && $insert['parametros']['id_rol'] === 37);
    $assert(password_verify($contrasena, $insert['parametros']['hash_contrasena']) && str_contains($insert['sql'], 'hash_contrasena, activo)'));
    $assert(!str_contains($pdo->consultas[2]['sql'], 'activo'));
    foreach ([null, ['id_rol' => 37, 'nombre' => 'CAJERO']] as $fila) {
        $repo = new PdoAdministradorInicialRepository(new PdoBootstrapFicticio([['sql' => 'SELECT id_rol', 'fila' => $fila]]));
        $assert($repo->obtenerIdRolAdministrador() === null);
        $repo->cerrar();
    }
    $errorSql = new PDOException('FICTICIO_SENSIBLE');
    $errorSql->errorInfo = ['23000', 1062, 'FICTICIO_SENSIBLE'];
    $repo = new PdoAdministradorInicialRepository(new PdoBootstrapFicticio([['sql' => 'INSERT INTO usuario', 'error' => $errorSql]]));
    $error = $rechazar(static fn () => $repo->insertar(37, $identidad, 'HASH_FICTICIO'));
    $assert(str_contains($error->getMessage(), 'ya existe'));
    $repo->cerrar();

    $caso = 'comando, secretos, terminal y fábrica diferida';
    foreach (['http', 'argumentos', 'terminal', 'existente', 'rol', 'confirmacion', 'error', 'valido'] as $escenario) {
        [$datos, $repo, $service] = $nuevo();
        $terminal = new TerminalBootstrapFicticia();
        $fabricaciones = 0;
        if ($escenario === 'terminal') { $terminal->disponible = false; }
        if ($escenario === 'existente') { $datos->cuentas[] = ['id_rol' => 37, 'usuario' => 'existente', 'activo' => 0]; }
        if ($escenario === 'rol') { $datos->rol = null; }
        if ($escenario === 'confirmacion') { $terminal->secretos = [$contrasena]; }
        if ($escenario === 'error') { $repo->fallar = 'rol'; }
        $command = new CrearAdministradorInicialCommand($terminal, static function () use (&$fabricaciones, $service): AdministradorInicialService {
            $fabricaciones++;
            return $service;
        });
        $estado = $command->ejecutar($escenario === 'http' ? 'cgi-fcgi' : 'cli', $escenario === 'argumentos' ? ['comando', 'FICTICIO_SENSIBLE'] : ['comando']);
        $assert($estado === (in_array($escenario, ['existente', 'valido'], true) ? 0 : 1));
        $assert(!str_contains($terminal->salida, 'FICTICIO') && !str_contains($terminal->salida, '$2y$'));
        if (in_array($escenario, ['http', 'argumentos', 'terminal'], true)) {
            $assert($fabricaciones === 0 && $datos->cuentas === []);
        }
        if (in_array($escenario, ['existente', 'rol', 'error'], true)) {
            $assert($terminal->lecturasSecretas === 0);
        }
        if ($escenario === 'valido') {
            $assert(count($datos->cuentas) === 1 && str_contains($terminal->salida, 'creado correctamente'));
        }
        if ($fabricaciones > 0) {
            $assert(in_array('cerrar', $repo->eventos, true));
        }
    }

    $caso = 'entrada CLI real rechaza redirección antes de abrir BD';
    $entry = $raiz . '/scripts/crear-administrador-inicial.php';
    [$codigo, $salida, $errores] = $proceso([PHP_BINARY, $entry]);
    $assert($codigo === 1 && $salida === '' && str_contains($errores, 'entrada oculta'));
    [$codigo, $salida, $errores] = $proceso([PHP_BINARY, $entry, 'FICTICIO_SENSIBLE']);
    $assert($codigo === 1 && str_contains($errores, 'no acepta argumentos') && !str_contains($errores, 'FICTICIO'));

    $caso = 'rechazo HTTP real mediante SAPI CGI sin servidor';
    $cgi = dirname(PHP_BINARY) . (PHP_OS_FAMILY === 'Windows' ? '/php-cgi.exe' : '/php-cgi');
    if (!is_file($cgi)) {
        throw new RuntimeException('SAPI CGI no disponible para comprobar rechazo HTTP.');
    }
    [$codigo, $salida, $errores] = $proceso([$cgi, '-n'], [
        'REQUEST_METHOD' => 'GET',
        'REDIRECT_STATUS' => '1',
        'SCRIPT_FILENAME' => $entry,
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'SystemRoot' => 'C:\\Windows',
    ]);
    $assert($codigo === 0 && str_contains($salida, 'Status: 404') && $errores === '');
    $assert(!str_contains($salida, 'Nombres:') && !str_contains($salida, 'creado') && !str_contains($salida, 'FICTICIO'));

    echo "Comprobaciones de administrador inicial correctas: $comprobaciones. Dobles en memoria; sin conexión a MariaDB ni alta real.\n";
} catch (Throwable) {
    fwrite(STDERR, "Falló la suite de administrador inicial en: $caso. Sin detalles privados.\n");
    exit(1);
}
