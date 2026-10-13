<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SFATSA\Services\ErrorAdministradorInicial;

final class PdoAdministradorInicialRepository implements AdministradorInicialRepository
{
    private const BLOQUEO = 'sfatza.administrador-inicial.v1';

    /** Instancia exclusiva, no persistente, creada por ConnectionFactory. */
    public function __construct(private ?PDO $connection)
    {
    }

    private function preparar(string $sql): PDOStatement
    {
        if ($this->connection === null) {
            throw new RuntimeException('Repositorio cerrado.');
        }
        $statement = $this->connection->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Operación de repositorio no disponible.');
        }
        return $statement;
    }

    public function obtenerIdRolAdministrador(): ?int
    {
        // Durante el alta, conservar el rol leído hasta confirmar/revertir.
        $sql = 'SELECT id_rol, nombre FROM rol WHERE nombre = :nombre LIMIT 1';
        if ($this->enTransaccion()) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->preparar($sql);
        $statement->execute(['nombre' => 'ADMINISTRADOR']);
        $rol = $statement->fetch(PDO::FETCH_ASSOC);
        if ($rol === false || $rol['nombre'] !== 'ADMINISTRADOR') {
            return null;
        }
        $id = filter_var($rol['id_rol'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        return $id === false ? null : $id;
    }

    public function existeAdministrador(int $idRol): bool
    {
        // También una cuenta inactiva impide volver a usar el bootstrap.
        $statement = $this->preparar('SELECT 1 FROM usuario WHERE id_rol = :id_rol LIMIT 1');
        $statement->execute(['id_rol' => $idRol]);
        return $statement->fetchColumn() !== false;
    }

    public function existeUsuario(string $usuario): bool
    {
        // MariaDB aplica la collation real; UNIQUE complementa esta comprobación.
        $statement = $this->preparar('SELECT 1 FROM usuario WHERE usuario = :usuario LIMIT 1');
        $statement->execute(['usuario' => $usuario]);
        return $statement->fetchColumn() !== false;
    }

    public function adquirirBloqueo(): bool
    {
        $statement = $this->preparar('SELECT GET_LOCK(:nombre, 0)');
        $statement->execute(['nombre' => self::BLOQUEO]);
        $result = $statement->fetchColumn();
        if ($result === null || $result === false) {
            throw new RuntimeException('Bloqueo no disponible.');
        }
        return (int) $result === 1;
    }

    public function liberarBloqueo(): void
    {
        $statement = $this->preparar('SELECT RELEASE_LOCK(:nombre)');
        $statement->execute(['nombre' => self::BLOQUEO]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo confirmar la liberación.');
        }
    }

    public function iniciarTransaccion(): void
    {
        if ($this->connection === null || $this->connection->inTransaction() || !$this->connection->beginTransaction()) {
            throw new RuntimeException('No se pudo iniciar la transacción.');
        }
    }

    public function confirmarTransaccion(): void
    {
        if ($this->connection === null || !$this->connection->commit()) {
            throw new RuntimeException('No se pudo confirmar la transacción.');
        }
    }

    public function revertirTransaccion(): void
    {
        if ($this->connection === null || !$this->connection->rollBack()) {
            throw new RuntimeException('No se pudo revertir la transacción.');
        }
    }

    public function enTransaccion(): bool
    {
        return $this->connection !== null && $this->connection->inTransaction();
    }

    public function insertar(int $idRol, array $identidad, #[\SensitiveParameter] string $hash): void
    {
        $statement = $this->preparar(
            'INSERT INTO usuario (id_rol, nombres, apellidos, usuario, hash_contrasena, activo)'
            . ' VALUES (:id_rol, :nombres, :apellidos, :usuario, :hash_contrasena, 1)'
        );
        try {
            $statement->execute([
                'id_rol' => $idRol,
                'nombres' => $identidad['nombres'],
                'apellidos' => $identidad['apellidos'],
                'usuario' => $identidad['usuario'],
                'hash_contrasena' => $hash,
            ]);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ErrorAdministradorInicial('DUPLICADO');
            }
            // No conservar errorInfo, SQL, argumentos ni excepción encadenada.
            throw new ErrorAdministradorInicial();
        }
        if ($statement->rowCount() !== 1) {
            throw new ErrorAdministradorInicial();
        }
    }

    public function cerrar(): void
    {
        // El CLI no conserva otras referencias a esta conexión exclusiva.
        // Desconectar también libera GET_LOCK y revierte una transacción abierta.
        $this->connection = null;
    }
}
