<?php
declare(strict_types=1);
namespace SFATSA\Services;

use SFATSA\Repositories\CatalogoOperacionRepository;

abstract class ServicioOperacion
{
    public function __construct(protected readonly CatalogoOperacionRepository $catalogos, protected readonly OperacionSprint03Service $operaciones, protected readonly RelojOperacion $reloj) {}
    public static function id(mixed $id): int
    {
        if ((!is_string($id) && !is_int($id)) || preg_match('/\A[1-9][0-9]*\z/', (string)$id)!==1 || filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) { throw new ErrorGestionSprint03([], 'Identificador no válido.', 400); }
        return (int)$id;
    }
    protected function campos(array $datos, array $permitidos): void
    {
        foreach (array_keys($datos) as $campo) { if (!in_array($campo,$permitidos,true)) { throw new ErrorGestionSprint03([(string)$campo=>'Este campo no está permitido.']); } }
    }
    protected function leer(\Closure $accion): mixed
    {
        try { return $accion(); } catch (ErrorGestionSprint03 $e) { throw $e; } catch (\Throwable) { throw new ErrorGestionSprint03([], 'No se pudo consultar la información.', 503); }
    }
    public function opciones(): array { return $this->leer(fn () => $this->catalogos->opciones()); }
    protected function validarRuta(int $ruta, int $tipo): void
    {
        $r=$this->catalogos->ruta($ruta,true); $s=$this->catalogos->tipo($tipo,true);
        if ($r===null || (int)$r['activa']!==1 || $s===null || (int)$s['activo']!==1 || (int)$r['id_tipo_servicio']!==$tipo) {
            throw new ErrorGestionSprint03(['id_ruta'=>'Seleccione una ruta habilitada y su tipo de servicio correspondiente.']);
        }
    }
    public static function version(array $fila): string { return hash('sha256',serialize(array_map(static fn ($v) => $v===null?null:(string)$v,$fila))); }
}
