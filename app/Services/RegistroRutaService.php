<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use SFATSA\Security\SessionStore;

/** Revisión privada de rutas, siguiendo RegistroVehiculoService sin cambiar HU-02. */
final class RegistroRutaService
{
    private const CLAVE = 'rutas_registros';
    private const VIGENCIA = 900;
    private const MAX_RESUMENES = 5;

    public function __construct(private readonly RutaService $rutas, private readonly SessionStore $store, private readonly ?Closure $reloj = null) {}

    private function ahora(): int { return $this->reloj === null ? time() : ($this->reloj)(); }

    private function pendientes(): array
    {
        $pendientes = $this->store->obtener(self::CLAVE); $ahora = $this->ahora();
        return is_array($pendientes) ? array_filter($pendientes, static fn (mixed $p): bool =>
            is_array($p) && is_int($p['actor'] ?? null) && is_array($p['datos'] ?? null)
            && is_int($p['creado'] ?? null) && $p['creado'] <= $ahora && $ahora - $p['creado'] < self::VIGENCIA) : [];
    }

    public function preparar(int $actor, array $entrada): array
    {
        if ($actor <= 0) { throw new ErrorGestionRutas([], 'La sesión no es válida.', 403); }
        $datos = $this->rutas->prepararRegistro($entrada); $pendientes = $this->pendientes();
        while (count($pendientes) >= self::MAX_RESUMENES) { array_shift($pendientes); }
        $token = bin2hex(random_bytes(32));
        $pendientes[$token] = ['actor' => $actor, 'creado' => $this->ahora(), 'datos' => $datos];
        $this->store->guardar(self::CLAVE, $pendientes);
        return ['token' => $token, 'datos' => $datos];
    }

    public function obtenerResumen(int $actor, mixed $token): array
    {
        if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) { throw new ErrorGestionRutas([], 'El resumen no es válido.', 400); }
        $pendientes = $this->pendientes(); $this->store->guardar(self::CLAVE, $pendientes);
        if (!isset($pendientes[$token])) { throw new ErrorGestionRutas([], 'El resumen caducó o ya fue utilizado. Revise los datos nuevamente.', 409); }
        if ($pendientes[$token]['actor'] !== $actor) { throw new ErrorGestionRutas([], 'El resumen no pertenece al administrador actual.', 403); }
        return $pendientes[$token]['datos'];
    }

    private function consumir(string $token): void
    {
        $pendientes = $this->pendientes(); unset($pendientes[$token]); $this->store->guardar(self::CLAVE, $pendientes);
    }

    public function cancelar(int $actor, mixed $token): void { $this->obtenerResumen($actor, $token); $this->consumir($token); }

    public function confirmar(int $actor, mixed $token): int
    {
        $datos = $this->obtenerResumen($actor, $token);
        // Consumir ANTES de persistir: tampoco reintentar con este token ante COMMIT incierto.
        $this->consumir($token);
        return $this->rutas->registrar($actor, $datos, '1');
    }
}
