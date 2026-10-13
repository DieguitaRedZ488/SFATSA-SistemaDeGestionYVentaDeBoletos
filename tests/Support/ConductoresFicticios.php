<?php

declare(strict_types=1);

use SFATSA\Repositories\ConductorRepository;
use SFATSA\Repositories\GestionUsuarioRepository;

final class ConductoresFicticios implements ConductorRepository
{
    public array $filas = [
        11 => ['id_conductor' => 11, 'nombres' => 'Conductor ficticio', 'apellidos' => 'Prueba Álvarez', 'numero_licencia' => 'LICEN000000011', 'telefono' => '555 000 0011', 'activo' => 1],
        12 => ['id_conductor' => 12, 'nombres' => 'Persona ficticia', 'apellidos' => 'Segundo Apellido', 'numero_licencia' => null, 'telefono' => null, 'activo' => 0],
        13 => ['id_conductor' => 13, 'nombres' => 'Conductor tercero', 'apellidos' => 'Prueba', 'numero_licencia' => 'LICEN000000013', 'telefono' => null, 'activo' => 1],
    ];
    public array $eventos = [];
    public ?string $fallar = null;
    public ?Closure $enTransaccion = null;

    private function escribir(string $evento): void
    {
        $this->eventos[] = $evento;
        if ($this->enTransaccion === null || !($this->enTransaccion)()) { throw new RuntimeException('ESCRITURA_FUERA_TRANSACCION_FICTICIA'); }
        if ($this->fallar === $evento) { throw new RuntimeException('FICTICIO_INTERNO_SENSIBLE'); }
        if ($this->fallar === 'duplicado') {
            $error = new PDOException('FICTICIO_INTERNO_SENSIBLE');
            $error->errorInfo = ['23000', 1062, 'FICTICIO_INTERNO_SENSIBLE'];
            throw $error;
        }
    }

    public function listar(string $busqueda): array
    {
        $this->eventos[] = 'listar';
        return array_values(array_filter($this->filas, static fn (array $fila): bool => $busqueda === ''
            || stripos($fila['nombres'], $busqueda) !== false || stripos($fila['apellidos'], $busqueda) !== false
            || ($fila['numero_licencia'] !== null && stripos($fila['numero_licencia'], $busqueda) !== false)));
    }
    public function buscar(int $idConductor, bool $bloquear = false): ?array
    {
        $this->eventos[] = $bloquear ? 'buscar_bloqueado' : 'buscar';
        return $this->filas[$idConductor] ?? null;
    }
    public function existeLicencia(string $licencia, ?int $excepto = null): bool
    {
        foreach ($this->filas as $id => $fila) {
            if ($id !== $excepto && $fila['numero_licencia'] !== null && strcasecmp($fila['numero_licencia'], $licencia) === 0) { return true; }
        }
        return false;
    }
    public function insertar(array $datos): void
    {
        $this->escribir('insertar');
        $id = max(array_keys($this->filas)) + 1;
        $this->filas[$id] = $datos + ['id_conductor' => $id, 'activo' => 1];
    }
    public function actualizar(int $idConductor, array $datos): void
    {
        $this->escribir('actualizar');
        $this->filas[$idConductor] = array_replace($this->filas[$idConductor], $datos);
    }
    public function cambiarEstado(int $idConductor, int $nuevoEstado, int $estadoAnterior): void
    {
        $this->escribir('estado');
        if ($this->filas[$idConductor]['activo'] !== $estadoAnterior) { throw new RuntimeException('CONFLICTO_FICTICIO'); }
        $this->filas[$idConductor]['activo'] = $nuevoEstado;
    }
}

/** Coordina dos repositorios ficticios bajo la misma transacción en memoria. */
final class AdministracionConductoresFicticia implements GestionUsuarioRepository
{
    private ?array $instantanea = null;
    public ?Closure $trasBloqueo = null;
    public function __construct(public GestionUsuariosFicticia $usuarios, private ConductoresFicticios $conductores)
    {
        $conductores->enTransaccion = fn (): bool => $this->enTransaccion();
    }
    public function listar(string $busqueda): array { return $this->usuarios->listar($busqueda); }
    public function roles(): array { return $this->usuarios->roles(); }
    public function buscar(int $idUsuario, bool $bloquear = false): ?array { return $this->usuarios->buscar($idUsuario, $bloquear); }
    public function existeUsuario(string $usuario, ?int $excepto = null): bool { return $this->usuarios->existeUsuario($usuario, $excepto); }
    public function iniciarTransaccion(): void { $this->usuarios->iniciarTransaccion(); $this->instantanea = $this->conductores->filas; }
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
        if ($this->instantanea !== null) { $this->conductores->filas = $this->instantanea; }
        $this->instantanea = null;
    }
}
