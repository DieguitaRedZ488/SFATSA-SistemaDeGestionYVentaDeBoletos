<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Repositories\ParadaRepository;
use SFATSA\Repositories\RutaParadaRepository;
use SFATSA\Repositories\RutaRepository;
use SFATSA\Repositories\TipoServicioRepository;

final class RutaService extends ServicioHU04
{
    protected const ERROR = ErrorGestionRutas::class;
    protected const CAMPOS_VERSION = ['id_ruta', 'codigo', 'nombre', 'activa', 'id_tipo_servicio'];

    public function __construct(
        private readonly RutaRepository $repository,
        private readonly ParadaRepository $paradas,
        private readonly RutaParadaRepository $asociaciones,
        OperacionAdministrativaService $operaciones,
        private readonly ?TipoServicioRepository $tipos = null
    ) {
        parent::__construct($operaciones);
    }

    private function validar(array $datos): array
    {
        $this->campos($datos, ['codigo', 'nombre', 'origen', 'destino', 'intermedias', 'id_tipo_servicio']);
        $codigo = $this->texto($datos['codigo'] ?? null, 'codigo', 30, false, true);
        $nombre = $this->texto($datos['nombre'] ?? null, 'nombre', 120);
        try { $id_tipo_servicio = self::id($datos['id_tipo_servicio'] ?? null, 255); }
        catch (ErrorGestionHU04) { throw static::error(['id_tipo_servicio' => 'Seleccione un tipo de servicio válido y activo.']); }
        try {
            $origen = self::id($datos['origen'] ?? null);
            $destino = self::id($datos['destino'] ?? null);
            $intermedias = $datos['intermedias'] ?? [];
            if (array_key_exists('intermedias', $datos) && $datos['intermedias'] === null) { throw static::error(); }
            if (!is_array($intermedias) || !array_is_list($intermedias) || count($intermedias) > 3) { throw static::error(); }
            $intermedias = array_map(fn (mixed $id): int => self::id($id), $intermedias);
        } catch (ErrorGestionHU04) {
            throw static::error(['recorrido' => 'Seleccione origen, destino y hasta tres paradas intermedias: máximo cinco puntos.']);
        }
        $puntos = [$origen, ...$intermedias, $destino];
        if (count(array_unique($puntos, SORT_REGULAR)) !== count($puntos)) {
            throw static::error(['recorrido' => 'No repita paradas; el origen y destino deben ser distintos.']);
        }
        return compact('codigo', 'nombre', 'origen', 'destino', 'intermedias', 'id_tipo_servicio');
    }

    private function puntos(array $datos): array { return [$datos['origen'], ...$datos['intermedias'], $datos['destino']]; }

    private function validarParadas(array $ids, bool $bloquear): void
    {
        $filas = $bloquear ? $this->paradas->bloquearPorIds($ids) : [];
        foreach ($ids as $id) {
            $fila = $bloquear ? ($filas[$id] ?? null) : $this->paradas->buscar($id);
            if ($fila === null || (int) $fila['activo'] !== 1) {
                throw static::error(['recorrido' => 'Todas las paradas del recorrido deben existir y estar activas.']);
            }
        }
    }

    private function unico(string $codigo, ?int $excepto = null): void
    {
        if ($this->repository->existeCodigo($codigo, $excepto)) { throw static::error(['codigo' => 'Este código ya está registrado.'], 'Código de ruta duplicado.', 409); }
    }

    public function admiteServicio(): bool { return $this->leer(fn (): bool => $this->repository->servicioDisponible()); }

    private function exigirServicio(): void
    {
        if (!$this->repository->servicioDisponible() || $this->tipos === null) {
            throw static::error([], 'El registro y la actualización requieren aplicar la migración 004 de tipos de servicio. No se guardaron cambios.', 503);
        }
    }

