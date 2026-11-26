<?php
declare(strict_types=1);
namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\{Respuesta,Vista};
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\{AutorizacionService,CsrfService,ErrorGestionSprint03};

abstract class OperacionController
{
    protected const MODULO='';
    public function __construct(private readonly Closure $usuarioActual,protected readonly Closure $crearServicio,protected readonly CsrfService $csrf,protected readonly SessionStore $store,private readonly Vista $vista,private readonly AutorizacionService $autorizacion){}
    protected function ejecutar(Closure $operacion): Respuesta
    {
        try{
            $u=($this->usuarioActual)();if($u===null){return Respuesta::redirigir('/');}
            $permiso=static::MODULO==='tarifas'?$this->autorizacion->puedeGestionar($u):$this->autorizacion->puedeControlarViajes($u);
            if(!$permiso){return $this->mensaje('Su rol no permite realizar esta operación.',403);}
            return $operacion($u);
        }catch(ErrorGestionSprint03 $e){return $this->mensaje($e->getMessage(),$e->estado);}catch(\Throwable){return $this->mensaje('No se pudo procesar la solicitud. Consulte el listado antes de repetir.',503);}
    }
    protected function vista(string $nombre,array $datos,int $estado=200): Respuesta { return new Respuesta($this->vista->renderizar($nombre,$datos),$estado); }
    protected function mensaje(string $mensaje,int $estado): Respuesta {return $this->vista('mensaje',['titulo'=>'Operación de transporte','mensaje'=>$mensaje],$estado);}
    protected function entrada(array $datos,array $campos,array $transporte=[]): array
    {
        if(!$this->csrf->validar($datos['csrf']??null)){throw new ErrorGestionSprint03([], 'El formulario expiró o no es válido. Vuelva a abrirlo.',403);}
        foreach(array_keys($datos)as$campo){if(!in_array($campo,[...$campos,'csrf',...$transporte],true)){throw new ErrorGestionSprint03([(string)$campo=>'Este campo no está permitido.']);}}
        return array_intersect_key($datos,array_flip($campos));
    }
    protected function contexto(UsuarioAutenticado $usuario): array {return ['usuario'=>$usuario,'token'=>$this->csrf->token()];}
    protected function valores(array $datos,array $campos): array
    {
        $salida=[];foreach($campos as$campo){$v=$datos[$campo]??'';$salida[$campo]=(is_string($v)||is_int($v))&&strlen((string)$v)<=250?(string)$v:'';}return $salida;
    }
    protected function exito(string $mensaje): Respuesta {$this->store->guardar(static::MODULO.'_exito',$mensaje);return Respuesta::redirigir('/?ruta='.static::MODULO);}
    protected function flash(): string {$v=$this->store->obtener(static::MODULO.'_exito');$this->store->quitar(static::MODULO.'_exito');return is_string($v)?$v:'';}
}
