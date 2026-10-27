<?php

declare(strict_types=1);

use SFATSA\Controllers\VehiculoController;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\AutorizacionService;
use SFATSA\Services\CsrfService;
use SFATSA\Services\OperacionAdministrativaService;
use SFATSA\Services\RegistroVehiculoService;
use SFATSA\Services\VehiculoService;

ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/VehiculosFicticios.php';
set_error_handler(static function (): never { throw new RuntimeException('Advertencia de renderizado.'); });

$checks = 0; $caso = 'inicio';
$assert = static function (bool $condicion) use (&$checks): void {
    if (!$condicion) { throw new RuntimeException('Comprobación fallida en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); }
    $checks++;
};
$documento = static function (string $html): DOMXPath {
    $doc = new DOMDocument();
    $anterior = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($anterior);
    return new DOMXPath($doc);
};
$fixture = static function (): array {
    $usuarios = new GestionUsuariosFicticia(); $repo = new VehiculosFicticios();
    $servicio = new VehiculoService($repo, new OperacionAdministrativaService(new AdministracionVehiculosFicticia($usuarios, $repo)));
    $store = new SesionGestionFicticia(); $csrf = new CsrfService($store);
    $actor = new UsuarioAutenticado(41, 'Persona', 'Sintética', 'admin_sintetico', 'ADMINISTRADOR');
    $controller = new VehiculoController(static fn () => $actor, static fn () => $servicio,
        static fn () => new RegistroVehiculoService($servicio, $store), $csrf, $store, new Vista(), new AutorizacionService());
    return [$controller, $repo, $csrf, $store, $actor];
};
$datos = ['numero_economico'=>' eco-21 ', 'placa'=>' a-00021-a ', 'descripcion'=>' Autobús sintético ', 'capacidad'=>'25'];

try {
    $caso = 'listado, navegación y ausencia de resultados';
    [$controller, $repo, $csrf, $store, $actor] = $fixture(); $antes = $repo->filas;
    $r = $controller->listar(); $x = $documento($r->cuerpo);
    $assert($r->estado === 200 && $x->query('//table/tbody/tr')->length === 2);
    $assert($x->query('//table/thead/tr/th')->length === 6);
    $assert($x->query('//nav/a[@aria-current="page" and @href="/?ruta=vehiculos"]')->length === 1);
    $assert($x->query('//nav/a[contains(@class,"active")]')->length === 1);
    $assert($x->query('//form[@role="search" and @method="get"]//input[@name="q"]')->length === 1);
    $assert($x->query('//a[@href="/?ruta=vehiculos.editar&id=11"]')->length === 1);
    $assert($x->query('//a[@href="/?ruta=vehiculos.estado&id=11&estado=INACTIVO"]')->length === 1);
    $assert($x->query('//a[@href="/?ruta=vehiculos.estado&id=12&estado=ACTIVO"]')->length === 1);
    $assert($x->query('//a[@href="/?ruta=vehiculos.eliminar&id=11" and text()="Eliminar"]')->length === 1);
    $assert(!str_contains($r->cuerpo, 'Página 1 de 1'));
    $assert(str_contains($controller->listar(['q'=>'00011']) ->cuerpo, 'A-00011-A'));
    $assert(str_contains($controller->listar(['q'=>'ausente'])->cuerpo, 'No se encontraron vehículos'));
    $repo->filas = []; $assert(str_contains($controller->listar()->cuerpo, 'No hay vehículos registrados')); $repo->filas = $antes;
    $store->guardar('vehiculos_exito', 'Éxito sintético');
    $assert(str_contains($controller->listar()->cuerpo, 'Éxito sintético'));
    $assert(!str_contains($controller->listar()->cuerpo, 'Éxito sintético'));

    $caso = 'formulario de alta/edición y errores por campo';
    $r = $controller->nuevo(); $x = $documento($r->cuerpo);
    $assert($r->estado === 200 && $x->query('//form[@action="/?ruta=vehiculos.revisar" and @method="post"]')->length === 1);
    $assert($x->query('//select[@name="estado"]/option[@selected and @value="ACTIVO"]')->length === 1);
    $assert($x->query('//select[@name="estado"]/option')->length === 2 && !str_contains($r->cuerpo,'MANTENIMIENTO'));
    $assert(str_contains($r->cuerpo,'Revisar datos') && $x->query('//input[@name="capacidad" and @min="1" and @max="30" and @step="1"]')->length === 1);
    $r = $controller->editar(['id'=>'12']); $x = $documento($r->cuerpo);
    $assert($r->estado === 200 && $x->query('//*[@name="estado"]')->length === 0);
    $assert($x->query('//form[@action="/?ruta=vehiculos.editar&id=12" and @method="post"]')->length === 1);
    $assert($x->query('//input[@name="numero_economico" and @value="ECO-12"]')->length === 1);
    $invalido = $datos; $invalido['descripcion'] = ''; $r = $controller->revisar($invalido + ['csrf'=>$csrf->token()]); $x = $documento($r->cuerpo);
    $assert($r->estado === 422 && $x->query('//input[@id="descripcion" and @aria-invalid="true"]')->length === 1);
    $assert($x->query('//*[@id="descripcion-error"]')->length === 1);
    $assert($x->query('//input[@name="placa"]')->item(0)->getAttribute('value') === $datos['placa']);
    $assert($repo->filas === $antes);

    $caso = 'resumen inmutable, CSRF separado y cancelación';
    $r = $controller->revisar($datos + ['csrf'=>$csrf->token()]); $x = $documento($r->cuerpo);
    $assert($r->estado === 200 && str_contains($r->cuerpo, 'A-00021-A') && str_contains($r->cuerpo, 'ECO-21'));
    $assert($x->query('//form')->length === 3); // Logout, cancelar y confirmar.
    $tokensResumen = $x->query('//input[@name="token"]');
    $assert($tokensResumen->length === 2);
    $tokenResumen = $tokensResumen->item(0)->getAttribute('value');
    $assert($tokenResumen !== $csrf->token() && $tokensResumen->item(1)->getAttribute('value') === $tokenResumen);
    foreach ($x->query('//form') as $form) {
        $assert($form->getAttribute('method') === 'post');
        $assert($x->query('.//input[@name="csrf"]', $form)->item(0)->getAttribute('value') === $csrf->token());
    }
    foreach (['numero_economico','placa','descripcion','capacidad','estado'] as $campo) { $assert($x->query('//*[@name="'.$campo.'"]')->length === 0); }
    $assert($repo->filas === $antes);
    $assert($controller->cancelar(['csrf'=>$csrf->token(),'token'=>$tokenResumen])->estado === 303 && $repo->filas === $antes);
    $assert($controller->confirmar(['csrf'=>$csrf->token(),'token'=>$tokenResumen])->estado === 409);
    $r = $controller->revisar($datos + ['csrf'=>$csrf->token()]); $x = $documento($r->cuerpo);
    $tokenResumen = $x->query('//input[@name="token"]')->item(0)->getAttribute('value');
    $assert($controller->confirmar(['csrf'=>$csrf->token(),'token'=>$tokenResumen])->estado === 303);
    $assert(count($repo->filas) === 3);
    $assert($controller->confirmar(['csrf'=>$csrf->token(),'token'=>$tokenResumen])->estado === 409 && count($repo->filas) === 3);

    $caso = 'confirmación independiente de estado';
    foreach (['ACTIVO','INACTIVO'] as $estado) {
        $r = $controller->confirmarEstado(['id'=>'11','estado'=>$estado]); $x = $documento($r->cuerpo);
        $assert($r->estado === 200 && $repo->filas[11]['estado'] === 'ACTIVO');
        $assert($x->query('//input[@name="confirmacion"]')->length === ($estado === 'ACTIVO' ? 0 : 1));
        if ($estado === 'INACTIVO') {
            $assert($x->query('//form[@action="/?ruta=vehiculos.estado&id=11&estado=INACTIVO" and @method="post"]')->length === 1);
            $assert($x->query('//input[@name="confirmacion" and @value="1"]')->length === 1);
            $assert($x->query('//a[@href="/?ruta=vehiculos" and text()="Cancelar"]')->length === 1);
        }
    }
    $assert($controller->cambiarEstado(['csrf'=>$csrf->token(),'confirmacion'=>'1'],['id'=>'11','estado'=>'INACTIVO'])->estado === 303);
    $assert($repo->filas[11]['estado'] === 'INACTIVO');

    $caso = 'escape de salida de vistas y confirmación de eliminación POST';
    $ataque = '\"><script>alert(1)</script><img src=x onerror=alert(1)>';
    $repo->filas[11]['numero_economico'] = $ataque; $repo->filas[11]['placa'] = $ataque; $repo->filas[11]['descripcion'] = $ataque;
    $store->guardar('vehiculos_exito', $ataque);
    $htmls = [$controller->listar()->cuerpo, $controller->editar(['id'=>'11'])->cuerpo,
        $controller->confirmarEstado(['id'=>'11','estado'=>'ACTIVO'])->cuerpo,
        $controller->confirmarEliminacion(['id'=>'11'])->cuerpo];
    $htmls[] = (new Vista())->renderizar('vehiculos/confirmacion', ['usuario'=>$actor,'tokenCsrf'=>$csrf->token(),'token'=>str_repeat('a',64),
        'datos'=>['numero_economico'=>$ataque,'placa'=>$ataque,'descripcion'=>$ataque,'capacidad'=>25,'estado'=>'ACTIVO']]);
    foreach ($htmls as $html) {
        $x = $documento($html);
        $assert(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'));
        $assert($x->query('//img|//*[@onerror]')->length === 0);
        $assert($x->query('//nav/a[contains(@class,"active")]')->length === 1);
        foreach ($x->query('//form[@method="post"]') as $form) { $assert($x->query('.//input[@name="csrf"]', $form)->length === 1); }
    }
    $x = $documento($htmls[3]);
    $assert($x->query('//form[@action="/?ruta=vehiculos.eliminar&id=11" and @method="post"]')->length === 1);
    $assert($x->query('//input[@type="checkbox" and @name="confirmacion" and @required]')->length === 1);
    $assert($x->query('//input[@name="version"]')->length === 1 && str_contains($htmls[3], 'La operación se bloqueará'));
    $caso = 'acceso desde Inicio según rol';
    foreach ([true,false] as $permiso) {
        $html = (new Vista())->renderizar('inicio',['usuario'=>$actor,'token'=>$csrf->token(),'puedeGestionar'=>$permiso]);
        $assert(str_contains($html,'Abrir Gestión de vehículos') === $permiso);
    }
    echo "Vistas HU-02: $checks comprobaciones correctas; plantillas reales, repositorios/sesión simulados, sin MariaDB.\n";
} catch (Throwable $error) {
    $detalle = preg_match('/\AComprobación fallida en línea [0-9]+\z/', $error->getMessage()) ? $error->getMessage() : '';
    fwrite(STDERR,"Falló vistas HU-02 en: $caso. $detalle " . get_class($error) . ':' . $error->getLine() . ". Sin detalles privados.\n");
    exit(1);
}
