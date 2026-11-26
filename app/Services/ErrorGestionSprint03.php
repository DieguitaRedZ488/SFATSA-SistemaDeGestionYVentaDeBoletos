<?php
declare(strict_types=1);
namespace SFATSA\Services;

final class ErrorGestionSprint03 extends \RuntimeException
{
    public function __construct(public readonly array $errores = [], string $mensaje = 'Revise los datos señalados.', public readonly int $estado = 422)
    {
        parent::__construct($mensaje);
    }
}
