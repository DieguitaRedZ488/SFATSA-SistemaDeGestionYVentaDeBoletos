<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use PDOException;
use SFATSA\Repositories\VehiculoRepository;
use Throwable;

final class VehiculoService
{
    public function __construct(
        private readonly VehiculoRepository $repository,
        private readonly OperacionAdministrativaService $operaciones
    ) {
    }

    public static function id(mixed $valor): int
    {
        if ((!is_int($valor) && !is_string($valor)) || preg_match('/\A[1-9][0-9]*\z/', (string) $valor) !== 1
            || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ErrorGestionVehiculos([], 'El identificador del vehículo no es válido.', 400);
        }
        return (int) $valor;
    }

    public static function estado(mixed $valor): string
    {
        if (!in_array($valor, ['ACTIVO', 'INACTIVO'], true)) {
            throw new ErrorGestionVehiculos(['estado' => 'Seleccione Activo o Inactivo.'], 'El estado solicitado no es válido.', 400);
        }
        return $valor;
    }

    private function texto(mixed $valor, int $limite, string $campo, bool $mayusculas = false): string
    {
        if (!is_string($valor) || preg_match('//u', $valor) !== 1 || preg_match('/[\p{Cc}\p{Cf}]/u', $valor)) {
            throw new ErrorGestionVehiculos([$campo => 'Introduzca un texto válido, sin caracteres de control.']);
        }
        $valor = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $valor) ?? '';
        if ($valor === '') {
            throw new ErrorGestionVehiculos([$campo => 'Este campo es obligatorio.']);
        }
        if ($mayusculas) {
            $valor = mb_strtoupper($valor, 'UTF-8');
        }
        if (preg_match_all('/./us', $valor) > $limite) {
            throw new ErrorGestionVehiculos([$campo => 'Utilice como máximo ' . $limite . ' caracteres.']);
        }
        return $valor;
    }

    private function placa(mixed $valor): string
    {
        // Regla interna SFATSA. ASCII sin transliteración ni eliminación de espacios/guiones.
        $placa = is_string($valor) ? strtoupper($valor) : '';
        if (strlen($placa) !== 9 || preg_match('/\A[A-Z0-9]+-[A-Z0-9]+-[A-Z0-9]+\z/', $placa) !== 1) {
            throw new ErrorGestionVehiculos([
                'placa' => 'Distribuya exactamente 7 letras A-Z o números entre tres grupos no vacíos, separados por dos guiones (9 caracteres en total). No use espacios ni otros símbolos.',
            ]);
        }
        return $placa;
    }

    private function capacidad(mixed $valor): int
    {
        if (is_int($valor) && $valor >= 1 && $valor <= 30) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/\A[0-9]+\z/', $valor) === 1) {
            $significativos = ltrim($valor, '0');
            if ($significativos !== '' && strlen($significativos) <= 2 && (int) $significativos <= 30) {
                return (int) $significativos;
            }
        }
        throw new ErrorGestionVehiculos(['capacidad' => 'La capacidad debe estar entre 1 y 30 pasajeros']);
    }

    private function validar(array $entrada, bool $alta): array
    {
        $datos = [];
        $errores = [];
        foreach (['numero_economico', 'placa', 'descripcion', 'capacidad'] as $campo) {
            try {
                $valor = $entrada[$campo] ?? null;
                $datos[$campo] = match ($campo) {
                    'numero_economico' => $this->texto($valor, 30, $campo, true),
                    'placa' => $this->placa($valor),
                    'descripcion' => $this->texto($valor, 100, $campo),
                    'capacidad' => $this->capacidad($valor),
                };
            } catch (ErrorGestionVehiculos $error) {
                $errores += $error->errores;
            }
        }
        if ($alta) {
            try {
                $datos['estado'] = self::estado(array_key_exists('estado', $entrada) ? $entrada['estado'] : 'ACTIVO');
            } catch (ErrorGestionVehiculos $error) {
                $errores += $error->errores;
            }
        } elseif (array_key_exists('estado', $entrada)) {
            $errores['estado'] = 'Cambie el estado mediante la operación independiente de Activar o Desactivar.';
        }
        if ($errores !== []) {
            throw new ErrorGestionVehiculos($errores);
        }
        return $datos;
    }

    private function leer(Closure $lectura): mixed
    {
        try {
            return $lectura();
        } catch (ErrorGestionVehiculos $error) {
            throw $error;
        } catch (Throwable) {
            throw new ErrorGestionVehiculos([], 'No se pudo consultar la información de vehículos.', 503);
        }
    }

    public function listar(mixed $busqueda = ''): array
    {
        if (!is_string($busqueda) || preg_match('//u', $busqueda) !== 1
            || preg_match('/[\p{Cc}\p{Cf}]/u', $busqueda)) {
            throw new ErrorGestionVehiculos([], 'La búsqueda debe ser texto válido de hasta 30 caracteres.', 400);
        }
        $busqueda = mb_strtoupper(preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $busqueda) ?? '', 'UTF-8');
        if (preg_match_all('/./us', $busqueda) > 30) {
            throw new ErrorGestionVehiculos([], 'La búsqueda debe ser texto válido de hasta 30 caracteres.', 400);
        }
        return $this->leer(fn (): array => ['busqueda' => $busqueda, 'vehiculos' => $this->repository->listar($busqueda)]);
    }

    public function obtener(mixed $idVehiculo): array
    {
        $idVehiculo = self::id($idVehiculo);
        return $this->leer(function () use ($idVehiculo): array {
            $fila = $this->repository->buscar($idVehiculo);
            if ($fila === null) {
                throw new ErrorGestionVehiculos([], 'No se encontró el vehículo solicitado.', 404);
            }
            return $fila;
        });
    }

    private function versionEliminacion(array $fila): string
    {
        $campos = ['id_vehiculo', 'numero_economico', 'placa', 'descripcion', 'capacidad', 'estado'];
        $datos = [];
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $fila)) {
                throw new ErrorGestionVehiculos([], 'La información del vehículo no está disponible.', 503);
            }
            $datos[$campo] = $fila[$campo] === null ? null : (string) $fila[$campo];
        }
        return hash('sha256', serialize($datos));
    }

    public function prepararEliminacion(mixed $idVehiculo): array
    {
        $idVehiculo = self::id($idVehiculo);
        return $this->leer(function () use ($idVehiculo): array {
            if (!$this->repository->dependenciasVerificadas()) {
                throw new ErrorGestionVehiculos([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            $fila = $this->repository->buscar($idVehiculo);
            if ($fila === null) {
                throw new ErrorGestionVehiculos([], 'No se encontró el vehículo solicitado.', 404);
            }
            return $fila + ['version' => $this->versionEliminacion($fila)];
        });
    }

    private function comprobarUnicos(array $datos, ?int $excepto = null): void
    {
        $errores = [];
        if ($this->repository->existePlaca($datos['placa'], $excepto)) {
            $errores['placa'] = 'Esta placa ya pertenece a otro vehículo.';
        }
        if ($this->repository->existeNumeroEconomico($datos['numero_economico'], $excepto)) {
            $errores['numero_economico'] = 'Este número económico ya pertenece a otro vehículo.';
        }
        if ($errores !== []) {
            throw new ErrorGestionVehiculos($errores, 'La placa o el número económico ya está registrado.', 409);
        }
    }

    /** Resumen normalizado para revisión; no abre transacción ni inserta registros. */
    public function prepararRegistro(array $entrada): array
    {
        $datos = $this->validar($entrada, true);
        return $this->leer(function () use ($datos): array {
            $this->comprobarUnicos($datos);
            return $datos;
        });
    }

    private function operar(int $idActor, Closure $operacion): void
    {
        try {
            $this->operaciones->ejecutar($idActor, $operacion);
        } catch (ErrorGestionVehiculos $error) {
            throw $error;
        } catch (ErrorGestionUsuarios $error) {
            throw new ErrorGestionVehiculos([], $error->getMessage(), $error->estado);
        } catch (PDOException $error) {
            if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
                $campos = ['placa', 'numero_economico'];
                if (preg_match("~for key '(?:vehiculo\.)?(placa|numero_economico)'~i", $error->errorInfo[2] ?? '', $m)) {
                    $campos = [strtolower($m[1])];
                }
                throw new ErrorGestionVehiculos(
                    array_fill_keys($campos, 'Este identificador ya pertenece a otro vehículo.'),
                    'La placa o el número económico ya está registrado.', 409
                );
            }
            if ((int) ($error->errorInfo[1] ?? 0) === 1451) {
                throw new ErrorGestionVehiculos([], 'No se puede eliminar porque existen registros relacionados que deben conservarse.', 409);
            }
            throw $this->errorDeEscritura();
        } catch (Throwable) {
            throw $this->errorDeEscritura();
        }
    }

    private function errorDeEscritura(): ErrorGestionVehiculos
    {
        return new ErrorGestionVehiculos([], 'No se pudo guardar la operación. Revise el listado antes de intentarlo nuevamente.', 503);
    }

    /** El flujo de sesión debe llamar a este método solo tras confirmar su resumen. */
    public function registrar(int $idActor, array $entrada, mixed $confirmacion): void
    {
        if ($confirmacion !== '1') {
            throw new ErrorGestionVehiculos([], 'Confirme el resumen del vehículo antes de registrarlo.');
        }
        $datos = $this->validar($entrada, true);
        $this->operar($idActor, function () use ($datos): void {
            $this->comprobarUnicos($datos);
            $this->repository->insertar($datos);
        });
    }

    public function modificar(int $idActor, mixed $idVehiculo, array $entrada): void
    {
        $idVehiculo = self::id($idVehiculo);
        $datos = $this->validar($entrada, false);
        $this->operar($idActor, function () use ($idVehiculo, $datos): void {
            $fila = $this->repository->buscar($idVehiculo, true);
            if ($fila === null) {
                throw new ErrorGestionVehiculos([], 'No se encontró el vehículo solicitado.', 404);
            }
            // No convertir ni sobrescribir silenciosamente un estado heredado incompatible.
            self::estado($fila['estado']);
            $this->comprobarUnicos($datos, $idVehiculo);
            $this->repository->actualizar($idVehiculo, $datos);
        });
    }

    public function cambiarEstado(int $idActor, mixed $idVehiculo, mixed $nuevoEstado, mixed $confirmacion): void
    {
        $idVehiculo = self::id($idVehiculo);
        $nuevoEstado = self::estado($nuevoEstado);
        if ($confirmacion !== '1') {
            throw new ErrorGestionVehiculos([], 'Confirme el cambio de estado antes de continuar.');
        }
        $this->operar($idActor, function () use ($idVehiculo, $nuevoEstado): void {
            $fila = $this->repository->buscar($idVehiculo, true);
            if ($fila === null) {
                throw new ErrorGestionVehiculos([], 'No se encontró el vehículo solicitado.', 404);
            }
            $anterior = self::estado($fila['estado']);
            if ($anterior === $nuevoEstado) {
                throw new ErrorGestionVehiculos([], 'El vehículo ya tiene el estado solicitado. No se realizaron cambios.', 409);
            }
            $this->repository->cambiarEstado($idVehiculo, $nuevoEstado, $anterior);
        });
    }

    public function eliminar(int $idActor, mixed $idVehiculo, mixed $versionEsperada, mixed $confirmacion): void
    {
        $idVehiculo = self::id($idVehiculo);
        if ($confirmacion !== '1') {
            throw new ErrorGestionVehiculos([], 'Confirme la eliminación física del vehículo antes de continuar.');
        }
        if (!is_string($versionEsperada) || preg_match('/\A[a-f0-9]{64}\z/', $versionEsperada) !== 1) {
            throw new ErrorGestionVehiculos([], 'La confirmación de los datos no es válida.', 400);
        }
        $this->operar($idActor, function () use ($idVehiculo, $versionEsperada): void {
            $fila = $this->repository->buscar($idVehiculo, true);
            if ($fila === null) {
                throw new ErrorGestionVehiculos([], 'No se encontró el vehículo solicitado.', 404);
            }
            if (!hash_equals($this->versionEliminacion($fila), $versionEsperada)) {
                throw new ErrorGestionVehiculos([], 'Los datos del vehículo cambiaron. Revise nuevamente antes de eliminar.', 409);
            }
            if (!$this->repository->dependenciasVerificadas()) {
                throw new ErrorGestionVehiculos([], 'El esquema de dependencias no está verificado; la eliminación está deshabilitada.', 503);
            }
            if ($this->repository->tieneViajes($idVehiculo)) {
                throw new ErrorGestionVehiculos([], 'No se puede eliminar: el vehículo tiene viajes registrados.', 409);
            }
            if ($this->repository->tieneMantenimientos($idVehiculo)) {
                throw new ErrorGestionVehiculos([], 'No se puede eliminar: el vehículo tiene registros de mantenimiento.', 409);
            }
            $this->repository->eliminar($idVehiculo);
        });
    }
}
