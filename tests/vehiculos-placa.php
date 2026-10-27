<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/VehiculosFicticios.php';
set_error_handler(static function (): never { throw new RuntimeException('Advertencia PHP en prueba focalizada.'); });

use SFATSA\Controllers\VehiculoController;
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{AutorizacionService, CsrfService, ErrorGestionVehiculos, OperacionAdministrativaService, RegistroVehiculoService, VehiculoService};

$checks = 0;
$assert = static function (bool $ok) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('Fallo en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); } $checks++;
};
$html = static function (string $texto): DOMXPath {
    $doc = new DOMDocument(); $anterior = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $texto, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($anterior); return new DOMXPath($doc);
};
try {
    $usuarios = new GestionUsuariosFicticia(); $repo = new VehiculosFicticios();
    $s = new VehiculoService($repo, new OperacionAdministrativaService(new AdministracionVehiculosFicticia($usuarios, $repo)));
    $store = new SesionGestionFicticia(); $csrf = new CsrfService($store);
    $actor = new UsuarioAutenticado(41, 'Persona', 'Prueba', 'admin_sintetico', 'ADMINISTRADOR');
    $c = new VehiculoController(static fn () => $actor, static fn () => $s, static fn () => new RegistroVehiculoService($s, $store),
        $csrf, $store, new Vista(), new AutorizacionService());
    $datos = ['numero_economico' => 'ECO-PLACA', 'descripcion' => 'Vehículo ficticio', 'capacidad' => '25'];
    foreach (['ABC-123-A', 'AB-12-345', 'A-12345-B', 'ABC-12-34', '12-ABC-34'] as $placa) {
        $assert($s->prepararRegistro($datos + ['placa' => strtolower($placa)])['placa'] === $placa);
    }
    $antes = $repo->filas;
    foreach (['ABC1234', 'ABC-1234', 'ABC--1234', 'ABC-123-45', 'ABC-12-3', 'ABC 123 A', 'ABC-12-@A', ' ABC-123-A', 'ABC-123-A ', 'ABÑ-123-A', "ABC-123-A\n", '', [], null] as $placa) {
        try { $s->prepararRegistro($datos + ['placa' => $placa]); throw new RuntimeException('Faltó rechazo'); }
        catch (ErrorGestionVehiculos $e) { $assert($e->estado === 422 && isset($e->errores['placa'])); }
    }
    $assert($repo->filas === $antes);
    $r = $c->nuevo(); $x = $html($r->cuerpo);
    $assert($r->estado === 200 && $x->query('//input[@data-placa-grupo]')->length === 3);
    $assert($x->query('//input[@data-placa-grupo and @name]')->length === 0 && $x->query('//input[@name="placa"]')->length === 1);
    $assert($x->query('//span[@class="placa-separador"]')->length === 2);
    $assert($x->query('//input[@data-placa-grupo and @aria-describedby and @disabled]')->length === 3);
    $assert($x->query('//input[@name="placa" and @minlength="9" and @maxlength="9"]')->length === 1);
    $assert(str_contains($r->cuerpo, '/assets/js/vehiculos-placa.js') && str_contains($r->cuerpo, '/assets/css/vehiculos-placa.css'));
    $post = ['csrf' => $csrf->token()] + $datos + ['placa' => 'abc-123-a'];
    $r = $c->revisar($post); $x = $html($r->cuerpo);
    $assert($r->estado === 200 && str_contains($r->cuerpo, 'ABC-123-A') && $repo->filas === $antes);
    $token = $x->query('//input[@name="token"]')->item(0)->getAttribute('value');
    $assert($c->confirmar(['csrf' => $csrf->token(), 'token' => $token])->estado === 303);
    $id = max(array_keys($repo->filas)); $assert($repo->filas[$id]['placa'] === 'ABC-123-A');
    $assert($c->confirmar(['csrf' => $csrf->token(), 'token' => $token])->estado === 409);
    $assert($c->revisar(array_replace($post, ['numero_economico' => 'OTRO']))->estado === 409);
    $editar = ['csrf' => $csrf->token()] + $datos + ['placa' => 'ab-12-345'];
    $assert($c->modificar($editar, ['id' => $id])->estado === 303 && $repo->filas[$id]['placa'] === 'AB-12-345');
    $x = $html($c->editar(['id' => $id])->cuerpo);
    foreach ([1 => 'AB', 2 => '12', 3 => '345'] as $grupo => $valor) { $assert($x->query('//input[@id="placa-grupo-' . $grupo . '" and @value="' . $valor . '"]')->length === 1); }
    $antes = $repo->filas; $r = $c->modificar(array_replace($editar, ['placa' => 'ABC-12-3']), ['id' => $id]);
    $assert($r->estado === 422 && $repo->filas === $antes);
    $x = $html($r->cuerpo); $assert($x->query('//input[@name="placa" and @value="ABC-12-3"]')->length === 1);
    $assert($c->modificar(array_replace($editar, ['csrf' => 'incorrecto']), ['id' => $id])->estado === 403 && $repo->filas === $antes);
    // El legado se consulta/administra sin pasar por la validación nueva de captura.
    $repo->filas[11]['placa'] = 'ANTIGUA1';
    $assert($c->listar()->estado === 200 && str_contains($c->editar(['id' => 11])->cuerpo, 'ANTIGUA1'));
    $assert($c->modificar(['csrf' => $csrf->token(), 'numero_economico' => 'ECO-11', 'placa' => 'ANTIGUA1', 'descripcion' => 'Legado', 'capacidad' => '25'], ['id' => 11])->estado === 422);
    $s->cambiarEstado(41, 11, 'INACTIVO', '1'); $assert($repo->filas[11]['estado'] === 'INACTIVO');
    $s->cambiarEstado(41, 11, 'ACTIVO', '1'); $assert($repo->filas[11]['estado'] === 'ACTIVO');
    $version = $s->prepararEliminacion(11)['version']; $s->eliminar(41, 11, $version, '1'); $assert(!isset($repo->filas[11]));
    $assert(str_contains($c->listar()->cuerpo, 'AB-12-345'));
    echo 'Placas HU-02: ' . $checks . " comprobaciones focalizadas correctas; servicios/controlador/renderizado con datos ficticios, sin MariaDB.\n";
} catch (Throwable $e) { fwrite(STDERR, 'FALLO placas HU-02: ' . $e->getMessage() . "\n"); exit(1); }
