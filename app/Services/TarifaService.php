<?php
declare(strict_types=1);
namespace SFATSA\Services;

use SFATSA\Repositories\{TarifaRepository,CatalogoOperacionRepository};

final class TarifaService extends ServicioOperacion
{
    public const CAMPOS=['id_ruta','id_tipo_servicio','id_origen_ruta_parada','id_destino_ruta_parada','importe','fecha_inicial','fecha_final'];
    public function __construct(private readonly TarifaRepository $repository,CatalogoOperacionRepository $catalogos,OperacionSprint03Service $operaciones,RelojOperacion $reloj) { parent::__construct($catalogos,$operaciones,$reloj); }
    private function validar(array $datos): array
    {
        $this->campos($datos,self::CAMPOS); $validos=[];
        foreach (array_slice(self::CAMPOS,0,4) as $campo) { try { $validos[$campo]=self::id($datos[$campo]??null); } catch(ErrorGestionSprint03) { throw new ErrorGestionSprint03([$campo=>'Seleccione una opción válida.']); } }
        $importe=$datos['importe']??null;
        if (!is_string($importe) || preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/',$importe)!==1) { throw new ErrorGestionSprint03(['importe'=>'Introduzca de 0.01 a 99999999.99 MXN, con máximo dos decimales.']); }
        [$entero,$decimal]=array_pad(explode('.',$importe),2,''); $importe=$entero.'.'.str_pad($decimal,2,'0');
        if ($importe==='0.00') { throw new ErrorGestionSprint03(['importe'=>'El importe debe ser mayor que cero.']); }
        $desde=RelojOperacion::fecha($datos['fecha_inicial']??null,'fecha_inicial'); $hasta=RelojOperacion::fecha($datos['fecha_final']??null,'fecha_final');
        if ($hasta<$desde) { throw new ErrorGestionSprint03(['fecha_final'=>'La fecha final no puede ser anterior a la inicial.']); }
        return $validos+['importe'=>$importe,'vigente_desde'=>$desde.' 00:00:00','vigente_hasta'=>$hasta.' 23:59:59'];
    }
    private function referencias(array $datos): void
    {
        $this->validarRuta($datos['id_ruta'],$datos['id_tipo_servicio']);
        $puntos=array_column($this->catalogos->puntos($datos['id_ruta'],true),null,'id_ruta_parada');
        $o=$puntos[$datos['id_origen_ruta_parada']]??null; $d=$puntos[$datos['id_destino_ruta_parada']]??null;
        if ($o===null || $d===null || (int)$o['activo']!==1 || (int)$d['activo']!==1 || (int)$o['orden']>=(int)$d['orden']) {
            throw new ErrorGestionSprint03(['id_destino_ruta_parada'=>'Seleccione dos paradas habilitadas de la misma ruta, con origen anterior al destino.']);
        }
    }
    public function registrar(int $actor,array $entrada): int
    {
        $datos=$this->validar($entrada);
        return $this->operaciones->ejecutar($actor,'ADMINISTRADOR',function()use($datos):int { $this->referencias($datos); if($this->repository->solapa($datos)){throw new ErrorGestionSprint03([], 'Ya existe una tarifa con vigencia superpuesta para este servicio y trayecto.',409);} return $this->repository->insertar($datos); });
    }
    public function obtener(mixed $id): array
    {
        $id=self::id($id);
        return $this->leer(function()use($id):array { $fila=$this->repository->buscar($id)??throw new ErrorGestionSprint03([], 'No se encontró la tarifa.',404);return $fila+['version'=>self::version($fila),'fecha_inicial'=>substr($fila['vigente_desde'],0,10),'fecha_final'=>$fila['vigente_hasta']===null?'':substr($fila['vigente_hasta'],0,10)]; });
    }
    public function modificar(int $actor,mixed $id,array $entrada,mixed $version): void
    {
        $id=self::id($id);$datos=$this->validar($entrada);
        $this->operaciones->ejecutar($actor,'ADMINISTRADOR',function()use($id,$datos,$version):void {
            $fila=$this->repository->buscar($id,true)??throw new ErrorGestionSprint03([], 'No se encontró la tarifa.',404);
            if(!is_string($version)||!hash_equals(self::version($fila),$version)){throw new ErrorGestionSprint03([], 'La tarifa cambió. Vuelva a consultarla antes de guardar.',409);}
            if($this->repository->utilizada($id)){throw new ErrorGestionSprint03([], 'Esta tarifa tiene boletos asociados y debe conservarse. Registre una nueva tarifa con vigencia compatible.',409);}
            $this->referencias($datos);
            if($this->repository->solapa($datos,$id)){throw new ErrorGestionSprint03([], 'La vigencia se superpone con otra tarifa del mismo servicio y trayecto.',409);}
            $this->repository->actualizar($id,$datos);
        });
    }
    public function listar(array $consulta=[]): array
    {
        $filtros=[];
        foreach(['id_ruta','id_tipo_servicio']as$campo){if(($consulta[$campo]??'')!==''){$filtros[$campo]=self::id($consulta[$campo]);}}
        $q=$consulta['q']??'';if(!is_string($q)||preg_match('//u',$q)!==1||mb_strlen($q,'UTF-8')>120||preg_match('/[\p{Cc}\p{Cf}]/u',$q)){throw new ErrorGestionSprint03([], 'Búsqueda no válida.',400);} $filtros['q']=trim($q);
        return $this->leer(fn()=>['tarifas'=>$this->repository->listar($filtros),'filtros'=>$filtros,'ahora'=>$this->reloj->ahora()]);
    }
    public function vigente(int $tipo,int $origen,int $destino): ?array
    {
        return $this->leer(function()use($tipo,$origen,$destino){$filas=$this->repository->vigentes($tipo,$origen,$destino,$this->reloj->ahora());if(count($filas)>1){throw new ErrorGestionSprint03([], 'Hay tarifas vigentes incompatibles. Revise sus periodos.',409);}return $filas[0]??null;});
    }
}
