<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

final class PdoParadaRepository extends PdoRepositorioHU04 implements ParadaRepository
{
    protected const TABLA = 'parada';
    protected const ID = 'id_parada';
    protected const COLUMNAS = 'id_parada, nombre, referencia, activo';

    public function listar(string $busqueda): array { return $this->listarFilas($busqueda, ['nombre', 'referencia']); }
    public function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        $normalizar = "UPPER(TRIM(REGEXP_REPLACE(%s,'[[:space:]]+',' ')))";
        $sql = 'SELECT id_parada FROM parada WHERE ' . sprintf($normalizar, 'nombre') . '=' . sprintf($normalizar, ':nombre');
        $params = ['nombre' => $nombre];
        if ($excepto !== null) { $sql .= ' AND id_parada<>:excepto'; $params['excepto'] = $excepto; }
        return $this->consulta($sql . ' LIMIT 1', $params)->fetch() !== false;
    }
    public function buscar(int $idParada, bool $bloquear = false): ?array { return $this->buscarFila($idParada, $bloquear); }

    public function bloquearPorIds(array $ids): array
    {
        $this->transaccion();
        sort($ids, SORT_NUMERIC);
        $filas = [];
        foreach ($ids as $id) {
            $fila = $this->buscar($id, true);
            if ($fila !== null) { $filas[$id] = $fila; }
        }
        return $filas;
    }

    public function insertar(array $datos): int
    {
        return $this->insertarId('INSERT INTO parada (nombre, referencia, activo) VALUES (:nombre, :referencia, 1)',
            ['nombre' => $datos['nombre'], 'referencia' => $datos['referencia']]);
    }

    public function actualizar(int $idParada, array $datos): void
    {
        $this->mutar('UPDATE parada SET nombre = :nombre, referencia = :referencia WHERE id_parada = :id',
            ['nombre' => $datos['nombre'], 'referencia' => $datos['referencia'], 'id' => $idParada]);
    }

    public function cambiarEstado(int $idParada, int $nuevo, int $anterior): void { $this->estadoFila($idParada, $nuevo, $anterior, 'activo'); }

    public function dependenciasVerificadas(): bool
    {
        return $this->dependenciasEntrantesVerificadas('parada', ['ruta_parada.id_parada']);
    }

    public function tieneRutas(int $idParada): bool
    {
        return $this->consulta(
            'SELECT id_ruta_parada FROM ruta_parada WHERE id_parada = :id LIMIT 1',
            ['id' => $idParada]
        )->fetch() !== false;
    }

    public function eliminar(int $idParada): void
    {
        if ($this->mutar('DELETE FROM parada WHERE id_parada = :id', ['id' => $idParada])->rowCount() !== 1) {
            throw new \RuntimeException('No se pudo eliminar la parada.');
        }
    }
}
