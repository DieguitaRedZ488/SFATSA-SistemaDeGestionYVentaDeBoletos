<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface ParadaRepository
{
    public function listar(string $busqueda): array;
    public function existeNombre(string $nombre, ?int $excepto = null): bool;
    public function buscar(int $idParada, bool $bloquear = false): ?array;
    /** Devuelve filas por ID; bloquea en orden ascendente, nunca cambia el orden del recorrido. */
    public function bloquearPorIds(array $ids): array;
    public function insertar(array $datos): int;
    public function actualizar(int $idParada, array $datos): void;
    public function cambiarEstado(int $idParada, int $nuevo, int $anterior): void;
    public function dependenciasVerificadas(): bool;
    public function tieneRutas(int $idParada): bool;
    public function eliminar(int $idParada): void;
}
