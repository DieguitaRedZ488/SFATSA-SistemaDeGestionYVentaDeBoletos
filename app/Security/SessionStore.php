<?php

declare(strict_types=1);

namespace SFATSA\Security;

interface SessionStore
{
    public function obtener(string $clave): mixed;
    public function guardar(string $clave, mixed $valor): void;
    public function quitar(string $clave): void;
    public function limpiar(): void;
    public function regenerar(): void;
    public function destruir(): void;
}
