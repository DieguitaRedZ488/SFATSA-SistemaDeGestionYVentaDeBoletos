<?php
declare(strict_types=1);
namespace SFATSA\Services;

use SFATSA\Repositories\{ViajeRepository,CatalogoOperacionRepository};

final class ViajeService extends ServicioOperacion
{
    public const CAMPOS=['id_ruta','id_tipo_servicio','id_vehiculo','id_conductor'];
    public function __construct(private readonly ViajeRepository $repository,CatalogoOperacionRepository $catalogos,OperacionSprint03Service $operaciones,RelojOperacion $reloj){parent::__construct($catalogos,$operaciones,$reloj);}
    public function salir(int $actor,array $entrada): int
    {
        $this->campos($entrada,self::CAMPOS);$datos=[];
        foreach(self::CAMPOS as$campo){try{$datos[$campo]=self::id($entrada[$campo]??null);}catch(ErrorGestionSprint03){throw new ErrorGestionSprint03([$campo=>'Seleccione una opción válida.']);}}
        return $this->operaciones->ejecutar($actor,'CHECADORA',function()use($datos,$actor):int{
            $this->validarRuta($datos['id_ruta'],$datos['id_tipo_servicio']);
            // Filas estables, incluso sin viajes previos: evita la carrera de consultar ausencia.
            $v=$this->catalogos->vehiculo($datos['id_vehiculo'],true);$c=$this->catalogos->conductor($datos['id_conductor'],true);
            if($v===null||$v['estado']!=='ACTIVO'||(int)$v['capacidad']<1||(int)$v['capacidad']>30){throw new ErrorGestionSprint03(['id_vehiculo'=>'Seleccione un vehículo habilitado con capacidad válida.']);}
            if($c===null||(int)$c['activo']!==1){throw new ErrorGestionSprint03(['id_conductor'=>'Seleccione un conductor habilitado.']);}
            foreach(['id_vehiculo'=>'El vehículo ya tiene un viaje en curso.','id_conductor'=>'El conductor ya tiene un viaje en curso.']as$campo=>$mensaje){if($this->repository->ocupado($campo,$datos[$campo])){throw new ErrorGestionSprint03([$campo=>$mensaje],$mensaje,409);}}
            return $this->repository->insertar($datos+['salida_real'=>$this->reloj->ahora(),'id_usuario_salida'=>$actor,'capacidad_al_salir'=>(int)$v['capacidad']]);
        });
    }
    public function obtener(mixed $id): array
    {
        $id=self::id($id);return $this->leer(fn()=>$this->repository->buscar($id)??throw new ErrorGestionSprint03([], 'No se encontró el viaje.',404));
    }
    public function llegar(int $actor,mixed $id): void
    {
        $id=self::id($id);
        $this->operaciones->ejecutar($actor,'CHECADORA',function()use($id,$actor):void{
            $v=$this->repository->buscar($id,true)??throw new ErrorGestionSprint03([], 'No se encontró el viaje.',404);
            if($v['estado']!=='EN_CURSO'||$v['llegada_real']!==null||$v['id_usuario_llegada']!==null){throw new ErrorGestionSprint03([], 'El viaje ya fue finalizado o no está en curso.',409);}
            $ahora=$this->reloj->ahora();
            if($ahora<$v['salida_real']){throw new ErrorGestionSprint03([], 'La hora del servidor es anterior a la salida. No se registró la llegada.',409);}
            if(!$this->repository->finalizar($id,$ahora,$actor)){throw new ErrorGestionSprint03([], 'El viaje cambió. Consulte nuevamente.',409);}
        });
    }
    public function listar(array $consulta=[]): array
    {
        $filtros=[];
        if(($consulta['fecha']??'')!==''){$filtros['fecha']=RelojOperacion::fecha($consulta['fecha'],'fecha');}
        if(($consulta['estado']??'')!==''){if(!in_array($consulta['estado'],['EN_CURSO','FINALIZADO','CANCELADO'],true)){throw new ErrorGestionSprint03([], 'Estado no válido.',400);}$filtros['estado']=$consulta['estado'];}
        return $this->leer(fn()=>['viajes'=>$this->repository->listar($filtros),'filtros'=>$filtros]);
    }
}
