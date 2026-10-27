<?php

declare(strict_types=1);

namespace SFATSA\Services;

use RuntimeException;

class ErrorGestionHU04 extends RuntimeException
{
    public function __construct(
        public readonly array $errores = [],
        string $mensaje = 'Revise los datos señalados.',
        public readonly int $estado = 422
    ) {
        parent::__construct($mensaje);
    }
}
