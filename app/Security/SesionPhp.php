<?php

declare(strict_types=1);

namespace SFATSA\Security;

use RuntimeException;

final class SesionPhp implements SessionStore
{
    public function __construct(bool $https)
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Sesión no disponible.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', '1800');
        session_name('SFATSA_SESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new RuntimeException('Sesión no disponible.');
        }
    }

    public function obtener(string $clave): mixed
    {
        return $_SESSION[$clave] ?? null;
    }

    public function guardar(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public function quitar(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    public function limpiar(): void
    {
        $_SESSION = [];
    }

    public function regenerar(): void
    {
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('No se pudo renovar la sesión.');
        }
    }

    public function destruir(): void
    {
        $this->limpiar();
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $parametros['path'],
            'domain' => $parametros['domain'],
            'secure' => $parametros['secure'],
            'httponly' => $parametros['httponly'],
            'samesite' => $parametros['samesite'],
        ]);
        if (!session_destroy()) {
            throw new RuntimeException('No se pudo cerrar la sesión.');
        }
    }
}
