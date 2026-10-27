<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use RuntimeException;

final class PdoVehiculoRepository implements VehiculoRepository
{
    private const COLUMNAS = 'id_vehiculo, numero_economico, placa, descripcion, capacidad, estado';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function listar(string $busqueda): array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM vehiculo';
        $parametros = [];
        if ($busqueda !== '') {
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $sql .= " WHERE (placa LIKE :placa ESCAPE '!' OR numero_economico LIKE :economico ESCAPE '!')";
            $parametros = ['placa' => $patron, 'economico' => $patron];
        }
        $statement = $this->connection->prepare($sql . ' ORDER BY numero_economico, id_vehiculo');
        $statement->execute($parametros);
        return $statement->fetchAll();
    }

    public function buscar(int $idVehiculo, bool $bloquear = false): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM vehiculo WHERE id_vehiculo = :id_vehiculo LIMIT 1'
            . ($bloquear ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id_vehiculo' => $idVehiculo]);
        $fila = $statement->fetch();
        return $fila === false ? null : $fila;
    }

    public function existePlaca(string $placa, ?int $excepto = null): bool
    {
        return $this->existe('placa', $placa, $excepto);
    }

    public function existeNumeroEconomico(string $numeroEconomico, ?int $excepto = null): bool
    {
        return $this->existe('numero_economico', $numeroEconomico, $excepto);
    }

    /** La columna procede exclusivamente de los dos métodos de este repositorio. */
    private function existe(string $columna, string $valor, ?int $excepto): bool
    {
        $sql = 'SELECT id_vehiculo FROM vehiculo WHERE ' . $columna . ' = :valor';
        $parametros = ['valor' => $valor];
        if ($excepto !== null) {
            $sql .= ' AND id_vehiculo <> :excepto';
            $parametros['excepto'] = $excepto;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);
        return $statement->fetch() !== false;
    }

    public function insertar(array $datos): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO vehiculo (numero_economico, placa, descripcion, capacidad, estado)'
            . ' VALUES (:numero_economico, :placa, :descripcion, :capacidad, :estado)'
        );
        $statement->execute([
            'numero_economico' => $datos['numero_economico'], 'placa' => $datos['placa'],
            'descripcion' => $datos['descripcion'], 'capacidad' => $datos['capacidad'], 'estado' => $datos['estado'],
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function actualizar(int $idVehiculo, array $datos): void
    {
        $statement = $this->connection->prepare(
            'UPDATE vehiculo SET numero_economico = :numero_economico, placa = :placa,'
            . ' descripcion = :descripcion, capacidad = :capacidad WHERE id_vehiculo = :id_vehiculo'
        );
        $statement->execute([
            'numero_economico' => $datos['numero_economico'], 'placa' => $datos['placa'],
            'descripcion' => $datos['descripcion'], 'capacidad' => $datos['capacidad'], 'id_vehiculo' => $idVehiculo,
        ]);
        // Los mismos valores son válidos; el servicio comprobó existencia bajo bloqueo.
    }

    public function cambiarEstado(int $idVehiculo, string $nuevoEstado, string $estadoAnterior): void
    {
        $statement = $this->connection->prepare(
            'UPDATE vehiculo SET estado = :nuevo WHERE id_vehiculo = :id_vehiculo AND estado = :anterior'
        );
        $statement->execute(['nuevo' => $nuevoEstado, 'id_vehiculo' => $idVehiculo, 'anterior' => $estadoAnterior]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function dependenciasVerificadas(): bool
    {
        $statement = $this->connection->prepare(
            'SELECT k.TABLE_NAME, k.COLUMN_NAME, r.DELETE_RULE'
            . ' FROM information_schema.KEY_COLUMN_USAGE k'
            . ' INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r'
            . ' ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . ' AND r.TABLE_NAME = k.TABLE_NAME'
            . ' WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = :tabla'
            . ' AND k.REFERENCED_COLUMN_NAME IS NOT NULL'
        );
        $statement->execute(['tabla' => 'vehiculo']);
        $reales = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $clave = $fila['TABLE_NAME'] . '.' . $fila['COLUMN_NAME'];
            if (!in_array($clave, ['mantenimiento.id_vehiculo', 'viaje.id_vehiculo'], true)
                || !in_array(strtoupper((string) $fila['DELETE_RULE']), ['RESTRICT', 'NO ACTION'], true)) {
                return false;
            }
            $reales[] = $clave;
        }
        sort($reales);
        return $reales === ['mantenimiento.id_vehiculo', 'viaje.id_vehiculo'];
    }

    public function tieneViajes(int $idVehiculo): bool
    {
        $statement = $this->connection->prepare('SELECT id_viaje FROM viaje WHERE id_vehiculo = :id LIMIT 1');
        $statement->execute(['id' => $idVehiculo]);
        return $statement->fetch() !== false;
    }

    public function tieneMantenimientos(int $idVehiculo): bool
    {
        $statement = $this->connection->prepare('SELECT id_mantenimiento FROM mantenimiento WHERE id_vehiculo = :id LIMIT 1');
        $statement->execute(['id' => $idVehiculo]);
        return $statement->fetch() !== false;
    }

    public function eliminar(int $idVehiculo): void
    {
        if (!$this->connection->inTransaction()) {
            throw new RuntimeException('Operación no disponible.');
        }
        $statement = $this->connection->prepare('DELETE FROM vehiculo WHERE id_vehiculo = :id');
        $statement->execute(['id' => $idVehiculo]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }
}
