<?php
declare(strict_types=1);
namespace SFATSA\Controllers;

use SFATSA\Http\Respuesta;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{TarifaService,ErrorGestionSprint03,ServicioOperacion};

final class TarifaController extends OperacionController
{
    protected const MODULO='tarifas';
    private function formulario(UsuarioAutenticado $u,array $datos=[],?int $id=null,mixed $version='',?ErrorGestionSprint03 $error=null): Respuesta
    {
        if($error!==null&&!in_array($error->estado,[409,422],true)){return $this->mensaje($error->getMessage(),$error->estado);}
        return $this->vista('tarifas/formulario',$this->contexto($u)+['opciones'=>($this->crearServicio)()->opciones(),'valores'=>$this->valores($datos,TarifaService::CAMPOS),'id'=>$id,'version'=>is_string($version)?$version:'','error'=>$error?->getMessage()??'','errores'=>$error?->errores??[]],$error?->estado??200);
    }
    public function listar(array $consulta=[]): Respuesta {return $this->ejecutar(fn($u)=>$this->vista('tarifas/listado',$this->contexto($u)+($this->crearServicio)()->listar($consulta)+['opciones'=>($this->crearServicio)()->opciones(),'exito'=>$this->flash()]));}
    public function nuevo(array $consulta=[]): Respuesta {return $this->ejecutar(fn($u)=>$this->formulario($u));}
    public function editar(array $consulta=[]): Respuesta {return $this->ejecutar(function($u)use($consulta){$f=($this->crearServicio)()->obtener($consulta['id']??null);return $this->formulario($u,$f,(int)$f['id_tarifa'],$f['version']);});}
    public function registrar(array $datos,array $consulta=[]): Respuesta
    {
        return $this->ejecutar(function($u)use($datos){try{$e=$this->entrada($datos,TarifaService::CAMPOS);($this->crearServicio)()->registrar($u->idUsuario,$e);}catch(ErrorGestionSprint03 $e){return $this->formulario($u,$datos,error:$e);}return $this->exito('Tarifa registrada correctamente.');});
    }
    public function modificar(array $datos,array $consulta=[]): Respuesta
    {
        return $this->ejecutar(function($u)use($datos,$consulta){$id=ServicioOperacion::id($consulta['id']??null);try{$e=$this->entrada($datos,TarifaService::CAMPOS,['version']);($this->crearServicio)()->modificar($u->idUsuario,$id,$e,$datos['version']??null);}catch(ErrorGestionSprint03 $e){return $this->formulario($u,$datos,$id,$datos['version']??'',$e);}return $this->exito('Tarifa actualizada correctamente.');});
    }
}
