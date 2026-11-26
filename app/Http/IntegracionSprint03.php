<?php
declare(strict_types=1);
namespace SFATSA\Http;

use Closure;
use SFATSA\Controllers\{TarifaController,ViajeController,OperacionController};
use SFATSA\Repositories\{PdoGestionUsuarioRepository,PdoTarifaRepository,PdoViajeRepository,PdoCatalogoOperacionRepository};
use SFATSA\Services\{TarifaService,ViajeService,OperacionSprint03Service,RelojOperacion,CsrfService,AutorizacionService};
use SFATSA\Security\SessionStore;

final class IntegracionSprint03
{
    public const PLANTILLAS=['tarifas'=>['listado','formulario'],'viajes'=>['listado','salida','llegada','detalle']];
    public static function esRuta(string $ruta): bool {return isset(self::PLANTILLAS[explode('.',$ruta,2)[0]]);}
    public static function atender(array $rutas,string $ruta,string $metodo,array $consulta,array $datos,Closure $crear): Respuesta
    {
        if(!isset($rutas[$ruta])){return new Respuesta('Página no encontrada.',404);}
        if(!isset($rutas[$ruta][$metodo])){return new Respuesta('Método no permitido.',405,['Allow'=>implode(', ',array_keys($rutas[$ruta]))]);}
        [$modulo,$accion]=$rutas[$ruta][$metodo];$controlador=$crear($modulo);
        return $metodo==='POST'?$controlador->$accion($datos,$consulta):$controlador->$accion($consulta);
    }
    public static function controlador(string $modulo,Closure $usuario,Closure $conexion,CsrfService $csrf,SessionStore $store): OperacionController
    {
        if(!isset(self::PLANTILLAS[$modulo])){throw new \InvalidArgumentException('Módulo no permitido.');}
        $servicio=null;$crear=static function()use(&$servicio,$conexion,$modulo){if($servicio===null){$pdo=$conexion();$ops=new OperacionSprint03Service(new PdoGestionUsuarioRepository($pdo));$catalogos=new PdoCatalogoOperacionRepository($pdo);$reloj=new RelojOperacion();$servicio=$modulo==='tarifas'?new TarifaService(new PdoTarifaRepository($pdo),$catalogos,$ops,$reloj):new ViajeService(new PdoViajeRepository($pdo),$catalogos,$ops,$reloj);}return $servicio;};
        $clase=$modulo==='tarifas'?TarifaController::class:ViajeController::class;
        return new $clase($usuario,$crear,$csrf,$store,new Vista(),new AutorizacionService());
    }
}
