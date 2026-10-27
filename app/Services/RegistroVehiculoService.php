<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use SFATSA\Security\SessionStore;

/** Coordina revisión/confirmación sin confiar nuevamente en datos enviados por el cliente.
 * El futuro controlador debe autorizar al actor y comprobar CSRF antes de cada POST.
 */
final class RegistroVehiculoService
{
    private const CLAVE = 'vehiculos_registros';
    private const VIGENCIA = 900;
    private const MAX_RESUMENES = 5;

    public function __construct(
        private readonly VehiculoService $vehiculos,
        private readonly SessionStore $sesion,
        private readonly ?Closure $reloj = null
    ) {
    }

    private function ahora(): int
    {
        return $this->reloj === null ? time() : ($this->reloj)();
    }

    private function pendientes(): array
    {
        $pendientes = $this->sesion->obtener(self::CLAVE);
        if (!is_array($pendientes)) {
            return [];
        }
        $ahora = $this->ahora();
        return array_filter($pendientes, static fn (mixed $p): bool => is_array($p)
            && isset($p['creado'], $p['actor'], $p['datos']) && is_int($p['creado'])
            && is_array($p['datos']) && $p['creado'] <= $ahora && $ahora - $p['creado'] < self::VIGENCIA);
    }

    public function preparar(int $idActor, array $entrada): array
    {
        if ($idActor <= 0) {
            throw new ErrorGestionVehiculos([], 'La sesión del usuario no es válida.', 403);
        }
        $datos = $this->vehiculos->prepararRegistro($entrada);
        $pendientes = $this->pendientes();
        while (count($pendientes) >= self::MAX_RESUMENES) {
            array_shift($pendientes);
        }
        $token = bin2hex(random_bytes(32));
        $pendientes[$token] = ['actor' => $idActor, 'datos' => $datos, 'creado' => $this->ahora()];
        $this->sesion->guardar(self::CLAVE, $pendientes);
        return ['token' => $token, 'datos' => $datos];
    }

    public function obtenerResumen(int $idActor, mixed $token): array
    {
        if (!is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            throw new ErrorGestionVehiculos([], 'El resumen solicitado no es válido.', 400);
        }
        $pendientes = $this->pendientes();
        $this->sesion->guardar(self::CLAVE, $pendientes);
        if (!isset($pendientes[$token])) {
            throw new ErrorGestionVehiculos([], 'El resumen caducó o ya fue utilizado. Revise los datos nuevamente.', 409);
        }
        if ($pendientes[$token]['actor'] !== $idActor) {
            throw new ErrorGestionVehiculos([], 'El resumen no pertenece al usuario de la sesión.', 403);
        }
        return $pendientes[$token]['datos'];
    }

    private function consumir(string $token): void
    {
        $pendientes = $this->pendientes();
        unset($pendientes[$token]);
        $this->sesion->guardar(self::CLAVE, $pendientes);
    }

    public function cancelar(int $idActor, mixed $token): void
    {
        $this->obtenerResumen($idActor, $token);
        $this->consumir($token);
    }

    public function confirmar(int $idActor, mixed $token): void
    {
        $datos = $this->obtenerResumen($idActor, $token);
        // Un fallo de conexión durante COMMIT puede tener resultado incierto: no reintentar el token.
        $this->consumir($token);
        $this->vehiculos->registrar($idActor, $datos, '1');
    }
}
