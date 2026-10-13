<?php

declare(strict_types=1);

// El rechazo HTTP sucede antes de cargar clases, configuración o secretos.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $raizProyecto = dirname(__DIR__);
    require_once $raizProyecto . '/app/Services/ErrorAdministradorInicial.php';
    require_once $raizProyecto . '/app/Repositories/AdministradorInicialRepository.php';
    require_once $raizProyecto . '/app/Repositories/PdoAdministradorInicialRepository.php';
    require_once $raizProyecto . '/app/Services/AdministradorInicialService.php';
    require_once $raizProyecto . '/app/Console/Terminal.php';
    require_once $raizProyecto . '/app/Console/TerminalWindows.php';
    require_once $raizProyecto . '/app/Console/CrearAdministradorInicialCommand.php';
    set_error_handler(static function (): never {
        throw new SFATSA\Services\ErrorAdministradorInicial();
    });

    $command = new SFATSA\Console\CrearAdministradorInicialCommand(
        new SFATSA\Console\TerminalWindows(),
        static function () use ($raizProyecto): SFATSA\Services\AdministradorInicialService {
            require_once $raizProyecto . '/config/Environment.php';
            require_once $raizProyecto . '/app/Repositories/ConnectionFactory.php';
            $validarConfiguracion = require $raizProyecto . '/config/database.php';
            $configuracion = $validarConfiguracion(new SFATSA\Config\Environment($raizProyecto . '/.env'));
            if ($configuracion['database'] !== 'sfatza') {
                throw new SFATSA\Services\ErrorAdministradorInicial();
            }
            return new SFATSA\Services\AdministradorInicialService(
                new SFATSA\Repositories\PdoAdministradorInicialRepository(
                    (new SFATSA\Repositories\ConnectionFactory($configuracion))->create()
                )
            );
        }
    );
    exit($command->ejecutar(PHP_SAPI, $argv));
} catch (Throwable) {
    fwrite(STDERR, "No se pudo iniciar el alta inicial. No se muestran detalles privados.\n");
    exit(1);
}
