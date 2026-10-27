<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

final class PdoRutaRepository extends PdoRepositorioHU04 implements RutaRepository
{
    protected const TABLA = 'ruta';
    protected const ID = 'id_ruta';
    protected const COLUMNAS = 'id_ruta, codigo, nombre, activa';

    private ?bool $conServicio = null;

    public function servicioDisponible(): bool
    {
        return $this->conServicio ??= (int) $this->consulta("SELECT COUNT(*) FROM information_schema.COLUMNS"
            . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ruta' AND COLUMN_NAME='id_tipo_servicio'")->fetchColumn() === 1;
    }
    private function seleccion(): string
    {
        return $this->servicioDisponible()
            ? 'SELECT r.id_ruta,r.codigo,r.nombre,r.activa,r.id_tipo_servicio,t.nombre AS tipo_servicio_nombre'
                . ' FROM ruta r LEFT JOIN tipo_servicio t ON t.id_tipo_servicio=r.id_tipo_servicio'
            : 'SELECT r.id_ruta,r.codigo,r.nombre,r.activa,NULL AS id_tipo_servicio,NULL AS tipo_servicio_nombre FROM ruta r';
    }
    public function listar(string $busqueda): array
    {
        $sql = $this->seleccion(); $params = [];
        if ($busqueda !== '') {
            $sql .= " WHERE (r.codigo LIKE :codigo ESCAPE '!' OR r.nombre LIKE :nombre ESCAPE '!')";
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $params = ['codigo' => $patron, 'nombre' => $patron];
        }
        return $this->consulta($sql . ' ORDER BY r.nombre,r.id_ruta', $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function buscar(int $idRuta, bool $bloquear = false): ?array
    {
        if ($bloquear) { $this->transaccion(); }
        $fila = $this->consulta($this->seleccion() . ' WHERE r.id_ruta=:id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : ''), ['id' => $idRuta])->fetch(\PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }
    public function existeCodigo(string $codigo, ?int $excepto = null): bool
    {
        $sql = 'SELECT id_ruta FROM ruta WHERE codigo=:codigo'; $params = ['codigo' => $codigo];
        if ($excepto !== null) { $sql .= ' AND id_ruta<>:excepto'; $params['excepto'] = $excepto; }
        return $this->consulta($sql . ' LIMIT 1', $params)->fetch() !== false;
    }
    public function dependenciasVerificadas(): bool
    {
        return $this->dependenciasEntrantesVerificadas('ruta', ['ruta_parada.id_ruta', 'viaje.id_ruta']);
    }
    public function tieneViajes(int $idRuta): bool
    {
        return $this->consulta('SELECT id_viaje FROM viaje WHERE id_ruta = :id LIMIT 1', ['id' => $idRuta])->fetch() !== false;
    }
    public function insertar(array $datos): int
    {
        return $this->insertarId('INSERT INTO ruta (codigo,nombre,id_tipo_servicio,activa) VALUES (:codigo,:nombre,:tipo,1)',
            ['codigo' => $datos['codigo'], 'nombre' => $datos['nombre'], 'tipo' => $datos['id_tipo_servicio']]);
    }
    public function actualizar(int $idRuta, array $datos): void
    {
        $this->mutar('UPDATE ruta SET codigo=:codigo,nombre=:nombre,id_tipo_servicio=:tipo WHERE id_ruta=:id',
            ['codigo' => $datos['codigo'], 'nombre' => $datos['nombre'], 'tipo' => $datos['id_tipo_servicio'], 'id' => $idRuta]);
    }
    public function cambiarEstado(int $idRuta, int $nuevo, int $anterior): void { $this->estadoFila($idRuta, $nuevo, $anterior, 'activa'); }
    public function eliminar(int $idRuta): void
    {
        if ($this->mutar('DELETE FROM ruta WHERE id_ruta = :id', ['id' => $idRuta])->rowCount() !== 1) {
            throw new \RuntimeException('No se pudo eliminar la ruta.');
        }
    }
}