    private function validarTipo(int $id, bool $bloquear, int $puntos): void
    {
        $tipo = $this->tipos?->buscar($id, $bloquear);
        if ($tipo === null || (int) $tipo['activo'] !== 1) {
            throw static::error(['id_tipo_servicio' => 'El tipo de servicio no existe o está inactivo.']);
        }
        if (strtoupper(trim((string) $tipo['nombre'])) === 'DIRECTO' && $puntos !== 2) {
            throw static::error(['recorrido' => 'El servicio directo únicamente permite origen y destino.']);
        }
    }

    /** Validación previa sin INSERT, transacción ni bloqueos; cancelar no llama a registrar. */
    public function prepararRegistro(array $datos): array
    {
        $datos = $this->validar($datos);
        return $this->leer(function () use ($datos): array {
            $this->exigirServicio();
            $this->validarTipo($datos['id_tipo_servicio'], false, count($this->puntos($datos)));
            $this->unico($datos['codigo']);
            $this->validarParadas($this->puntos($datos), false);
            return $datos;
        });
    }

    public function registrar(int $actor, array $datos, mixed $confirmacion): int
    {
        $this->confirmar($confirmacion); $datos = $this->validar($datos);
        return $this->escribir($actor, function () use ($datos): int {
            $this->exigirServicio();
            $this->validarTipo($datos['id_tipo_servicio'], true, count($this->puntos($datos)));
            $ids = $this->puntos($datos);
            $this->validarParadas($ids, true);
            $this->unico($datos['codigo']);
            $id = $this->repository->insertar($datos);
            $this->asociaciones->insertarPuntos($id, $ids);
            return $id;
        }, 'codigo');
    }

    public function listar(mixed $busqueda = ''): array
    {
        $busqueda = $this->busqueda($busqueda, 120);
        return $this->leer(fn (): array => ['busqueda' => $busqueda,
            'rutas' => array_map(fn (array $fila): array => $this->publicar($fila), $this->repository->listar($busqueda))]);
    }

    private function buscar(int $id, bool $bloquear = false): array
    {
        return $this->repository->buscar($id, $bloquear) ?? throw static::error([], 'No se encontró la ruta.', 404);
    }

    private function versionEliminacion(array $ruta, array $paradas): string
    {
        $recorrido = [];
        foreach ($paradas as $parada) {
            $punto = [];
            foreach (['id_ruta_parada', 'id_parada', 'orden', 'distancia_acumulada_km', 'nombre', 'referencia', 'activo'] as $campo) {
                if (!array_key_exists($campo, $parada)) {
                    throw static::error([], 'La información del recorrido no está disponible.', 503);
                }
                $punto[$campo] = $parada[$campo] === null ? null : (string) $parada[$campo];
            }
            $recorrido[] = $punto;
        }
        return hash('sha256', serialize([$this->version($ruta), $recorrido]));
    }

    public function obtener(mixed $id): array
    {
        $id = self::id($id);
        return $this->leer(fn (): array => $this->publicar($this->buscar($id)) + ['paradas' => $this->asociaciones->listarPorRutaOrdenado($id)]);
    }

    public function prepararActualizacion(mixed $id): array
    {
        $id = self::id($id);
        return $this->leer(function () use ($id): array {
            $this->exigirServicio();
            $ruta = $this->buscar($id);
            if ($this->repository->tieneViajes($id)) { throw static::error([], 'No se puede actualizar una ruta con viajes asociados.', 409); }
            $paradas = $this->asociaciones->listarPorRutaOrdenado($id);
            $ruta['paradas'] = $paradas;
            $ruta['version'] = $this->versionEliminacion($ruta, $paradas);
            return $ruta;
        });
    }

