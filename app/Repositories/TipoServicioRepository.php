<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface TipoServicioRepository
{
    public function listar(string $busqueda): array;
    public function buscar(int $idTipoServicio, bool $bloquear = false): ?array;
    public function existeNombre(string $nombre, ?int $excepto = null): bool;
    public function insertar(array $datos): int;
    public function actualizarNombre(int $idTipoServicio, string $nombre): void;
    public function cambiarEstado(int $idTipoServicio, int $nuevo, int $anterior): void;
}
