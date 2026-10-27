<?php

declare(strict_types=1);

namespace SFATSA\Services;

use RuntimeException;

final class ErrorGestionVehiculos extends RuntimeException
{
    public function __construct(
        public readonly array $errores = [],
        string $mensaje = 'Revise los datos señalados e intente nuevamente.',
        public readonly int $estado = 422
    ) {
        parent::__construct($mensaje);
    }
}
