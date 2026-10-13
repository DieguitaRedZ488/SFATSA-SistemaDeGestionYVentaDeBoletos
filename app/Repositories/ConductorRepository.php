<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface ConductorRepository
{
    public function listar(string $busqueda): array;
    public function buscar(int $idConductor, bool $bloquear = false): ?array;
    public function existeLicencia(string $licencia, ?int $excepto = null): bool;
    public function insertar(array $datos): void;
    public function actualizar(int $idConductor, array $datos): void;
    public function cambiarEstado(int $idConductor, int $nuevoEstado, int $estadoAnterior): void;
}
