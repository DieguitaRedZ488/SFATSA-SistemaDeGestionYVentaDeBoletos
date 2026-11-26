<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

/** Lecturas operativas acotadas; no concede acceso a gestión administrativa. */
final class PdoCatalogoOperacionRepository extends PdoRepositorioHU04 implements CatalogoOperacionRepository
{
    private function fila(string $tabla, string $columna, int $id, bool $bloquear): ?array
    {
        if ($bloquear) { $this->transaccion(); }
        $fila = $this->consulta("SELECT * FROM $tabla WHERE $columna=:id" . ($bloquear ? ' FOR UPDATE' : ''), ['id' => $id])->fetch();
        return $fila === false ? null : $fila;
    }
    public function ruta(int $id, bool $bloquear = false): ?array { return $this->fila('ruta', 'id_ruta', $id, $bloquear); }
    public function tipo(int $id, bool $bloquear = false): ?array { return $this->fila('tipo_servicio', 'id_tipo_servicio', $id, $bloquear); }
    public function vehiculo(int $id, bool $bloquear = false): ?array { return $this->fila('vehiculo', 'id_vehiculo', $id, $bloquear); }
    public function conductor(int $id, bool $bloquear = false): ?array { return $this->fila('conductor', 'id_conductor', $id, $bloquear); }
    public function puntos(int $ruta, bool $bloquear = false): array
    {
        if ($bloquear) { $this->transaccion(); }
        return $this->consulta('SELECT rp.*,p.nombre,p.activo FROM ruta_parada rp JOIN parada p USING(id_parada) WHERE rp.id_ruta=:ruta ORDER BY rp.orden,rp.id_ruta_parada' . ($bloquear ? ' FOR UPDATE' : ''), ['ruta' => $ruta])->fetchAll();
    }
    public function opciones(): array
    {
        return [
            'rutas' => $this->consulta('SELECT r.*,t.nombre AS servicio FROM ruta r JOIN tipo_servicio t USING(id_tipo_servicio) WHERE r.activa=1 AND t.activo=1 ORDER BY r.nombre,r.id_ruta')->fetchAll(),
            'tipos' => $this->consulta('SELECT id_tipo_servicio,nombre FROM tipo_servicio WHERE activo=1 ORDER BY nombre')->fetchAll(),
            'puntos' => $this->consulta('SELECT rp.*,p.nombre,p.activo FROM ruta_parada rp JOIN parada p USING(id_parada) ORDER BY rp.id_ruta,rp.orden')->fetchAll(),
            'vehiculos' => $this->consulta("SELECT id_vehiculo,numero_economico,placa,capacidad FROM vehiculo v WHERE estado='ACTIVO' AND NOT EXISTS(SELECT 1 FROM viaje j WHERE j.id_vehiculo=v.id_vehiculo AND j.estado='EN_CURSO') ORDER BY numero_economico")->fetchAll(),
            'conductores' => $this->consulta("SELECT id_conductor,nombres,apellidos FROM conductor c WHERE activo=1 AND NOT EXISTS(SELECT 1 FROM viaje j WHERE j.id_conductor=c.id_conductor AND j.estado='EN_CURSO') ORDER BY apellidos,nombres")->fetchAll(),
        ];
    }
}
