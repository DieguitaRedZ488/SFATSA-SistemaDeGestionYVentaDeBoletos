<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use RuntimeException;

final class PdoGestionUsuarioRepository implements GestionUsuarioRepository
{
    private const COLUMNAS = 'u.id_usuario, u.id_rol, u.nombres, u.apellidos, u.usuario, u.activo, r.nombre AS rol';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function listar(string $busqueda): array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM usuario u INNER JOIN rol r ON r.id_rol = u.id_rol';
        $parametros = [];
        if ($busqueda !== '') {
            // ! es el escape explícito: %, _ y ! del buscador son texto literal.
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $sql .= " WHERE (u.nombres LIKE :nombres ESCAPE '!' OR u.apellidos LIKE :apellidos ESCAPE '!'"
                . " OR u.usuario LIKE :usuario ESCAPE '!')";
            $parametros = ['nombres' => $patron, 'apellidos' => $patron, 'usuario' => $patron];
        }
        $sql .= ' ORDER BY u.apellidos, u.nombres, u.id_usuario';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parametros);
        return $statement->fetchAll();
    }

    public function roles(): array
    {
        $statement = $this->connection->prepare(
            'SELECT id_rol, nombre FROM rol WHERE nombre IN (:administrador, :cajero, :checadora) ORDER BY nombre'
        );
        $statement->execute(['administrador' => 'ADMINISTRADOR', 'cajero' => 'CAJERO', 'checadora' => 'CHECADORA']);
        return $statement->fetchAll();
    }

    public function buscar(int $idUsuario, bool $bloquear = false): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM usuario u INNER JOIN rol r ON r.id_rol = u.id_rol'
            . ' WHERE u.id_usuario = :id_usuario LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id_usuario' => $idUsuario]);
        $fila = $statement->fetch();
        return $fila === false ? null : $fila;
    }

    public function existeUsuario(string $usuario, ?int $excepto = null): bool
    {
        $sql = 'SELECT id_usuario FROM usuario WHERE usuario = :usuario';
        $parametros = ['usuario' => $usuario];
        if ($excepto !== null) {
            $sql .= ' AND id_usuario <> :excepto';
            $parametros['excepto'] = $excepto;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);
        return $statement->fetch() !== false;
    }

    public function iniciarTransaccion(): void
    {
        if (!$this->connection->beginTransaction()) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function bloquearAdministracion(): void
    {
        $statement = $this->connection->prepare('SELECT id_rol FROM rol WHERE nombre = :nombre LIMIT 1 FOR UPDATE');
        $statement->execute(['nombre' => 'ADMINISTRADOR']);
        if ($statement->fetch() === false) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function administradoresActivos(): int
    {
        // Lectura bloqueante actual, no una instantánea anterior al bloqueo.
        $statement = $this->connection->prepare(
            'SELECT u.id_usuario FROM usuario u INNER JOIN rol r ON r.id_rol = u.id_rol'
            . ' WHERE r.nombre = :rol AND u.activo = :activo ORDER BY u.id_usuario FOR UPDATE'
        );
        $statement->execute(['rol' => 'ADMINISTRADOR', 'activo' => 1]);
        return count($statement->fetchAll());
    }

    public function insertar(array $datos, #[\SensitiveParameter] string $hash): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO usuario (id_rol, nombres, apellidos, usuario, hash_contrasena, activo)'
            . ' VALUES (:id_rol, :nombres, :apellidos, :usuario, :hash_contrasena, :activo)'
        );
        $statement->execute($datos + ['hash_contrasena' => $hash, 'activo' => 1]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function actualizar(int $idUsuario, array $datos): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuario SET nombres = :nombres, apellidos = :apellidos, usuario = :usuario, id_rol = :id_rol'
            . ' WHERE id_usuario = :id_usuario'
        );
        $statement->execute($datos + ['id_usuario' => $idUsuario]);
        // Cero filas cambiadas es válido cuando se guardan los mismos valores.
    }

    public function desactivar(int $idUsuario): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuario SET activo = :inactivo WHERE id_usuario = :id_usuario AND activo = :activo'
        );
        $statement->execute(['inactivo' => 0, 'id_usuario' => $idUsuario, 'activo' => 1]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function activar(int $idUsuario): void
    {
        $statement = $this->connection->prepare(
            'UPDATE usuario SET activo = :activo WHERE id_usuario = :id_usuario AND activo = :inactivo'
        );
        $statement->execute(['activo' => 1, 'id_usuario' => $idUsuario, 'inactivo' => 0]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function confirmarTransaccion(): void
    {
        if (!$this->connection->commit()) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function enTransaccion(): bool
    {
        return $this->connection->inTransaction();
    }

    public function revertirTransaccion(): void
    {
        if (!$this->connection->rollBack()) {
            throw new RuntimeException('Operación no disponible.');
        }
    }
}
