<?php

declare(strict_types=1);

use SFATSA\Controllers\AutenticacionController;
use SFATSA\Http\Respuesta;
use SFATSA\Http\Vista;
use SFATSA\Repositories\PdoUsuarioRepository;
use SFATSA\Repositories\UsuarioRepository;
use SFATSA\Security\SessionStore;
use SFATSA\Services\AutenticacionService;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\SesionService;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';

final class UsuariosLoginFicticios implements UsuarioRepository
{
    public int $lecturas = 0;
    public bool $fallar = false;
    public function __construct(public array $fila) {}
    public function buscarPorUsuario(string $usuario): ?array
    {
        $this->lecturas++;
        if ($this->fallar) { throw new RuntimeException('FICTICIO_SENSIBLE'); }
        return $usuario === ($this->fila['usuario'] ?? null) ? $this->fila : null;
    }
    public function buscarPorId(int $idUsuario): ?array
    {
        $this->lecturas++;
        if ($this->fallar) { throw new RuntimeException('FICTICIO_SENSIBLE'); }
        $fila = $idUsuario === ($this->fila['id_usuario'] ?? null) ? $this->fila : null;
        if ($fila !== null) { unset($fila['hash_contrasena']); }
        return $fila;
    }
}

final class SesionLoginFicticia implements SessionStore
{
    public array $datos = [];
    public int $regeneraciones = 0;
    public bool $destruida = false;
    public function obtener(string $clave): mixed { return $this->datos[$clave] ?? null; }
    public function guardar(string $clave, mixed $valor): void { $this->datos[$clave] = $valor; }
    public function quitar(string $clave): void { unset($this->datos[$clave]); }
    public function limpiar(): void { $this->datos = []; }
    public function regenerar(): void { $this->regeneraciones++; }
    public function destruir(): void { $this->datos = []; $this->destruida = true; }
}

final class PdoLoginFicticio extends PDO
{
    public array $consultas = [];
    public function __construct(private array $fila) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new SentenciaLoginFicticia($this, $query, $this->fila);
    }
}
final class SentenciaLoginFicticia extends PDOStatement
{
    public function __construct(private PdoLoginFicticio $pdo, private string $sql, private array $fila) {}
    public function execute(?array $params = null): bool
    {
        $this->pdo->consultas[] = ['sql' => $this->sql, 'parametros' => $params];
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->fila; }
}

$checks = 0;
$caso = 'inicio';
$directorio = null;
$assert = static function (bool $valor) use (&$checks): void {
    if (!$valor) { throw new RuntimeException('Comprobación fallida.'); }
    $checks++;
};
$contrasena = 'FICTICIO-seguro-123';
$fila = [
    'id_usuario' => 41, 'nombres' => 'Nombres ficticios', 'apellidos' => 'Apellidos ficticios',
    'usuario' => 'cuenta_ficticia', 'activo' => 1, 'rol' => 'ADMINISTRADOR',
    'hash_contrasena' => password_hash($contrasena, PASSWORD_BCRYPT),
];
$crear = static function (array $cuenta, int &$ahora): array {
    $repo = new UsuariosLoginFicticios($cuenta);
    $store = new SesionLoginFicticia();
    $sesion = new SesionService($store, static function () use (&$ahora): int { return $ahora; });
    $csrf = new CsrfService($store);
    $auth = new AutenticacionService($repo);
    $controller = new AutenticacionController($sesion, $csrf, static fn (): AutenticacionService => $auth, new Vista(), new AutorizacionService());
    return [$repo, $store, $sesion, $csrf, $auth, $controller];
};

