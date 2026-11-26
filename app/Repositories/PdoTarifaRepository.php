<?php
declare(strict_types=1);
namespace SFATSA\Repositories;

final class PdoTarifaRepository extends PdoRepositorioHU04 implements TarifaRepository
{
    public function listar(array $filtros): array
    {
        $sql = 'SELECT t.*,rp.id_ruta,r.nombre AS ruta,p.nombre AS origen,q.nombre AS destino,s.nombre AS servicio FROM tarifa t JOIN ruta_parada rp ON rp.id_ruta_parada=t.id_origen_ruta_parada JOIN ruta r ON r.id_ruta=rp.id_ruta JOIN parada p ON p.id_parada=rp.id_parada JOIN ruta_parada rd ON rd.id_ruta_parada=t.id_destino_ruta_parada JOIN parada q ON q.id_parada=rd.id_parada JOIN tipo_servicio s ON s.id_tipo_servicio=t.id_tipo_servicio WHERE 1=1';
        $params = [];
        foreach (['id_ruta' => 'rp.id_ruta', 'id_tipo_servicio' => 't.id_tipo_servicio'] as $campo => $col) {
            if (isset($filtros[$campo])) { $sql .= " AND $col=:$campo"; $params[$campo] = $filtros[$campo]; }
        }
        if (($filtros['q'] ?? '') !== '') {
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']) . '%';
            $sql .= " AND (p.nombre LIKE :q1 ESCAPE '!' OR q.nombre LIKE :q2 ESCAPE '!' OR r.nombre LIKE :q3 ESCAPE '!')";
            $params += ['q1' => $patron, 'q2' => $patron, 'q3' => $patron];
        }
        return $this->consulta($sql . ' ORDER BY t.vigente_desde DESC,t.id_tarifa DESC', $params)->fetchAll();
    }
    public function buscar(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) { $this->transaccion(); }
        $fila = $this->consulta('SELECT t.*,rp.id_ruta FROM tarifa t JOIN ruta_parada rp ON rp.id_ruta_parada=t.id_origen_ruta_parada WHERE t.id_tarifa=:id' . ($bloquear ? ' FOR UPDATE' : ''), ['id' => $id])->fetch();
        return $fila === false ? null : $fila;
    }
    public function utilizada(int $id): bool
    {
        $this->transaccion();
        return $this->consulta('SELECT folio_boleto FROM boleto WHERE id_tarifa=:id LIMIT 1 FOR UPDATE', ['id' => $id])->fetch() !== false;
    }
    public function solapa(array $datos, ?int $excepto = null): bool
    {
        $this->transaccion();
        return $this->consulta('SELECT id_tarifa FROM tarifa WHERE id_tipo_servicio=:tipo AND id_origen_ruta_parada=:origen AND id_destino_ruta_parada=:destino AND vigente_desde<=:hasta AND (vigente_hasta IS NULL OR vigente_hasta>=:desde) AND id_tarifa<>:excepto LIMIT 1 FOR UPDATE', [
            'tipo' => $datos['id_tipo_servicio'], 'origen' => $datos['id_origen_ruta_parada'], 'destino' => $datos['id_destino_ruta_parada'],
            'desde' => $datos['vigente_desde'], 'hasta' => $datos['vigente_hasta'], 'excepto' => $excepto ?? 0,
        ])->fetch() !== false;
    }
    public function insertar(array $datos): int
    {
        return $this->insertarId('INSERT INTO tarifa (id_tipo_servicio,id_origen_ruta_parada,id_destino_ruta_parada,importe,vigente_desde,vigente_hasta) VALUES (:id_tipo_servicio,:id_origen_ruta_parada,:id_destino_ruta_parada,:importe,:vigente_desde,:vigente_hasta)', $this->datos($datos));
    }
    private function datos(array $datos): array { return array_intersect_key($datos, array_flip(['id_tipo_servicio','id_origen_ruta_parada','id_destino_ruta_parada','importe','vigente_desde','vigente_hasta'])); }
    public function actualizar(int $id, array $datos): void
    {
        $this->mutar('UPDATE tarifa SET id_tipo_servicio=:id_tipo_servicio,id_origen_ruta_parada=:id_origen_ruta_parada,id_destino_ruta_parada=:id_destino_ruta_parada,importe=:importe,vigente_desde=:vigente_desde,vigente_hasta=:vigente_hasta WHERE id_tarifa=:id', $this->datos($datos) + ['id' => $id]);
    }
    public function vigentes(int $tipo, int $origen, int $destino, string $instante): array
    {
        return $this->consulta('SELECT * FROM tarifa WHERE activa=1 AND id_tipo_servicio=:tipo AND id_origen_ruta_parada=:origen AND id_destino_ruta_parada=:destino AND vigente_desde<=:ahora1 AND (vigente_hasta IS NULL OR vigente_hasta>=:ahora2) ORDER BY id_tarifa', ['tipo'=>$tipo,'origen'=>$origen,'destino'=>$destino,'ahora1'=>$instante,'ahora2'=>$instante])->fetchAll();
    }
}
