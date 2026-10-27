<?php

declare(strict_types=1);

namespace SFATSA\Http;

use Closure;
use SFATSA\Controllers\{AdministracionHU04Controller, ParadaController, RutaController, TipoServicioController};
use SFATSA\Repositories\{PdoGestionUsuarioRepository, PdoParadaRepository, PdoRutaRepository, PdoRutaParadaRepository, PdoTipoServicioRepository};
use SFATSA\Security\SessionStore;
use SFATSA\Services\{AutorizacionService, CsrfService, OperacionAdministrativaService, ParadaService, RutaService, TipoServicioService, RegistroRutaService};
use InvalidArgumentException;

/** Composición y guarda HU-04 del despachador query-route existente, sin abrir PDO al cargar. */
final class IntegracionHU04
{
    public const PLANTILLAS = [
        'paradas' => ['listado', 'formulario', 'estado', 'eliminar'],
        'tipos-servicio' => ['listado', 'formulario', 'estado'],
        'rutas' => ['listado', 'detalle', 'formulario', 'confirmacion', 'estado', 'eliminar'],
    ];

    public static function esRuta(string $ruta): bool { return isset(self::PLANTILLAS[explode('.', $ruta, 2)[0]]); }

    public static function rutasDisponibles(array $rutas, ?Closure $existePlantilla = null): array
    {
        $existe = $existePlantilla ?? static fn (string $p): bool => is_file(dirname(__DIR__) . '/Views/' . $p . '.php');
        foreach (self::PLANTILLAS as $modulo => $plantillas) {
            foreach ($plantillas as $plantilla) {
                if (!$existe($modulo . '/' . $plantilla)) {
                    foreach ($rutas as $nombre => $metodos) {
                        if (explode('.', $nombre, 2)[0] === $modulo) { unset($rutas[$nombre]); }
                    }
                    break;
                }
            }
        }
        return $rutas;
    }

    public static function atender(array $rutas, string $ruta, string $metodo, array $consulta, array $datos,
        Closure $crearControlador, ?Closure $existePlantilla = null): Respuesta
    {
        $rutas = self::rutasDisponibles($rutas, $existePlantilla);
        if (!self::esRuta($ruta) || !isset($rutas[$ruta])) {
            return new Respuesta((new Vista())->renderizar('mensaje', ['titulo' => 'Página no encontrada', 'mensaje' => 'La página solicitada no está disponible.']), 404);
        }
        if (!isset($rutas[$ruta][$metodo])) {
            return new Respuesta((new Vista())->renderizar('mensaje', ['titulo' => 'Método no permitido', 'mensaje' => 'La solicitud no utiliza el método permitido.']),
                405, ['Allow' => implode(', ', array_keys($rutas[$ruta]))]);
        }
        [$modulo, $accion] = $rutas[$ruta][$metodo];
        $controlador = $crearControlador($modulo);
        return $metodo === 'POST' ? $controlador->$accion($datos, $consulta) : $controlador->$accion($consulta);
    }

    public static function controlador(string $modulo, Closure $usuarioActual, Closure $crearConexion,
        CsrfService $csrf, SessionStore $store, Vista $vista, AutorizacionService $autorizacion,
        ?Closure $renderizar = null): AdministracionHU04Controller
    {
        if (!isset(self::PLANTILLAS[$modulo])) { throw new InvalidArgumentException('Módulo no permitido.'); }
        $servicios = null;
        $crear = static function () use (&$servicios, $crearConexion): array {
            if ($servicios === null) {
                // Resolver EXACTAMENTE una PDO y compartir identidad con autorización/transacción/agregado.
                $pdo = $crearConexion();
                $operaciones = new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo));
                $paradas = new PdoParadaRepository($pdo);
                $servicios = [
                    'paradas' => new ParadaService($paradas, $operaciones),
                    'tipos-servicio' => new TipoServicioService(new PdoTipoServicioRepository($pdo), $operaciones),
                    'rutas' => new RutaService(new PdoRutaRepository($pdo), $paradas, new PdoRutaParadaRepository($pdo), $operaciones, new PdoTipoServicioRepository($pdo)),
                ];
            }
            return $servicios;
        };
        $servicio = static fn () => $crear()[$modulo];
        if ($modulo === 'rutas') {
            return new RutaController($usuarioActual, $servicio, static fn () => $crear()['paradas'],
                static fn () => new RegistroRutaService($crear()['rutas'], $store), $csrf, $store, $vista, $autorizacion, $renderizar,
                static fn () => $crear()['tipos-servicio']);
        }
        $clase = $modulo === 'paradas' ? ParadaController::class : TipoServicioController::class;
        return new $clase($usuarioActual, $servicio, $csrf, $store, $vista, $autorizacion, $renderizar);
    }
}
