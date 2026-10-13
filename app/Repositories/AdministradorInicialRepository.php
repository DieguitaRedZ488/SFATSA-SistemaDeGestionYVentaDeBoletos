<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

/** Puerto exclusivo del alta inicial; no ofrece edición ni elección de rol. */
interface AdministradorInicialRepository
{
    public function obtenerIdRolAdministrador(): ?int;
    public function existeAdministrador(int $idRol): bool;
    public function existeUsuario(string $usuario): bool;
    public function adquirirBloqueo(): bool;
    public function liberarBloqueo(): void;
    public function iniciarTransaccion(): void;
    public function confirmarTransaccion(): void;
    public function revertirTransaccion(): void;
    public function enTransaccion(): bool;
    public function insertar(int $idRol, array $identidad, #[\SensitiveParameter] string $hash): void;
    public function cerrar(): void;
}
