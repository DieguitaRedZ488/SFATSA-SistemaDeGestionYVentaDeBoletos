<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use PDOStatement;
use RuntimeException;

/** Infraestructura interna HU-04. Los identificadores provienen solo de constantes de clase. */
abstract class PdoRepositorioHU04
{
    protected const TABLA = '';
    protected const ID = '';
    protected const COLUMNAS = '';

    public function __construct(protected readonly PDO $connection)
    {
    }

    protected function consulta(string $sql, array $parametros = []): PDOStatement
    {
        $st = $this->connection->prepare($sql);
        $st->execute($parametros);
        return $st;
    }

    protected function transaccion(): void
    {
        if (!$this->connection->inTransaction()) { throw new RuntimeException('La operación requiere una transacción compartida.'); }
    }

    /** Rechaza esquemas con dependencias nuevas o cascadas que esta operación no gestiona. */
    protected function dependenciasEntrantesVerificadas(string $tabla, array $esperadas): bool
    {
        $st = $this->consulta(
            'SELECT k.TABLE_NAME, k.COLUMN_NAME, r.DELETE_RULE'
            . ' FROM information_schema.KEY_COLUMN_USAGE k'
            . ' INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r'
            . ' ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . ' AND r.TABLE_NAME = k.TABLE_NAME'
            . ' WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME = :tabla'
            . ' AND k.REFERENCED_COLUMN_NAME IS NOT NULL',
            ['tabla' => $tabla]
        );
        $reales = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $clave = $fila['TABLE_NAME'] . '.' . $fila['COLUMN_NAME'];
            if (!in_array($clave, $esperadas, true)
                || !in_array(strtoupper((string) $fila['DELETE_RULE']), ['RESTRICT', 'NO ACTION'], true)) {
                return false;
            }
            $reales[] = $clave;
        }
        sort($reales);
        sort($esperadas);
        return $reales === $esperadas;
    }

    protected function mutar(string $sql, array $parametros): PDOStatement
    {
        $this->transaccion();
        return $this->consulta($sql, $parametros);
    }

    protected function insertarId(string $sql, array $parametros): int
    {
        if ($this->mutar($sql, $parametros)->rowCount() !== 1) { throw new RuntimeException('No se pudo guardar.'); }
        $id = filter_var($this->connection->lastInsertId(), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) { throw new RuntimeException('Identificador no disponible.'); }
        return $id;
    }

    protected function buscarFila(int $id, bool $bloquear): ?array
    {
        if ($bloquear) { $this->transaccion(); }
        $st = $this->consulta('SELECT ' . static::COLUMNAS . ' FROM ' . static::TABLA
            . ' WHERE ' . static::ID . ' = :id LIMIT 1' . ($bloquear ? ' FOR UPDATE' : ''), ['id' => $id]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }

    protected function listarFilas(string $busqueda, array $columnas): array
    {
        $sql = 'SELECT ' . static::COLUMNAS . ' FROM ' . static::TABLA;
        $parametros = [];
        if ($busqueda !== '') {
            $partes = [];
            foreach ($columnas as $i => $columna) {
                $clave = 'q' . $i;
                $partes[] = $columna . " LIKE :" . $clave . " ESCAPE '!'";
                $parametros[$clave] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busqueda) . '%';
            }
            $sql .= ' WHERE (' . implode(' OR ', $partes) . ')';
        }
        return $this->consulta($sql . ' ORDER BY nombre, ' . static::ID, $parametros)->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function estadoFila(int $id, int $nuevo, int $anterior, string $columna): void
    {
        if ($this->mutar('UPDATE ' . static::TABLA . ' SET ' . $columna . ' = :nuevo WHERE '
            . static::ID . ' = :id AND ' . $columna . ' = :anterior', compact('id', 'nuevo', 'anterior'))->rowCount() !== 1) {
            throw new RuntimeException('No se pudo confirmar el cambio de estado.');
        }
    }
}
