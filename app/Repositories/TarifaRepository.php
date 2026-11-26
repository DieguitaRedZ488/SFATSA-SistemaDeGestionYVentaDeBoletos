<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

interface TarifaRepository
{
    public function listar(array $filtros): array;
    public function buscar(int $id, bool $bloquear = false): ?array;
    public function utilizada(int $id): bool;
    public function solapa(array $datos, ?int $excepto = null): bool;
    public function insertar(array $datos): int;
    public function actualizar(int $id, array $datos): void;
    public function vigentes(int $tipo, int $origen, int $destino, string $instante): array;
}
