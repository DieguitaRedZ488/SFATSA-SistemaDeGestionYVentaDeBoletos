<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use RuntimeException;

final class PdoConductorRepository implements ConductorRepository
{
    private const COLUMNAS = 'id_conductor, nombres, apellidos, numero_licencia, telefono, activo';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function listar(string $busqueda): array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM conductor';
        $parametros = [];
        if ($busqueda !== '') {
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            $sql .= " WHERE (nombres LIKE :nombres ESCAPE '!' OR apellidos LIKE :apellidos ESCAPE '!'"
                . " OR numero_licencia LIKE :licencia ESCAPE '!')";
            $parametros = ['nombres' => $patron, 'apellidos' => $patron, 'licencia' => $patron];
        }
        $statement = $this->connection->prepare($sql . ' ORDER BY apellidos, nombres, id_conductor');
        $statement->execute($parametros);
        return $statement->fetchAll();
    }

    public function buscar(int $idConductor, bool $bloquear = false): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNAS . ' FROM conductor WHERE id_conductor = :id_conductor LIMIT 1'
            . ($bloquear ? ' FOR UPDATE' : '')
        );
        $statement->execute(['id_conductor' => $idConductor]);
        $fila = $statement->fetch();
        return $fila === false ? null : $fila;
    }

    public function existeLicencia(string $licencia, ?int $excepto = null): bool
    {
        $sql = 'SELECT id_conductor FROM conductor WHERE numero_licencia = :licencia';
        $parametros = ['licencia' => $licencia];
        if ($excepto !== null) {
            $sql .= ' AND id_conductor <> :excepto';
            $parametros['excepto'] = $excepto;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);
        return $statement->fetch() !== false;
    }

    public function insertar(array $datos): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO conductor (nombres, apellidos, numero_licencia, telefono, activo)'
            . ' VALUES (:nombres, :apellidos, :numero_licencia, :telefono, :activo)'
        );
        $statement->execute($datos + ['activo' => 1]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }

    public function actualizar(int $idConductor, array $datos): void
    {
        $statement = $this->connection->prepare(
            'UPDATE conductor SET nombres = :nombres, apellidos = :apellidos,'
            . ' numero_licencia = :numero_licencia, telefono = :telefono WHERE id_conductor = :id_conductor'
        );
        $statement->execute($datos + ['id_conductor' => $idConductor]);
        // Conservar los mismos valores es válido; existencia comprobada bajo bloqueo.
    }

    public function cambiarEstado(int $idConductor, int $nuevoEstado, int $estadoAnterior): void
    {
        $statement = $this->connection->prepare(
            'UPDATE conductor SET activo = :nuevo WHERE id_conductor = :id_conductor AND activo = :anterior'
        );
        $statement->execute(['nuevo' => $nuevoEstado, 'id_conductor' => $idConductor, 'anterior' => $estadoAnterior]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Operación no disponible.');
        }
    }
}