    public function modificar(int $actor, mixed $id, array $datos, mixed $versionEsperada): void
    {
        $id = self::id($id); $datos = $this->validar($datos);
        $this->escribir($actor, function () use ($id, $datos, $versionEsperada): void {
            $this->exigirServicio();
            $ruta = $this->buscar($id, true);
            // El bloqueo de ruta impide nuevas referencias FK de viaje hasta terminar.
            if ($this->repository->tieneViajes($id)) { throw static::error([], 'No se puede actualizar una ruta con viajes asociados.', 409); }
            $anteriores = $this->asociaciones->bloquearPorRuta($id);
            if (!is_string($versionEsperada) || preg_match('/\A[a-f0-9]{64}\z/', $versionEsperada) !== 1) { throw static::error([], 'Versión no válida.', 400); }
            if (!hash_equals($this->versionEliminacion($ruta, $anteriores), $versionEsperada)) { throw static::error([], 'La ruta o sus paradas cambiaron. Consulte de nuevo antes de actualizar.', 409); }
            $ids = $this->puntos($datos);
            $this->validarTipo($datos['id_tipo_servicio'], true, count($ids));
            $this->validarParadas($ids, true);
            $this->unico($datos['codigo'], $id);
            $cambiaRecorrido = array_map('intval', array_column($anteriores, 'id_parada')) !== $ids;
            if ($cambiaRecorrido) {
                if (!$this->asociaciones->dependenciasVerificadas()) { throw static::error([], 'Las dependencias del recorrido no están verificadas.', 503); }
                if ($this->asociaciones->tieneTarifasPorRuta($id)) { throw static::error(['recorrido' => 'No se puede reemplazar un recorrido con tarifas asociadas. Sus referencias deben conservarse.'], 'El recorrido tiene información relacionada.', 409); }
            }
            $this->repository->actualizar($id, $datos);
            if ($cambiaRecorrido) {
                $this->asociaciones->eliminarPorRuta($id);
                $this->asociaciones->insertarPuntos($id, $ids);
            }
        }, 'codigo');
    }

    public function prepararEliminacion(mixed $id): array
    {
        $id = self::id($id);
        return $this->leer(function () use ($id): array {
            if (!$this->repository->dependenciasVerificadas() || !$this->asociaciones->dependenciasVerificadas()) {
                throw static::error([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            $ruta = $this->buscar($id);
            $paradas = $this->asociaciones->listarPorRutaOrdenado($id);
            $publicada = $this->publicar($ruta);
            $publicada['paradas'] = $paradas;
            $publicada['version'] = $this->versionEliminacion($ruta, $paradas);
            return $publicada;
        });
    }

    public function cambiarEstado(int $actor, mixed $id, mixed $nuevo, mixed $versionEsperada, mixed $confirmacion): void
    {
        $id = self::id($id); $nuevo = self::estado($nuevo); $this->confirmar($confirmacion);
        $this->escribir($actor, function () use ($id, $nuevo, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            $this->comprobarVersion($fila, $versionEsperada);
            $anterior = self::estado($fila['activa']);
            if ($nuevo === $anterior) { throw static::error([], 'La ruta ya tiene el estado solicitado.', 409); }
            $this->repository->cambiarEstado($id, $nuevo, $anterior);
        });
    }

    public function eliminar(int $actor, mixed $id, mixed $versionEsperada, mixed $confirmacion): void
    {
        $id = self::id($id);
        $this->confirmar($confirmacion);
        $this->escribir($actor, function () use ($id, $versionEsperada): void {
            $fila = $this->buscar($id, true);
            if (!$this->repository->dependenciasVerificadas() || !$this->asociaciones->dependenciasVerificadas()) {
                throw static::error([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            if ($this->repository->tieneViajes($id)) {
                throw static::error([], 'No se puede eliminar: existen viajes registrados para esta ruta.', 409);
            }
            $paradas = $this->asociaciones->bloquearPorRuta($id);
            if (!is_string($versionEsperada) || preg_match('/\A[a-f0-9]{64}\z/', $versionEsperada) !== 1
                || !hash_equals($this->versionEliminacion($fila, $paradas), $versionEsperada)) {
                throw static::error([], 'Los datos del recorrido cambiaron. Revise nuevamente antes de eliminar.', 409);
            }
            if ($this->asociaciones->tieneTarifasPorRuta($id)) {
                throw static::error([], 'No se puede eliminar: existen tarifas asociadas a puntos de esta ruta.', 409);
            }
            $this->asociaciones->eliminarPorRuta($id);
            $this->repository->eliminar($id);
        });
    }
}
