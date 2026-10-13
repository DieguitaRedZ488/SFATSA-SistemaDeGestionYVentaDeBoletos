<?php

declare(strict_types=1);

namespace SFATSA\Http;

use InvalidArgumentException;
use Throwable;

final class Vista
{
    public function renderizar(string $plantilla, array $datos = []): string
    {
        if (!in_array($plantilla, ['login', 'inicio', 'mensaje', 'usuarios/listado', 'usuarios/formulario', 'usuarios/desactivar', 'usuarios/activar', 'conductores/listado', 'conductores/formulario', 'conductores/estado'], true)) {
            throw new InvalidArgumentException('Vista no disponible.');
        }
        $escapar = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        extract($datos, EXTR_SKIP);
        ob_start();
        try {
            require dirname(__DIR__) . '/Views/' . $plantilla . '.php';
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
