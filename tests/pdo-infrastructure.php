<?php

declare(strict_types=1);

use SFATSA\Config\Environment;

require_once dirname(__DIR__) . '/config/Environment.php';
$validate = require dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/app/Repositories/ConnectionFactory.php';

// Exclusivamente datos ficticios y validación; no se invoca ConnectionFactory::create().
$checks = 0;
$assert = static function (bool $condition) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('Falló una comprobación local.');
    }
    $checks++;
};
$reject = static function (callable $operation) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException $error) {
        $assert(!str_contains($error->getMessage(), 'FICTICIO'));
        return;
    }
    throw new RuntimeException('Se aceptó configuración inválida.');
};

$values = Environment::parse("# comentario\r\nDB_PASSWORD=  FICTICIO#=\$HOME\\ruta  \r\nEMPTY=\r\n");
$assert($values['DB_PASSWORD'] === '  FICTICIO#=$HOME\\ruta  ');
$assert($values['EMPTY'] === '');
$assert(Environment::parse('VALUE="  FICTICIO#=${HOME}  "')['VALUE'] === '  FICTICIO#=${HOME}  ');
$assert(Environment::parse("VALUE='FICTICIO\\n'")['VALUE'] === 'FICTICIO\\n');
foreach (["VALUE=1\nVALUE=2", 'export VALUE=FICTICIO', 'value=FICTICIO',
    'VALUE="FICTICIO', 'VALUE="FICTICIO" # final', "VALUE=FICTICIO\0", "\xEF\xBB\xBFVALUE=FICTICIO",
    "VALUE=\xFF"] as $invalid) {
    $reject(static fn () => Environment::parse($invalid));
}

$keys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_CHARSET', 'DB_USERNAME', 'DB_PASSWORD', 'SFATSA_TEST_VALUE'];
$previous = [];
$temporaryFile = tempnam(sys_get_temp_dir(), 'sfatsa-test-');
try {
    foreach ($keys as $key) {
        $previous[$key] = getenv($key);
        putenv($key);
    }
    file_put_contents($temporaryFile, "SFATSA_TEST_VALUE=archivo\nDB_USERNAME=usuario_ficticio\nDB_PASSWORD='  FICTICIO#=  '\n");
    $environment = new Environment($temporaryFile);
    $assert($environment->get('SFATSA_TEST_VALUE') === 'archivo');
    putenv('SFATSA_TEST_VALUE=proceso');
    $assert($environment->get('SFATSA_TEST_VALUE') === 'proceso');
    putenv('SFATSA_TEST_VALUE=');
    $assert($environment->get('SFATSA_TEST_VALUE') === '');
    $configuration = $validate($environment);
    $assert($configuration['host'] === '127.0.0.1' && $configuration['port'] === 3306);
    $assert($configuration['database'] === 'sfatza' && $configuration['charset'] === 'utf8mb4');
    $assert($configuration['password'] === '  FICTICIO#=  ');
    foreach (['DB_HOST' => ['localhost', '127.0.0.1;FICTICIO'], 'DB_PORT' => ['0', '65536', '3306;FICTICIO'],
        'DB_DATABASE' => ['', 'sfatza;FICTICIO'], 'DB_CHARSET' => ['latin1', 'utf8mb4;FICTICIO'],
        'DB_USERNAME' => ['', 'root', 'ROOT'], 'DB_PASSWORD' => ['']] as $key => $invalidValues) {
        foreach ($invalidValues as $invalidValue) {
            putenv($key . '=' . $invalidValue);
            $reject(static fn () => $validate($environment));
        }
        putenv($key);
    }
    $reject(static fn () => $validate(new Environment()));
    // Construir la fábrica no conecta y no requiere un servidor disponible.
    $assert(new SFATSA\Repositories\ConnectionFactory($configuration) instanceof SFATSA\Repositories\ConnectionFactory);
} finally {
    foreach ($previous as $key => $value) {
        putenv($value === false ? $key : $key . '=' . $value);
    }
    if ($temporaryFile !== false) {
        unlink($temporaryFile);
    }
}
echo "Comprobaciones locales correctas: $checks. Sin conexión a MariaDB.\n";
