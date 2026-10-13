<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use PDOException;
use SFATSA\Repositories\ConductorRepository;
use Throwable;

final class ConductorService
{
    public function __construct(
        private readonly ConductorRepository $repository,
        private readonly OperacionAdministrativaService $operaciones
    ) {
    }

    public static function id(mixed $valor): int
    {
        try {
            return UsuarioService::id($valor);
        } catch (ErrorGestionUsuarios) {
            throw new ErrorGestionConductores([], 'El identificador del conductor no es válido.', 400);
        }
    }

    public static function estado(mixed $valor): int
    {
        if (!in_array($valor, ['0', '1', 0, 1], true)) {
            throw new ErrorGestionConductores([], 'El estado solicitado no es válido.', 400);
        }
        return (int) $valor;
    }

    private function texto(mixed $valor, int $limite, string $campo, bool $obligatorio): ?string
    {
        if ($valor === null) {
            if (!$obligatorio) {
                return null;
            }
            throw new ErrorGestionConductores([$campo => 'Este campo es obligatorio.']);
        }
        if (!is_string($valor) || preg_match('//u', $valor) !== 1 || str_contains($valor, "\0")) {
            throw new ErrorGestionConductores([$campo => 'Introduzca un texto válido.']);
        }
        $valor = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $valor) ?? '';
        if ($valor === '') {
            if ($obligatorio) {
                throw new ErrorGestionConductores([$campo => 'Este campo es obligatorio.']);
            }
            return null;
        }
        if (preg_match_all('/./us', $valor) > $limite) {
            throw new ErrorGestionConductores([$campo => 'Utilice como máximo ' . $limite . ' caracteres.']);
        }
        return $valor;
    }

    private function licencia(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            throw new ErrorGestionConductores(['numero_licencia' => 'Este campo es obligatorio.']);
        }
        if (!is_string($valor) || preg_match('//u', $valor) !== 1 || str_contains($valor, "\0")) {
            throw new ErrorGestionConductores(['numero_licencia' => 'Introduzca un texto válido.']);
        }
        $valor = strtoupper($valor);
        if (preg_match('/\A[A-Z0-9]{14}\z/', $valor) !== 1) {
            throw new ErrorGestionConductores([
                'numero_licencia' => 'Use exactamente 14 caracteres alfanuméricos (A-Z y 0-9), sin espacios ni símbolos.',
            ]);
        }
        return $valor;
    }

    private function validar(array $entrada): array
    {
        $datos = [];
        $errores = [];
        foreach (['nombres' => 120, 'apellidos' => 120, 'numero_licencia' => 40, 'telefono' => 25] as $campo => $limite) {
            if ($campo === 'numero_licencia') {
                try {
                    $datos[$campo] = $this->licencia($entrada[$campo] ?? null);
                } catch (ErrorGestionConductores $error) {
                    $errores += $error->errores;
                }
                continue;
            }
            try {
                $datos[$campo] = $this->texto(
                    $entrada[$campo] ?? null,
                    $limite,
                    $campo,
                    in_array($campo, ['nombres', 'apellidos', 'numero_licencia'], true)
                );
            } catch (ErrorGestionConductores $error) {
                $errores += $error->errores;
            }
        }
        if ($errores !== []) {
            throw new ErrorGestionConductores($errores);
        }
        return $datos;
    }

    public function listar(mixed $busqueda): array
    {
        if (!is_string($busqueda) || preg_match('//u', $busqueda) !== 1 || str_contains($busqueda, "\0")
            || preg_match_all('/./us', $busqueda) > 241) {
            throw new ErrorGestionConductores([], 'La búsqueda debe ser texto de hasta 241 caracteres.', 400);
        }
        $busqueda = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $busqueda) ?? '';
        return ['busqueda' => $busqueda, 'conductores' => $this->repository->listar($busqueda)];
    }

    public function obtener(mixed $idConductor): array
    {
        $fila = $this->repository->buscar(self::id($idConductor));
        if ($fila === null) {
            throw new ErrorGestionConductores([], 'No se encontró el conductor solicitado.', 404);
        }
        return $fila;
    }

    private function operar(int $idActor, Closure $operacion): void
    {
        try {
            $this->operaciones->ejecutar($idActor, $operacion);
        } catch (ErrorGestionConductores $error) {
            throw $error;
        } catch (ErrorGestionUsuarios $error) {
            throw new ErrorGestionConductores([], $error->getMessage(), $error->estado);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                throw new ErrorGestionConductores(['numero_licencia' => 'Esta licencia ya pertenece a otro conductor.'], 'La licencia ya está registrada.', 409);
            }
            throw new ErrorGestionConductores([], 'No se pudo guardar la operación. Revise el listado antes de intentarlo nuevamente.', 503);
        } catch (Throwable) {
            throw new ErrorGestionConductores([], 'No se pudo guardar la operación. Revise el listado antes de intentarlo nuevamente.', 503);
        }
    }

    private function comprobarLicencia(string $licencia, ?int $excepto = null): void
    {
        if ($this->repository->existeLicencia($licencia, $excepto)) {
            throw new ErrorGestionConductores(['numero_licencia' => 'Esta licencia ya pertenece a otro conductor.'], 'La licencia ya está registrada.', 409);
        }
    }

    public function registrar(int $idActor, array $entrada): void
    {
        $datos = $this->validar($entrada);
        $this->operar($idActor, function () use ($datos): void {
            $this->comprobarLicencia($datos['numero_licencia']);
            $this->repository->insertar($datos);
        });
    }

    public function modificar(int $idActor, mixed $idConductor, array $entrada): void
    {
        $idConductor = self::id($idConductor);
        $datos = $this->validar($entrada);
        $this->operar($idActor, function () use ($idConductor, $datos): void {
            if ($this->repository->buscar($idConductor, true) === null) {
                throw new ErrorGestionConductores([], 'No se encontró el conductor solicitado.', 404);
            }
            $this->comprobarLicencia($datos['numero_licencia'], $idConductor);
            $this->repository->actualizar($idConductor, $datos);
        });
    }

    public function cambiarEstado(int $idActor, mixed $idConductor, mixed $nuevoEstado, mixed $confirmacion): void
    {
        $idConductor = self::id($idConductor);
        $nuevoEstado = self::estado($nuevoEstado);
        if ($confirmacion !== '1') {
            throw new ErrorGestionConductores([], 'Confirme el cambio de estado antes de continuar.');
        }
        $this->operar($idActor, function () use ($idConductor, $nuevoEstado): void {
            $fila = $this->repository->buscar($idConductor, true);
            if ($fila === null) {
                throw new ErrorGestionConductores([], 'No se encontró el conductor solicitado.', 404);
            }
            $anterior = self::estado($fila['activo']);
            if ($anterior === $nuevoEstado) {
                throw new ErrorGestionConductores([], $nuevoEstado === 1 ? 'El conductor ya está activo. No se realizaron cambios.' : 'El conductor ya está inactivo. No se realizaron cambios.', 409);
            }
            $this->repository->cambiarEstado($idConductor, $nuevoEstado, $anterior);
        });
    }
}
