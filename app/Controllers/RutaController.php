<?php

declare(strict_types=1);

namespace SFATSA\Controllers;

use Closure;
use SFATSA\Http\{Respuesta, Vista};
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Security\SessionStore;
use SFATSA\Services\{AutorizacionService, CsrfService, ErrorGestionHU04, ServicioHU04};

final class RutaController extends AdministracionHU04Controller
{
    private const CAMPOS = ['codigo', 'nombre', 'origen', 'destino', 'intermedias', 'id_tipo_servicio'];

    public function __construct(Closure $usuarioActual, private readonly Closure $crearServicio,
        private readonly Closure $crearParadas, private readonly Closure $crearRegistro,
        CsrfService $csrf, SessionStore $store, Vista $vista, AutorizacionService $autorizacion, ?Closure $renderizar = null,
        private readonly ?Closure $crearTipos = null)
    {
        parent::__construct($usuarioActual, $csrf, $store, $vista, $autorizacion, $renderizar);
    }

    private function formulario(UsuarioAutenticado $usuario, array $valores = [], ?ErrorGestionHU04 $error = null,
        bool $edicion = false, mixed $idRuta = null, mixed $version = null): Respuesta
    {
        if ($error !== null && !in_array($error->estado, [409, 422], true)) { return $this->mensaje($error->getMessage(), $error->estado); }
        $paradas = ($this->crearParadas)()->listar()['paradas'];
        $tipos = $this->crearTipos === null ? [] : ($this->crearTipos)()->listar()['tipos_servicio'];
        return $this->vista('rutas/formulario', ['usuario' => $usuario, 'token' => $this->csrf->token(),
            'edicion' => $edicion, 'idRuta' => $idRuta, 'version' => is_string($version) ? $version : '',
            'tipos' => array_values(array_filter($tipos, static fn (array $t): bool => (int) $t['activo'] === 1)),
            'asociacionDisponible' => ($this->crearServicio)()->admiteServicio(),
            'valores' => $this->valores($valores, self::CAMPOS), 'errores' => $error?->errores ?? [], 'error' => $error?->getMessage() ?? '',
            'paradas' => array_values(array_filter($paradas, static fn (array $p): bool => (int) $p['activo'] === 1)),
        ], $error?->estado ?? 200);
    }

    public function listar(array $consulta = []): Respuesta
    {
        return $this->ejecutar(fn (UsuarioAutenticado $u): Respuesta => $this->vista('rutas/listado', ($this->crearServicio)()->listar($consulta['q'] ?? '')
            + ['usuario' => $u, 'token' => $this->csrf->token(), 'exito' => $this->flash('rutas')]));
    }

    public function detalle(array $consulta = []): Respuesta
    {
        return $this->ejecutar(fn (UsuarioAutenticado $u): Respuesta => $this->vista('rutas/detalle',
            ['usuario' => $u, 'token' => $this->csrf->token(), 'ruta' => ($this->crearServicio)()->obtener($consulta['id'] ?? null)]));
    }

    public function nuevo(array $consulta = []): Respuesta { return $this->ejecutar(fn (UsuarioAutenticado $u): Respuesta => $this->formulario($u)); }

    private function captura(array $datos, array $transporte = []): array
    {
        $entrada = $this->entrada($datos, self::CAMPOS, $transporte);
        if (isset($entrada['intermedias']) && is_array($entrada['intermedias']) && array_is_list($entrada['intermedias'])) {
            $entrada['intermedias'] = array_values(array_filter($entrada['intermedias'], static fn (mixed $id): bool => $id !== ''));
        }
        return $entrada;
    }

