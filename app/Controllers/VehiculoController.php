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
use SFATSA\Services\ErrorGestionVehiculos;
use SFATSA\Services\RegistroVehiculoService;
use SFATSA\Services\VehiculoService;

final class VehiculoController
{
    private readonly Closure $renderizar;

    public function __construct(
        private readonly Closure $usuarioActual,
        private readonly Closure $crearServicio,
        private readonly Closure $crearRegistro,
        private readonly CsrfService $csrf,
        private readonly SessionStore $store,
        Vista $vista,
        private readonly AutorizacionService $autorizacion,
        ?Closure $renderizar = null
    ) {
        $this->renderizar = $renderizar ?? static fn (string $plantilla, array $datos): string => $vista->renderizar($plantilla, $datos);
    }

    private function autorizar(): UsuarioAutenticado|Respuesta
    {
        $usuario = ($this->usuarioActual)();
        if ($usuario === null) {
            return Respuesta::redirigir('/');
        }
        if (!$this->autorizacion->puedeGestionar($usuario)) {
            return $this->mensaje('Acceso denegado', 'Su rol no permite gestionar vehículos.', 403);
        }
        return $usuario;
    }

    private function vista(string $plantilla, array $datos, int $estado = 200): Respuesta
    {
        return new Respuesta(($this->renderizar)($plantilla, $datos), $estado);
    }

    private function mensaje(string $titulo, string $mensaje, int $estado): Respuesta
    {
        return $this->vista('mensaje', compact('titulo', 'mensaje'), $estado);
    }

    private function comprobarCsrf(array $datos): ?Respuesta
    {
        return $this->csrf->validar($datos['csrf'] ?? null) ? null : $this->mensaje(
            'Solicitud no válida',
            'El formulario expiró o no es válido. Vuelva a Gestión de vehículos e intente nuevamente.',
            403
        );
    }

    private function valoresPublicos(array $entrada): array
    {
        $valores = [];
        foreach (['numero_economico', 'placa', 'descripcion', 'capacidad', 'estado'] as $campo) {
            $valor = $entrada[$campo] ?? '';
            $valores[$campo] = (is_string($valor) || is_int($valor)) && strlen((string) $valor) <= 2000
                ? (string) $valor : '';
        }
        return $valores;
    }

    private function formulario(
        UsuarioAutenticado $usuario,
        bool $edicion,
        array $valores = [],
        array $errores = [],
        string $error = '',
        int $estado = 200,
        ?int $idVehiculo = null
    ): Respuesta {
        return $this->vista('vehiculos/formulario', [
            'usuario' => $usuario,
            'token' => $this->csrf->token(),
            'edicion' => $edicion,
            'valores' => $this->valoresPublicos($valores),
            'errores' => $errores,
            'error' => $error,
            'idVehiculo' => $idVehiculo,
        ], $estado);
    }

    private function formularioTrasError(
        UsuarioAutenticado $usuario,
        bool $edicion,
        array $valores,
        ErrorGestionVehiculos $error,
        ?int $idVehiculo = null
    ): Respuesta {
        if (in_array($error->estado, [400, 403, 404, 503], true)) {
            return $this->mensaje(
                $error->estado === 503 ? 'Operación no disponible' : 'Vehículo no disponible',
                $error->getMessage(),
                $error->estado
            );
        }
        return $this->formulario(
            $usuario, $edicion, $valores, $error->errores, $error->getMessage(), $error->estado, $idVehiculo
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
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('Búsqueda no válida', $error->getMessage(), $error->estado);
        }
        $exito = $this->store->obtener('vehiculos_exito');
        $this->store->quitar('vehiculos_exito');
        return $this->vista('vehiculos/listado', $resultado + [
            'usuario' => $usuario,
            'token' => $this->csrf->token(),
            'exito' => is_string($exito) ? $exito : '',
        ]);
    }

    public function nuevo(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        return $usuario instanceof Respuesta ? $usuario : $this->formulario(
            $usuario, false, ['estado' => 'ACTIVO']
        );
    }

