<?php

declare(strict_types=1);

// Focalizadas: memoria y renderizado PHP; nunca carga .env ni abre PDO.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/HU04Ficticios.php';
set_error_handler(static function (): never { throw new RuntimeException('Advertencia PHP durante prueba focalizada.'); });

use SFATSA\Controllers\RutaController;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{AutorizacionService, CsrfService, ErrorGestionHU04, OperacionAdministrativaService, ParadaService, RegistroRutaService, RutaService, TipoServicioService};

$checks = 0;
$assert = static function (bool $ok, string $mensaje = '') use (&$checks): void {
    if (!$ok) { throw new RuntimeException('Fallo en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0) . ': ' . $mensaje); } $checks++;
};
$rechazo = static function (Closure $accion, int $estado) use ($assert): ErrorGestionHU04 {
    try { $accion(); throw new RuntimeException('Faltó rechazo.'); }
    catch (ErrorGestionHU04 $e) { $assert($e->estado === $estado && $e->getPrevious() === null, 'Error esperado ' . $estado . ', recibido ' . $e->estado . '; caso línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); return $e; }
};

try {
    $d = new DatosHU04Ficticios(); $u = new GestionUsuariosFicticia(); $admin = new AdministracionHU04Ficticia($u, $d);
    $ops = new OperacionAdministrativaService($admin); $rp = new AsociacionesHU04Ficticias($d); $repo = new RutasHU04Ficticias($d);
    $tiposRepo = new TiposHU04Ficticios($d); $paradasRepo = new ParadasHU04Ficticias($d);
    $r = new RutaService($repo, $paradasRepo, $rp, $ops, $tiposRepo); $p = new ParadaService($paradasRepo, $ops);
    $t = new TipoServicioService($tiposRepo, $ops);
    $alta = ['codigo' => ' r-01 ', 'nombre' => ' Ruta de prueba ', 'id_tipo_servicio' => 1, 'origen' => 1, 'destino' => 2];
    $sinTipo = $alta; unset($sinTipo['id_tipo_servicio']);
    $assert(isset($rechazo(fn () => $r->prepararRegistro($sinTipo), 422)->errores['id_tipo_servicio']));
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['id_tipo_servicio' => 99])), 422);
    $d->tipos[2]['activo'] = 0;
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['id_tipo_servicio' => 2])), 422); $d->tipos[2]['activo'] = 1;
    $d->servicioDisponible = false; $foto = $d->foto();
    $rechazo(fn () => $r->registrar(41, $alta, '1'), 503); $assert($foto === $d->foto()); $d->servicioDisponible = true;
    $id = $r->registrar(41, $alta, '1');
    $assert($r->obtener($id)['id_tipo_servicio'] === 1 && $r->obtener($id)['tipo_servicio_nombre'] === 'DIRECTO');
    $assert($r->listar()['rutas'][0]['tipo_servicio_nombre'] === 'DIRECTO');
    $rechazo(fn () => $r->registrar(41, $alta, '1'), 409);
    $cinco = array_replace($alta, ['codigo' => 'R-05', 'id_tipo_servicio' => 2, 'intermedias' => [3, 4, 5]]);
    $id5 = $r->registrar(41, $cinco, '1');
    $assert(array_column($r->obtener($id5)['paradas'], 'id_parada') === [1, 3, 4, 5, 2]);
    // DIRECTO: revisión, alta y actualización rechazan POST manipulados sin efectos.
    $directoInvalido = array_replace($alta, ['codigo' => 'DIRECTO-INVALIDO', 'intermedias' => [3]]);
    $foto = $d->foto();
    foreach ([fn () => $r->prepararRegistro($directoInvalido), fn () => $r->registrar(41, $directoInvalido, '1'),
        fn () => $r->modificar(41, $id5, array_replace($cinco, ['id_tipo_servicio' => 1]), $r->prepararActualizacion($id5)['version'])] as $accion) {
        $assert(($rechazo($accion, 422)->errores['recorrido'] ?? '') === 'El servicio directo únicamente permite origen y destino.');
        $assert($foto === $d->foto(), 'No modificar datos al rechazar DIRECTO con intermedias');
    }
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['codigo' => 'R-06', 'intermedias' => [3, 4, 5, 6]])), 422);
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['intermedias' => [3, '3']])), 422);
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['codigo' => 'INACTIVA', 'destino' => 60])), 422);
    $rechazo(fn () => $r->prepararRegistro(array_replace($alta, ['codigo' => 'INEXISTENTE', 'destino' => 999])), 422);
    // Metadata y tipo cambian; estado y asociaciones intactos cuando no cambia recorrido.
    $r->cambiarEstado(41, $id, 0, $r->obtener($id)['version'], '1');
    $edicion = $r->prepararActualizacion($id); $puntos = $d->asociaciones;
    $nuevos = array_replace($alta, ['codigo' => 'R-EDIT', 'nombre' => 'Actualizada', 'id_tipo_servicio' => 2]);
    $r->modificar(41, $id, $nuevos, $edicion['version']);
    $assert($d->rutas[$id]['activa'] === 0 && $d->rutas[$id]['id_tipo_servicio'] === 2 && $d->asociaciones === $puntos);
    $assert($d->tipos[1]['controla_capacidad'] === 1 && $d->tipos[2]['controla_capacidad'] === 0);
    $rechazo(fn () => $r->modificar(41, $id, $nuevos, $edicion['version']), 409);
    $edicion = $r->prepararActualizacion($id);
    $rechazo(fn () => $r->modificar(41, $id, array_replace($nuevos, ['codigo' => 'R-05']), $edicion['version']), 409);
    $nuevoRecorrido = array_replace($nuevos, ['origen' => 2, 'destino' => 1, 'intermedias' => [4]]);
    $r->modificar(41, $id, $nuevoRecorrido, $edicion['version']);
    $assert(array_column($r->obtener($id)['paradas'], 'id_parada') === [2, 4, 1]);
    $assert(array_column($r->obtener($id)['paradas'], 'orden') === [1, 2, 3]);
    $edicion = $r->prepararActualizacion($id); $d->viajes[$id] = true; $foto = $d->foto();
    $rechazo(fn () => $r->prepararActualizacion($id), 409);
    $rechazo(fn () => $r->modificar(41, $id, $nuevos, $edicion['version']), 409); $assert($foto === $d->foto()); unset($d->viajes[$id]);
    // Rollback de actualización completa tras fallo al reinsertar puntos.
    $edicion = $r->prepararActualizacion($id); $foto = $d->foto(); $d->fallar = 'asociacion:2';
    $rechazo(fn () => $r->modificar(41, $id, $nuevos, $edicion['version']), 503); $assert($foto === $d->foto()); $d->fallar = null;
    $d->tarifas[$id] = true;
    $rechazo(fn () => $r->modificar(41, $id, $nuevos, $edicion['version']), 409); unset($d->tarifas[$id]);
    // Normalización y exclusión del propio ID, incluyendo nombres de filas inactivas.
    $idP = $p->registrar(41, ['nombre' => '  Central   Norte  ', 'referencia' => 'Primera']);
    $assert($p->obtener($idP)['nombre'] === 'Central Norte');
    $p->modificar(41, $idP, ['nombre' => 'Central Norte', 'referencia' => 'Otra'], $p->obtener($idP)['version']);
    $p->cambiarEstado(41, $idP, 0, $p->obtener($idP)['version'], '1');
    $rechazo(fn () => $p->registrar(41, ['nombre' => 'central  NORTE', 'referencia' => 'Diferente']), 409);
    $rechazo(fn () => $p->modificar(41, 1, ['nombre' => 'CENTRAL NORTE'], $p->obtener(1)['version']), 409);
    $d->paradas[100] = ['id_parada' => 100, 'nombre' => 'Central Norte', 'referencia' => 'Legado', 'activo' => 1]; $foto = $d->foto();
    $rechazo(fn () => $p->modificar(41, $idP, ['nombre' => 'central norte', 'referencia' => 'Cambio'], $p->obtener($idP)['version']), 409);
    $assert($foto === $d->foto(), 'No se fusiona ni renombra legado');
    // Presentación y controller reales con repositorios/sesión ficticios.
    $store = new SesionGestionFicticia(); $csrf = new CsrfService($store);
    $controller = new RutaController(static fn () => new UsuarioAutenticado(41, 'Admin', 'Prueba', 'admin', 'ADMINISTRADOR'),
        static fn () => $r, static fn () => $p, static fn () => new RegistroRutaService($r, $store),
        $csrf, $store, new Vista(), new AutorizacionService(), crearTipos: static fn () => $t);
    $html = $controller->nuevo(); $assert($html->estado === 200 && str_contains($html->cuerpo, 'name="id_tipo_servicio"') && str_contains($html->cuerpo, 'data-max-intermedias="3"'));
    $html = $controller->listar(); $assert($html->estado === 200 && str_contains($html->cuerpo, 'Tipo de servicio') && str_contains($html->cuerpo, 'PARADAS_CONTINUAS'));
    $html = $controller->detalle(['id' => $id]); $assert(str_contains($html->cuerpo, 'PARADAS_CONTINUAS'));
    $html = $controller->editar(['id' => $id]); $assert($html->estado === 200 && str_contains($html->cuerpo, 'Guardar cambios'));
    $html = $controller->revisar(['csrf' => $csrf->token()] + $directoInvalido);
    $assert($html->estado === 422 && str_contains($html->cuerpo, 'El servicio directo únicamente permite origen y destino.'));
    $html = $controller->modificar(['csrf' => $csrf->token(), 'version' => $r->prepararActualizacion($id5)['version']] + array_replace($cinco, ['id_tipo_servicio' => 1]), ['id' => $id5]);
    $assert($html->estado === 422 && count($r->obtener($id5)['paradas']) === 5);
    $d->viajes[$id] = true; $assert($controller->editar(['id' => $id])->estado === 409); unset($d->viajes[$id]);
    $errores = ['csrf' => $csrf->token()] + array_replace($nuevos, ['nombre' => '', 'id_tipo_servicio' => 2]);
    $html = $controller->revisar($errores); $assert($html->estado === 422 && preg_match('/value="2"[^>]* selected/', $html->cuerpo) === 1);
    $httpAlta = ['csrf' => $csrf->token()] + array_replace($alta, ['codigo' => 'HTTP', 'id_tipo_servicio' => 2, 'intermedias' => ['', '3', '']]);
    $html = $controller->revisar($httpAlta); $assert($html->estado === 200 && str_contains($html->cuerpo, 'PARADAS_CONTINUAS'));
    preg_match('/name="token" value="([a-f0-9]{64})"/', $html->cuerpo, $m);
    $respuesta = $controller->confirmar(['csrf' => $csrf->token(), 'token' => $m[1] ?? '']);
    $assert($respuesta->estado === 303 && $r->listar('HTTP')['rutas'][0]['id_tipo_servicio'] === 2);
    $assert($controller->confirmar(['csrf' => $csrf->token(), 'token' => $m[1] ?? ''])->estado === 409);
    // La confirmación vuelve a consultar el tipo; no confía solo en la revisión.
    $html = $controller->revisar(array_replace($httpAlta, ['codigo' => 'REVALIDAR-DIRECTO']));
    preg_match('/name="token" value="([a-f0-9]{64})"/', $html->cuerpo, $m);
    $d->tipos[2]['nombre'] = 'DIRECTO'; $foto = $d->foto();
    $assert($controller->confirmar(['csrf' => $csrf->token(), 'token' => $m[1] ?? ''])->estado === 422);
    $assert($foto === $d->foto()); $d->tipos[2]['nombre'] = 'PARADAS_CONTINUAS';
    // Eliminar sigue siendo independiente: usa el flujo existente, solo en datos ficticios.
    $antesParadas = $d->paradas; $borrar = $r->prepararEliminacion($id5); $r->eliminar(41, $id5, $borrar['version'], '1');
    $assert(!isset($d->rutas[$id5]) && $d->paradas === $antesParadas);
    $borrar = $p->prepararEliminacion(100); $p->eliminar(41, 100, $borrar['version'], '1'); $assert(!isset($d->paradas[100]));
    $r->cambiarEstado(41, $id, 1, $r->obtener($id)['version'], '1'); $assert($d->rutas[$id]['activa'] === 1);
    echo 'HU-04 ajustes: ' . $checks . " comprobaciones focalizadas correctas; memoria/renderizado PHP, sin escrituras ni migraciones en sfatza.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALLO HU-04 ajustes: ' . $e->getMessage() . ' (línea ' . $e->getLine() . ").\n"); exit(1);
}
