<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface RutaRepository
{
    public function listar(string $busqueda): array;
    public function buscar(int $idRuta, bool $bloquear = false): ?array;
    public function existeCodigo(string $codigo, ?int $excepto = null): bool;
    public function servicioDisponible(): bool;
    public function actualizar(int $idRuta, array $datos): void;
    public function dependenciasVerificadas(): bool;
    public function tieneViajes(int $idRuta): bool;
    public function insertar(array $datos): int;
    public function cambiarEstado(int $idRuta, int $nuevo, int $anterior): void;
    public function eliminar(int $idRuta): void;
}
