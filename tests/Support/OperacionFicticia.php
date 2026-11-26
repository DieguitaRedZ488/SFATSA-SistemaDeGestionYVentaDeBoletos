<?php
declare(strict_types=1);

use SFATSA\Repositories\{CatalogoOperacionRepository,TarifaRepository,ViajeRepository};

final class CatalogosOperacionFicticios implements CatalogoOperacionRepository
{
    public array $eventos=[];
    public array $rutas=[1=>['id_ruta'=>1,'id_tipo_servicio'=>1,'activa'=>1,'codigo'=>'R1','nombre'=>'Ruta de prueba','servicio'=>'DIRECTO']];
    public array $tipos=[1=>['id_tipo_servicio'=>1,'nombre'=>'DIRECTO','activo'=>1]];
    public array $paradas=[['id_ruta_parada'=>11,'id_ruta'=>1,'id_parada'=>1,'nombre'=>'Origen','activo'=>1,'orden'=>1],['id_ruta_parada'=>12,'id_ruta'=>1,'id_parada'=>2,'nombre'=>'Destino','activo'=>1,'orden'=>2]];
    public array $vehiculos=[1=>['id_vehiculo'=>1,'estado'=>'ACTIVO','capacidad'=>25,'numero_economico'=>'E1','placa'=>'A-00001-A'],2=>['id_vehiculo'=>2,'estado'=>'ACTIVO','capacidad'=>20,'numero_economico'=>'E2','placa'=>'A-00002-A']];
    public array $conductores=[1=>['id_conductor'=>1,'activo'=>1,'nombres'=>'Persona','apellidos'=>'Uno'],2=>['id_conductor'=>2,'activo'=>1,'nombres'=>'Persona','apellidos'=>'Dos']];
    public function opciones(): array {return ['rutas'=>array_values($this->rutas),'tipos'=>array_values($this->tipos),'puntos'=>$this->paradas,'vehiculos'=>array_values($this->vehiculos),'conductores'=>array_values($this->conductores)];}
    public function ruta(int $id,bool $bloquear=false):?array{$this->eventos[]='ruta';return $this->rutas[$id]??null;}
    public function tipo(int $id,bool $bloquear=false):?array{$this->eventos[]='tipo';return $this->tipos[$id]??null;}
    public function puntos(int $ruta,bool $bloquear=false):array{return array_values(array_filter($this->paradas,static fn($p)=>$p['id_ruta']===$ruta));}
    public function vehiculo(int $id,bool $bloquear=false):?array{$this->eventos[]='vehiculo';return $this->vehiculos[$id]??null;}
    public function conductor(int $id,bool $bloquear=false):?array{$this->eventos[]='conductor';return $this->conductores[$id]??null;}
}
final class TarifasOperacionFicticias implements TarifaRepository
{
    public array $filas=[],$boletos=[];
    public function listar(array $filtros):array{return array_map(static fn($f)=>$f+['ruta'=>'Ruta de prueba','servicio'=>'DIRECTO','origen'=>'Origen','destino'=>'Destino'],array_values($this->filas));}
    public function buscar(int $id,bool $bloquear=false):?array{return $this->filas[$id]??null;}
    public function utilizada(int $id):bool{return isset($this->boletos[$id]);}
    public function solapa(array $d,?int $excepto=null):bool{foreach($this->filas as$id=>$f){if($id===$excepto)continue;if($d['id_tipo_servicio']===$f['id_tipo_servicio']&&$d['id_origen_ruta_parada']===$f['id_origen_ruta_parada']&&$d['id_destino_ruta_parada']===$f['id_destino_ruta_parada']&&$f['vigente_desde']<=$d['vigente_hasta']&&($f['vigente_hasta']===null||$f['vigente_hasta']>=$d['vigente_desde']))return true;}return false;}
    public function insertar(array $datos):int{$id=count($this->filas)+1;$this->filas[$id]=$datos+['id_tarifa'=>$id,'activa'=>1];return $id;}
    public function actualizar(int $id,array $datos):void{$this->filas[$id]=array_replace($this->filas[$id],$datos);}
    public function vigentes(int $tipo,int $origen,int $destino,string $instante):array{return array_values(array_filter($this->filas,static fn($f)=>$f['activa']===1&&$f['id_tipo_servicio']===$tipo&&$f['id_origen_ruta_parada']===$origen&&$f['id_destino_ruta_parada']===$destino&&$f['vigente_desde']<=$instante&&($f['vigente_hasta']===null||$f['vigente_hasta']>=$instante)));}
}
final class ViajesOperacionFicticios implements ViajeRepository
{
    public array $filas=[];
    public function listar(array $filtros):array{return array_map(fn($f)=>$f+['ruta'=>'Ruta de prueba','servicio'=>'DIRECTO','numero_economico'=>'E1','placa'=>'A-00001-A','conductor'=>'Persona Uno','usuario_salida'=>'checadora','usuario_llegada'=>null],array_values($this->filas));}
    public function buscar(int $id,bool $bloquear=false):?array{foreach($this->listar([])as$f){if($f['id_viaje']===$id)return $f;}return null;}
    public function ocupado(string $recurso,int $id):bool{foreach($this->filas as$f){if($f[$recurso]===$id&&$f['estado']==='EN_CURSO')return true;}return false;}
    public function insertar(array $datos):int{$id=count($this->filas)+1;$this->filas[$id]=$datos+['id_viaje'=>$id,'estado'=>'EN_CURSO','llegada_real'=>null,'id_usuario_llegada'=>null];return $id;}
    public function finalizar(int $id,string $llegada,int $actor):bool{$this->filas[$id]=array_replace($this->filas[$id],['estado'=>'FINALIZADO','llegada_real'=>$llegada,'id_usuario_llegada'=>$actor]);return true;}
}