    public function revisar(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $resumen = ($this->crearRegistro)()->preparar($usuario->idUsuario, $datos);
        } catch (ErrorGestionVehiculos $error) {
            return $this->formularioTrasError($usuario, false, $datos, $error);
        }
        return $this->vista('vehiculos/confirmacion', [
            'usuario' => $usuario,
            'tokenCsrf' => $this->csrf->token(),
            'token' => $resumen['token'],
            'datos' => $resumen['datos'],
        ]);
    }

    public function confirmar(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        $flujo = ($this->crearRegistro)();
        $resumen = null;
        try {
            $resumen = $flujo->obtenerResumen($usuario->idUsuario, $datos['token'] ?? null);
            $flujo->confirmar($usuario->idUsuario, $datos['token'] ?? null);
        } catch (ErrorGestionVehiculos $error) {
            if ($error->estado === 503) {
                return $this->mensaje('Operación no disponible', $error->getMessage(), $error->estado);
            }
            if ($resumen !== null && in_array($error->estado, [409, 422], true)) {
                return $this->formularioTrasError($usuario, false, $resumen, $error);
            }
            return $this->mensaje('No se pudo confirmar el registro', $error->getMessage(), $error->estado);
        }
        return $this->exito('Vehículo registrado correctamente.');
    }

    public function cancelar(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            ($this->crearRegistro)()->cancelar($usuario->idUsuario, $datos['token'] ?? null);
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('No se pudo cancelar el registro', $error->getMessage(), $error->estado);
        }
        return Respuesta::redirigir('/?ruta=vehiculos');
    }

    public function editar(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $datos = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('Vehículo no disponible', $error->getMessage(), $error->estado);
        }
        return $this->formulario(
            $usuario, true, $datos, idVehiculo: (int) $datos['id_vehiculo']
        );
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
            $idVehiculo = VehiculoService::id($consulta['id'] ?? null);
            ($this->crearServicio)()->modificar($usuario->idUsuario, $idVehiculo, $datos);
        } catch (ErrorGestionVehiculos $error) {
            $idVehiculo = isset($idVehiculo) ? $idVehiculo : null;
            return $this->formularioTrasError($usuario, true, $datos, $error, $idVehiculo);
        }
        return $this->exito('Datos del vehículo actualizados correctamente.');
    }

    private function confirmacionEstado(
        UsuarioAutenticado $usuario,
        array $vehiculo,
        string $nuevoEstado,
        string $error = '',
        int $estado = 200
    ): Respuesta {
        return $this->vista('vehiculos/estado', [
            'usuario' => $usuario,
            'token' => $this->csrf->token(),
            'vehiculo' => $vehiculo,
            'nuevoEstado' => $nuevoEstado,
            'redundante' => $vehiculo['estado'] === $nuevoEstado,
            'error' => $error,
        ], $estado);
    }

    public function confirmarEstado(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $nuevoEstado = VehiculoService::estado($consulta['estado'] ?? null);
            $vehiculo = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('Vehículo no disponible', $error->getMessage(), $error->estado);
        }
        return $this->confirmacionEstado($usuario, $vehiculo, $nuevoEstado);
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
            $idVehiculo = VehiculoService::id($consulta['id'] ?? null);
            $nuevoEstado = VehiculoService::estado($consulta['estado'] ?? null);
            ($this->crearServicio)()->cambiarEstado(
                $usuario->idUsuario, $idVehiculo, $nuevoEstado, $datos['confirmacion'] ?? null
            );
        } catch (ErrorGestionVehiculos $error) {
            if (in_array($error->estado, [400, 403, 404, 503], true)) {
                return $this->mensaje('Vehículo no disponible', $error->getMessage(), $error->estado);
            }
            try {
                $vehiculo = ($this->crearServicio)()->obtener($idVehiculo);
            } catch (ErrorGestionVehiculos $lectura) {
                return $this->mensaje('Vehículo no disponible', $lectura->getMessage(), $lectura->estado);
            }
            return $this->confirmacionEstado($usuario, $vehiculo, $nuevoEstado, $error->getMessage(), $error->estado);
        }
        return $this->exito($nuevoEstado === 'ACTIVO'
            ? 'Vehículo activado correctamente.'
            : 'Vehículo desactivado correctamente.');
    }

    public function confirmarEliminacion(array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        try {
            $vehiculo = ($this->crearServicio)()->prepararEliminacion($consulta['id'] ?? null);
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('Vehículo no disponible', $error->getMessage(), $error->estado);
        }
        return $this->vista('vehiculos/eliminar', [
            'usuario' => $usuario,
            'token' => $this->csrf->token(),
            'vehiculo' => $vehiculo,
            'version' => $vehiculo['version'],
        ]);
    }

    public function eliminar(array $datos, array $consulta = []): Respuesta
    {
        $usuario = $this->autorizar();
        if ($usuario instanceof Respuesta) {
            return $usuario;
        }
        if (($rechazo = $this->comprobarCsrf($datos)) !== null) {
            return $rechazo;
        }
        try {
            $idVehiculo = VehiculoService::id($consulta['id'] ?? null);
            foreach (array_keys($datos) as $campo) {
                if (!in_array($campo, ['csrf', 'version', 'confirmacion'], true)) {
                    return $this->mensaje('Solicitud no válida', 'La solicitud de eliminación contiene datos no permitidos.', 400);
                }
            }
            ($this->crearServicio)()->eliminar(
                $usuario->idUsuario,
                $idVehiculo,
                $datos['version'] ?? null,
                $datos['confirmacion'] ?? null
            );
        } catch (ErrorGestionVehiculos $error) {
            return $this->mensaje('No se pudo eliminar el vehículo', $error->getMessage(), $error->estado);
        }
        return $this->exito('Vehículo eliminado correctamente.');
    }

    private function exito(string $mensaje): Respuesta
    {
        $this->store->guardar('vehiculos_exito', $mensaje);
        return Respuesta::redirigir('/?ruta=vehiculos');
    }
}
