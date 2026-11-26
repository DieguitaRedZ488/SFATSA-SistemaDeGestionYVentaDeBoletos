<?php
declare(strict_types=1);
// Opt-in, instancia temporal sin TCP identificada. Nunca carga .env ni conecta a sfatza.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
require_once dirname(__DIR__).'/app/bootstrap-web.php';

use SFATSA\Repositories\{PdoTarifaRepository,PdoViajeRepository,PdoCatalogoOperacionRepository,PdoGestionUsuarioRepository};
use SFATSA\Services\{TarifaService,ViajeService,OperacionSprint03Service,RelojOperacion,ErrorGestionSprint03};

function conexionSprint03(array $config,bool $conBase=true): PDO
{
    $id=$config['id']??'';
    if(!is_string($id)||preg_match('/\A[a-f0-9]{32}\z/',$id)!==1||($config['pipe']??'')!=='SFATSA_S03_'.$id){throw new RuntimeException('Identidad de ensayo no válida.');}
    $raiz=realpath(sys_get_temp_dir().'/sfatsa-s03-'.$id);$datos=realpath((string)($config['datadir']??''));
    if($raiz===false||$datos===false||realpath($raiz.'/data')!==$datos||trim((string)file_get_contents($raiz.'/identidad.txt'))!==$id){throw new RuntimeException('Directorio no permitido.');}
    $base='sfatsa_s03_ensayo_'.$id;
    $pdo=new PDO('mysql:host=.;unix_socket=\\\\.\\pipe\\'.$config['pipe'].';charset=utf8mb4'.($conBase?';dbname='.$base:''),$conBase?'s03_pruebas':'root',$conBase?($config['clave']??''):'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $i=$pdo->query('SELECT DATABASE() db,@@datadir datadir,@@socket socket,@@skip_networking sin_tcp,@@port puerto,@@foreign_key_checks fk,@@check_constraint_checks ck,VERSION() version')->fetch();
    if($i['db']!==($conBase?$base:null)||$i['socket']!==$config['pipe']||(int)$i['sin_tcp']!==1||(int)$i['puerto']!==0||(int)$i['fk']!==1||(int)$i['ck']!==1||realpath($i['datadir'])!==$datos||!str_contains($i['version'],'MariaDB')){throw new RuntimeException('Instancia de ensayo no permitida.');}
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=8');
    $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}
function serviciosSprint03(PDO $pdo,?RelojOperacion $reloj=null):array
{
    $catalogos=new PdoCatalogoOperacionRepository($pdo);$ops=new OperacionSprint03Service(new PdoGestionUsuarioRepository($pdo));$reloj??=new RelojOperacion();
    return [new TarifaService(new PdoTarifaRepository($pdo),$catalogos,$ops,$reloj),new ViajeService(new PdoViajeRepository($pdo),$catalogos,$ops,$reloj)];
}
function accionSprint03(PDO $pdo,string $accion,array $datos):array
{
    [$t,$v]=serviciosSprint03($pdo);
    try{$id=match($accion){'tarifa'=>$t->registrar(1,$datos),'salida'=>$v->salir(3,$datos),'llegada'=>(function()use($v,$datos){$v->llegar(3,$datos['id']);return $datos['id'];})(),default=>throw new RuntimeException('Acción no válida')};return ['estado'=>200,'id'=>$id];}
    catch(ErrorGestionSprint03 $e){return ['estado'=>$e->estado];}
}
$checks=0;$caso='configuración';$admin=null;$baseCreada=false;$hijos=[];$codigo=0;
$assert=static function(bool $ok)use(&$checks){if(!$ok){throw new RuntimeException('Comprobación fallida en línea '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,1)[0]['line']);}$checks++;};
$rechazo=static function(Closure $op,int $estado)use($assert){try{$op();}catch(ErrorGestionSprint03 $e){$assert($e->estado===$estado);return;}throw new RuntimeException('Se esperaba rechazo');};
try{
    $config=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
    if(($argv[1]??'')==='--trabajador'){
        $pdo=conexionSprint03($config);echo "READY\n";flush();$trabajo=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);echo json_encode(accionSprint03($pdo,$trabajo['accion'],$trabajo['datos']))."\n";exit;
    }
    $admin=conexionSprint03($config,false);$base='sfatsa_s03_ensayo_'.$config['id'];
    $caso='esquema aislado';
    // Solo en la instancia vacía comprobada. No importa filas ni definiciones de la base operativa.
    $sql=(string)file_get_contents(dirname(__DIR__).'/database/install.sql');$sql=preg_replace('/^--.*$/m','',$sql);
    $sql=str_replace(['CREATE DATABASE sfatza','USE sfatza'],['CREATE DATABASE `'.$base.'`','USE `'.$base.'`'],$sql);
    foreach(explode(';',$sql)as$sentencia){if(trim($sentencia)!==''){$admin->exec($sentencia);$baseCreada=true;}}
    // Construcción del fixture con el contrato ya aprobado de 004/005; no ejecuta migraciones.
    $admin->exec('ALTER TABLE ruta ADD id_tipo_servicio TINYINT UNSIGNED NOT NULL,ADD KEY fk_ruta_tipo_servicio(id_tipo_servicio),ADD CONSTRAINT fk_ruta_tipo_servicio FOREIGN KEY(id_tipo_servicio) REFERENCES tipo_servicio(id_tipo_servicio)');
    $admin->exec("ALTER TABLE parada ADD nombre_normalizado VARCHAR(360) GENERATED ALWAYS AS (UPPER(TRIM(REGEXP_REPLACE(nombre,'[[:space:]]+',' ')))) PERSISTENT,ADD UNIQUE KEY nombre(nombre_normalizado)");
    $config['clave']=bin2hex(random_bytes(32));
    $admin->exec("CREATE USER 's03_pruebas'@'localhost' IDENTIFIED BY ".$admin->quote($config['clave']));
    $admin->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$base."`.* TO 's03_pruebas'@'localhost'");
    $pdo=conexionSprint03($config);
    $assert($pdo->query('SELECT CURRENT_USER()')->fetchColumn()==='s03_pruebas@localhost');
    try{$pdo->exec('CREATE TABLE no_permitida(id INT)');throw new RuntimeException('Cuenta con DDL inesperado');}catch(PDOException $e){$assert((int)$e->errorInfo[1]===1142);}
    $pdo->exec("INSERT INTO usuario(id_usuario,id_rol,nombres,apellidos,usuario,hash_contrasena) VALUES(1,1,'Admin','Prueba','admin_s03','ficticio'),(2,2,'Caja','Prueba','caja_s03','ficticio'),(3,3,'Control','Prueba','control_s03','ficticio')");
    $pdo->exec("INSERT INTO ruta(id_ruta,codigo,nombre,id_tipo_servicio) VALUES(1,'R1','Ruta prueba',2),(2,'R2','Otra ruta',2)");
    $pdo->exec("INSERT INTO parada(id_parada,nombre) VALUES(1,'Origen'),(2,'Intermedia'),(3,'Destino'),(4,'Otra')");
    $pdo->exec('INSERT INTO ruta_parada(id_ruta_parada,id_ruta,id_parada,orden) VALUES(11,1,1,1),(12,1,2,2),(13,1,3,3),(21,2,4,1)');
    $pdo->exec("INSERT INTO vehiculo(id_vehiculo,numero_economico,placa,capacidad) VALUES(1,'E1','A-00001-A',25),(2,'E2','A-00002-A',20)");
    $pdo->exec("INSERT INTO conductor(id_conductor,nombres,apellidos,numero_licencia) VALUES(1,'Persona','Uno','L1'),(2,'Persona','Dos','L2')");
    $definiciones=[];foreach(['tarifa','viaje','ruta','boleto']as$tabla){$definiciones[$tabla]=$pdo->query('SHOW CREATE TABLE '.$tabla)->fetch(PDO::FETCH_NUM)[1];}
    $momento='2026-11-18T12:00:00-06:00';$reloj=new RelojOperacion(static function()use(&$momento){return new DateTimeImmutable($momento);});[$t,$v]=serviciosSprint03($pdo,$reloj);
    $datos=['id_ruta'=>1,'id_tipo_servicio'=>2,'id_origen_ruta_parada'=>11,'id_destino_ruta_parada'=>13,'importe'=>'0.01','fecha_inicial'=>'2026-11-18','fecha_final'=>'2026-11-18'];
    $caso='tarifas válidas y límites';$id=$t->registrar(1,$datos);$f=$t->obtener($id);$assert($f['importe']==='0.01'&&$f['vigente_hasta']==='2026-11-18 23:59:59');
    $assert(count($t->listar(['q'=>'Origen','id_tipo_servicio'=>2])['tarifas'])===1);
    foreach(['0','0.00','1.001','999999999.99','1e2',1.2]as$importe){$rechazo(fn()=>$t->registrar(1,array_replace($datos,['importe'=>$importe])),422);}
    foreach([['fecha_final'=>'2026-11-17'],['fecha_final'=>'2026-02-30'],['id_destino_ruta_parada'=>21],['id_origen_ruta_parada'=>13,'id_destino_ruta_parada'=>11],['id_tipo_servicio'=>1]]as$c){$rechazo(fn()=>$t->registrar(1,array_replace($datos,$c)),422);}
    $rechazo(fn()=>$t->registrar(1,$datos),409);$rechazo(fn()=>$t->registrar(2,$datos),403);
    $t->modificar(1,$id,array_replace($datos,['importe'=>'99999999.99']),$f['version']);$assert($t->obtener($id)['importe']==='99999999.99');$rechazo(fn()=>$t->modificar(1,$id,$datos,$f['version']),409);
    $caso='salida y llegada';$salida=['id_ruta'=>1,'id_tipo_servicio'=>2,'id_vehiculo'=>1,'id_conductor'=>1];$j=$v->salir(3,$salida);$fila=$v->obtener($j);$assert($fila['estado']==='EN_CURSO'&&$fila['salida_real']==='2026-11-18 12:00:00'&&(int)$fila['capacidad_al_salir']===25);
    $assert(count($v->listar(['estado'=>'EN_CURSO'])['viajes'])===1);$assert(count($v->opciones()['vehiculos'])===1&&count($v->opciones()['conductores'])===1);
    $rechazo(fn()=>$v->salir(3,array_replace($salida,['id_conductor'=>2])),409);$rechazo(fn()=>$v->salir(3,array_replace($salida,['id_vehiculo'=>2])),409);
    $momento='2026-11-18T11:59:59-06:00';$rechazo(fn()=>$v->llegar(3,$j),409);$momento='2026-11-18T13:00:00-06:00';$v->llegar(3,$j);$assert($v->obtener($j)['llegada_real']==='2026-11-18 13:00:00'&&(int)$v->obtener($j)['id_usuario_llegada']===3);$rechazo(fn()=>$v->llegar(3,$j),409);$rechazo(fn()=>$v->llegar(3,9999),404);
    $caso='historia incluso boleto cancelado';$pdo->exec("INSERT INTO venta(folio_venta,id_cajero,id_metodo_pago) VALUES('V-S03',2,1)");$st=$pdo->prepare("INSERT INTO boleto(folio_boleto,folio_venta,id_viaje,id_tarifa,estado) VALUES('B-S03','V-S03',:viaje,:tarifa,'CANCELADO')");$st->execute(['viaje'=>$j,'tarifa'=>$id]);$f=$t->obtener($id);$rechazo(fn()=>$t->modificar(1,$id,$datos,$f['version']),409);$assert($t->obtener($id)['importe']==='99999999.99');
    $caso='permiso revocado tras captura';$pdo->exec('UPDATE usuario SET activo=0 WHERE id_usuario=3');$rechazo(fn()=>$v->salir(3,$salida),403);$pdo->exec('UPDATE usuario SET activo=1 WHERE id_usuario=3');
    $caso='rollback después de escritura';
    $fallo=new class(new PdoViajeRepository($pdo)) implements \SFATSA\Repositories\ViajeRepository {
        public function __construct(private PdoViajeRepository $base){}
        public function listar(array $f):array{return $this->base->listar($f);}
        public function buscar(int $id,bool $b=false):?array{return $this->base->buscar($id,$b);}
        public function ocupado(string $r,int $id):bool{return $this->base->ocupado($r,$id);}
        public function insertar(array $d):int{$this->base->insertar($d);throw new RuntimeException('Fallo sintético posterior a INSERT');}
        public function finalizar(int $id,string $l,int $a):bool{return $this->base->finalizar($id,$l,$a);}
    };
    $antes=(int)$pdo->query('SELECT COUNT(*) FROM viaje')->fetchColumn();
    $fallido=new ViajeService($fallo,new PdoCatalogoOperacionRepository($pdo),new OperacionSprint03Service(new PdoGestionUsuarioRepository($pdo)),$reloj);
    $rechazo(fn()=>$fallido->salir(3,$salida),503);$assert((int)$pdo->query('SELECT COUNT(*) FROM viaje')->fetchColumn()===$antes&&!$pdo->inTransaction());
    $caso='concurrencia real con procesos';
    $carrera=static function(string $accion,array $a,array $b)use($config,$pdo,$assert,&$hijos):array{
        $pdo->beginTransaction();$pdo->query("SELECT id_rol FROM rol WHERE nombre='ADMINISTRADOR' FOR UPDATE")->fetch();$procesos=[];
        foreach([$a,$b]as$d){$pipes=[];$p=proc_open([PHP_BINARY,__FILE__,'--trabajador'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('No se pudo iniciar trabajador');$hijos[]=[$p,$pipes];fwrite($pipes[0],json_encode($config)."\n");fflush($pipes[0]);$assert(trim((string)fgets($pipes[1]))==='READY');fwrite($pipes[0],json_encode(['accion'=>$accion,'datos'=>$d])."\n");fclose($pipes[0]);$procesos[]=[$p,$pipes];}
        // Verificar contención real, no solo dos llamadas secuenciales a los servicios.
        $observador=conexionSprint03($config);$limite=microtime(true)+5;$esperas=0;
        do{$esperas=(int)$observador->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID<>CONNECTION_ID() AND COMMAND<>'Sleep' AND INFO LIKE '%id_rol FROM rol%'")->fetchColumn();if($esperas>=2)break;usleep(20000);}while(microtime(true)<$limite);
        $assert($esperas>=2);
        $pdo->commit();$resultados=[];
        foreach($procesos as[$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);$assert($exit===0&&$err==='');$resultados[]=json_decode(trim($out),true,512,JSON_THROW_ON_ERROR);}
        $estados=array_column($resultados,'estado');sort($estados);$assert($estados===[200,409]);$hijos=[];return $resultados;
    };
    $nuevo=array_replace($datos,['fecha_inicial'=>'2026-12-01','fecha_final'=>'2026-12-31']);$carrera('tarifa',$nuevo,$nuevo);$assert((int)$pdo->query("SELECT COUNT(*) FROM tarifa WHERE vigente_desde='2026-12-01 00:00:00'")->fetchColumn()===1);
    $resultados=$carrera('salida',$salida,array_replace($salida,['id_conductor'=>2]));$ganador=array_values(array_filter($resultados,static fn($r)=>$r['estado']===200))[0]['id'];
    $assert((int)$pdo->query("SELECT COUNT(*) FROM viaje WHERE id_vehiculo=1 AND estado='EN_CURSO'")->fetchColumn()===1);
    serviciosSprint03($pdo)[1]->llegar(3,$ganador);
    $resultados=$carrera('salida',$salida,array_replace($salida,['id_vehiculo'=>2]));$ganador=array_values(array_filter($resultados,static fn($r)=>$r['estado']===200))[0]['id'];
    $assert((int)$pdo->query("SELECT COUNT(*) FROM viaje WHERE id_conductor=1 AND estado='EN_CURSO'")->fetchColumn()===1);
    $carrera('llegada',['id'=>$ganador],['id'=>$ganador]);$assert($v->obtener($ganador)['estado']==='FINALIZADO');
    $caso='esquema conservado';foreach($definiciones as$tabla=>$ddl){$despues=$pdo->query('SHOW CREATE TABLE '.$tabla)->fetch(PDO::FETCH_NUM)[1];$assert(preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$despues)===preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$ddl));}
    echo "Sprint III MariaDB: $checks comprobaciones correctas; CRUD, historia, permisos y cuatro carreras con procesos independientes. Instancia aislada sin TCP.\n";
}catch(Throwable $e){$codigo=1;fwrite(STDERR,'FALLO ensayo Sprint III: '.$caso.'; '.get_class($e).' línea '.$e->getLine().(str_starts_with($e->getMessage(),'Comprobación fallida')?' '.$e->getMessage():'').". Sin detalles privados.\n");}
finally{
    foreach($hijos as[$p,$pipes]){foreach($pipes as$pipe){if(is_resource($pipe))fclose($pipe);}if(is_resource($p)){proc_terminate($p);proc_close($p);}}
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    if($admin instanceof PDO&&$baseCreada){$control=conexionSprint03($config,false);$control=null;$pdo=null;$admin->exec('DROP DATABASE `sfatsa_s03_ensayo_'.$config['id'].'`');$admin->exec("DROP USER IF EXISTS 's03_pruebas'@'localhost'");echo "Base temporal retirada; base operativa nunca conectada por esta prueba.\n";}
}
exit($codigo);
