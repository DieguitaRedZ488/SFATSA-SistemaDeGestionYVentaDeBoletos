<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use RuntimeException;

final class PdoRutaParadaRepository extends PdoRepositorioHU04 implements RutaParadaRepository
{
    public function insertarPuntos(int $idRuta, array $idsParadas): void
    {
        $this->transaccion();
        if (!array_is_list($idsParadas) || count($idsParadas) < 2 || count($idsParadas) > 5
            || count(array_unique($idsParadas, SORT_REGULAR)) !== count($idsParadas)) {
            throw new RuntimeException('Recorrido no disponible.');
        }
        $st = $this->connection->prepare('INSERT INTO ruta_parada (id_ruta, id_parada, orden, distancia_acumulada_km)'
            . ' VALUES (:ruta, :parada, :orden, NULL)');
        foreach ($idsParadas as $i => $id) {
            if (!is_int($id) || $id <= 0) { throw new RuntimeException('Parada no disponible.'); }
            $st->execute(['ruta' => $idRuta, 'parada' => $id, 'orden' => $i + 1]);
            if ($st->rowCount() !== 1) { throw new RuntimeException('No se pudo guardar el recorrido.'); }
        }
    }

    public function listarPorRutaOrdenado(int $idRuta): array
    {
        return $this->consulta('SELECT rp.id_ruta_parada, rp.id_ruta, rp.id_parada, rp.orden, rp.distancia_acumulada_km,'
            . ' p.nombre, p.referencia, p.activo FROM ruta_parada rp INNER JOIN parada p ON p.id_parada = rp.id_parada'
            . ' WHERE rp.id_ruta = :ruta ORDER BY rp.orden, rp.id_ruta_parada', ['ruta' => $idRuta])->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dependenciasVerificadas(): bool
    {
        return $this->dependenciasEntrantesVerificadas(
            'ruta_parada',
            ['tarifa.id_destino_ruta_parada', 'tarifa.id_origen_ruta_parada']
        );
    }

    public function bloquearPorRuta(int $idRuta): array
    {
        $this->transaccion();
        return $this->consulta(
            'SELECT rp.id_ruta_parada, rp.id_ruta, rp.id_parada, rp.orden, rp.distancia_acumulada_km,'
            . ' p.nombre, p.referencia, p.activo FROM ruta_parada rp INNER JOIN parada p ON p.id_parada = rp.id_parada'
            . ' WHERE rp.id_ruta = :ruta ORDER BY rp.orden, rp.id_ruta_parada FOR UPDATE',
            ['ruta' => $idRuta]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tieneTarifasPorRuta(int $idRuta): bool
    {
        return $this->consulta(
            'SELECT t.id_tarifa FROM tarifa t INNER JOIN ruta_parada rp'
            . ' ON rp.id_ruta_parada = t.id_origen_ruta_parada OR rp.id_ruta_parada = t.id_destino_ruta_parada'
            . ' WHERE rp.id_ruta = :ruta LIMIT 1',
            ['ruta' => $idRuta]
        )->fetch() !== false;
    }

    public function eliminarPorRuta(int $idRuta): void
    {
        $this->mutar('DELETE FROM ruta_parada WHERE id_ruta = :ruta', ['ruta' => $idRuta]);
    }
}
