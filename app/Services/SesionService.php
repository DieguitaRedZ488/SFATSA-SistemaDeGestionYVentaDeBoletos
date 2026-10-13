<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use SFATSA\Security\SessionStore;

final class SesionService
{
    private const INACTIVIDAD = 1800;
    private readonly Closure $reloj;

    public function __construct(private readonly SessionStore $store, ?Closure $reloj = null)
    {
        $this->reloj = $reloj ?? static fn (): int => time();
    }

    public function iniciar(\SFATSA\Models\UsuarioAutenticado $usuario): void
    {
        $this->store->limpiar();
        $this->store->regenerar();
        $this->store->guardar('autenticacion', [
            'id_usuario' => $usuario->idUsuario,
            'ultima_actividad' => ($this->reloj)(),
            'rol_anterior' => $usuario->rol,
        ]);
    }

    public function idUsuario(): ?int
    {
        $datos = $this->store->obtener('autenticacion');
        if ($datos === null) {
            return null;
        }
        $ahora = ($this->reloj)();
        if (!is_array($datos) || !is_int($datos['id_usuario'] ?? null) || $datos['id_usuario'] < 1
            || !is_int($datos['ultima_actividad'] ?? null) || $datos['ultima_actividad'] > $ahora
            || $ahora - $datos['ultima_actividad'] >= self::INACTIVIDAD) {
            $this->invalidar();
            return null;
        }
        return $datos['id_usuario'];
    }

    /** Solo después de revalidar la cuenta/rol mediante la BD. */
    public function registrarActividad(string $rolActual): void
    {
        $datos = $this->store->obtener('autenticacion');
        if (!is_array($datos)) {
            return;
        }
        if (($datos['rol_anterior'] ?? null) !== $rolActual) {
            $this->store->regenerar();
            $this->store->quitar('csrf');
        }
        $datos['ultima_actividad'] = ($this->reloj)();
        $datos['rol_anterior'] = $rolActual;
        $this->store->guardar('autenticacion', $datos);
    }

    public function invalidar(): void
    {
        $this->store->limpiar();
        $this->store->regenerar();
    }

    public function cerrar(): void
    {
        $this->store->destruir();
    }
}
