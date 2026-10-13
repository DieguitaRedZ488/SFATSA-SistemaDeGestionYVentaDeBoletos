<?php

declare(strict_types=1);

namespace SFATSA\Models;

/** Datos aptos para la presentación: nunca incluye hash o contraseña. */
final class UsuarioAutenticado
{
    public function __construct(
        public readonly int $idUsuario,
        public readonly string $nombres,
        public readonly string $apellidos,
        public readonly string $usuario,
        public readonly string $rol
    ) {
    }

    public function nombreCompleto(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }
}
