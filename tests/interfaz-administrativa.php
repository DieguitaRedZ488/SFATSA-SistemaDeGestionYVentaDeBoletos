<?php

declare(strict_types=1);

// Comprobaciones focalizadas: controladores y HTML real con almacenes en memoria.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
foreach (['GestionUsuariosFicticia', 'ConductoresFicticios', 'VehiculosFicticios', 'HU04Ficticios'] as $doble) {
    require_once __DIR__ . '/Support/' . $doble . '.php';
}
set_error_handler(static function (int $nivel, string $mensaje): never { throw new RuntimeException($mensaje); });

use SFATSA\Controllers\{UsuarioController, ConductorController, VehiculoController, RutaController, ParadaController, TipoServicioController};
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{AutorizacionService, CsrfService, UsuarioService, ConductorService, VehiculoService, RutaService, ParadaService, TipoServicioService, RegistroVehiculoService, RegistroRutaService, OperacionAdministrativaService};

$checks = 0;
$assert = static function (bool $ok) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('Fallo en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); }
    $checks++;
};
$dom = static function (string $html): DOMXPath {
    $documento = new DOMDocument(); $previo = libxml_use_internal_errors(true);
    $documento->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previo); return new DOMXPath($documento);
};
$exportar = static function (string $nombre, string $html) use ($assert): void {
    $directorio = getenv('SFATSA_UI_PREVIEW_DIR');
    if (!is_string($directorio) || $directorio === '') { return; }
    // Copia autónoma de datos ficticios para capturas: ningún acceso a sfatza.
    $html = preg_replace_callback('~<link rel="stylesheet" href="(/assets/[^"<>]+)">~', static function (array $m): string {
        return '<style>' . file_get_contents(dirname(__DIR__) . '/public' . $m[1]) . '</style>';
    }, $html);
    $scripts = '';
    $html = preg_replace_callback('~<script src="(/assets/[^"<>]+)" defer></script>~', static function (array $m) use (&$scripts): string {
        $scripts .= '<script>' . file_get_contents(dirname(__DIR__) . '/public' . $m[1]) . '</script>';
        return '';
    }, $html);
    $html = str_replace('</body>', $scripts . '</body>', $html);
    if (file_put_contents($directorio . '/' . $nombre . '.html', $html) === false) { throw new RuntimeException('No se pudo generar la vista previa ficticia.'); }
};

