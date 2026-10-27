<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\{Respuesta, Vista};
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\{AutorizacionService, CsrfService, ErrorGestionHU04, ServicioHU04};

/** Acciones comunes de los dos catálogos; no incluye edición de rutas. */
abstract class CatalogoHU04Controller extends AdministracionHU04Controller
{
    protected const MODULO = '';
    protected const ID = '';
    protected const CAMPOS = [];

    public function __construct(Closure $usuarioActual, private readonly Closure $crearServicio,
        CsrfService $csrf, SessionStore $store, Vista $vista, AutorizacionService $autorizacion, ?Closure $renderizar = null)
    {
        parent::__construct($usuarioActual, $csrf, $store, $vista, $autorizacion, $renderizar);
    }

    private function formulario(UsuarioAutenticado $usuario, bool $edicion, array $valores = [], mixed $id = null,
        mixed $version = null, ?ErrorGestionHU04 $error = null): Respuesta
    {
        if ($error !== null && !in_array($error->estado, [409, 422], true)) { return $this->mensaje($error->getMessage(), $error->estado); }
        if ($edicion) { $id = ServicioHU04::id($id, static::MODULO === 'tipos-servicio' ? 255 : PHP_INT_MAX); }
        return $this->vista(static::MODULO . '/formulario', [
            'usuario' => $usuario, 'token' => $this->csrf->token(), 'edicion' => $edicion,
            'id' => $id, 'version' => is_string($version) ? $version : '',
            'valores' => $this->valores($valores, static::CAMPOS), 'errores' => $error?->errores ?? [],
            'error' => $error?->getMessage() ?? '',
        ], $error?->estado ?? 200);
    }

    public function listar(array $consulta = []): Respuesta
    {
        return $this->ejecutar(fn (UsuarioAutenticado $u): Respuesta => $this->vista(static::MODULO . '/listado',
            ($this->crearServicio)()->listar($consulta['q'] ?? '') + ['usuario' => $u, 'token' => $this->csrf->token(), 'exito' => $this->flash(static::MODULO)]));
    }

    public function nuevo(array $consulta = []): Respuesta
    {
        return $this->ejecutar(fn (UsuarioAutenticado $u): Respuesta => $this->formulario($u, false));
    }

    public function registrar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos): Respuesta {
            try {
                $entrada = $this->entrada($datos, static::CAMPOS);
                ($this->crearServicio)()->registrar($u->idUsuario, $entrada);
            } catch (ErrorGestionHU04 $e) { return $this->formulario($u, false, $datos, error: $e); }
            return $this->exito(static::MODULO, 'Registro creado correctamente.');
        });
    }

    public function editar(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $fila = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
            return $this->formulario($u, true, $fila, $fila[static::ID], $fila['version']);
        });
    }

    public function modificar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos, $consulta): Respuesta {
            try {
                $entrada = $this->entrada($datos, static::CAMPOS, ['version']);
                ($this->crearServicio)()->modificar($u->idUsuario, $consulta['id'] ?? null, $entrada, $datos['version'] ?? null);
            } catch (ErrorGestionHU04 $e) {
                // No sustituir versión obsoleta por la nueva: obliga a revisar con GET antes de reintentar.
                return $this->formulario($u, true, $datos, $consulta['id'] ?? null, $datos['version'] ?? null, $e);
            }
            return $this->exito(static::MODULO, 'Datos actualizados correctamente.');
        });
    }

    public function confirmarEstado(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $nuevo = ServicioHU04::estado($consulta['estado'] ?? null);
            $fila = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
            return $this->vista(static::MODULO . '/estado', [
                'usuario' => $u, 'token' => $this->csrf->token(), 'registro' => array_intersect_key($fila, array_flip([static::ID, 'nombre', 'activo'])),
                'version' => $fila['version'], 'nuevoEstado' => $nuevo, 'redundante' => (int) $fila['activo'] === $nuevo,
            ]);
        });
    }

    public function cambiarEstado(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos, $consulta): Respuesta {
            $this->entrada($datos, [], ['version', 'confirmacion']);
            ($this->crearServicio)()->cambiarEstado($u->idUsuario, $consulta['id'] ?? null, $consulta['estado'] ?? null,
                $datos['version'] ?? null, $datos['confirmacion'] ?? null);
            return $this->exito(static::MODULO, 'Estado actualizado correctamente.');
        });
    }

    public function confirmarEliminacion(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $fila = ($this->crearServicio)()->prepararEliminacion($consulta['id'] ?? null);
            return $this->vista(static::MODULO . '/eliminar', [
                'usuario' => $u,
                'token' => $this->csrf->token(),
                'registro' => array_intersect_key($fila, array_flip([static::ID, 'nombre', 'referencia', 'activo'])),
                'version' => $fila['version'],
            ]);
        });
    }

    public function eliminar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos, $consulta): Respuesta {
            $this->entrada($datos, [], ['version', 'confirmacion']);
            ($this->crearServicio)()->eliminar(
                $u->idUsuario,
                $consulta['id'] ?? null,
                $datos['version'] ?? null,
                $datos['confirmacion'] ?? null
            );
            return $this->exito(static::MODULO, 'Registro eliminado correctamente.');
        });
    }
}
