<?php

declare(strict_types=1);

use SFATSA\Repositories\GestionUsuarioRepository;
use SFATSA\Repositories\UsuarioRepository;
use SFATSA\Security\SessionStore;

/** Dobles aislados: sin configuración privada, constructor PDO o conexión real. */
final class GestionUsuariosFicticia implements GestionUsuarioRepository, UsuarioRepository
{
    public array $filas = [];
    public array $catalogo = [
        ['id_rol' => 7, 'nombre' => 'ADMINISTRADOR'],
        ['id_rol' => 13, 'nombre' => 'CAJERO'],
        ['id_rol' => 29, 'nombre' => 'CHECADORA'],
    ];
    public array $eventos = [];
    public ?string $fallar = null;
    public ?int $conteoAdministradores = null;
    private ?array $instantanea = null;

    public function __construct()
    {
        foreach ([41 => 7, 42 => 13, 43 => 29, 44 => 13, 45 => 7] as $id => $rol) {
            $this->filas[$id] = [
                'id_usuario' => $id, 'id_rol' => $rol, 'nombres' => 'Persona ficticia ' . $id,
                'apellidos' => 'Prueba Álvarez', 'usuario' => 'ficticio' . $id,
                'activo' => $id === 44 ? 0 : 1, 'hash_contrasena' => 'HASH_FICTICIO_NO_PUBLICABLE',
            ];
        }
    }

    private function filaPublica(array $fila): array
    {
        unset($fila['hash_contrasena']);
        foreach ($this->catalogo as $rol) {
            if ($rol['id_rol'] === $fila['id_rol']) { $fila['rol'] = $rol['nombre']; }
        }
        return $fila;
    }

    private function evento(string $nombre): void
    {
        $this->eventos[] = $nombre;
        if ($this->fallar === $nombre) { throw new RuntimeException('FICTICIO_INTERNO_SENSIBLE'); }
        if ($this->fallar === 'duplicado' && $nombre === 'insertar') {
            $error = new PDOException('FICTICIO_INTERNO_SENSIBLE');
            $error->errorInfo = ['23000', 1062, 'FICTICIO_INTERNO_SENSIBLE'];
            throw $error;
        }
        if ($this->fallar === 'fk' && $nombre === 'insertar') {
            $error = new PDOException('FICTICIO_INTERNO_SENSIBLE');
            $error->errorInfo = ['23000', 1452, 'FICTICIO_INTERNO_SENSIBLE'];
            throw $error;
        }
    }

    public function listar(string $busqueda): array
    {
        $this->evento('listar');
        return array_values(array_map(fn (array $fila): array => $this->filaPublica($fila), array_filter(
            $this->filas, static fn (array $fila): bool => $busqueda === ''
                || stripos($fila['nombres'], $busqueda) !== false || stripos($fila['apellidos'], $busqueda) !== false
                || stripos($fila['usuario'], $busqueda) !== false
        )));
    }
    public function roles(): array { return $this->catalogo; }
    public function buscar(int $idUsuario, bool $bloquear = false): ?array
    {
        $this->eventos[] = $bloquear ? 'buscar_bloqueado' : 'buscar';
        return isset($this->filas[$idUsuario]) ? $this->filaPublica($this->filas[$idUsuario]) : null;
    }
    public function existeUsuario(string $usuario, ?int $excepto = null): bool
    {
        foreach ($this->filas as $id => $fila) {
            if ($id !== $excepto && strcasecmp($fila['usuario'], $usuario) === 0) { return true; }
        }
        return false;
    }
    public function iniciarTransaccion(): void { $this->evento('iniciar'); $this->instantanea = $this->filas; }
    public function bloquearAdministracion(): void { $this->evento('bloquear'); }
    public function administradoresActivos(): int
    {
        $this->evento('administradores_bloqueados');
        return $this->conteoAdministradores ?? count(array_filter($this->filas,
            fn (array $fila): bool => $fila['activo'] === 1 && $this->filaPublica($fila)['rol'] === 'ADMINISTRADOR'));
    }
    public function insertar(array $datos, #[\SensitiveParameter] string $hash): void
    {
        $this->evento('insertar');
        $id = max(array_keys($this->filas)) + 1;
        $this->filas[$id] = $datos + ['id_usuario' => $id, 'hash_contrasena' => $hash, 'activo' => 1];
    }
    public function actualizar(int $idUsuario, array $datos): void
    {
        $this->evento('actualizar');
        $this->filas[$idUsuario] = array_replace($this->filas[$idUsuario], $datos);
    }
    public function desactivar(int $idUsuario): void { $this->evento('desactivar'); $this->filas[$idUsuario]['activo'] = 0; }
    public function activar(int $idUsuario): void { $this->evento('activar'); $this->filas[$idUsuario]['activo'] = 1; }
    public function confirmarTransaccion(): void { $this->evento('confirmar'); $this->instantanea = null; }
    public function enTransaccion(): bool { return $this->instantanea !== null; }
    public function revertirTransaccion(): void
    {
        $this->evento('revertir');
        if ($this->instantanea !== null) { $this->filas = $this->instantanea; }
        $this->instantanea = null;
    }
    public function buscarPorUsuario(string $usuario): ?array
    {
        foreach ($this->filas as $fila) {
            if ($fila['usuario'] === $usuario) { return $this->filaPublica($fila) + ['hash_contrasena' => $fila['hash_contrasena']]; }
        }
        return null;
    }
    public function buscarPorId(int $idUsuario): ?array { return $this->buscar($idUsuario); }
}

final class SesionGestionFicticia implements SessionStore
{
    public array $datos = [];
    public int $regeneraciones = 0;
    public function obtener(string $clave): mixed { return $this->datos[$clave] ?? null; }
    public function guardar(string $clave, mixed $valor): void { $this->datos[$clave] = $valor; }
    public function quitar(string $clave): void { unset($this->datos[$clave]); }
    public function limpiar(): void { $this->datos = []; }
    public function regenerar(): void { $this->regeneraciones++; }
    public function destruir(): void { $this->limpiar(); }
}
