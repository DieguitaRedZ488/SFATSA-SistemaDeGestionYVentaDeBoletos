<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Repositories\ParadaRepository;

final class ParadaService extends ServicioHU04
{
    protected const ERROR = ErrorGestionParadas::class;
    protected const CAMPOS_VERSION = ['id_parada', 'nombre', 'referencia', 'activo'];

    public function __construct(private readonly ParadaRepository $repository, OperacionAdministrativaService $operaciones)
    {
        parent::__construct($operaciones);
    }

    private function validar(array $datos): array
    {
        $this->campos($datos, ['nombre', 'referencia']);
        $nombre = $datos['nombre'] ?? null;
        if (is_string($nombre)) { $nombre = preg_replace('/\p{Z}+/u', ' ', $nombre) ?? $nombre; }
        return ['nombre' => $this->texto($nombre, 'nombre', 120),
            'referencia' => $this->texto($datos['referencia'] ?? null, 'referencia', 255, true)];
    }

    public function listar(mixed $busqueda = ''): array
    {
        $busqueda = $this->busqueda($busqueda, 120);
        return $this->leer(fn (): array => ['busqueda' => $busqueda,
            'paradas' => array_map(fn (array $fila): array => $this->publicar($fila), $this->repository->listar($busqueda))]);
    }

    private function buscar(int $id, bool $bloquear = false): array
    {
        return $this->repository->buscar($id, $bloquear) ?? throw static::error([], 'No se encontró la parada.', 404);
    }

    public function obtener(mixed $id): array
    {
        $id = self::id($id);
        return $this->leer(fn (): array => $this->publicar($this->buscar($id)));
    }

    public function prepararEliminacion(mixed $id): array
    {
        $id = self::id($id);
        return $this->leer(function () use ($id): array {
            if (!$this->repository->dependenciasVerificadas()) {
                throw static::error([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            return $this->publicar($this->buscar($id));
        });
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
        $id = self::id($id); $datos = $this->validar($datos);
        $this->escribir($actor, function () use ($id, $datos, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            self::estado($fila['activo']);
            $this->unico($datos['nombre'], $id);
            $this->repository->actualizar($id, $datos);
        }, 'nombre');
    }

    private function unico(string $nombre, ?int $excepto = null): void
    {
        if ($this->repository->existeNombre($nombre, $excepto)) {
            throw static::error(['nombre' => 'Ya existe una parada con este nombre, aunque esté inactiva o tenga otra referencia.'], 'Nombre de parada duplicado.', 409);
        }
    }

    public function cambiarEstado(int $actor, mixed $id, mixed $nuevo, mixed $versionEsperada, mixed $confirmacion): void
    {
        $id = self::id($id); $nuevo = self::estado($nuevo); $this->confirmar($confirmacion);
        $this->escribir($actor, function () use ($id, $nuevo, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            $anterior = self::estado($fila['activo']);
            if ($nuevo === $anterior) { throw static::error([], 'La parada ya tiene el estado solicitado.', 409); }
            $this->repository->cambiarEstado($id, $nuevo, $anterior);
        });
    }

    public function eliminar(int $actor, mixed $id, mixed $versionEsperada, mixed $confirmacion): void
    {
        $id = self::id($id);
        $this->confirmar($confirmacion);
        $this->escribir($actor, function () use ($id, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            if (!$this->repository->dependenciasVerificadas()) {
                throw static::error([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            if ($this->repository->tieneRutas($id)) {
                throw static::error([], 'No se puede eliminar: la parada pertenece a uno o más recorridos. Desactívela para impedir que se use en nuevas rutas.', 409);
            }
            $this->repository->eliminar($id);
        });
    }
}
