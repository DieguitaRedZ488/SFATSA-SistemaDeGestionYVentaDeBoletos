<?php

declare(strict_types=1);

use SFATSA\Repositories\VehiculoRepository;
use SFATSA\Repositories\GestionUsuarioRepository;

/** Datos sintéticos y fallos deterministas: nunca abre PDO ni consulta configuración. */
final class VehiculosFicticios implements VehiculoRepository
{
    public array $filas = [
        11 => ['id_vehiculo' => 11, 'numero_economico' => 'ECO-11', 'placa' => 'A-00011-A', 'descripcion' => 'Autobús sintético', 'capacidad' => 40, 'estado' => 'ACTIVO'],
        12 => ['id_vehiculo' => 12, 'numero_economico' => 'ECO-12', 'placa' => 'B-00012-B', 'descripcion' => 'Otro autobús sintético', 'capacidad' => 30, 'estado' => 'INACTIVO'],
    ];
    public array $eventos = [];
    public ?string $fallar = null;
    public ?Closure $enTransaccion = null;

    private function evento(string $nombre, bool $escritura = false): void
    {
        $this->eventos[] = $nombre;
        if ($escritura && ($this->enTransaccion === null || !($this->enTransaccion)())) {
            throw new RuntimeException('ESCRITURA_FUERA_TRANSACCION_FICTICIA');
        }
        if ($this->fallar === $nombre) { throw new RuntimeException('FICTICIO_INTERNO_SENSIBLE'); }
        if ($escritura && str_starts_with($this->fallar ?? '', 'duplicado:')) {
            $error = new PDOException('FICTICIO_INTERNO_SENSIBLE');
            $error->errorInfo = ['23000', 1062, "Duplicate entry 'FICTICIO' for key '" . substr($this->fallar, 10) . "'"];
            throw $error;
        }
    }
    public function listar(string $busqueda): array
    {
        $this->evento('listar');
        return array_values(array_filter($this->filas, static fn (array $fila): bool => $busqueda === ''
            || mb_stripos($fila['placa'], $busqueda, 0, 'UTF-8') !== false
            || mb_stripos($fila['numero_economico'], $busqueda, 0, 'UTF-8') !== false));
    }
    public function buscar(int $idVehiculo, bool $bloquear = false): ?array
    {
        $this->evento($bloquear ? 'buscar_bloqueado' : 'buscar');
        return $this->filas[$idVehiculo] ?? null;
    }
    private function existe(string $campo, string $valor, ?int $excepto): bool
    {
        $this->evento('existe');
        foreach ($this->filas as $id => $fila) {
            if ($id !== $excepto && mb_strtoupper($fila[$campo], 'UTF-8') === mb_strtoupper($valor, 'UTF-8')) { return true; }
        }
        return false;
    }
    public function existePlaca(string $placa, ?int $excepto = null): bool { return $this->existe('placa', $placa, $excepto); }
    public function existeNumeroEconomico(string $numeroEconomico, ?int $excepto = null): bool { return $this->existe('numero_economico', $numeroEconomico, $excepto); }
    public function insertar(array $datos): void
    {
        $this->evento('insertar', true);
        $id = max(array_keys($this->filas)) + 1;
        $this->filas[$id] = $datos + ['id_vehiculo' => $id];
    }
    public function actualizar(int $idVehiculo, array $datos): void
    {
        $this->evento('actualizar', true);
        $this->filas[$idVehiculo] = array_replace($this->filas[$idVehiculo], $datos);
    }
    public function cambiarEstado(int $idVehiculo, string $nuevoEstado, string $estadoAnterior): void
    {
        $this->evento('estado', true);
        if ($this->filas[$idVehiculo]['estado'] !== $estadoAnterior) { throw new RuntimeException('CONFLICTO_FICTICIO'); }
        $this->filas[$idVehiculo]['estado'] = $nuevoEstado;
    }
}

/** Coordina dos repositorios ficticios bajo la misma transacción en memoria. */
final class AdministracionVehiculosFicticia implements GestionUsuarioRepository
{
    private ?array $instantanea = null;
    public ?Closure $trasBloqueo = null;
    public function __construct(public GestionUsuariosFicticia $usuarios, private VehiculosFicticios $vehiculos)
    {
        $vehiculos->enTransaccion = fn (): bool => $this->enTransaccion();
    }
    public function listar(string $busqueda): array { return $this->usuarios->listar($busqueda); }
    public function roles(): array { return $this->usuarios->roles(); }
    public function buscar(int $idUsuario, bool $bloquear = false): ?array { return $this->usuarios->buscar($idUsuario, $bloquear); }
    public function existeUsuario(string $usuario, ?int $excepto = null): bool { return $this->usuarios->existeUsuario($usuario, $excepto); }
    public function iniciarTransaccion(): void { $this->usuarios->iniciarTransaccion(); $this->instantanea = $this->vehiculos->filas; }
    public function bloquearAdministracion(): void
    {
        $this->usuarios->bloquearAdministracion();
        if ($this->trasBloqueo !== null) { ($this->trasBloqueo)(); }
    }
    public function administradoresActivos(): int { return $this->usuarios->administradoresActivos(); }
    public function insertar(array $datos, #[\SensitiveParameter] string $hash): void { $this->usuarios->insertar($datos, $hash); }
    public function actualizar(int $idUsuario, array $datos): void { $this->usuarios->actualizar($idUsuario, $datos); }
    public function desactivar(int $idUsuario): void { $this->usuarios->desactivar($idUsuario); }
    public function activar(int $idUsuario): void { $this->usuarios->activar($idUsuario); }
    public function confirmarTransaccion(): void { $this->usuarios->confirmarTransaccion(); $this->instantanea = null; }
    public function enTransaccion(): bool { return $this->usuarios->enTransaccion(); }
    public function revertirTransaccion(): void
    {
        $this->usuarios->revertirTransaccion();
        if ($this->instantanea !== null) { $this->vehiculos->filas = $this->instantanea; }
        $this->instantanea = null;
    }
}
