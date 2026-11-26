<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap-web.php';
require_once __DIR__.'/Support/GestionUsuariosFicticia.php';
require_once __DIR__.'/Support/OperacionFicticia.php';

use SFATSA\Services\{TarifaService,ViajeService,OperacionSprint03Service,RelojOperacion,ErrorGestionSprint03,CsrfService,AutorizacionService};
use SFATSA\Controllers\{TarifaController,ViajeController};
use SFATSA\Http\{Vista,IntegracionSprint03};
use SFATSA\Models\UsuarioAutenticado;

$checks=0;
$assert=static function(bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException('Comprobación fallida en línea '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1)[0]['line']);$checks++;};
$rechazo=static function(Closure $accion,int $estado)use($assert):void{try{$accion();}catch(ErrorGestionSprint03 $e){$assert($e->estado===$estado);$assert($e->getPrevious()===null);return;}throw new RuntimeException('Se esperaba rechazo');};
try{
    $usuarios=new GestionUsuariosFicticia();$catalogos=new CatalogosOperacionFicticios();$tarifas=new TarifasOperacionFicticias();$viajes=new ViajesOperacionFicticios();$ops=new OperacionSprint03Service($usuarios);
    $momento='2026-11-18T12:30:00-06:00';$reloj=new RelojOperacion(static function()use(&$momento,$catalogos){$catalogos->eventos[]='reloj';return new DateTimeImmutable($momento);});
    $t=new TarifaService($tarifas,$catalogos,$ops,$reloj);$v=new ViajeService($viajes,$catalogos,$ops,$reloj);
    $datos=['id_ruta'=>'1','id_tipo_servicio'=>'1','id_origen_ruta_parada'=>'11','id_destino_ruta_parada'=>'12','importe'=>'12.3','fecha_inicial'=>'2026-11-18','fecha_final'=>'2026-11-18'];
    $id=$t->registrar(41,$datos);$f=$t->obtener($id);$assert($f['importe']==='12.30'&&$f['vigente_desde']==='2026-11-18 00:00:00'&&$f['vigente_hasta']==='2026-11-18 23:59:59');
    foreach(['0','0.00','-1','1.001','1e2','100000000','1,25',[],1.25,' 1','01']as$importe){$rechazo(fn()=>$t->registrar(41,array_replace($datos,['importe'=>$importe])),422);}
    foreach([['fecha_final'=>'2026-11-17'],['fecha_inicial'=>'2026-02-30'],['fecha_final'=>''],['id_origen_ruta_parada'=>12,'id_destino_ruta_parada'=>11],['id_origen_ruta_parada'=>11,'id_destino_ruta_parada'=>11],['id_destino_ruta_parada'=>999],['id_tipo_servicio'=>2],['id_ruta'=>2]]as$c){$rechazo(fn()=>$t->registrar(41,array_replace($datos,$c)),422);}
    $rechazo(fn()=>$t->registrar(43,$datos),403);$rechazo(fn()=>$t->registrar(41,$datos),409);
    foreach([['2026-11-17','2026-11-18'],['2026-11-18','2026-11-20'],['2026-11-17','2026-11-20']]as[$desde,$hasta]){$rechazo(fn()=>$t->registrar(41,array_replace($datos,['fecha_inicial'=>$desde,'fecha_final'=>$hasta])),409);}
    $id2=$t->registrar(41,array_replace($datos,['fecha_inicial'=>'2026-11-19','fecha_final'=>'2026-11-19','importe'=>'99999999.99']));$assert($id2===2);
    foreach(['2026-11-18T00:00:00-06:00','2026-11-18T23:59:59-06:00']as$momento){$assert($t->vigente(1,11,12)['id_tarifa']===$id);}
    $momento='2026-11-17T23:59:59-06:00';$assert($t->vigente(1,11,12)===null);$momento='2026-11-18T12:30:00-06:00';
    $t->modificar(41,$id,array_replace($datos,['importe'=>'15']),$f['version']);$assert($t->obtener($id)['importe']==='15.00');
    $rechazo(fn()=>$t->modificar(41,$id,$datos,$f['version']),409);$f=$t->obtener($id);$tarifas->boletos[$id]='CANCELADO';$rechazo(fn()=>$t->modificar(41,$id,$datos,$f['version']),409);$assert($t->obtener($id)['importe']==='15.00');
    $salida=['id_ruta'=>1,'id_tipo_servicio'=>1,'id_vehiculo'=>1,'id_conductor'=>1];$catalogos->eventos=[];$j=$v->salir(43,$salida);$assert(array_slice($catalogos->eventos,-3)===['vehiculo','conductor','reloj']);$assert($v->obtener($j)['salida_real']==='2026-11-18 12:30:00');
    $rechazo(fn()=>$v->salir(43,array_replace($salida,['id_conductor'=>2])),409);$rechazo(fn()=>$v->salir(43,array_replace($salida,['id_vehiculo'=>2])),409);
    $rechazo(fn()=>$v->salir(41,$salida),403);$rechazo(fn()=>$v->salir(43,$salida+['salida_real'=>'2020-01-01']),422);
    $momento='2026-11-18T12:29:59-06:00';$rechazo(fn()=>$v->llegar(43,$j),409);$momento='2026-11-19T01:00:00-06:00';$v->llegar(43,$j);$assert($v->obtener($j)['estado']==='FINALIZADO'&&$v->obtener($j)['id_usuario_llegada']===43);$rechazo(fn()=>$v->llegar(43,$j),409);$rechazo(fn()=>$v->llegar(43,999),404);
    $j2=$v->salir(43,$salida);$viajes->filas[$j2]['estado']='CANCELADO';$rechazo(fn()=>$v->llegar(43,$j2),409);$usuarios->filas[43]['activo']=0;$rechazo(fn()=>$v->salir(43,$salida),403);$usuarios->filas[43]['activo']=1;
    $rechazo(fn()=>$v->listar(['estado'=>'PROGRAMADO']),400);$rechazo(fn()=>$v->listar(['fecha'=>[]]),422);
    $usuarios->iniciarTransaccion();$rechazo(fn()=>$v->salir(43,$salida),503);$assert($usuarios->enTransaccion());$usuarios->revertirTransaccion();
    $relojUtc=new RelojOperacion(static fn()=>new DateTimeImmutable('2026-11-18T18:30:00Z'));$assert($relojUtc->ahora()==='2026-11-18 12:30:00');
    $store=new SesionGestionFicticia();$csrf=new CsrfService($store);$vista=new Vista();$auth=new AutorizacionService();$actor=new UsuarioAutenticado(41,'Persona','Prueba','admin','ADMINISTRADOR');
    $actual=static function()use(&$actor){return $actor;};$tc=new TarifaController($actual,static fn()=>$t,$csrf,$store,$vista,$auth);$vc=new ViajeController($actual,static fn()=>$v,$csrf,$store,$vista,$auth);
    $assert($tc->nuevo()->estado===200);$assert($tc->registrar(['csrf'=>'invalido'])->estado===403);$assert($vc->nuevo()->estado===403);
    $actor=new UsuarioAutenticado(43,'Persona','Prueba','checadora','CHECADORA');$assert($tc->nuevo()->estado===403);$assert($vc->salir(['csrf'=>'invalido'])->estado===403);
    $html=$vc->nuevo()->cuerpo;$doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$x=new DOMXPath($doc);$operacion=$x->query('//input[@name="operacion"]')->item(0)->getAttribute('value');
    $assert($x->query('//input[@name="salida_real" or @name="llegada_real"]')->length===0);$assert($x->query('//a[@aria-current="page" and @href="/?ruta=viajes"]')->length===1);$assert($x->query('//script[not(@src)]')->length===0);
    $post=$salida+['csrf'=>$csrf->token(),'operacion'=>$operacion,'confirmacion'=>'1'];$assert($vc->salir($post)->estado===303);$assert($vc->salir($post)->estado===409);
    $assert($vc->salir($post+['salida_real'=>'falsa'])->estado===422);$assert($vc->llegar(['csrf'=>'invalido'],['id'=>1])->estado===403);
    $actor=null;$assert($tc->listar()->estado===303);
    $routes=require dirname(__DIR__).'/routes/web.php';$assert(IntegracionSprint03::atender($routes,'viajes.salida','DELETE',[],[],static fn()=>throw new RuntimeException('No construir'))->estado===405);
    echo "Sprint III: $checks comprobaciones correctas; dominio, reloj, HTTP y vistas con datos ficticios, sin MariaDB.\n";
}catch(Throwable $e){fwrite(STDERR,'FALLO Sprint III: '.get_class($e).' '.$e->getMessage().' en '.$e->getLine()."\n");exit(1);}
