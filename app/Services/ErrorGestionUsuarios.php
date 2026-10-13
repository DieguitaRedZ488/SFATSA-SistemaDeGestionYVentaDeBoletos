<?php

declare(strict_types=1);

namespace SFATSA\Services;

use RuntimeException;

final class ErrorGestionUsuarios extends RuntimeException
{
    public function __construct(
        public readonly array $errores = [],
        string $mensaje = 'Revise los datos señalados e intente nuevamente.',
        public readonly int $estado = 422
    ) {
        // Solo mensajes definidos en la aplicación; no encadenar excepciones PDO.
        parent::__construct($mensaje);
    }
}
