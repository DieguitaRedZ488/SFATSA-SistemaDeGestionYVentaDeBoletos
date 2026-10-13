<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\Respuesta;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\ErrorGestionUsuarios;
use SFATSA\Services\UsuarioService;

final class UsuarioController
{
    public function __construct(
        private readonly Closure $usuarioActual,
        private readonly Closure $crearServicio,
        private readonly CsrfService $csrf,
        private readonly SessionStore $store,
        private readonly Vista $vista,
        private readonly AutorizacionService $autorizacion
    ) {
    }

    private function autorizar(): UsuarioAutenticado|Respuesta
    {
        $usuario = ($this->usuarioActual)();
        if ($usuario === null) {
            return Respuesta::redirigir('/');
        }
        if (!$this->autorizacion->puedeGestionar($usuario)) {
            return $this->mensaje('Acceso denegado', 'Su rol no permite gestionar usuarios.', 403);
        }
        return $usuario;
    }

    private function mensaje(string $titulo, string $mensaje, int $estado): Respuesta
    {
        return new Respuesta($this->vista->renderizar('mensaje', compact('titulo', 'mensaje')), $estado);
    }

    private function comprobarCsrf(#[\SensitiveParameter] array $datos): ?Respuesta
    {
        return $this->csrf->validar($datos['csrf'] ?? null) ? null : $this->mensaje(
            'Solicitud no válida', 'El formulario expiró o no es válido. Vuelva a Gestión de usuarios e intente nuevamente.', 403
        );
    }

    public function listar(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $resultado = ($this->crearServicio)()->listar($consulta['q'] ?? '');
        } catch (ErrorGestionUsuarios $error) {
            return $this->mensaje('Búsqueda no válida', $error->getMessage(), $error->estado);
        }
        $exito = $this->store->obtener('usuarios_exito');
        $this->store->quitar('usuarios_exito');
        return new Respuesta($this->vista->renderizar('usuarios/listado', $resultado + [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'exito' => is_string($exito) ? $exito : '',
        ]));
    }

    public function nuevo(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        return $usuario instanceof Respuesta ? $usuario : $this->formulario($usuario, false);
    }

    private function valoresPublicos(#[\SensitiveParameter] array $entrada): array
    {
        $datos = [];
        foreach (['nombres', 'apellidos', 'usuario', 'id_rol'] as $campo) {
            $valor = $entrada[$campo] ?? '';
            $datos[$campo] = (is_string($valor) || is_int($valor)) && strlen((string) $valor) <= 2000 ? (string) $valor : '';
        }
        return $datos;
    }

    private function formulario(
        UsuarioAutenticado $usuario, bool $edicion, array $datos = [], array $errores = [],
        string $error = '', int $estado = 200, ?int $idUsuario = null
    ): Respuesta {
        return new Respuesta($this->vista->renderizar('usuarios/formulario', [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'edicion' => $edicion,
            'valores' => $this->valoresPublicos($datos), 'roles' => ($this->crearServicio)()->roles(),
            'errores' => $errores, 'error' => $error, 'idUsuario' => $idUsuario,
        ]), $estado);
    }

    public function registrar(#[\SensitiveParameter] array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            ($this->crearServicio)()->registrar($usuario->idUsuario, $datos);
        } catch (ErrorGestionUsuarios $error) {
            return $this->formulario($usuario, false, $datos, $error->errores, $error->getMessage(), $error->estado);
        }
        return $this->exito('Usuario registrado correctamente. La cuenta está activa.');
    }

    public function editar(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $datos = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionUsuarios $error) {
            return $this->mensaje('Usuario no disponible', $error->getMessage(), $error->estado);
        }
        return $this->formulario($usuario, true, $datos, idUsuario: (int) $datos['id_usuario']);
    }

    public function modificar(#[\SensitiveParameter] array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $idUsuario = UsuarioService::id($consulta['id'] ?? null);
            ($this->crearServicio)()->modificar($usuario->idUsuario, $idUsuario, $datos);
        } catch (ErrorGestionUsuarios $error) {
            if (in_array($error->estado, [400, 403, 404], true)) {
                return $this->mensaje('Usuario no disponible', $error->getMessage(), $error->estado);
            }
            return $this->formulario($usuario, true, $datos, $error->errores, $error->getMessage(), $error->estado, $idUsuario);
        }
        return $this->exito('Datos y rol del usuario actualizados correctamente.');
    }

    private function confirmacion(
        UsuarioAutenticado $usuario, array $datos, string $error = '', int $estado = 200, bool $activacion = false
    ): Respuesta
    {
        $protegido = $activacion ? (int) $datos['activo'] !== 0
            : (int) $datos['id_usuario'] === $usuario->idUsuario || (int) $datos['activo'] !== 1;
        return new Respuesta($this->vista->renderizar($activacion ? 'usuarios/activar' : 'usuarios/desactivar', [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'cuenta' => $datos,
            'protegido' => $protegido, 'error' => $error,
        ]), $estado);
    }

    public function confirmarDesactivacion(array $consulta = []): Respuesta
    {
        return $this->confirmarEstado($consulta, false);
    }

    public function confirmarActivacion(array $consulta = []): Respuesta
    {
        return $this->confirmarEstado($consulta, true);
    }

    private function confirmarEstado(array $consulta, bool $activacion): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $datos = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionUsuarios $error) {
            return $this->mensaje('Usuario no disponible', $error->getMessage(), $error->estado);
        }
        return $this->confirmacion($usuario, $datos, activacion: $activacion);
    }

    public function desactivar(#[\SensitiveParameter] array $datos, array $consulta = []): Respuesta
    {
        return $this->cambiarEstado($datos, $consulta, false);
    }

    public function activar(#[\SensitiveParameter] array $datos, array $consulta = []): Respuesta
    {
        return $this->cambiarEstado($datos, $consulta, true);
    }

    private function cambiarEstado(#[\SensitiveParameter] array $datos, array $consulta, bool $activacion): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $idUsuario = UsuarioService::id($consulta['id'] ?? null);
            $servicio = ($this->crearServicio)();
            if ($activacion) {
                $servicio->activar($usuario->idUsuario, $idUsuario, $datos['confirmacion'] ?? null);
            } else {
                $servicio->desactivar($usuario->idUsuario, $idUsuario, $datos['confirmacion'] ?? null);
            }
        } catch (ErrorGestionUsuarios $error) {
            if (in_array($error->estado, [400, 403, 404], true)) {
                return $this->mensaje('Usuario no disponible', $error->getMessage(), $error->estado);
            }
            $cuenta = ($this->crearServicio)()->obtener($idUsuario);
            return $this->confirmacion($usuario, $cuenta, $error->getMessage(), $error->estado, $activacion);
        }
        return $this->exito($activacion ? 'Usuario activado correctamente. Se conservan sus datos y contraseña.'
            : 'Usuario desactivado correctamente. Su registro se conserva.');
    }

    private function exito(string $mensaje): Respuesta
    {
        $this->store->guardar('usuarios_exito', $mensaje);
        return Respuesta::redirigir('/?ruta=usuarios');
    }
}
