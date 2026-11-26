<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

final class PdoViajeRepository extends PdoRepositorioHU04 implements ViajeRepository
{
    private const SELECT = 'SELECT v.*,r.nombre AS ruta,t.nombre AS servicio,a.numero_economico,a.placa,CONCAT(c.nombres,\' \',c.apellidos) AS conductor,u.usuario AS usuario_salida,l.usuario AS usuario_llegada FROM viaje v JOIN ruta r USING(id_ruta) JOIN tipo_servicio t ON t.id_tipo_servicio=v.id_tipo_servicio JOIN vehiculo a USING(id_vehiculo) LEFT JOIN conductor c USING(id_conductor) JOIN usuario u ON u.id_usuario=v.id_usuario_salida LEFT JOIN usuario l ON l.id_usuario=v.id_usuario_llegada';
    public function listar(array $filtros): array
    {
        $sql = self::SELECT . ' WHERE 1=1'; $params = [];
        if (isset($filtros['estado'])) { $sql .= ' AND v.estado=:estado'; $params['estado'] = $filtros['estado']; }
        if (isset($filtros['fecha'])) { $sql .= ' AND v.salida_real BETWEEN :desde AND :hasta'; $params += ['desde'=>$filtros['fecha'].' 00:00:00','hasta'=>$filtros['fecha'].' 23:59:59']; }
        return $this->consulta($sql . " ORDER BY (v.estado='EN_CURSO') DESC,v.salida_real DESC,v.id_viaje DESC", $params)->fetchAll();
    }
    public function buscar(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) { $this->transaccion(); }
        // Bloqueo limitado a viaje; presentación y joins solo para lectura.
        $sql = $bloquear ? 'SELECT * FROM viaje v' : self::SELECT;
        $fila = $this->consulta($sql . ' WHERE v.id_viaje=:id' . ($bloquear ? ' FOR UPDATE' : ''), ['id'=>$id])->fetch();
        return $fila === false ? null : $fila;
    }
    public function ocupado(string $recurso, int $id): bool
    {
        $this->transaccion();
        if (!in_array($recurso, ['id_vehiculo','id_conductor'], true)) { throw new \InvalidArgumentException('Recurso no permitido.'); }
        return $this->consulta("SELECT id_viaje FROM viaje WHERE $recurso=:id AND estado='EN_CURSO' LIMIT 1 FOR UPDATE", ['id'=>$id])->fetch() !== false;
    }
    public function insertar(array $datos): int
    {
        return $this->insertarId("INSERT INTO viaje (id_ruta,id_tipo_servicio,id_vehiculo,id_conductor,salida_real,id_usuario_salida,capacidad_al_salir,estado) VALUES (:id_ruta,:id_tipo_servicio,:id_vehiculo,:id_conductor,:salida_real,:id_usuario_salida,:capacidad_al_salir,'EN_CURSO')", $datos);
    }
    public function finalizar(int $id, string $llegada, int $actor): bool
    {
        return $this->mutar("UPDATE viaje SET llegada_real=:llegada,id_usuario_llegada=:actor,estado='FINALIZADO' WHERE id_viaje=:id AND estado='EN_CURSO' AND llegada_real IS NULL AND id_usuario_llegada IS NULL", ['id'=>$id,'llegada'=>$llegada,'actor'=>$actor])->rowCount() === 1;
    }
}
