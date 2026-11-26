<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

interface CatalogoOperacionRepository
{
    public function opciones(): array;
    public function ruta(int $id, bool $bloquear = false): ?array;
    public function puntos(int $ruta, bool $bloquear = false): array;
    public function tipo(int $id, bool $bloquear = false): ?array;
    public function vehiculo(int $id, bool $bloquear = false): ?array;
    public function conductor(int $id, bool $bloquear = false): ?array;
}
