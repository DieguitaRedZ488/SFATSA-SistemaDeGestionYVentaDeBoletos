<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface UsuarioRepository
{
    /** Exclusivo del servicio de autenticación; puede contener el hash. */
    public function buscarPorUsuario(string $usuario): ?array;
    /** Estado/rol actuales, sin hash. */
    public function buscarPorId(int $idUsuario): ?array;
}
