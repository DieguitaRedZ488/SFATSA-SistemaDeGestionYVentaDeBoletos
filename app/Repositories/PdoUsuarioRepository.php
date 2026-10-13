<?php

declare(strict_types=1);

namespace SFATSA\Repositories;

use PDO;
use RuntimeException;

final class PdoUsuarioRepository implements UsuarioRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function buscarPorUsuario(string $usuario): ?array
    {
        return $this->consultar(
            'SELECT u.id_usuario, u.nombres, u.apellidos, u.usuario, u.activo,'
            . ' u.hash_contrasena, r.nombre AS rol'
            . ' FROM usuario u INNER JOIN rol r ON r.id_rol = u.id_rol'
            . ' WHERE u.usuario = :usuario LIMIT 1',
            ['usuario' => $usuario]
        );
    }

    public function buscarPorId(int $idUsuario): ?array
    {
        return $this->consultar(
            'SELECT u.id_usuario, u.nombres, u.apellidos, u.usuario, u.activo, r.nombre AS rol'
            . ' FROM usuario u INNER JOIN rol r ON r.id_rol = u.id_rol'
            . ' WHERE u.id_usuario = :id_usuario LIMIT 1',
            ['id_usuario' => $idUsuario]
        );
    }

    private function consultar(string $sql, array $parametros): ?array
    {
        $statement = $this->connection->prepare($sql);
        if ($statement === false || !$statement->execute($parametros)) {
            throw new RuntimeException('Consulta de autenticación no disponible.');
        }
        $fila = $statement->fetch(PDO::FETCH_ASSOC);
        return $fila === false ? null : $fila;
    }
}
