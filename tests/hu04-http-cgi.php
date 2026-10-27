<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
require_once dirname(__DIR__) . '/app/bootstrap-web.php';

use SFATSA\Http\IntegracionHU04;

$checks = 0; $temporal = null; $fallo = false;
$assert = static function (bool $valor) use (&$checks): void {
    if (!$valor) { throw new RuntimeException('Comprobación CGI fallida en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); } $checks++;
};
try {
    $temporal = sys_get_temp_dir() . '/sfatsa-hu04-cgi-' . bin2hex(random_bytes(16));
    if (!mkdir($temporal, 0700)) { throw new RuntimeException('No se pudo crear temporal propio.'); }
    $cgi = dirname(PHP_BINARY) . (PHP_OS_FAMILY === 'Windows' ? '/php-cgi.exe' : '/php-cgi');
    $entry = dirname(__DIR__) . '/public/index.php';
    $rutas = require dirname(__DIR__) . '/routes/web.php';
    foreach ($rutas as $ruta => $_) {
        if (!IntegracionHU04::esRuta($ruta)) { continue; }
        foreach (['GET', 'POST'] as $metodo) {
            $query = http_build_query(['ruta' => $ruta]);
            $body = $metodo === 'POST' ? http_build_query(['nombre' => 'NO_DEBE_GUARDARSE', 'csrf' => str_repeat('a', 64)]) : '';
            $env = ['REQUEST_METHOD' => $metodo, 'QUERY_STRING' => $query, 'REDIRECT_STATUS' => '1',
                'SCRIPT_FILENAME' => $entry, 'SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/?' . $query,
                'SERVER_PROTOCOL' => 'HTTP/1.1', 'GATEWAY_INTERFACE' => 'CGI/1.1', 'SystemRoot' => 'C:\\Windows',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'CONTENT_LENGTH' => (string) strlen($body)];
            $pipes = [];
            // -n: sin php.ini ni extensión PDO_MYSQL. Una apertura accidental no podría pasar inadvertida como éxito.
            $proceso = proc_open([$cgi, '-n', '-d', 'session.save_path=' . $temporal, '-d', 'session.gc_probability=0'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $env, ['bypass_shell' => true]);
            if (!is_resource($proceso)) { throw new RuntimeException('CGI no disponible.'); }
            try {
                fwrite($pipes[0], $body); fclose($pipes[0]);
                $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]); $codigo = proc_close($proceso);
            } finally {
                if (is_resource($proceso)) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } proc_terminate($proceso); proc_close($proceso); }
            }
            $assert($codigo === 0 && $err === '');
            $assert(str_contains($out, 'Status: 303') && str_contains($out, 'Location: /'));
            $assert(str_contains($out, 'Cache-Control: no-store') && str_contains($out, 'X-Content-Type-Options: nosniff'));
            $assert(!str_contains($out, 'SQLSTATE') && !str_contains($out, 'NO_DEBE_GUARDARSE') && !str_contains($out, 'Stack trace'));
        }
    }
    echo 'HTTP HU-04 CGI: ' . $checks . " comprobaciones correctas; 30 solicitudes reales al front controller, redirección al login sin sesión ni PDO_MYSQL.\n";
} catch (Throwable $e) {
    $detalle = preg_match('/\AComprobación CGI fallida en línea [0-9]+\z/', $e->getMessage()) ? $e->getMessage() : 'Error en línea ' . $e->getLine();
    fwrite(STDERR, 'FALLO HTTP HU-04 CGI. ' . $detalle . ". Sin detalles privados.\n"); $fallo = true;
} finally {
    if ($temporal !== null && is_dir($temporal)) {
        foreach (scandir($temporal) as $archivo) {
            if ($archivo === '.' || $archivo === '..') { continue; }
            if (!preg_match('/\Asess_[a-zA-Z0-9,-]+\z/', $archivo) || is_link($temporal . '/' . $archivo) || !is_file($temporal . '/' . $archivo)) {
                $fallo = true; continue;
            }
            if (!unlink($temporal . '/' . $archivo)) { $fallo = true; }
        }
        if (!rmdir($temporal)) { $fallo = true; }
    }
}
exit($fallo ? 1 : 0);
