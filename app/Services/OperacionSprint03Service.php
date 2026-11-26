<?php
declare(strict_types=1);
namespace SFATSA\Services;

use Closure;
use SFATSA\Repositories\GestionUsuarioRepository;
use Throwable;

/** Mismo cerrojo y orden que administración; el permiso se valida por operación. */
final class OperacionSprint03Service
{
    public function __construct(private readonly GestionUsuarioRepository $usuarios) {}
    public function ejecutar(int $actor, string $rol, Closure $operacion): mixed
    {
        if (!in_array($rol, ['ADMINISTRADOR', 'CHECADORA'], true) || $this->usuarios->enTransaccion()) {
            throw new ErrorGestionSprint03([], 'No se pudo iniciar la operación.', 503);
        }
        $iniciada = false;
        try {
            $this->usuarios->iniciarTransaccion(); $iniciada = true;
            // Coordina también revocación de permisos y edición de catálogos de sprints previos.
            $this->usuarios->bloquearAdministracion();
            $usuario = $this->usuarios->buscar($actor, true);
            if ($usuario === null || (int) $usuario['activo'] !== 1 || $usuario['rol'] !== $rol) {
                throw new ErrorGestionSprint03([], 'Su cuenta ya no permite realizar esta operación.', 403);
            }
            $resultado = $operacion();
            $this->usuarios->confirmarTransaccion();
            return $resultado;
        } catch (Throwable $e) {
            try { if ($iniciada && $this->usuarios->enTransaccion()) { $this->usuarios->revertirTransaccion(); } }
            catch (Throwable) { throw new ErrorGestionSprint03([], 'No se pudo confirmar el resultado. Consulte el listado antes de repetir.', 503); }
            if ($e instanceof ErrorGestionSprint03) { throw $e; }
            if ($e instanceof \PDOException && in_array((int) ($e->errorInfo[1] ?? 0), [1062, 1205, 1213, 1451, 1452], true)) {
                throw new ErrorGestionSprint03([], 'La información cambió o está siendo utilizada. Consulte nuevamente.', 409);
            }
            throw new ErrorGestionSprint03([], 'No se pudo confirmar el resultado. Consulte el listado antes de repetir.', 503);
        }
    }
}
