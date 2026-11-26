<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/app/bootstrap-web.php';
use SFATSA\Http\IntegracionSprint03;
$checks=0;$tmp=null;$codigo=0;
try{
    $tmp=sys_get_temp_dir().'/sfatsa-s03-cgi-'.bin2hex(random_bytes(16));if(!mkdir($tmp,0700))throw new RuntimeException('Temporal no disponible');
    $routes=require dirname(__DIR__).'/routes/web.php';
    foreach($routes as$ruta=>$metodos){if(!IntegracionSprint03::esRuta($ruta))continue;
        foreach(['GET','POST']as$metodo){
            $body=$metodo==='POST'?'csrf=invalido&confirmacion=1':'';
            $env=['REQUEST_METHOD'=>$metodo,'QUERY_STRING'=>http_build_query(['ruta'=>$ruta]),'REDIRECT_STATUS'=>'1','SCRIPT_FILENAME'=>dirname(__DIR__).'/public/index.php','SCRIPT_NAME'=>'/index.php','SERVER_PROTOCOL'=>'HTTP/1.1','GATEWAY_INTERFACE'=>'CGI/1.1','SystemRoot'=>'C:\\Windows','CONTENT_TYPE'=>'application/x-www-form-urlencoded','CONTENT_LENGTH'=>(string)strlen($body)];
            $p=proc_open([dirname(PHP_BINARY).'/php-cgi.exe','-n','-d','session.save_path='.$tmp,'-d','session.gc_probability=0'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);
            if(!is_resource($p))throw new RuntimeException('CGI no disponible');
            fwrite($pipes[0],$body);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
            $esperado=isset($metodos[$metodo])?'Status: 303':'Status: 405';
            foreach([$exit===0&&$err==='',str_contains($out,$esperado),str_contains($out,'Content-Security-Policy:'),str_contains($out,'Cache-Control: no-store'),!str_contains($out,'SQLSTATE')&&!str_contains($out,'Stack trace')]as$ok){if(!$ok)throw new RuntimeException('Fallo '.$ruta.' '.$metodo);$checks++;}
        }
    }
    echo "Sprint III CGI: $checks comprobaciones correctas; 14 solicitudes reales al punto de entrada sin sesión ni PDO_MYSQL.\n";
}catch(Throwable $e){fwrite(STDERR,'FALLO CGI Sprint III: '.$e->getMessage()."\n");$codigo=1;}
finally{if($tmp!==null&&is_dir($tmp)){foreach(scandir($tmp)as$f){if(preg_match('/\Asess_[a-zA-Z0-9,-]+\z/',$f)===1&&is_file($tmp.'/'.$f)&&!is_link($tmp.'/'.$f))unlink($tmp.'/'.$f);}rmdir($tmp);}}
exit($codigo);
