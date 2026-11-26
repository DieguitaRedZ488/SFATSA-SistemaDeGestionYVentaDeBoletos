<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

interface ViajeRepository
{
    public function listar(array $filtros): array;
    public function buscar(int $id, bool $bloquear = false): ?array;
    public function ocupado(string $recurso, int $id): bool;
    public function insertar(array $datos): int;
    public function finalizar(int $id, string $llegada, int $actor): bool;
}
