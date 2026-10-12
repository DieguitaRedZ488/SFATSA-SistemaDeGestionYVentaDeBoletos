<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use Throwable;
use RuntimeException;

final class ConnectionFactory
{
    /** Recibe exclusivamente la configuración validada por config/database.php. */
    public function __construct(#[\SensitiveParameter] private readonly array $configuration)
    {
    }

    public function create(): PDO
    {
        try {
            $configuration = $this->configuration;
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $configuration['host'],
                $configuration['port'],
                $configuration['database'],
                $configuration['charset']
            );
            return new PDO($dsn, $configuration['username'], $configuration['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]);
        } catch (Throwable) {
            // No encadenar la excepción original: puede contener DSN o credenciales.
            throw new RuntimeException('No se pudo establecer la conexión a la base de datos.');
        }
    }
}
