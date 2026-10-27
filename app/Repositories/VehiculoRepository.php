<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

/** Comparte PDO con OperacionAdministrativaService durante cada escritura. */
interface VehiculoRepository
{
    public function listar(string $busqueda): array;
    public function buscar(int $idVehiculo, bool $bloquear = false): ?array;
    public function existePlaca(string $placa, ?int $excepto = null): bool;
    public function existeNumeroEconomico(string $numeroEconomico, ?int $excepto = null): bool;
    public function insertar(array $datos): void;
    /** Cambia datos identificativos y capacidad; conserva el estado. */
    public function actualizar(int $idVehiculo, array $datos): void;
    public function cambiarEstado(int $idVehiculo, string $nuevoEstado, string $estadoAnterior): void;
    public function dependenciasVerificadas(): bool;
    public function tieneViajes(int $idVehiculo): bool;
    public function tieneMantenimientos(int $idVehiculo): bool;
    public function eliminar(int $idVehiculo): void;
}
