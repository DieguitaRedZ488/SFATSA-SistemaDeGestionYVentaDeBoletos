<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

final class PdoTipoServicioRepository extends PdoRepositorioHU04 implements TipoServicioRepository
{
    protected const TABLA = 'tipo_servicio';
    protected const ID = 'id_tipo_servicio';
    protected const COLUMNAS = 'id_tipo_servicio, nombre, controla_capacidad, activo';

    public function listar(string $busqueda): array { return $this->listarFilas($busqueda, ['nombre']); }
    public function buscar(int $idTipoServicio, bool $bloquear = false): ?array { return $this->buscarFila($idTipoServicio, $bloquear); }

    public function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        $sql = 'SELECT id_tipo_servicio FROM tipo_servicio WHERE nombre = :nombre';
        $params = ['nombre' => $nombre];
        if ($excepto !== null) { $sql .= ' AND id_tipo_servicio <> :excepto'; $params['excepto'] = $excepto; }
        return $this->consulta($sql . ' LIMIT 1', $params)->fetch() !== false;
    }

    public function insertar(array $datos): int
    {
        return $this->insertarId('INSERT INTO tipo_servicio (nombre, controla_capacidad, activo) VALUES (:nombre, 1, 1)', ['nombre' => $datos['nombre']]);
    }

    public function actualizarNombre(int $idTipoServicio, string $nombre): void
    {
        $this->mutar('UPDATE tipo_servicio SET nombre = :nombre WHERE id_tipo_servicio = :id', ['nombre' => $nombre, 'id' => $idTipoServicio]);
    }

    public function cambiarEstado(int $idTipoServicio, int $nuevo, int $anterior): void { $this->estadoFila($idTipoServicio, $nuevo, $anterior, 'activo'); }
}
