<?php

declare(strict_types=1);

namespace SFATSA\Services;

use Closure;
use PDOException;
use SFATSA\Repositories\GestionUsuarioRepository;
use Throwable;

final class UsuarioService
{
    private const ROLES = ['ADMINISTRADOR', 'CAJERO', 'CHECADORA'];

    public function __construct(private readonly GestionUsuarioRepository $repository)
    {
    }

    public static function id(mixed $valor): int
    {
        if ((!is_int($valor) && !is_string($valor)) || preg_match('/^[1-9][0-9]*$/D', (string) $valor) !== 1
            || filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ErrorGestionUsuarios([], 'El identificador del usuario no es válido.', 400);
        }
        return (int) $valor;
    }

    private function texto(mixed $valor, int $limite, string $etiqueta): string
    {
        if (!is_string($valor) || preg_match('//u', $valor) !== 1 || str_contains($valor, "\0")) {
            throw new ErrorGestionUsuarios([$etiqueta => 'Introduzca un texto válido.']);
        }
        // Recortar solo espacios exteriores, sin cambiar acentos, caso o espacios internos.
        $valor = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $valor) ?? '';
        if ($valor === '') {
            throw new ErrorGestionUsuarios([$etiqueta => 'Este campo es obligatorio.']);
        }
        if (preg_match_all('/./us', $valor) > $limite) {
            throw new ErrorGestionUsuarios([$etiqueta => 'Utilice como máximo ' . $limite . ' caracteres.']);
        }
        return $valor;
    }

    public function listar(mixed $busqueda): array
    {
        if (!is_string($busqueda) || preg_match('//u', $busqueda) !== 1 || str_contains($busqueda, "\0")
            || preg_match_all('/./us', $busqueda) > 241) {
            throw new ErrorGestionUsuarios([], 'La búsqueda debe ser texto de hasta 241 caracteres.', 400);
        }
        $busqueda = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $busqueda) ?? '';
        return ['busqueda' => $busqueda, 'usuarios' => $this->repository->listar($busqueda)];
    }

    public function roles(): array
    {
        return array_values(array_filter($this->repository->roles(), static fn (array $rol): bool =>
            in_array($rol['nombre'] ?? null, self::ROLES, true)));
    }

    public function obtener(mixed $idUsuario): array
    {
        $usuario = $this->repository->buscar(self::id($idUsuario));
        if ($usuario === null) {
            throw new ErrorGestionUsuarios([], 'No se encontró el usuario solicitado.', 404);
        }
        return $usuario;
    }

    private function validar(#[\SensitiveParameter] array $entrada, bool $registro): array
    {
        $datos = [];
        $errores = [];
        foreach (['nombres' => 120, 'apellidos' => 120, 'usuario' => 50] as $campo => $limite) {
            try {
                $datos[$campo] = $this->texto($entrada[$campo] ?? null, $limite, $campo);
            } catch (ErrorGestionUsuarios $error) {
                $errores += $error->errores;
            }
        }
        $idRol = null;
        try {
            $idRol = self::id($entrada['id_rol'] ?? null);
        } catch (ErrorGestionUsuarios) {
            $errores['id_rol'] = 'Seleccione un rol válido.';
        }
        $roles = $this->roles();
        if ($idRol !== null && !in_array($idRol, array_map(static fn (array $rol): int => (int) $rol['id_rol'], $roles), true)) {
            $errores['id_rol'] = 'Seleccione uno de los roles disponibles.';
        }
        $datos['id_rol'] = $idRol;
        if ($registro) {
            $contrasena = $entrada['contrasena'] ?? null;
            $confirmacion = $entrada['confirmacion'] ?? null;
            if (!is_string($contrasena) || preg_match('//u', $contrasena) !== 1
                || str_contains($contrasena, "\0") || strlen($contrasena) > 72
                || preg_match('/[^\s\p{Z}]/u', $contrasena) !== 1 || preg_match_all('/./us', $contrasena) < 12) {
                $errores['contrasena'] = 'Use al menos 12 caracteres y como máximo 72 bytes, sin valores vacíos.';
            }
            if (!is_string($contrasena) || !is_string($confirmacion) || !hash_equals($contrasena, $confirmacion)) {
                $errores['confirmacion'] = 'La confirmación debe coincidir con la contraseña.';
            }
        }
        if ($errores !== []) {
            throw new ErrorGestionUsuarios($errores);
        }
        return $datos;
    }

    private function operar(int $idActor, Closure $operacion): void
    {
        try {
            (new OperacionAdministrativaService($this->repository))->ejecutar($idActor, $operacion);
        } catch (Throwable $error) {
            $fallo = $error instanceof ErrorGestionUsuarios ? $error : new ErrorGestionUsuarios(
                [], 'No se pudo guardar la operación. Revise el listado antes de intentarlo nuevamente.', 503
            );
            if ($error instanceof PDOException) {
                $codigo = (int) ($error->errorInfo[1] ?? 0);
                if ($codigo === 1062) {
                    $fallo = new ErrorGestionUsuarios(['usuario' => 'Ese nombre de usuario ya está registrado.'], 'El usuario ya existe.', 409);
                } elseif (in_array($codigo, [1451, 1452], true)) {
                    $fallo = new ErrorGestionUsuarios(['id_rol' => 'El rol ya no está disponible. Vuelva a seleccionarlo.'], 'No se pudo guardar el rol.', 409);
                }
            }
            throw $fallo;
        }
    }

    public function registrar(int $idActor, #[\SensitiveParameter] array $entrada): void
    {
        $datos = $this->validar($entrada, true);
        $hash = password_hash($entrada['contrasena'], PASSWORD_BCRYPT);
        $this->operar($idActor, function () use ($datos, $hash): void {
            if ($this->repository->existeUsuario($datos['usuario'])) {
                throw new ErrorGestionUsuarios(['usuario' => 'Ese nombre de usuario ya está registrado.'], 'El usuario ya existe.', 409);
            }
            $this->repository->insertar($datos, $hash);
        });
    }

    public function modificar(int $idActor, mixed $idUsuario, #[\SensitiveParameter] array $entrada): void
    {
        $idUsuario = self::id($idUsuario);
        $datos = $this->validar($entrada, false);
        $this->operar($idActor, function () use ($idUsuario, $datos): void {
            $usuario = $this->repository->buscar($idUsuario, true);
            if ($usuario === null) {
                throw new ErrorGestionUsuarios([], 'No se encontró el usuario solicitado.', 404);
            }
            if ((int) $usuario['activo'] === 1 && $usuario['rol'] === 'ADMINISTRADOR'
                && (int) $usuario['id_rol'] !== $datos['id_rol'] && $this->repository->administradoresActivos() <= 1) {
                throw new ErrorGestionUsuarios(
                    ['id_rol' => 'Conserve el rol ADMINISTRADOR del último administrador activo.'],
                    'No puede retirar el rol del último administrador activo.', 409
                );
            }
            if ($this->repository->existeUsuario($datos['usuario'], $idUsuario)) {
                throw new ErrorGestionUsuarios(['usuario' => 'Ese nombre de usuario ya está registrado.'], 'El usuario ya existe.', 409);
            }
            $this->repository->actualizar($idUsuario, $datos);
        });
    }

    public function desactivar(int $idActor, mixed $idUsuario, mixed $confirmacion): void
    {
        $idUsuario = self::id($idUsuario);
        if ($confirmacion !== '1') {
            throw new ErrorGestionUsuarios([], 'Confirme la desactivación antes de continuar.');
        }
        if ($idActor === $idUsuario) {
            throw new ErrorGestionUsuarios([], 'No puede desactivar su propia cuenta mientras está autenticado.', 409);
        }
        $this->operar($idActor, function () use ($idUsuario): void {
            $usuario = $this->repository->buscar($idUsuario, true);
            if ($usuario === null) {
                throw new ErrorGestionUsuarios([], 'No se encontró el usuario solicitado.', 404);
            }
            if ((int) $usuario['activo'] !== 1) {
                throw new ErrorGestionUsuarios([], 'Esta cuenta ya está inactiva. No se realizaron cambios.', 409);
            }
            if ($usuario['rol'] === 'ADMINISTRADOR' && $this->repository->administradoresActivos() <= 1) {
                throw new ErrorGestionUsuarios([], 'No puede desactivar al último administrador activo.', 409);
            }
            $this->repository->desactivar($idUsuario);
        });
    }

    public function activar(int $idActor, mixed $idUsuario, mixed $confirmacion): void
    {
        $idUsuario = self::id($idUsuario);
        if ($confirmacion !== '1') {
            throw new ErrorGestionUsuarios([], 'Confirme la activación antes de continuar.');
        }
        $this->operar($idActor, function () use ($idUsuario): void {
            $usuario = $this->repository->buscar($idUsuario, true);
            if ($usuario === null) {
                throw new ErrorGestionUsuarios([], 'No se encontró el usuario solicitado.', 404);
            }
            if ((int) $usuario['activo'] !== 0) {
                throw new ErrorGestionUsuarios([], 'Esta cuenta no está inactiva. No se realizaron cambios.', 409);
            }
            // Activar preserva identidad, rol, datos y contraseña; no crea otra cuenta.
            $this->repository->activar($idUsuario);
        });
    }
}
