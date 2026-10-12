<?php

declare(strict_types=1);

use SFATSA\Config\Environment;

require_once __DIR__ . '/Environment.php';

// Devuelve un validador sin abrir conexiones ni leer secretos al incluirlo.
return static function (#[SensitiveParameter] Environment $environment): array {
    $host = $environment->get('DB_HOST', '127.0.0.1');
    $port = $environment->get('DB_PORT', '3306');
    $database = $environment->get('DB_DATABASE', 'sfatza');
    $charset = $environment->get('DB_CHARSET', 'utf8mb4');
    $username = $environment->get('DB_USERNAME');
    $password = $environment->get('DB_PASSWORD');

    // Alcance local aprobado: TCP IPv4 loopback, sin opciones DSN adicionales.
    if ($host !== '127.0.0.1') {
        throw new RuntimeException('DB_HOST inválido; se requiere acceso local.');
    }
    if ($port === null || !preg_match('/^[1-9][0-9]{0,4}$/D', $port) || (int) $port > 65535) {
        throw new RuntimeException('DB_PORT inválido.');
    }
    if ($database === null || !preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $database)) {
        throw new RuntimeException('DB_DATABASE inválido.');
    }
    if ($charset !== 'utf8mb4') {
        throw new RuntimeException('DB_CHARSET inválido.');
    }
    if ($username === null || !preg_match('/^[a-zA-Z0-9_]{1,80}$/D', $username)
        || strcasecmp($username, 'root') === 0) {
        throw new RuntimeException('DB_USERNAME inválido; utilizar una cuenta de aplicación.');
    }
    if ($password === null || $password === '' || str_contains($password, "\0")) {
        throw new RuntimeException('DB_PASSWORD ausente o inválido.');
    }

    return compact('host', 'database', 'charset', 'username', 'password') + ['port' => (int) $port];
};
