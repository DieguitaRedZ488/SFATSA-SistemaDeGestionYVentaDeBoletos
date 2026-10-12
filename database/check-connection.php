<?php

declare(strict_types=1);

// Protección previa a cualquier lectura de configuración o acceso a MariaDB.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('zend.exception_ignore_args', '1');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    require_once dirname(__DIR__) . '/config/Environment.php';
    require_once dirname(__DIR__) . '/app/Repositories/ConnectionFactory.php';
    $validateConfiguration = require dirname(__DIR__) . '/config/database.php';
    $environment = new SFATSA\Config\Environment(dirname(__DIR__) . '/.env');
    $configuration = $validateConfiguration($environment);
    if ($configuration['database'] !== 'sfatza') {
        throw new RuntimeException('La comprobación requiere la base acordada.');
    }
    $connection = (new SFATSA\Repositories\ConnectionFactory($configuration))->create();
    $statement = $connection->query('SELECT 1');
    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException('Resultado inesperado.');
    }
    fwrite(STDOUT, "Conexión PDO comprobada correctamente.\n");
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "No se pudo comprobar la conexión PDO. Revise la configuración local.\n");
    exit(1);
}
