<?php

declare(strict_types=1);

ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/HU04Ficticios.php';

use SFATSA\Controllers\{AutenticacionController, CatalogoHU04Controller, ParadaController, RutaController, TipoServicioController};
use SFATSA\Http\{IntegracionHU04, Respuesta, Vista};
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{AutenticacionService, AutorizacionService, CsrfService, OperacionAdministrativaService, ParadaService, RegistroRutaService, RutaService, SesionService, TipoServicioService};

$checks = 0; $caso = 'inicio';
$assert = static function (bool $valor, string $mensaje = '') use (&$checks): void {
    if (!$valor) { throw new RuntimeException('Comprobación fallida en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0) . '. ' . $mensaje); }
    $checks++;
};

/** Sesión y reglas reales con repositorios/identidades sintéticos. No abre PDO. */
function escenarioHttpHU04(?int $actor = 41): object
{
    $f = (object) ['d' => new DatosHU04Ficticios(), 'u' => new GestionUsuariosFicticia(), 'store' => new SesionGestionFicticia(),
        'ahora' => 10000, 'vistas' => [], 'fabricas' => 0];
    $f->admin = new AdministracionHU04Ficticia($f->u, $f->d); $ops = new OperacionAdministrativaService($f->admin);
    $p = new ParadasHU04Ficticias($f->d);
    $f->servicios = ['paradas' => new ParadaService($p, $ops), 'tipos-servicio' => new TipoServicioService(new TiposHU04Ficticios($f->d), $ops),
        'rutas' => new RutaService(new RutasHU04Ficticias($f->d), $p, new AsociacionesHU04Ficticias($f->d), $ops)];
    $f->sesion = new SesionService($f->store, static fn (): int => $f->ahora); $f->csrf = new CsrfService($f->store);
    $autorizacion = new AutorizacionService();
    $f->auth = new AutenticacionController($f->sesion, $f->csrf, static fn () => new AutenticacionService($f->u), new Vista(), $autorizacion);
    if ($actor !== null) {
        $fila = $f->u->buscar($actor);
        $f->sesion->iniciar(new UsuarioAutenticado($actor, $fila['nombres'], $fila['apellidos'], $fila['usuario'], $fila['rol']));
    }
    $crear = static function (string $modulo) use ($f) { $f->fabricas++; return $f->servicios[$modulo]; };
    $renderizar = static function (string $plantilla, array $datos) use ($f): string {
        $f->vistas[] = compact('plantilla', 'datos'); return 'PRESENTACION_SIMULADA:' . $plantilla;
    };
    $actual = static fn () => $f->auth->usuarioActual();
    $f->controladores = [
        'paradas' => new ParadaController($actual, static fn () => $crear('paradas'), $f->csrf, $f->store, new Vista(), $autorizacion, $renderizar),
        'tipos-servicio' => new TipoServicioController($actual, static fn () => $crear('tipos-servicio'), $f->csrf, $f->store, new Vista(), $autorizacion, $renderizar),
        'rutas' => new RutaController($actual, static fn () => $crear('rutas'), static fn () => $crear('paradas'),
            static function () use ($f, $crear): RegistroRutaService { return new RegistroRutaService($crear('rutas'), $f->store, static fn (): int => $f->ahora); },
            $f->csrf, $f->store, new Vista(), $autorizacion, $renderizar),
    ];
    $routes = require dirname(__DIR__) . '/routes/web.php';
    $f->pedir = static fn (string $ruta, string $metodo = 'GET', array $consulta = [], array $datos = []): Respuesta =>
        IntegracionHU04::atender($routes, $ruta, $metodo, $consulta, $datos, static fn (string $m) => $f->controladores[$m], static fn (): bool => true);
    return $f;
}
function vistaHttpHU04(object $f): array { return $f->vistas[array_key_last($f->vistas)]['datos']; }
function postHttpHU04(object $f, string $ruta, array $datos = [], array $consulta = []): Respuesta
{
    return ($f->pedir)($ruta, 'POST', $consulta, ['csrf' => $f->csrf->token()] + $datos);
}
function prepararHttpHU04(object $f, array $cambios = []): array
{
    $datos = array_replace(['codigo' => ' ruta-01 ', 'nombre' => ' Ruta de prueba ', 'origen' => '1', 'destino' => '2'], $cambios);
    $respuesta = postHttpHU04($f, 'rutas.revisar', $datos);
    if ($respuesta->estado !== 200) { throw new RuntimeException('Revisión sintética fallida: ' . $respuesta->estado); }
    return vistaHttpHU04($f);
}

try {
    $routes = require dirname(__DIR__) . '/routes/web.php';
    $propias = array_filter($routes, static fn (string $k): bool => IntegracionHU04::esRuta($k), ARRAY_FILTER_USE_KEY);
    $caso = 'guarda por módulo, allowlist y métodos del despachador';
    $assert(count($propias) === 17);
    $cerradas = IntegracionHU04::rutasDisponibles($routes, static fn (): bool => false);
    foreach (array_keys($propias) as $ruta) {
        $assert(!isset($cerradas[$ruta]));
        foreach (['GET', 'POST'] as $metodo) {
            $respuesta = IntegracionHU04::atender($routes, $ruta, $metodo, [], [], static function (): never { throw new RuntimeException('No invocar fábrica sin vistas'); }, static fn (): bool => false);
            $assert($respuesta->estado === 404);
        }
    }
    foreach (['usuarios', 'conductores', 'vehiculos', 'login'] as $ruta) { $assert($cerradas[$ruta] === $routes[$ruta]); }
    foreach (IntegracionHU04::PLANTILLAS as $m => $nombres) {
        foreach ($nombres as $ausente) {
            $parciales = IntegracionHU04::rutasDisponibles($routes, static fn (string $v): bool => $v !== $m . '/' . $ausente);
            foreach ($propias as $ruta => $metodos) { $assert(isset($parciales[$ruta]) === (explode('.', $ruta)[0] !== $m)); }
        }
        try { (new Vista())->renderizar($m . '/no-permitida'); throw new LogicException('Faltó rechazo de vista'); }
        catch (InvalidArgumentException) { $assert(true); }
    }
    foreach ($propias as $ruta => $metodos) {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'] as $metodo) {
            if (isset($metodos[$metodo])) { continue; }
            $respuesta = IntegracionHU04::atender($routes, $ruta, $metodo, [], [], static function (): never { throw new RuntimeException('Fábrica en 405'); }, static fn (): bool => true);
            $assert($respuesta->estado === 405 && $respuesta->cabeceras['Allow'] === implode(', ', array_keys($metodos)));
        }
    }
    $f = escenarioHttpHU04();
    $assert(($f->pedir)('rutas.editar')->estado === 404 && ($f->pedir)('rutas.eliminar', 'POST')->estado === 403);
    $caso = 'autorización vigente y CSRF antes de servicios para todas las acciones';
    foreach ([null, 42, 43, 44] as $actor) {
        $f = escenarioHttpHU04($actor);
        foreach ($propias as $ruta => $metodos) {
            foreach ($metodos as $metodo => $_) {
                $r = ($f->pedir)($ruta, $metodo); $assert($r->estado === ($actor === null || $actor === 44 ? 303 : 403));
                if ($r->estado === 303) { $assert($r->cabeceras['Location'] === '/'); }
            }
        }
        $assert($f->fabricas === 0 && $f->d->rutas === []);
    }
    $f = escenarioHttpHU04(); $f->u->filas[41]['id_rol'] = 13;
    $assert(($f->pedir)('rutas')->estado === 403 && $f->fabricas === 0);
    $f = escenarioHttpHU04(); $f->csrf->token();
    foreach ($propias as $ruta => $metodos) {
        if (!isset($metodos['POST'])) { continue; }
        foreach ([[], ['csrf' => 'incorrecto'], ['csrf' => []]] as $datos) { $assert(($f->pedir)($ruta, 'POST', [], $datos)->estado === 403); }
    }
    $assert($f->fabricas === 0 && $f->d->rutas === []);
    $caso = 'GET administrativo, formularios y lectura controlada';
    foreach (['rutas', 'paradas', 'tipos-servicio', 'rutas.registrar', 'paradas.registrar', 'tipos-servicio.registrar'] as $ruta) { $assert(($f->pedir)($ruta)->estado === 200); }
    ($f->pedir)('rutas.registrar');
    $assert(count(vistaHttpHU04($f)['paradas']) === 59
        && count(array_filter(vistaHttpHU04($f)['paradas'], static fn (array $p): bool => (int) $p['activo'] !== 1)) === 0);
    $htmlFormularioRuta = (new Vista())->renderizar('rutas/formulario', [
        'usuario' => new UsuarioAutenticado(41, 'Admin', 'Sintético', 'admin_sintetico', 'ADMINISTRADOR'),
        'token' => str_repeat('a', 64),
        'valores' => ['codigo' => 'RUTA-UI', 'nombre' => 'Ruta UI', 'origen' => '1', 'destino' => '3', 'intermedias' => ['2']],
        'errores' => [],
        'error' => '',
        'paradas' => [
            ['id_parada' => 1, 'nombre' => 'Origen', 'referencia' => null, 'activo' => 1],
            ['id_parada' => 2, 'nombre' => 'Intermedia', 'referencia' => null, 'activo' => 1],
            ['id_parada' => 3, 'nombre' => 'Destino', 'referencia' => null, 'activo' => 1],
        ],
    ]);
    $assert(str_contains($htmlFormularioRuta, 'Agregar parada intermedia')
        && str_contains($htmlFormularioRuta, 'name="intermedias[]"')
        && str_contains($htmlFormularioRuta, 'data-max-intermedias="48"')
        && str_contains($htmlFormularioRuta, 'Subir parada intermedia 1')
        && str_contains($htmlFormularioRuta, '/assets/js/rutas.js'));
    $assert(str_contains($htmlFormularioRuta, 'Parada intermedia 1'), 'Etiqueta de intermedia renderizada');
    $assert(str_contains($htmlFormularioRuta, 'value="2" selected'), 'Intermedia seleccionada preservada');
    $assert(str_contains($htmlFormularioRuta, 'no se podrá editar su recorrido.'), 'Alcance de edición indicado');
    foreach (['rutas', 'paradas', 'tipos-servicio'] as $ruta) { $assert(($f->pedir)($ruta, 'GET', ['q' => []])->estado === 400); }
    $assert(($f->pedir)('rutas.detalle', 'GET', ['id' => '1 OR 1=1'])->estado === 400);
    $assert(($f->pedir)('rutas.detalle', 'GET', ['id' => 999])->estado === 404);
    $caso = 'validaciones y campos protegidos';
    foreach ([['codigo' => ''], ['nombre' => ''], ['codigo' => str_repeat('a', 31)], ['destino' => 1], ['destino' => 999], ['destino' => 60],
        ['intermedias' => [2]], ['intermedias' => [3, '3']], ['activa' => 0], ['orden' => 5], ['tipo_servicio' => 1], ['distancia_acumulada_km' => 10]] as $cambio) {
        $datos = array_replace(['codigo' => 'PRUEBA', 'nombre' => 'Ruta', 'origen' => 1, 'destino' => 2], $cambio);
        $assert(postHttpHU04($f, 'rutas.revisar', $datos)->estado === 422);
        $assert($f->d->rutas === [] && $f->d->asociaciones === []);
    }
    foreach ([['paradas.registrar', ['nombre' => '']], ['paradas.registrar', ['nombre' => 'A', 'activo' => 0]],
        ['tipos-servicio.registrar', ['nombre' => 'DIRECTO']], ['tipos-servicio.registrar', ['nombre' => 'A', 'controla_capacidad' => 0]],
        ['tipos-servicio.registrar', ['nombre' => str_repeat('x', 41)]]] as [$ruta, $datos]) {
        $assert(postHttpHU04($f, $ruta, $datos)->estado === ($datos['nombre'] === 'DIRECTO' ? 409 : 422));
    }
    $caso = 'revisión, separación de tokens, confirmación y PRG';
    $f = escenarioHttpHU04(); $resumen = prepararHttpHU04($f); $token = $resumen['token'];
    $assert($resumen['datos']['codigo'] === 'RUTA-01' && $resumen['datos']['nombre'] === 'Ruta de prueba');
    $assert(array_column($resumen['recorrido'], 'id_parada') === [1, 2] && array_column($resumen['recorrido'], 'nombre') === ['Parada 1', 'Parada 2']);
    $assert(preg_match('/\A[a-f0-9]{64}\z/', $token) === 1 && $resumen['tokenCsrf'] !== $token);
    $assert($f->d->rutas === [] && $f->d->asociaciones === []);
    $assert(!in_array('iniciar', $f->u->eventos, true));
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token, 'codigo' => 'MANIPULADO'])->estado === 422);
    $assert(($f->pedir)('rutas.confirmar', 'POST', [], ['csrf' => $token, 'token' => $token])->estado === 403);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $resumen['tokenCsrf']])->estado === 409);
    $r = postHttpHU04($f, 'rutas.confirmar', ['token' => $token]);
    $assert($r->estado === 303 && $r->cabeceras['Location'] === '/?ruta=rutas');
    $assert(count($f->d->rutas) === 1 && count($f->d->asociaciones) === 2);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409 && count($f->d->rutas) === 1);
    ($f->pedir)('rutas'); $assert(vistaHttpHU04($f)['exito'] === 'Ruta y recorrido registrados correctamente.');
    ($f->pedir)('rutas'); $assert(vistaHttpHU04($f)['exito'] === '');
    $assert(postHttpHU04($f, 'rutas.revisar', ['codigo' => 'ruta-01', 'nombre' => 'Otra', 'origen' => 1, 'destino' => 2])->estado === 409);
    ($f->pedir)('rutas.detalle', 'GET', ['id' => 1]); $assert(array_column(vistaHttpHU04($f)['ruta']['paradas'], 'orden') === [1, 2]);
    $caso = 'cancelación antes y después de revisión';
    $f = escenarioHttpHU04(); $assert(postHttpHU04($f, 'rutas.cancelar')->estado === 303 && $f->fabricas === 0);
    $token = prepararHttpHU04($f)['token']; $assert(postHttpHU04($f, 'rutas.cancelar', ['token' => $token])->estado === 303);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409 && $f->d->rutas === []);
    $assert(postHttpHU04($f, 'rutas.cancelar', ['token' => []])->estado === 400);
    $caso = 'expiración, límite de resúmenes y cambio de administrador';
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token']; $f->ahora += 900;
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409 && $f->d->rutas === []);
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token']; $f->ahora += 899;
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 303);
    $f = escenarioHttpHU04(); $tokens = [];
    for ($i = 0; $i < 6; $i++) { $tokens[] = prepararHttpHU04($f, ['codigo' => 'RESUMEN-' . $i])['token']; }
    $assert(count($f->store->obtener('rutas_registros')) === 5);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $tokens[0]])->estado === 409);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $tokens[5]])->estado === 303);
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token'];
    $sesion = $f->store->obtener('autenticacion'); $sesion['id_usuario'] = 45; $f->store->guardar('autenticacion', $sesion);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 403 && $f->d->rutas === []);
    $fila = $f->u->buscar(45); $viejoCsrf = $f->csrf->token();
    $f->sesion->iniciar(new UsuarioAutenticado(45, $fila['nombres'], $fila['apellidos'], $fila['usuario'], $fila['rol']));
    $assert(($f->pedir)('rutas.confirmar', 'POST', [], ['csrf' => $viejoCsrf, 'token' => $token])->estado === 403);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409);
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token'];
    $f->d->rutas[1] = ['id_ruta' => 1, 'codigo' => 'RUTA-01', 'nombre' => 'Registrada concurrentemente', 'activa' => 1];
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409 && count($f->d->rutas) === 1);
    $assert(isset(vistaHttpHU04($f)['errores']['codigo']) && vistaHttpHU04($f)['valores']['codigo'] === 'RUTA-01');
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409);
    $caso = '50 puntos, rechazo de 51, revalidación y rollback';
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f, ['destino' => 50, 'intermedias' => range(2, 49)])['token'];
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 303);
    $assert(array_column($f->d->asociaciones, 'orden') === range(1, 50));
    $assert(postHttpHU04($f, 'rutas.revisar', ['codigo' => '51', 'nombre' => 'No válida', 'origen' => 1, 'destino' => 51, 'intermedias' => range(2, 50)])->estado === 422);
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token']; $f->d->paradas[2]['activo'] = 0;
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 422 && $f->d->rutas === []);
    $assert(vistaHttpHU04($f)['valores']['codigo'] === 'RUTA-01');
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409);
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f, ['intermedias' => [3]])['token']; $f->d->fallar = 'asociacion:3';
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 503 && $f->d->rutas === [] && $f->d->asociaciones === []);
    $assert(!str_contains(vistaHttpHU04($f)['mensaje'], 'SENSIBLE'));
    $f->d->fallar = null; $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 409);
    $caso = 'edición y estados de catálogos con versiones, sin alterar asociaciones';
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token']; postHttpHU04($f, 'rutas.confirmar', ['token' => $token]);
    $asociaciones = $f->d->asociaciones;
    foreach (['paradas', 'tipos-servicio', 'rutas'] as $modulo) {
        ($f->pedir)($modulo . '.estado', 'GET', ['id' => 1, 'estado' => 0]); $v = vistaHttpHU04($f)['version'];
        $assert(postHttpHU04($f, $modulo . '.estado', ['version' => $v, 'confirmacion' => '1'], ['id' => 1, 'estado' => 0])->estado === 303);
        $assert(postHttpHU04($f, $modulo . '.estado', ['version' => $v, 'confirmacion' => '1'], ['id' => 1, 'estado' => 0])->estado === 409);
        ($f->pedir)($modulo . '.estado', 'GET', ['id' => 1, 'estado' => 1]); $v = vistaHttpHU04($f)['version'];
        $assert(postHttpHU04($f, $modulo . '.estado', ['version' => $v, 'confirmacion' => '0'], ['id' => 1, 'estado' => 1])->estado === 422);
        $assert(postHttpHU04($f, $modulo . '.estado', ['version' => $v, 'confirmacion' => '1'], ['id' => 1, 'estado' => 1])->estado === 303);
        $assert($f->d->asociaciones === $asociaciones);
    }
    $caso = 'confirmación HTTP y eliminación segura de rutas y paradas';
    $f = escenarioHttpHU04(); $resumenRuta = prepararHttpHU04($f);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $resumenRuta['token']])->estado === 303);
    $confirmarRuta = ($f->pedir)('rutas.eliminar', 'GET', ['id' => 1]);
    $datosRuta = vistaHttpHU04($f);
    $assert($confirmarRuta->estado === 200 && ($datosRuta['ruta']['codigo'] ?? null) === 'RUTA-01',
        'Estado=' . $confirmarRuta->estado . '; datos=' . implode(',', array_keys($datosRuta)));
    $htmlEliminarRuta = (new Vista())->renderizar('rutas/eliminar', [
        'usuario' => new UsuarioAutenticado(41, 'Admin', 'Sintético', 'admin_sintetico', 'ADMINISTRADOR'),
        'token' => $f->csrf->token(), 'ruta' => $datosRuta['ruta'], 'version' => $datosRuta['version'],
    ]);
    $assert(str_contains($htmlEliminarRuta, 'action="/?ruta=rutas.eliminar&amp;id=1"')
        && str_contains($htmlEliminarRuta, 'name="confirmacion" value="1" required')
        && str_contains($htmlEliminarRuta, '<li>Parada 1</li>') && str_contains($htmlEliminarRuta, '<li>Parada 2</li>'));
    $respuesta = postHttpHU04($f, 'rutas.eliminar', ['version' => $datosRuta['version'], 'confirmacion' => '1'], ['id' => 1]);
    $assert($respuesta->estado === 303 && $f->d->rutas === [] && $f->d->asociaciones === []);
    $assert(isset($f->d->paradas[1], $f->d->paradas[2]));
    $assert(postHttpHU04($f, 'rutas.eliminar', ['version' => $datosRuta['version'], 'confirmacion' => '1'], ['id' => 1])->estado === 404);

    $f = escenarioHttpHU04();
    $confirmarParada = ($f->pedir)('paradas.eliminar', 'GET', ['id' => 10]); $datosParada = vistaHttpHU04($f);
    $assert($confirmarParada->estado === 200 && $datosParada['registro']['id_parada'] === 10);
    $htmlEliminarParada = (new Vista())->renderizar('paradas/eliminar', [
        'usuario' => new UsuarioAutenticado(41, 'Admin', 'Sintético', 'admin_sintetico', 'ADMINISTRADOR'),
        'token' => $f->csrf->token(), 'registro' => $datosParada['registro'], 'version' => $datosParada['version'],
    ]);
    $assert(str_contains($htmlEliminarParada, 'action="/?ruta=paradas.eliminar&amp;id=10"')
        && str_contains($htmlEliminarParada, 'name="confirmacion" value="1" required'));
    $assert(postHttpHU04($f, 'paradas.eliminar', ['version' => $datosParada['version'], 'confirmacion' => '1'], ['id' => 10])->estado === 303);
    $assert(!isset($f->d->paradas[10]));

    $f = escenarioHttpHU04(); $resumenRuta = prepararHttpHU04($f);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $resumenRuta['token']])->estado === 303);
    $datosParada = $f->servicios['paradas']->prepararEliminacion(1);
    $respuesta = postHttpHU04($f, 'paradas.eliminar', ['version' => $datosParada['version'], 'confirmacion' => '1'], ['id' => 1]);
    $assert($respuesta->estado === 409 && isset($f->d->paradas[1]) && count($f->d->asociaciones) === 2);

    $f = escenarioHttpHU04(); $f->d->dependenciasDisponibles = false;
    $assert(($f->pedir)('paradas.eliminar', 'GET', ['id' => 10])->estado === 503 && isset($f->d->paradas[10]));

    $f = escenarioHttpHU04(); $resumenRuta = prepararHttpHU04($f);
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $resumenRuta['token']])->estado === 303);
    $asociaciones = $f->d->asociaciones;
    foreach (['paradas', 'tipos-servicio'] as $modulo) {
        $id = $modulo === 'paradas' ? 1 : 2;
        ($f->pedir)($modulo . '.editar', 'GET', ['id' => $id]); $v = vistaHttpHU04($f)['version'];
        $datos = ['nombre' => 'Nombre actualizado', 'version' => $v];
        if ($modulo === 'paradas') { $datos['referencia'] = 'Nueva referencia'; }
        $assert(postHttpHU04($f, $modulo . '.editar', $datos, ['id' => $id])->estado === 303);
        $assert(postHttpHU04($f, $modulo . '.editar', $datos, ['id' => $id])->estado === 409);
        $assert(vistaHttpHU04($f)['version'] === $v && vistaHttpHU04($f)['valores']['nombre'] === $datos['nombre']);
        $assert(postHttpHU04($f, $modulo . '.editar', ['nombre' => 'No', 'version' => []], ['id' => $id])->estado === 400);
    }
    $assert($f->d->tipos[2]['controla_capacidad'] === 0 && $f->d->asociaciones === $asociaciones);
    foreach (['controla_capacidad' => 1, 'activo' => 0, 'id_tipo_servicio' => 1] as $campoProtegido => $valor) {
        $v = $f->servicios['tipos-servicio']->obtener(2)['version'];
        $assert(postHttpHU04($f, 'tipos-servicio.editar', ['nombre' => 'Manipulado', 'version' => $v, $campoProtegido => $valor], ['id' => 2])->estado === 422);
        $assert($f->d->tipos[2]['nombre'] === 'Nombre actualizado' && $f->d->tipos[2]['controla_capacidad'] === 0);
    }
    $v = $f->servicios['paradas']->obtener(1)['version'];
    $assert(postHttpHU04($f, 'paradas.editar', ['nombre' => 'Manipulada', 'version' => $v, 'activo' => 0], ['id' => 1])->estado === 422);
    $assert($f->d->paradas[1]['nombre'] === 'Nombre actualizado' && $f->d->asociaciones === $asociaciones);
    foreach ([1, 2] as $id) {
        $v = $f->servicios['tipos-servicio']->obtener($id)['version'];
        postHttpHU04($f, 'tipos-servicio.estado', ['version' => $v, 'confirmacion' => '1'], ['id' => $id, 'estado' => 0]);
        $assert($f->d->tipos[$id]['controla_capacidad'] === ($id === 1 ? 1 : 0));
    }
    $assert(postHttpHU04($f, 'tipos-servicio.registrar', ['nombre' => 'Especial'])->estado === 303 && $f->d->tipos[3]['controla_capacidad'] === 1);
    foreach ([1, 2] as $_) { $assert(postHttpHU04($f, 'paradas.registrar', ['nombre' => 'Duplicable'])->estado === 303); }
    $assert($f->d->paradas[61]['nombre'] === $f->d->paradas[62]['nombre']);
    $caso = 'errores controlados y revocación durante transacción';
    $f = escenarioHttpHU04(); $f->d->fallar = 'listar';
    foreach (['paradas', 'tipos-servicio', 'rutas'] as $ruta) { $assert(($f->pedir)($ruta)->estado === 503 && !str_contains(vistaHttpHU04($f)['mensaje'], 'SENSIBLE')); }
    $f = escenarioHttpHU04(); $token = prepararHttpHU04($f)['token'];
    $f->admin->trasBloqueo = static function () use ($f): void { $f->u->filas[41]['activo'] = 0; };
    $assert(postHttpHU04($f, 'rutas.confirmar', ['token' => $token])->estado === 403 && $f->d->rutas === []);
    $caso = 'composición diferida con identidad única de PDO';
    $pdo = new class extends PDO { public function __construct() {} }; $llamadas = 0;
    $conexion = static function () use ($pdo, &$llamadas): PDO { $llamadas++; return $pdo; };
    $f = escenarioHttpHU04();
    $compuesto = IntegracionHU04::controlador('rutas', static fn () => null, $conexion, $f->csrf, $f->store, new Vista(), new AutorizacionService());
    $assert($compuesto->listar()->estado === 303 && $llamadas === 0);
    $servicio = (new ReflectionProperty(RutaController::class, 'crearServicio'))->getValue($compuesto); $r = $servicio();
    $campo = static fn (object $obj, string $nombre): mixed => (new ReflectionProperty($obj, $nombre))->getValue($obj);
    foreach (['repository', 'paradas', 'asociaciones'] as $nombre) { $assert($campo($campo($r, $nombre), 'connection') === $pdo); }
    $assert($campo($campo($campo($r, 'operaciones'), 'repository'), 'connection') === $pdo);
    $paradas = (new ReflectionProperty(RutaController::class, 'crearParadas'))->getValue($compuesto);
    $assert($campo($paradas(), 'repository') === $campo($r, 'paradas') && $llamadas === 1);
    $assert($servicio() === $r && $llamadas === 1);
    foreach (['paradas', 'tipos-servicio'] as $modulo) {
        $compuesto = IntegracionHU04::controlador($modulo, static fn () => null, $conexion, $f->csrf, $f->store, new Vista(), new AutorizacionService());
        $crear = (new ReflectionProperty(CatalogoHU04Controller::class, 'crearServicio'))->getValue($compuesto); $s = $crear();
        $assert($campo($campo($s, 'repository'), 'connection') === $pdo && $campo($campo($campo($s, 'operaciones'), 'repository'), 'connection') === $pdo);
    }
    try { Respuesta::redirigir('/?ruta=rutas&destino=https://ejemplo.invalid'); throw new LogicException('Redirección insegura'); }
    catch (InvalidArgumentException) { $assert(true); }
    echo 'HTTP HU-04: ' . $checks . " comprobaciones correctas. Despachador/controladores y vistas de confirmación reales; sesión y repositorios simulados, sin acceso a sfatza.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALLO HTTP HU-04: ' . $caso . '; ' . $e->getMessage() . "\n"); exit(1);
}
