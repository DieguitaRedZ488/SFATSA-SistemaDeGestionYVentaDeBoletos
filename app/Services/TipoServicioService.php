<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Repositories\TipoServicioRepository;

final class TipoServicioService extends ServicioHU04
{
    protected const ERROR = ErrorGestionTiposServicio::class;
    protected const CAMPOS_VERSION = ['id_tipo_servicio', 'nombre', 'controla_capacidad', 'activo'];

    public function __construct(private readonly TipoServicioRepository $repository, OperacionAdministrativaService $operaciones)
    {
        parent::__construct($operaciones);
    }

    private function validar(array $datos): array
    {
        $this->campos($datos, ['nombre']);
        return ['nombre' => $this->texto($datos['nombre'] ?? null, 'nombre', 40)];
    }

    public function listar(mixed $busqueda = ''): array
    {
        $busqueda = $this->busqueda($busqueda, 40);
        return $this->leer(fn (): array => ['busqueda' => $busqueda,
            'tipos_servicio' => array_map(fn (array $fila): array => $this->publicar($fila), $this->repository->listar($busqueda))]);
    }

    private function buscar(int $id, bool $bloquear = false): array
    {
        return $this->repository->buscar($id, $bloquear) ?? throw static::error([], 'No se encontró el tipo de servicio.', 404);
    }

    public function obtener(mixed $id): array
    {
        $id = self::id($id, 255);
        return $this->leer(fn (): array => $this->publicar($this->buscar($id)));
    }

    private function unico(string $nombre, ?int $excepto = null): void
    {
        if ($this->repository->existeNombre($nombre, $excepto)) { throw static::error(['nombre' => 'Este nombre ya está registrado.'], 'Tipo de servicio duplicado.', 409); }
    }

    public function registrar(int $actor, array $datos): int
    {
        $datos = $this->validar($datos);
        return $this->escribir($actor, function () use ($datos): int {
            $this->unico($datos['nombre']);
            return $this->repository->insertar($datos);
        }, 'nombre');
    }

    public function modificar(int $actor, mixed $id, array $datos, mixed $versionEsperada): void
    {
        $id = self::id($id, 255); $datos = $this->validar($datos);
        $this->escribir($actor, function () use ($id, $datos, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            self::estado($fila['activo']);
            $this->unico($datos['nombre'], $id);
            $this->repository->actualizarNombre($id, $datos['nombre']);
        }, 'nombre');
    }

    public function cambiarEstado(int $actor, mixed $id, mixed $nuevo, mixed $versionEsperada, mixed $confirmacion): void
    {
        $id = self::id($id, 255); $nuevo = self::estado($nuevo); $this->confirmar($confirmacion);
        $this->escribir($actor, function () use ($id, $nuevo, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            $anterior = self::estado($fila['activo']);
            if ($nuevo === $anterior) { throw static::error([], 'El servicio ya tiene el estado solicitado.', 409); }
            $this->repository->cambiarEstado($id, $nuevo, $anterior);
        });
    }
}