try {
    $usuarios = new GestionUsuariosFicticia(); $conductores = new ConductoresFicticios(); $vehiculos = new VehiculosFicticios(); $d = new DatosHU04Ficticios();
    $sesion = new SesionGestionFicticia(); $csrf = new CsrfService($sesion); $vista = new Vista(); $auth = new AutorizacionService();
    $actor = new UsuarioAutenticado(41, 'Persona', 'Prueba', 'admin', 'ADMINISTRADOR'); $actual = static fn () => $actor;
    $us = new UsuarioService($usuarios);
    $cs = new ConductorService($conductores, new OperacionAdministrativaService(new AdministracionConductoresFicticia($usuarios, $conductores)));
    $vs = new VehiculoService($vehiculos, new OperacionAdministrativaService(new AdministracionVehiculosFicticia($usuarios, $vehiculos)));
    $ops = new OperacionAdministrativaService(new AdministracionHU04Ficticia($usuarios, $d)); $pr = new ParadasHU04Ficticias($d); $tr = new TiposHU04Ficticios($d);
    $ps = new ParadaService($pr, $ops); $ts = new TipoServicioService($tr, $ops);
    $rs = new RutaService(new RutasHU04Ficticias($d), $pr, new AsociacionesHU04Ficticias($d), $ops, $tr);
    $uc = new UsuarioController($actual, static fn () => $us, $csrf, $sesion, $vista, $auth);
    $cc = new ConductorController($actual, static fn () => $cs, $csrf, $sesion, $vista, $auth);
    $vc = new VehiculoController($actual, static fn () => $vs, static fn () => new RegistroVehiculoService($vs, $sesion), $csrf, $sesion, $vista, $auth);
    $pc = new ParadaController($actual, static fn () => $ps, $csrf, $sesion, $vista, $auth);
    $tc = new TipoServicioController($actual, static fn () => $ts, $csrf, $sesion, $vista, $auth);
    $rc = new RutaController($actual, static fn () => $rs, static fn () => $ps, static fn () => new RegistroRutaService($rs, $sesion), $csrf, $sesion, $vista, $auth, crearTipos: static fn () => $ts);
    $ruta = $rs->registrar(41, ['codigo' => 'UI-RUTA', 'nombre' => 'Ruta ficticia', 'id_tipo_servicio' => 1, 'origen' => 1, 'destino' => 2], '1');
    $captura = ['csrf' => $csrf->token(), 'nombres' => 'Persona', 'apellidos' => 'Prueba', 'usuario' => 'ui-ficticio', 'id_rol' => 13, 'contrasena' => str_repeat('x', 16), 'confirmacion' => str_repeat('x', 16)];
    $assert($uc->registrar($captura)->estado === 303);
    $id = max(array_keys($usuarios->filas));
    $assert($uc->modificar(array_replace($captura, ['nombres' => 'Actualizada']), ['id' => $id])->estado === 303 && $usuarios->filas[$id]['nombres'] === 'Actualizada');
    $assert($uc->registrar(['csrf' => 'invalido'])->estado === 403);
    $xss = '<script>alert("ficticio")</script>';
    $usuarios->filas[42]['nombres'] = $xss; $conductores->filas[11]['nombres'] = $xss;
    $vehiculos->filas[11]['descripcion'] = $xss; $d->rutas[$ruta]['nombre'] = $xss; $d->paradas[1]['nombre'] = $xss;
    foreach (['usuarios' => $uc, 'conductores' => $cc, 'vehiculos' => $vc, 'rutas' => $rc, 'paradas' => $pc, 'tipos-servicio' => $tc] as $modulo => $controller) {
        $respuesta = $controller->listar(); $x = $dom($respuesta->cuerpo);
        $assert($respuesta->estado === 200);
        $assert($x->query('//nav[@aria-label="Navegación principal"]//svg[@data-icon="dashboard"]')->length === 1);
        $exportar($modulo . '-listado', $respuesta->cuerpo);
        $assert($x->query('//a[@aria-current and @href="/?ruta=' . $modulo . '"]')->length === ($modulo === 'tipos-servicio' ? 0 : 1));
        $assert($x->query('//span[contains(@class,"status--activo")]/span[@aria-hidden="true"]')->length > 0);
        $assert($x->query('//script[not(@src)]')->length === 0);
        $texto = $x->query('//body')->item(0)->textContent;
        $assert(preg_match('/Sprint\s*(?:0?[12]|II)|HU-0[124]/iu', $texto) === 0);
        $assert(!str_contains($texto, 'Inactivo'));
        if ($modulo !== 'tipos-servicio') { $assert(str_contains($texto, $xss)); }
        $assert(str_contains($controller->listar(['q' => 'SIN-RESULTADO-FICTICIO'])->cuerpo, 'empty-state'));
        foreach ([$controller->nuevo(), $controller->editar(['id' => match ($modulo) { 'usuarios' => 42, 'conductores', 'vehiculos' => 11, 'rutas' => $ruta, default => 1 }])] as $indice => $formulario) {
            $x = $dom($formulario->cuerpo);
            $assert($formulario->estado === 200 && $x->query('//form[@method="post"]//input[@name="csrf"]')->length >= 2);
            $assert($x->query('//link[@href="/assets/css/usuarios.css"]')->length === 1);
            $exportar($modulo . ($indice === 0 ? '-registro' : '-edicion'), $formulario->cuerpo);
        }
    }
    foreach ([['login', ['token' => $csrf->token(), 'error' => '', 'usuario' => '']], ['inicio', ['usuario' => $actor, 'token' => $csrf->token(), 'puedeGestionar' => true]]] as [$plantilla, $datos]) {
        $html = $vista->renderizar($plantilla, $datos); $x = $dom($html); $exportar($plantilla, $html);
        $assert(preg_match('/Sprint\s*(?:0?[12]|II)|HU-0[124]/iu', $x->query('//body')->item(0)->textContent) === 0);
        $assert($x->query('//link[@href="/assets/css/autenticacion.css"]')->length === 1);
    }
    $x = $dom($vista->renderizar('inicio', ['usuario' => $actor, 'token' => $csrf->token(), 'puedeGestionar' => true]));
    $assert($x->query('//a[@aria-current="page" and @href="/?ruta=inicio"]')->length === 1);
    $assert($x->query('//form[@action="/?ruta=logout"]//svg[@data-icon="log-out"]')->length === 1);
    $x = $dom($vista->renderizar('inicio', ['usuario' => new UsuarioAutenticado(42, 'Persona', 'Prueba', 'cajero', 'CAJERO'), 'token' => $csrf->token(), 'puedeGestionar' => false]));
    $assert($x->query('//nav//a[@href="/?ruta=usuarios" or @href="/?ruta=conductores" or @href="/?ruta=vehiculos" or @href="/?ruta=rutas" or @href="/?ruta=paradas"]')->length === 0);
    // Valores reales enviados intactos, aunque cambien las etiquetas de presentación.
    $x = $dom($vc->nuevo()->cuerpo);
    $assert($x->query('//select[@name="estado"]/option[@value="ACTIVO"]')->length === 1);
    $assert($x->query('//select[@name="estado"]/option[@value="INACTIVO"]')->length === 1);
    $x = $dom($rc->detalle(['id' => $ruta])->cuerpo);
    $assert($x->query('//section[contains(@class,"details-card")]//dt')->length === 3);
    $assert($x->query('//section[contains(@class,"details-card")]//span[contains(@class,"status--activo")]')->length === 1);
    foreach ([[$rc, $ruta, 0, 'Confirmar suspensión'], [$pc, 1, 0, 'Confirmar deshabilitación'],
        [$cc, 11, 0, 'Confirmar deshabilitación'], [$vc, 11, 'INACTIVO', 'Confirmar deshabilitación']] as [$controller, $idEstado, $estado, $etiqueta]) {
        $respuesta = $controller->confirmarEstado(['id' => $idEstado, 'estado' => $estado]);
        $assert($respuesta->estado === 200 && str_contains($respuesta->cuerpo, $etiqueta));
    }
    echo "Interfaz administrativa: $checks comprobaciones correctas; HTML/controladores/datos ficticios, sin navegador ni MariaDB.\n";
} catch (Throwable $e) { fwrite(STDERR, 'FALLO interfaz: ' . $e->getMessage() . "\n"); exit(1); }
