<?php

declare(strict_types=1);

use SFATSA\Repositories\{GestionUsuarioRepository, ParadaRepository, RutaRepository, RutaParadaRepository, TipoServicioRepository};

/** Almacén sintético compartido; no abre conexiones ni lee configuración. */
final class DatosHU04Ficticios
{
    public array $paradas = [], $rutas = [], $asociaciones = [];
    public array $viajes = [], $tarifas = [];
    public bool $dependenciasDisponibles = true;
    public bool $servicioDisponible = true;
    public array $tipos = [1 => ['id_tipo_servicio' => 1, 'nombre' => 'DIRECTO', 'controla_capacidad' => 1, 'activo' => 1],
        2 => ['id_tipo_servicio' => 2, 'nombre' => 'PARADAS_CONTINUAS', 'controla_capacidad' => 0, 'activo' => 1]];
    public array $eventos = [];
    public ?string $fallar = null;
    public ?Closure $transaccion = null;
    public function __construct()
    {
        for ($i = 1; $i <= 60; $i++) { $this->paradas[$i] = ['id_parada' => $i, 'nombre' => 'Parada ' . $i, 'referencia' => null, 'activo' => 1]; }
        $this->paradas[60]['activo'] = 0;
    }
    public function foto(): array { return [$this->paradas, $this->rutas, $this->asociaciones, $this->tipos, $this->viajes, $this->tarifas]; }
    public function restaurar(array $foto): void { [$this->paradas, $this->rutas, $this->asociaciones, $this->tipos, $this->viajes, $this->tarifas] = $foto; }
    public function evento(string $evento, bool $escritura = false): void
    {
        $this->eventos[] = $evento;
        if ($escritura && !($this->transaccion !== null && ($this->transaccion)())) { throw new RuntimeException('SIN_TRANSACCION'); }
        if ($this->fallar === $evento) { throw new RuntimeException('INTERNO_SENSIBLE'); }
        if ($escritura && str_starts_with($this->fallar ?? '', 'sql:')) {
            $partes = explode(':', $this->fallar);
            $e = new PDOException('INTERNO_SENSIBLE');
            $e->errorInfo = ['23000', (int) $partes[1], "Duplicate entry 'SENSIBLE' for key '" . ($partes[2] ?? 'desconocido') . "'"];
            throw $e;
        }
    }
    public function listar(array $filas, string $q, array $campos): array
    {
        $this->evento('listar');
        return array_values(array_filter($filas, static function (array $fila) use ($q, $campos): bool {
            foreach ($campos as $campo) { if ($q === '' || mb_stripos($fila[$campo] ?? '', $q) !== false) { return true; } }
            return false;
        }));
    }
}

final class ParadasHU04Ficticias implements ParadaRepository
{
    public function __construct(private DatosHU04Ficticios $d) {}
    public function listar(string $busqueda): array { return $this->d->listar($this->d->paradas, $busqueda, ['nombre', 'referencia']); }
    public function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        $normalizar = static fn (string $n): string => mb_strtoupper(trim(preg_replace('/[\s\p{Z}]+/u', ' ', $n)));
        foreach ($this->d->paradas as $id => $fila) { if ($id !== $excepto && $normalizar($fila['nombre']) === $normalizar($nombre)) { return true; } }
        return false;
    }
    public function buscar(int $idParada, bool $bloquear = false): ?array { $this->d->evento('parada_buscar', $bloquear); return $this->d->paradas[$idParada] ?? null; }
    public function bloquearPorIds(array $ids): array
    {
        sort($ids, SORT_NUMERIC); $filas = [];
        foreach ($ids as $id) { $fila = $this->buscar($id, true); if ($fila !== null) { $filas[$id] = $fila; } }
        return $filas;
    }
    public function insertar(array $datos): int { $this->d->evento('parada_insertar', true); $id = max(array_keys($this->d->paradas)) + 1; $this->d->paradas[$id] = $datos + ['id_parada' => $id, 'activo' => 1]; return $id; }
    public function actualizar(int $idParada, array $datos): void { $this->d->evento('parada_actualizar', true); $this->d->paradas[$idParada] = array_replace($this->d->paradas[$idParada], $datos); }
    public function cambiarEstado(int $idParada, int $nuevo, int $anterior): void { $this->d->evento('parada_estado', true); $this->d->paradas[$idParada]['activo'] = $nuevo; }
    public function dependenciasVerificadas(): bool { $this->d->evento('parada_dependencias'); return $this->d->dependenciasDisponibles; }
    public function tieneRutas(int $idParada): bool { $this->d->evento('parada_uso'); return (bool) array_filter($this->d->asociaciones, static fn (array $a): bool => $a['id_parada'] === $idParada); }
    public function eliminar(int $idParada): void { $this->d->evento('parada_eliminar', true); unset($this->d->paradas[$idParada]); }
}

