<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\Respuesta;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\ConductorService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\ErrorGestionConductores;

final class ConductorController
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
            return $this->mensaje('Acceso denegado', 'Su rol no permite gestionar conductores.', 403);
        }
        return $usuario;
    }

    private function mensaje(string $titulo, string $mensaje, int $estado): Respuesta
    {
        return new Respuesta($this->vista->renderizar('mensaje', compact('titulo', 'mensaje')), $estado);
    }

    private function comprobarCsrf(array $datos): ?Respuesta
    {
        return $this->csrf->validar($datos['csrf'] ?? null) ? null : $this->mensaje(
            'Solicitud no válida', 'El formulario expiró o no es válido. Vuelva a Gestión de conductores e intente nuevamente.', 403
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
        } catch (ErrorGestionConductores $error) {
            return $this->mensaje('Búsqueda no válida', $error->getMessage(), $error->estado);
        }
        $exito = $this->store->obtener('conductores_exito');
        $this->store->quitar('conductores_exito');
        return new Respuesta($this->vista->renderizar('conductores/listado', $resultado + [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'exito' => is_string($exito) ? $exito : '',
        ]));
    }

    public function nuevo(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        return $usuario instanceof Respuesta ? $usuario : $this->formulario($usuario, false);
    }

    private function valoresPublicos(array $entrada): array
    {
        $valores = [];
        foreach (['nombres', 'apellidos', 'numero_licencia', 'telefono'] as $campo) {
            $valor = $entrada[$campo] ?? '';
            $valores[$campo] = is_string($valor) && strlen($valor) <= 2000 ? $valor : '';
        }
        return $valores;
    }

    private function formulario(
        UsuarioAutenticado $usuario, bool $edicion, array $valores = [], array $errores = [],
        string $error = '', int $estado = 200, ?int $idConductor = null
    ): Respuesta {
        return new Respuesta($this->vista->renderizar('conductores/formulario', [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'edicion' => $edicion,
            'valores' => $this->valoresPublicos($valores), 'errores' => $errores, 'error' => $error, 'idConductor' => $idConductor,
        ]), $estado);
    }

    public function registrar(array $datos, array $consulta = []): Respuesta
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
        } catch (ErrorGestionConductores $error) {
            if ($error->estado === 403) {
                return $this->mensaje('Acceso denegado', $error->getMessage(), 403);
            }
            return $this->formulario($usuario, false, $datos, $error->errores, $error->getMessage(), $error->estado);
        }
        return $this->exito('Conductor registrado correctamente. Su estado inicial es Activo.');
    }

    public function editar(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $datos = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionConductores $error) {
            return $this->mensaje('Conductor no disponible', $error->getMessage(), $error->estado);
        }
        return $this->formulario($usuario, true, $datos, idConductor: (int) $datos['id_conductor']);
    }

    public function modificar(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $idConductor = ConductorService::id($consulta['id'] ?? null);
            ($this->crearServicio)()->modificar($usuario->idUsuario, $idConductor, $datos);
        } catch (ErrorGestionConductores $error) {
            if (in_array($error->estado, [400, 403, 404], true)) {
                return $this->mensaje('Conductor no disponible', $error->getMessage(), $error->estado);
            }
            return $this->formulario($usuario, true, $datos, $error->errores, $error->getMessage(), $error->estado, $idConductor);
        }
        return $this->exito('Datos del conductor actualizados correctamente.');
    }

    private function confirmacion(UsuarioAutenticado $usuario, array $conductor, int $nuevoEstado, string $error = '', int $estado = 200): Respuesta
    {
        return new Respuesta($this->vista->renderizar('conductores/estado', [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'conductor' => $conductor,
            'nuevoEstado' => $nuevoEstado, 'redundante' => (int) $conductor['activo'] === $nuevoEstado, 'error' => $error,
        ]), $estado);
    }

    public function confirmarEstado(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $nuevoEstado = ConductorService::estado($consulta['estado'] ?? null);
            $datos = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionConductores $error) {
            return $this->mensaje('Conductor no disponible', $error->getMessage(), $error->estado);
        }
        return $this->confirmacion($usuario, $datos, $nuevoEstado);
    }

    public function cambiarEstado(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $idConductor = ConductorService::id($consulta['id'] ?? null);
            $nuevoEstado = ConductorService::estado($consulta['estado'] ?? null);
            ($this->crearServicio)()->cambiarEstado($usuario->idUsuario, $idConductor, $nuevoEstado, $datos['confirmacion'] ?? null);
        } catch (ErrorGestionConductores $error) {
            if (in_array($error->estado, [400, 403, 404], true)) {
                return $this->mensaje('Conductor no disponible', $error->getMessage(), $error->estado);
            }
            try {
                $conductor = ($this->crearServicio)()->obtener($idConductor);
            } catch (ErrorGestionConductores $lectura) {
                return $this->mensaje('Conductor no disponible', $lectura->getMessage(), $lectura->estado);
            }
            return $this->confirmacion($usuario, $conductor, $nuevoEstado, $error->getMessage(), $error->estado);
        }
        return $this->exito($nuevoEstado === 1 ? 'Conductor reactivado correctamente.' : 'Conductor desactivado correctamente. Su registro se conserva.');
    }

    private function exito(string $mensaje): Respuesta
    {
        $this->store->guardar('conductores_exito', $mensaje);
        return Respuesta::redirigir('/?ruta=conductores');
    }
}
