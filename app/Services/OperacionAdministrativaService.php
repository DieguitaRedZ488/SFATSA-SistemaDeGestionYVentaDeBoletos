<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use SFATSA\Repositories\GestionUsuarioRepository;
use Throwable;

/** Una transacción y un orden de bloqueos para las escrituras del Sprint 1. */
final class OperacionAdministrativaService
{
    public function __construct(private readonly GestionUsuarioRepository $repository)
    {
    }

    public function ejecutar(int $idActor, Closure $operacion): void
    {
        // Rechazar anidamiento sin revertir una transacción perteneciente al llamador.
        if ($this->repository->enTransaccion()) {
            throw new ErrorGestionUsuarios([], 'No se pudo iniciar la operación administrativa.', 503);
        }
        $iniciada = false;
        try {
            $this->repository->iniciarTransaccion();
            $iniciada = true;
            $this->repository->bloquearAdministracion();
            $actor = $this->repository->buscar($idActor, true);
            if ($actor === null || (int) ($actor['activo'] ?? 0) !== 1 || ($actor['rol'] ?? null) !== 'ADMINISTRADOR') {
                throw new ErrorGestionUsuarios([], 'Su cuenta ya no permite realizar esta operación.', 403);
            }
            // Los repositorios de la operación deben utilizar la misma PDO.
            $operacion();
            $this->repository->confirmarTransaccion();
        } catch (Throwable $error) {
            try {
                if ($iniciada && $this->repository->enTransaccion()) {
                    $this->repository->revertirTransaccion();
                }
            } catch (Throwable) {
                throw new ErrorGestionUsuarios([], 'No se pudo confirmar el estado de la operación. Revise el listado antes de repetirla.', 503);
            }
            // Cada servicio traduce restricciones PDO sin exponer la excepción original.
            throw $error;
        }
    }
}