final class RutasHU04Ficticias implements RutaRepository
{
    public function __construct(private DatosHU04Ficticios $d) {}
    private function publicar(array $fila): array
    {
        $fila += ['id_tipo_servicio' => null];
        return $fila + ['tipo_servicio_nombre' => $this->d->tipos[$fila['id_tipo_servicio']]['nombre'] ?? null];
    }
    public function listar(string $busqueda): array { return array_map(fn (array $f): array => $this->publicar($f), $this->d->listar($this->d->rutas, $busqueda, ['codigo', 'nombre'])); }
    public function buscar(int $idRuta, bool $bloquear = false): ?array { $this->d->evento('ruta_buscar', $bloquear); return isset($this->d->rutas[$idRuta]) ? $this->publicar($this->d->rutas[$idRuta]) : null; }
    public function existeCodigo(string $codigo, ?int $excepto = null): bool
    {
        foreach ($this->d->rutas as $id => $fila) { if ($id !== $excepto && mb_strtoupper($fila['codigo']) === mb_strtoupper($codigo)) { return true; } } return false;
    }
    public function servicioDisponible(): bool { return $this->d->servicioDisponible; }
    public function actualizar(int $idRuta, array $datos): void
    {
        $this->d->evento('ruta_actualizar', true);
        $this->d->rutas[$idRuta] = array_replace($this->d->rutas[$idRuta], array_intersect_key($datos, array_flip(['codigo', 'nombre', 'id_tipo_servicio'])));
    }
    public function insertar(array $datos): int
    {
        $this->d->evento('ruta_insertar', true); $id = count($this->d->rutas) + 1;
        $this->d->rutas[$id] = ['id_ruta' => $id, 'codigo' => $datos['codigo'], 'nombre' => $datos['nombre'], 'activa' => 1, 'id_tipo_servicio' => $datos['id_tipo_servicio'] ?? null]; return $id;
    }
    public function cambiarEstado(int $idRuta, int $nuevo, int $anterior): void { $this->d->evento('ruta_estado', true); $this->d->rutas[$idRuta]['activa'] = $nuevo; }
    public function dependenciasVerificadas(): bool { $this->d->evento('ruta_dependencias'); return $this->d->dependenciasDisponibles; }
    public function tieneViajes(int $idRuta): bool { $this->d->evento('ruta_viajes'); return isset($this->d->viajes[$idRuta]); }
    public function eliminar(int $idRuta): void { $this->d->evento('ruta_eliminar', true); unset($this->d->rutas[$idRuta]); }
}

final class AsociacionesHU04Ficticias implements RutaParadaRepository
{
    public function __construct(private DatosHU04Ficticios $d) {}
    public function insertarPuntos(int $idRuta, array $idsParadas): void
    {
        foreach ($idsParadas as $i => $id) {
            $this->d->evento('asociacion:' . ($i + 1), true);
            $this->d->asociaciones[] = ['id_ruta_parada' => count($this->d->asociaciones) + 1, 'id_ruta' => $idRuta, 'id_parada' => $id, 'orden' => $i + 1, 'distancia_acumulada_km' => null];
        }
    }
    public function listarPorRutaOrdenado(int $idRuta): array
    {
        $filas = array_values(array_filter($this->d->asociaciones, fn (array $f): bool => $f['id_ruta'] === $idRuta));
        usort($filas, fn (array $a, array $b): int => $a['orden'] <=> $b['orden']);
        return array_map(fn (array $f): array => $f + $this->d->paradas[$f['id_parada']], $filas);
    }
    public function dependenciasVerificadas(): bool { $this->d->evento('asociacion_dependencias'); return $this->d->dependenciasDisponibles; }
    public function bloquearPorRuta(int $idRuta): array
    {
        $this->d->evento('asociacion_bloquear', true);
        return $this->listarPorRutaOrdenado($idRuta);
    }
    public function tieneTarifasPorRuta(int $idRuta): bool { $this->d->evento('asociacion_tarifas'); return isset($this->d->tarifas[$idRuta]); }
    public function eliminarPorRuta(int $idRuta): void
    {
        $this->d->evento('asociacion_eliminar', true);
        $this->d->asociaciones = array_values(array_filter($this->d->asociaciones, static fn (array $a): bool => $a['id_ruta'] !== $idRuta));
    }
}

