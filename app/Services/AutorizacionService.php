<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Models\UsuarioAutenticado;

final class AutorizacionService
{
    public function puedeControlarViajes(UsuarioAutenticado $usuario): bool
    {
        return $usuario->rol === 'CHECADORA';
    }

    public function puedeGestionar(UsuarioAutenticado $usuario): bool
    {
        return $usuario->rol === 'ADMINISTRADOR';
    }
}
