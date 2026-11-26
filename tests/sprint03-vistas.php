<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap-web.php';
require_once __DIR__.'/Support/GestionUsuariosFicticia.php';
require_once __DIR__.'/Support/OperacionFicticia.php';
use SFATSA\Services\{TarifaService,ViajeService,OperacionSprint03Service,RelojOperacion,CsrfService,AutorizacionService};
use SFATSA\Controllers\{TarifaController,ViajeController};
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
$checks=0;
$assert=static function(bool $ok)use(&$checks){if(!$ok)throw new RuntimeException('Fallo de vista en línea '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1)[0]['line']);$checks++;};
try{
    $u=new GestionUsuariosFicticia();$c=new CatalogosOperacionFicticios();$tr=new TarifasOperacionFicticias();$vr=new ViajesOperacionFicticios();$ops=new OperacionSprint03Service($u);$reloj=new RelojOperacion(static fn()=>new DateTimeImmutable('2026-11-18T12:00:00-06:00'));
    $t=new TarifaService($tr,$c,$ops,$reloj);$v=new ViajeService($vr,$c,$ops,$reloj);$s=new SesionGestionFicticia();$csrf=new CsrfService($s);$auth=new AutorizacionService();$vista=new Vista();
    $admin=new UsuarioAutenticado(41,'Persona','Prueba','admin','ADMINISTRADOR');$checadora=new UsuarioAutenticado(43,'Persona','Prueba','control','CHECADORA');
    $tc=new TarifaController(static fn()=>$admin,static fn()=>$t,$csrf,$s,$vista,$auth);$vc=new ViajeController(static fn()=>$checadora,static fn()=>$v,$csrf,$s,$vista,$auth);
    $t->registrar(41,['id_ruta'=>1,'id_tipo_servicio'=>1,'id_origen_ruta_parada'=>11,'id_destino_ruta_parada'=>12,'importe'=>'45.50','fecha_inicial'=>'2026-11-18','fecha_final'=>'2026-11-30']);$j=$v->salir(43,['id_ruta'=>1,'id_tipo_servicio'=>1,'id_vehiculo'=>1,'id_conductor'=>1]);
    $xss='<script>alert("prueba")</script>';$c->rutas[1]['nombre']=$xss;
    $paginas=['tarifas-listado'=>$tc->listar(),'tarifas-registro'=>$tc->nuevo(),'tarifas-edicion'=>$tc->editar(['id'=>1]),'viajes-listado'=>$vc->listar(),'viajes-salida'=>$vc->nuevo(),'viajes-llegada'=>$vc->confirmarLlegada(['id'=>$j]),'viajes-detalle'=>$vc->detalle(['id'=>$j])];
    foreach($paginas as$nombre=>$respuesta){
        $assert($respuesta->estado===200);$html=$respuesta->cuerpo;$doc=new DOMDocument();$previo=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previo);$x=new DOMXPath($doc);
        $assert($x->query('//main[@id="contenido"]')->length===1);$assert($x->query('//a[@aria-current="page"]')->length===1);$assert($x->query('//link[@href="/assets/css/tokens.css"]')->length===1);$assert($x->query('//script[not(@src)]')->length===0);$assert(!str_contains($html,$xss));$assert(preg_match('/HU-0[56]|CU-(?:09|10|11)|Sprint\s*(?:III|3)/iu',$x->query('//body')->item(0)->textContent)===0);
        foreach($x->query('//select|//input[not(@type="hidden")]')as$input){$id=$input->getAttribute('id');if($id!==''){$assert($x->query('//label[@for="'.$id.'"]')->length===1);}}
        $assert($x->query('//form[@method="post"]//input[@name="csrf"]')->length>=1);
        $directorio=getenv('SFATSA_UI_PREVIEW_DIR');if(is_string($directorio)&&$directorio!==''&&is_dir($directorio)){
            // Solo páginas con datos ficticios, exportación explícita fuera de public.
            $base='file:///'.str_replace('\\','/',dirname(__DIR__).'/public');
            $html=preg_replace_callback('~<link rel="stylesheet" href="(/assets/[^"<>]+\.css)">~',static function($m)use($base){$css=(string)file_get_contents(dirname(__DIR__).'/public'.$m[1]);return '<style>'.str_replace('/assets/',$base.'/assets/',$css).'</style>';},$html);
            $html=preg_replace('~<script[^>]*>.*?</script>~s','',$html);
            if(file_put_contents($directorio.'/'.$nombre.'.html',$html)===false)throw new RuntimeException('No se pudo exportar vista ficticia');
        }
    }
    echo "Sprint III vistas: $checks comprobaciones correctas; siete pantallas, navegación, escape y estructura accesible, sin MariaDB.\n";
}catch(Throwable $e){fwrite(STDERR,'FALLO vistas Sprint III: '.$e->getMessage()."\n");exit(1);}