final class TiposHU04Ficticios implements TipoServicioRepository
{
    public function __construct(private DatosHU04Ficticios $d) {}
    public function listar(string $busqueda): array { return $this->d->listar($this->d->tipos, $busqueda, ['nombre']); }
    public function buscar(int $idTipoServicio, bool $bloquear = false): ?array { $this->d->evento('tipo_buscar', $bloquear); return $this->d->tipos[$idTipoServicio] ?? null; }
    public function existeNombre(string $nombre, ?int $excepto = null): bool
    {
        foreach ($this->d->tipos as $id => $fila) { if ($id !== $excepto && mb_strtoupper($fila['nombre']) === mb_strtoupper($nombre)) { return true; } } return false;
    }
    public function insertar(array $datos): int { $this->d->evento('tipo_insertar', true); $id = max(array_keys($this->d->tipos)) + 1; $this->d->tipos[$id] = $datos + ['id_tipo_servicio' => $id, 'controla_capacidad' => 1, 'activo' => 1]; return $id; }
    public function actualizarNombre(int $idTipoServicio, string $nombre): void { $this->d->evento('tipo_actualizar', true); $this->d->tipos[$idTipoServicio]['nombre'] = $nombre; }
    public function cambiarEstado(int $idTipoServicio, int $nuevo, int $anterior): void { $this->d->evento('tipo_estado', true); $this->d->tipos[$idTipoServicio]['activo'] = $nuevo; }
}

final class AdministracionHU04Ficticia implements GestionUsuarioRepository
{
    private ?array $foto = null;
    public ?Closure $trasBloqueo = null;
    public function __construct(public GestionUsuariosFicticia $usuarios, private DatosHU04Ficticios $d) { $d->transaccion = fn (): bool => $this->enTransaccion(); }
    public function listar(string $busqueda): array { return $this->usuarios->listar($busqueda); }
    public function roles(): array { return $this->usuarios->roles(); }
    public function buscar(int $idUsuario, bool $bloquear = false): ?array { return $this->usuarios->buscar($idUsuario, $bloquear); }
    public function existeUsuario(string $usuario, ?int $excepto = null): bool { return $this->usuarios->existeUsuario($usuario, $excepto); }
    public function iniciarTransaccion(): void { $this->usuarios->iniciarTransaccion(); $this->foto = $this->d->foto(); }
    public function bloquearAdministracion(): void { $this->usuarios->bloquearAdministracion(); if ($this->trasBloqueo !== null) { ($this->trasBloqueo)(); } }
    public function administradoresActivos(): int { return $this->usuarios->administradoresActivos(); }
    public function insertar(array $datos, #[SensitiveParameter] string $hash): void { $this->usuarios->insertar($datos, $hash); }
    public function actualizar(int $idUsuario, array $datos): void { $this->usuarios->actualizar($idUsuario, $datos); }
    public function desactivar(int $idUsuario): void { $this->usuarios->desactivar($idUsuario); }
    public function activar(int $idUsuario): void { $this->usuarios->activar($idUsuario); }
    public function confirmarTransaccion(): void { $this->usuarios->confirmarTransaccion(); $this->foto = null; }
    public function enTransaccion(): bool { return $this->usuarios->enTransaccion(); }
    public function revertirTransaccion(): void { $this->usuarios->revertirTransaccion(); if ($this->foto !== null) { $this->d->restaurar($this->foto); } $this->foto = null; }
}