    public function editar(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $ruta = ($this->crearServicio)()->prepararActualizacion($consulta['id'] ?? null);
            $ids = array_column($ruta['paradas'], 'id_parada');
            $valores = $ruta + ['origen' => $ids[0] ?? '', 'destino' => $ids === [] ? '' : $ids[count($ids) - 1], 'intermedias' => array_slice($ids, 1, max(0, count($ids) - 2))];
            return $this->formulario($u, $valores, edicion: true, idRuta: $ruta['id_ruta'], version: $ruta['version']);
        });
    }

    public function modificar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos, $consulta): Respuesta {
            try {
                $entrada = $this->captura($datos, ['version']);
                $id = ServicioHU04::id($consulta['id'] ?? null);
                ($this->crearServicio)()->modificar($u->idUsuario, $id, $entrada, $datos['version'] ?? null);
            } catch (ErrorGestionHU04 $e) {
                return $this->formulario($u, $datos, $e, true, $id ?? null, $datos['version'] ?? null);
            }
            return $this->exito('rutas', 'Ruta actualizada correctamente.');
        });
    }

    public function revisar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos): Respuesta {
            try {
                $entrada = $this->captura($datos);
                $resumen = ($this->crearRegistro)()->preparar($u->idUsuario, $entrada);
            } catch (ErrorGestionHU04 $e) { return $this->formulario($u, $datos, $e); }
            $catalogo = array_column(($this->crearParadas)()->listar()['paradas'], null, 'id_parada');
            $ids = [$resumen['datos']['origen'], ...$resumen['datos']['intermedias'], $resumen['datos']['destino']];
            $recorrido = [];
            foreach ($ids as $i => $id) {
                $parada = $catalogo[$id] ?? [];
                $recorrido[] = ['id_parada' => $id, 'orden' => $i + 1, 'nombre' => $parada['nombre'] ?? '',
                    'referencia' => $parada['referencia'] ?? null, 'activo' => $parada['activo'] ?? 0];
            }
            return $this->vista('rutas/confirmacion', ['usuario' => $u, 'tokenCsrf' => $this->csrf->token(),
                'token' => $resumen['token'], 'datos' => $resumen['datos'], 'recorrido' => $recorrido,
                'tipoServicio' => ($this->crearTipos)()->obtener($resumen['datos']['id_tipo_servicio'])['nombre']]);
        });
    }

    public function confirmar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos): Respuesta {
            $this->entrada($datos, [], ['token']);
            $flujo = ($this->crearRegistro)(); $resumen = $flujo->obtenerResumen($u->idUsuario, $datos['token'] ?? null);
            try { $flujo->confirmar($u->idUsuario, $datos['token'] ?? null); }
            catch (ErrorGestionHU04 $e) { return $this->formulario($u, $resumen, $e); }
            return $this->exito('rutas', 'Ruta y recorrido registrados correctamente.');
        });
    }

    public function cancelar(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos): Respuesta {
            $this->entrada($datos, [], ['token']);
            // Antes de revisar no hay token ni resumen: cancelar solo descarta captura del cliente.
            if (array_key_exists('token', $datos)) { ($this->crearRegistro)()->cancelar($u->idUsuario, $datos['token']); }
            return $this->exito('rutas', 'Registro cancelado. No se guardaron datos.');
        });
    }

    public function confirmarEstado(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $nuevo = ServicioHU04::estado($consulta['estado'] ?? null); $ruta = ($this->crearServicio)()->obtener($consulta['id'] ?? null);
            return $this->vista('rutas/estado', ['usuario' => $u, 'token' => $this->csrf->token(), 'ruta' => $ruta,
                'version' => $ruta['version'], 'nuevoEstado' => $nuevo, 'redundante' => (int) $ruta['activa'] === $nuevo]);
        });
    }

    public function cambiarEstado(array $datos, array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($datos, $consulta): Respuesta {
            $this->entrada($datos, [], ['version', 'confirmacion']);
            ($this->crearServicio)()->cambiarEstado($u->idUsuario, $consulta['id'] ?? null, $consulta['estado'] ?? null,
                $datos['version'] ?? null, $datos['confirmacion'] ?? null);
            return $this->exito('rutas', 'Estado de la ruta actualizado correctamente.');
        });
    }

    public function confirmarEliminacion(array $consulta = []): Respuesta
    {
        return $this->ejecutar(function (UsuarioAutenticado $u) use ($consulta): Respuesta {
            $ruta = ($this->crearServicio)()->prepararEliminacion($consulta['id'] ?? null);
            return $this->vista('rutas/eliminar', [
                'usuario' => $u,
                'token' => $this->csrf->token(),
                'ruta' => $ruta,
                'version' => $ruta['version'],
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
            return $this->exito('rutas', 'Ruta eliminada correctamente. No se eliminaron las paradas del catálogo.');
        });
    }
}
