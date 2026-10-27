<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

interface RutaParadaRepository
{
    /** Solo para una ruta nueva; el orden se calcula a partir de la lista de IDs. */
    public function insertarPuntos(int $idRuta, array $idsParadas): void;
    public function listarPorRutaOrdenado(int $idRuta): array;
    public function dependenciasVerificadas(): bool;
    /** Bloquea y devuelve el recorrido actual ordenado bajo la misma transacción. */
    public function bloquearPorRuta(int $idRuta): array;
    public function tieneTarifasPorRuta(int $idRuta): bool;
    public function eliminarPorRuta(int $idRuta): void;
}
