<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Repositories\UsuarioRepository;

final class AutenticacionService
{
    // Hash ficticio para efectuar password_verify también sin una cuenta encontrada.
    // No corresponde a una credencial de SFATSA; nunca habilita una sesión.
    private const HASH_FICTICIO = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

    public function __construct(private readonly UsuarioRepository $repository)
    {
    }

    public function verificar(mixed $usuario, #[\SensitiveParameter] mixed $contrasena): ?UsuarioAutenticado
    {
        if (!is_string($usuario) || preg_match('//u', $usuario) !== 1
            || preg_match('/[^\\s\\p{Z}]/u', $usuario) !== 1 || preg_match_all('/./us', $usuario) > 50
            || !is_string($contrasena) || preg_match('//u', $contrasena) !== 1
            || str_contains($contrasena, "\0") || strlen($contrasena) > 72
            || preg_match_all('/./us', $contrasena) < 12) {
            return null;
        }
        $fila = $this->repository->buscarPorUsuario($usuario);
        $hash = is_string($fila['hash_contrasena'] ?? null) ? $fila['hash_contrasena'] : self::HASH_FICTICIO;
        $coincide = password_verify($contrasena, $hash);
        return $coincide && $fila !== null ? $this->usuarioActivo($fila) : null;
    }

    public function consultarActivo(int $idUsuario): ?UsuarioAutenticado
    {
        $fila = $this->repository->buscarPorId($idUsuario);
        return $fila === null ? null : $this->usuarioActivo($fila);
    }

    private function usuarioActivo(array $fila): ?UsuarioAutenticado
    {
        if ((int) ($fila['activo'] ?? 0) !== 1
            || !in_array($fila['rol'] ?? null, ['ADMINISTRADOR', 'CAJERO', 'CHECADORA'], true)
            || (int) ($fila['id_usuario'] ?? 0) < 1) {
            return null;
        }
        return new UsuarioAutenticado(
            (int) $fila['id_usuario'],
            (string) $fila['nombres'],
            (string) $fila['apellidos'],
            (string) $fila['usuario'],
            $fila['rol']
        );
    }
}
