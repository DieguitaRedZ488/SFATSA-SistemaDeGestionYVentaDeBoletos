<?php
declare(strict_types=1);
namespace SFATSA\Controllers;

use SFATSA\Http\Respuesta;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{ViajeService,ErrorGestionSprint03};

final class ViajeController extends OperacionController
{
    protected const MODULO='viajes';
    private function pendientes(): array
    {
        $p=$this->store->obtener('viajes_operaciones');return is_array($p)?array_filter($p,static fn($v)=>is_array($v)&&is_int($v['creado']??null)&&$v['creado']<=time()&&time()-$v['creado']<900):[];
    }
    private function tokenOperacion(int $actor): string
    {
        $p=$this->pendientes();while(count($p)>=10){array_shift($p);}$token=bin2hex(random_bytes(32));$p[$token]=['actor'=>$actor,'creado'=>time()];$this->store->guardar('viajes_operaciones',$p);return $token;
    }
    private function consumir(int $actor,mixed $token): void
    {
        $p=$this->pendientes();if(!is_string($token)||!isset($p[$token])||$p[$token]['actor']!==$actor){throw new ErrorGestionSprint03([], 'El formulario caducó o ya fue utilizado. Consulte los viajes antes de repetir.',409);}unset($p[$token]);$this->store->guardar('viajes_operaciones',$p);
    }
    private function formulario(UsuarioAutenticado $u,array $datos=[],?ErrorGestionSprint03 $error=null): Respuesta
    {
        if($error!==null&&!in_array($error->estado,[409,422],true)){return $this->mensaje($error->getMessage(),$error->estado);}
        return $this->vista('viajes/salida',$this->contexto($u)+['opciones'=>($this->crearServicio)()->opciones(),'valores'=>$this->valores($datos,ViajeService::CAMPOS),'operacion'=>$this->tokenOperacion($u->idUsuario),'error'=>$error?->getMessage()??'','errores'=>$error?->errores??[]],$error?->estado??200);
    }
    public function listar(array $consulta=[]): Respuesta {return $this->ejecutar(fn($u)=>$this->vista('viajes/listado',$this->contexto($u)+($this->crearServicio)()->listar($consulta)+['exito'=>$this->flash()]));}
    public function nuevo(array $consulta=[]): Respuesta {return $this->ejecutar(fn($u)=>$this->formulario($u));}
    public function salir(array $datos,array $consulta=[]): Respuesta
    {
        return $this->ejecutar(function($u)use($datos){try{$entrada=$this->entrada($datos,ViajeService::CAMPOS,['operacion','confirmacion']);if(($datos['confirmacion']??null)!=='1'){throw new ErrorGestionSprint03(['confirmacion'=>'Confirme el inicio del viaje.']);}$this->consumir($u->idUsuario,$datos['operacion']??null);$id=($this->crearServicio)()->salir($u->idUsuario,$entrada);}catch(ErrorGestionSprint03 $e){return $this->formulario($u,$datos,$e);}return $this->exito('Salida registrada. El viaje '.$id.' está en curso.');});
    }
    public function detalle(array $consulta=[]): Respuesta {return $this->ejecutar(fn($u)=>$this->vista('viajes/detalle',$this->contexto($u)+['viaje'=>($this->crearServicio)()->obtener($consulta['id']??null)]));}
    public function confirmarLlegada(array $consulta=[]): Respuesta
    {
        return $this->ejecutar(function($u)use($consulta){$v=($this->crearServicio)()->obtener($consulta['id']??null);if($v['estado']!=='EN_CURSO'){throw new ErrorGestionSprint03([], 'El viaje no está en curso.',409);}return $this->vista('viajes/llegada',$this->contexto($u)+['viaje'=>$v]);});
    }
    public function llegar(array $datos,array $consulta=[]): Respuesta
    {
        return $this->ejecutar(function($u)use($datos,$consulta){$this->entrada($datos,[],['confirmacion']);if(($datos['confirmacion']??null)!=='1'){throw new ErrorGestionSprint03([], 'Confirme la llegada.');}($this->crearServicio)()->llegar($u->idUsuario,$consulta['id']??null);return $this->exito('Llegada registrada. El viaje fue finalizado.');});
    }
}
