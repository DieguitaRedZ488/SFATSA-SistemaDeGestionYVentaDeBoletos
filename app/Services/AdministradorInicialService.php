<?php

declare(strict_types=1);

namespace SFATSA\Services;

use SFATSA\Repositories\AdministradorInicialRepository;
use Throwable;

final class AdministradorInicialService
{
    public function __construct(private readonly AdministradorInicialRepository $repository)
    {
    }

    /** Lectura preliminar para no pedir secretos cuando el alta no es necesaria. */
    public function necesitaAlta(): bool
    {
        try {
            $idRol = $this->repository->obtenerIdRolAdministrador();
            if ($idRol === null) {
                throw new ErrorAdministradorInicial('ROL');
            }
            return !$this->repository->existeAdministrador($idRol);
        } catch (ErrorAdministradorInicial $error) {
            throw $error;
        } catch (Throwable) {
            throw new ErrorAdministradorInicial();
        }
    }

    public function validarIdentidad(array $identidad): array
    {
        $campos = ['nombres' => 120, 'apellidos' => 120, 'usuario' => 50];
        if (count($identidad) !== 3 || array_diff(array_keys($identidad), array_keys($campos)) !== []) {
            throw new ErrorAdministradorInicial('DATOS');
        }
        foreach ($campos as $campo => $limite) {
            $valor = $identidad[$campo] ?? null;
            if (!is_string($valor) || preg_match('//u', $valor) !== 1
                || preg_match('/[^\\s\\p{Z}]/u', $valor) !== 1
                || preg_match_all('/./us', $valor) > $limite) {
                throw new ErrorAdministradorInicial(strtoupper($campo));
            }
        }
        // Conservar los valores; no imponer formatos ni normalizaciones de DEC-07.
        return $identidad;
    }

    private function validarContrasena(
        #[\SensitiveParameter] string $contrasena,
        #[\SensitiveParameter] string $confirmacion
    ): void {
        if (preg_match('//u', $contrasena) !== 1 || str_contains($contrasena, "\0")
            || preg_match('/[^\\s\\p{Z}]/u', $contrasena) !== 1
            || preg_match_all('/./us', $contrasena) < 12 || strlen($contrasena) > 72) {
            throw new ErrorAdministradorInicial('CONTRASENA');
        }
        if (!hash_equals($contrasena, $confirmacion)) {
            throw new ErrorAdministradorInicial('CONFIRMACION');
        }
    }

    /** true: creada; false: ya existe un administrador, sin modificarlo. */
    public function crear(
        array $identidad,
        #[\SensitiveParameter] string $contrasena,
        #[\SensitiveParameter] string $confirmacion
    ): bool {
        $bloqueado = false;
        $creado = false;
        $fallo = null;
        try {
            $identidad = $this->validarIdentidad($identidad);
            $this->validarContrasena($contrasena, $confirmacion);
            $bloqueado = $this->repository->adquirirBloqueo();
            if (!$bloqueado) {
                throw new ErrorAdministradorInicial('OCUPADO');
            }
            // La instantánea se obtiene después del mutex y antes del INSERT.
            $this->repository->iniciarTransaccion();
            $idRol = $this->repository->obtenerIdRolAdministrador();
            if ($idRol === null) {
                throw new ErrorAdministradorInicial('ROL');
            }
            if (!$this->repository->existeAdministrador($idRol)) {
                if ($this->repository->existeUsuario($identidad['usuario'])) {
                    throw new ErrorAdministradorInicial('DUPLICADO');
                }
                // bcrypt admite hasta 72 bytes; se rechaza el exceso sin truncarlo.
                $hash = password_hash($contrasena, PASSWORD_BCRYPT);
                if (!password_verify($contrasena, $hash)) {
                    throw new ErrorAdministradorInicial();
                }
                $this->repository->insertar($idRol, $identidad, $hash);
                unset($hash);
                $this->repository->confirmarTransaccion();
                $creado = true;
            }
        } catch (Throwable $error) {
            $fallo = $error instanceof ErrorAdministradorInicial ? $error : new ErrorAdministradorInicial();
        } finally {
            try {
                if ($this->repository->enTransaccion()) {
                    $this->repository->revertirTransaccion();
                }
            } catch (Throwable) {
                $fallo = new ErrorAdministradorInicial();
            }
            // Intentar liberar incluso si ROLLBACK falló; cerrar es el respaldo.
            try {
                if ($bloqueado) {
                    $this->repository->liberarBloqueo();
                }
            } catch (Throwable) {
                $fallo = new ErrorAdministradorInicial();
            } finally {
                $this->repository->cerrar();
            }
        }
        if ($fallo !== null) {
            throw $fallo;
        }
        return $creado;
    }

    public function cerrar(): void
    {
        $this->repository->cerrar();
    }
}
