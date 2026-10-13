<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface GestionUsuarioRepository
{
    /** Filas de presentación: nunca contienen hash_contrasena. */
    public function listar(string $busqueda): array;
    public function roles(): array;
    public function buscar(int $idUsuario, bool $bloquear = false): ?array;
    public function existeUsuario(string $usuario, ?int $excepto = null): bool;
    public function iniciarTransaccion(): void;
    /** Serializa las escrituras de este módulo bloqueando el rol ADMINISTRADOR. */
    public function bloquearAdministracion(): void;
    public function administradoresActivos(): int;
    public function insertar(array $datos, #[\SensitiveParameter] string $hash): void;
    public function actualizar(int $idUsuario, array $datos): void;
    public function desactivar(int $idUsuario): void;
    public function activar(int $idUsuario): void;
    public function confirmarTransaccion(): void;
    public function enTransaccion(): bool;
    public function revertirTransaccion(): void;
}