try {
    $caso = 'credenciales válidas, inválidas e inactivas';
    foreach (['ADMINISTRADOR', 'CAJERO', 'CHECADORA'] as $rol) {
        $cuenta = $fila; $cuenta['rol'] = $rol; $ahora = 10000;
        [$repo, , , , $auth] = $crear($cuenta, $ahora);
        $usuario = $auth->verificar('cuenta_ficticia', $contrasena);
        $assert($usuario !== null && $usuario->rol === $rol && $usuario->nombreCompleto() === 'Nombres ficticios Apellidos ficticios');
        $assert(!property_exists($usuario, 'hash_contrasena'));
        $assert($auth->verificar('cuenta_ficticia', 'FICTICIO-incorrecta') === null);
        $assert($auth->verificar('inexistente_ficticio', $contrasena) === null);
        $repo->fila['activo'] = 0;
        $assert($auth->verificar('cuenta_ficticia', $contrasena) === null);
        $repo->fila['activo'] = 1; $repo->fila['rol'] = 'ROL_NO_APROBADO';
        $assert($auth->verificar('cuenta_ficticia', $contrasena) === null);
    }
    $ahora = 10000;
    [$repo, , , , $auth] = $crear($fila, $ahora);
    foreach ([[[], $contrasena], [' ', $contrasena], [str_repeat('u', 51), $contrasena], ['cuenta_ficticia', []], ['cuenta_ficticia', "FICTICIO\0-no-valido"], ['cuenta_ficticia', str_repeat('a', 73)]] as [$nombre, $password]) {
        $previas = $repo->lecturas;
        $assert($auth->verificar($nombre, $password) === null && $repo->lecturas === $previas);
    }

    $caso = 'rutas protegidas y CSRF';
    [$repo, $store, $sesion, $csrf, $auth, $controller] = $crear($fila, $ahora);
    $assert($controller->inicio()->cabeceras['Location'] === '/' && $repo->lecturas === 0);
    $assert($controller->moduloPendiente()->cabeceras['Location'] === '/');
    $login = $controller->mostrarLogin();
    $assert($login->estado === 200 && str_contains($login->cuerpo, 'autocomplete="current-password"'));
    $assert(!str_contains($login->cuerpo, $contrasena) && !str_contains($login->cuerpo, $fila['hash_contrasena']));
    $token = $csrf->token();
    foreach ([null, [], str_repeat('0', 64)] as $invalido) {
        $assert(!$csrf->validar($invalido));
        $assert($controller->iniciarSesion(['csrf' => $invalido, 'usuario' => 'cuenta_ficticia', 'contrasena' => $contrasena])->estado === 403);
        $assert($repo->lecturas === 0 && $sesion->idUsuario() === null);
    }
    $rechazoA = $controller->iniciarSesion(['csrf' => $token, 'usuario' => 'inexistente_ficticio', 'contrasena' => $contrasena]);
    $rechazoB = $controller->iniciarSesion(['csrf' => $token, 'usuario' => 'cuenta_ficticia', 'contrasena' => 'FICTICIO-incorrecta']);
    $mensaje = 'Usuario o contraseña incorrectos. Verifique sus datos e intente nuevamente.';
    $assert(str_contains($rechazoA->cuerpo, $mensaje) && str_contains($rechazoB->cuerpo, $mensaje));

    $caso = 'login, regeneración, rol vigente y módulos reservados';
    $respuesta = $controller->iniciarSesion([
        'csrf' => $token, 'usuario' => 'cuenta_ficticia', 'contrasena' => $contrasena,
        'rol' => 'CAJERO', 'id_rol' => 999, 'destino' => 'https://invalido.example',
    ]);
    $assert($respuesta->estado === 303 && $respuesta->cabeceras['Location'] === '/?ruta=inicio');
    $assert($store->regeneraciones === 1 && $sesion->idUsuario() === 41 && !$csrf->validar($token));
    $assert(!str_contains(serialize($store->datos), $contrasena) && !str_contains(serialize($store->datos), $fila['hash_contrasena']));
    $assert($controller->inicio()->estado === 200 && str_contains($controller->inicio()->cuerpo, 'ADMINISTRADOR'));
    $assert($controller->mostrarLogin()->cabeceras['Location'] === '/?ruta=inicio');
    $assert($controller->moduloPendiente()->estado === 501);
    foreach (['CAJERO', 'CHECADORA'] as $rol) {
        $repo->fila['rol'] = $rol;
        $tokenAnterior = $csrf->token();
        $vista = $controller->inicio();
        $assert($vista->estado === 200 && str_contains($vista->cuerpo, $rol));
        $assert(!$csrf->validar($tokenAnterior));
        $assert($controller->moduloPendiente()->estado === 403);
    }
    $repo->fila['activo'] = 0;
    $assert($controller->inicio()->cabeceras['Location'] === '/' && $sesion->idUsuario() === null);

    $caso = 'expiración por inactividad y cierre';
    $ahora = 10000;
    [$repo, $store, $sesion, $csrf, $auth, $controller] = $crear($fila, $ahora);
    $sesion->iniciar($auth->verificar('cuenta_ficticia', $contrasena));
    $ahora += 1799;
    $assert($sesion->idUsuario() === 41);
    $sesion->registrarActividad('ADMINISTRADOR');
    $ahora += 1799;
    $assert($sesion->idUsuario() === 41);
    $ahora++;
    $assert($controller->inicio()->cabeceras['Location'] === '/' && $sesion->idUsuario() === null);
    $sesion->iniciar($auth->verificar('cuenta_ficticia', $contrasena));
    $assert($controller->cerrarSesion(['csrf' => str_repeat('0', 64)])->estado === 403 && !$store->destruida);
    $assert($controller->cerrarSesion(['csrf' => $csrf->token()])->cabeceras['Location'] === '/' && $store->destruida);
    $assert($sesion->idUsuario() === null);

    $caso = 'escapado XSS y SQL preparado';
    $ahora = 10000;
    $peligrosa = $fila;
    $peligrosa['nombres'] = '<img src=x onerror=alert(1)>';
    $peligrosa['apellidos'] = 'Ficticios & prueba';
    [$repo, , $sesion, $csrf, $auth, $controller] = $crear($peligrosa, $ahora);
    $sesion->iniciar($auth->verificar('cuenta_ficticia', $contrasena));
    $html = $controller->inicio()->cuerpo;
    $assert(!str_contains($html, '<img src=x') && str_contains($html, '&lt;img src=x') && str_contains($html, 'Ficticios &amp; prueba'));
    $error = $controller->iniciarSesion(['csrf' => $csrf->token(), 'usuario' => '"><script>alert(1)</script>', 'contrasena' => $contrasena])->cuerpo;
    $assert(!str_contains($error, '"><script>') && str_contains($error, '&quot;&gt;&lt;script&gt;'));
    $pdo = new PdoLoginFicticio($fila);
    $repository = new PdoUsuarioRepository($pdo);
    $repository->buscarPorUsuario("ficticio' OR '1'='1");
    $repository->buscarPorId(41);
    $assert($pdo->consultas[0]['parametros'] === ['usuario' => "ficticio' OR '1'='1"]);
    $assert(!str_contains($pdo->consultas[0]['sql'], "OR '1'"));
    $assert(str_contains($pdo->consultas[0]['sql'], 'u.hash_contrasena') && str_contains($pdo->consultas[0]['sql'], 'r.nombre AS rol'));
    $assert($pdo->consultas[1]['parametros'] === ['id_usuario' => 41] && !str_contains($pdo->consultas[1]['sql'], 'hash_contrasena'));
    $routes = require dirname(__DIR__) . '/routes/web.php';
    $assert(array_keys($routes['login']) === ['GET', 'POST'] && array_keys($routes['logout']) === ['POST']);
    try { Respuesta::redirigir('https://invalido.example'); throw new RuntimeException('Destino aceptado.'); }
    catch (InvalidArgumentException) { $assert(true); }

    $caso = 'cookies y CSRF nativos bajo CGI sin BD';
    $directorio = sys_get_temp_dir() . '/sfatsa-login-prueba-' . bin2hex(random_bytes(8));
    if (!mkdir($directorio, 0700)) { throw new RuntimeException('No se pudo preparar el temporal propio.'); }
    $cgi = dirname(PHP_BINARY) . (PHP_OS_FAMILY === 'Windows' ? '/php-cgi.exe' : '/php-cgi');
    $entry = dirname(__DIR__) . '/public/index.php';
    $request = static function (string $metodo, string $query, string $cookie = '', string $body = '', bool $https = false) use ($cgi, $entry, $directorio): array {
        $env = [
            'REQUEST_METHOD' => $metodo, 'QUERY_STRING' => $query, 'REDIRECT_STATUS' => '1',
            'SCRIPT_FILENAME' => $entry, 'SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/?' . $query,
            'SERVER_PROTOCOL' => 'HTTP/1.1', 'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SystemRoot' => 'C:\\Windows', 'HTTP_COOKIE' => $cookie,
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'CONTENT_LENGTH' => (string) strlen($body),
        ];
        if ($https) { $env['HTTPS'] = 'on'; }
        $pipes = [];
        $process = proc_open([$cgi, '-n', '-d', 'session.save_path=' . $directorio, '-d', 'session.gc_probability=0'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, null, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) { throw new RuntimeException('Proceso CGI no disponible.'); }
        fwrite($pipes[0], $body); fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    };
    [$codigo, $html, $errores] = $request('GET', 'ruta=login', 'SFATSA_SESSION=' . str_repeat('a', 26));
    $assert($codigo === 0 && $errores === '' && str_contains($html, '<h2 id="login-title">Bienvenido</h2>') && !str_contains($html, 'Status: 503'));
    preg_match('/^Set-Cookie: SFATSA_SESSION=([^;]+);[^\r\n]*/mi', $html, $cookie);
    $assert(isset($cookie[1]) && $cookie[1] !== str_repeat('a', 26));
    $assert(str_contains($cookie[0], 'HttpOnly') && str_contains($cookie[0], 'SameSite=Lax') && !str_contains(strtolower($cookie[0]), '; secure'));
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $token);
    $assert(isset($token[1]));
    [$codigo, $out, $err] = $request('POST', 'ruta=login', 'SFATSA_SESSION=' . $cookie[1], 'csrf=invalido');
    $assert($codigo === 0 && $err === '' && str_contains($out, 'Status: 403') && !str_contains($out, 'Status: 503'));
    [$codigo, $out, $err] = $request('GET', 'ruta=inicio');
    $assert($codigo === 0 && str_contains($out, 'Status: 303') && str_contains($out, 'Location: /'));
    [$codigo, $out, $err] = $request('GET', 'ruta=logout', 'SFATSA_SESSION=' . $cookie[1]);
    $assert($codigo === 0 && str_contains($out, 'Status: 405') && str_contains($out, 'Allow: POST'));
    [$codigo, $out, $err] = $request('POST', 'ruta=logout', 'SFATSA_SESSION=' . $cookie[1], 'csrf=' . $token[1]);
    $assert($codigo === 0 && str_contains($out, 'Status: 303') && str_contains($out, 'Location: /') && str_contains($out, 'expires='));
    [$codigo, $out, $err] = $request('GET', 'ruta=login', '', '', true);
    $assert($codigo === 0 && preg_match('/^Set-Cookie:.*; secure; HttpOnly; SameSite=Lax/mi', $out) === 1);
    [$codigo, $out, $err] = $request('GET', 'ruta=no-existe');
    $assert($codigo === 0 && str_contains($out, 'Status: 404'));

    echo "Comprobaciones de login MVC correctas: $checks. Dobles en memoria y sesiones CGI propias; sin conexión a MariaDB ni cambios en registros.\n";
} catch (Throwable) {
    fwrite(STDERR, "Falló la suite de login MVC en: $caso. Sin detalles privados.\n");
    $fallo = true;
} finally {
    if ($directorio !== null && is_dir($directorio)) {
        // Solo archivos de sesión del directorio exclusivo creado por esta suite.
        foreach (new DirectoryIterator($directorio) as $archivo) {
            if ($archivo->isFile() && preg_match('/^sess_[A-Za-z0-9,-]+$/D', $archivo->getFilename())) {
                unlink($archivo->getPathname());
            }
        }
        rmdir($directorio);
    }
}
if (isset($fallo)) { exit(1); }
