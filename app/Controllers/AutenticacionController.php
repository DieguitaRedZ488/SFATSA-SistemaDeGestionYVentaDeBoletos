<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\Respuesta;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\AutenticacionService;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\SesionService;

final class AutenticacionController
{
    public function __construct(
        private readonly SesionService $sesion,
        private readonly CsrfService $csrf,
        private readonly Closure $crearAutenticacion,
        private readonly Vista $vista,
        private readonly AutorizacionService $autorizacion
    ) {
    }

    /** Identidad vigente compartida por las rutas protegidas, sin duplicar autenticación. */
    public function usuarioActual(): ?UsuarioAutenticado
    {
        $idUsuario = $this->sesion->idUsuario();
        if ($idUsuario === null) {
            return null;
        }
        $usuario = ($this->crearAutenticacion)()->consultarActivo($idUsuario);
        if ($usuario === null) {
            $this->sesion->invalidar();
            return null;
        }
        $this->sesion->registrarActividad($usuario->rol);
        return $usuario;
    }

    public function mostrarLogin(): Respuesta
    {
        if ($this->usuarioActual() !== null) {
            return Respuesta::redirigir('/?ruta=inicio');
        }
        return $this->formulario();
    }

    private function formulario(string $error = '', string $usuario = '', int $estado = 200): Respuesta
    {
        return new Respuesta($this->vista->renderizar('login', [
            'token' => $this->csrf->token(),
            'error' => $error,
            'usuario' => $usuario,
        ]), $estado);
    }

    public function iniciarSesion(#[\SensitiveParameter] array $datos): Respuesta
    {
        $usuario = is_string($datos['usuario'] ?? null) && strlen($datos['usuario']) <= 200 ? $datos['usuario'] : '';
        if (!$this->csrf->validar($datos['csrf'] ?? null)) {
            return $this->formulario('El formulario expiró o no es válido. Intente nuevamente.', $usuario, 403);
        }
        $autenticacion = ($this->crearAutenticacion)();
        $cuenta = $autenticacion->verificar($datos['usuario'] ?? null, $datos['contrasena'] ?? null);
        if ($cuenta === null) {
            return $this->formulario('Usuario o contraseña incorrectos. Verifique sus datos e intente nuevamente.', $usuario);
        }
        $this->sesion->iniciar($cuenta);
        return Respuesta::redirigir('/?ruta=inicio');
    }

    public function inicio(): Respuesta
    {
        $usuario = $this->usuarioActual();
        if ($usuario === null) {
            return Respuesta::redirigir('/');
        }
        return new Respuesta($this->vista->renderizar('inicio', [
            'usuario' => $usuario,
            'token' => $this->csrf->token(),
            'puedeGestionar' => $this->autorizacion->puedeGestionar($usuario),
        ]));
    }

    public function cerrarSesion(#[\SensitiveParameter] array $datos): Respuesta
    {
        if (!$this->csrf->validar($datos['csrf'] ?? null)) {
            return $this->mensaje('Solicitud no válida', 'No se pudo validar el cierre de sesión. Vuelva a la página e intente nuevamente.', 403);
        }
        $this->sesion->cerrar();
        return Respuesta::redirigir('/');
    }

    /** Reserva protegida, sin implementar ni simular un módulo de gestión. */
    public function moduloPendiente(): Respuesta
    {
        $usuario = $this->usuarioActual();
        if ($usuario === null) {
            return Respuesta::redirigir('/');
        }
        if (!$this->autorizacion->puedeGestionar($usuario)) {
            return $this->mensaje('Acceso denegado', 'Su rol no permite acceder a esta sección.', 403);
        }
        return $this->mensaje('Módulo en desarrollo', 'La gestión de conductores todavía está en desarrollo.', 501);
    }

    public function mensaje(string $titulo, string $mensaje, int $estado, array $cabeceras = []): Respuesta
    {
        return new Respuesta($this->vista->renderizar('mensaje', compact('titulo', 'mensaje')), $estado, $cabeceras);
    }
}
