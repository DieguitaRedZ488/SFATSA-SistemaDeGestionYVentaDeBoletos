<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/VehiculosFicticios.php';
require_once __DIR__ . '/Support/ConductoresFicticios.php';
set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): never {
    throw new RuntimeException('Advertencia en ' . basename($archivo) . ':' . $linea . ': ' . $mensaje);
});

use SFATSA\Controllers\{ConductorController, VehiculoController};
use SFATSA\Http\Vista;
use SFATSA\Models\UsuarioAutenticado;
use SFATSA\Services\{AutorizacionService, CsrfService, ConductorService, ErrorGestionConductores, ErrorGestionVehiculos, OperacionAdministrativaService, RegistroVehiculoService, VehiculoService};

$checks = 0;
$assert = static function (bool $ok) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('Fallo en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); } $checks++;
};
$documento = static function (string $html): DOMXPath {
    $doc = new DOMDocument(); $prev = libxml_use_internal_errors(true); $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prev); return new DOMXPath($doc);
};
try {
    $usuarios = new GestionUsuariosFicticia(); $vehiculos = new VehiculosFicticios(); $conductores = new ConductoresFicticios();
    $v = new VehiculoService($vehiculos, new OperacionAdministrativaService(new AdministracionVehiculosFicticia($usuarios, $vehiculos)));
    $c = new ConductorService($conductores, new OperacionAdministrativaService(new AdministracionConductoresFicticia($usuarios, $conductores)));
    $store = new SesionGestionFicticia(); $csrf = new CsrfService($store); $actor = new UsuarioAutenticado(41, 'Persona', 'Prueba', 'admin', 'ADMINISTRADOR');
    $vc = new VehiculoController(static fn () => $actor, static fn () => $v, static fn () => new RegistroVehiculoService($v, $store), $csrf, $store, new Vista(), new AutorizacionService());
    $cc = new ConductorController(static fn () => $actor, static fn () => $c, $csrf, $store, new Vista(), new AutorizacionService());
    $unidad = ['numero_economico' => 'ECO-VALIDACION', 'placa' => 'ABC-123-A', 'descripcion' => 'Vehículo ficticio', 'capacidad' => '25'];
    foreach ([1, 15, 25, 30, '30', '00030'] as $capacidad) { $assert($v->prepararRegistro(array_replace($unidad, ['capacidad' => $capacidad]))['capacidad'] === (int) $capacidad); }
    $antes = $vehiculos->filas;
    foreach ([null, '', ' ', 0, '0', -1, 31, 60, 65535, '15.5', 15.5, 'abc', true, [], '+15', '1e1', 1.0] as $capacidad) {
        $datos = array_replace($unidad, ['capacidad' => $capacidad]);
        foreach ([fn () => $v->registrar(41, $datos, '1'), fn () => $v->modificar(41, 11, $datos)] as $accion) {
            try { $accion(); throw new RuntimeException('Faltó rechazo de capacidad.'); }
            catch (ErrorGestionVehiculos $e) { $assert($e->estado === 422 && ($e->errores['capacidad'] ?? '') === 'La capacidad debe estar entre 1 y 30 pasajeros'); }
        }
    }
    $assert($vehiculos->filas === $antes);
    foreach ([false, true] as $edicion) {
        $r = $edicion ? $vc->editar(['id' => 11]) : $vc->nuevo(); $x = $documento($r->cuerpo);
        $assert($r->estado === 200 && $x->query('//input[@name="capacidad" and @min="1" and @max="30" and @step="1"]')->length === 1);
    }
    $r = $vc->revisar(['csrf' => $csrf->token()] + array_replace($unidad, ['capacidad' => '30'])); $x = $documento($r->cuerpo);
    $assert($r->estado === 200 && str_contains($r->cuerpo, '>30<'));
    $token = $x->query('//input[@name="token"]')->item(0)->getAttribute('value');
    $assert($vc->confirmar(['csrf' => $csrf->token(), 'token' => $token])->estado === 303);
    $id = max(array_keys($vehiculos->filas)); $assert($vehiculos->filas[$id]['capacidad'] === 30 && $vehiculos->filas[$id]['placa'] === 'ABC-123-A');
    $assert($vc->modificar(['csrf' => $csrf->token()] + array_replace($unidad, ['capacidad' => '1']), ['id' => $id])->estado === 303 && $vehiculos->filas[$id]['capacidad'] === 1);
    $assert($vc->modificar(['csrf' => $csrf->token()] + array_replace($unidad, ['capacidad' => '31']), ['id' => $id])->estado === 422);
    $r = $vc->revisar(['csrf' => $csrf->token()] + array_replace($unidad, ['descripcion' => '', 'capacidad' => '15']));
    $assert($r->estado === 422 && $documento($r->cuerpo)->query('//input[@name="capacidad" and @value="15"]')->length === 1);
    // Capacidad heredada fuera de rango: no bloquea consulta/estado/eliminación independiente.
    $assert($v->obtener(11)['capacidad'] === 40 && $vc->listar()->estado === 200);
    $v->cambiarEstado(41, 11, 'INACTIVO', '1'); $assert($vehiculos->filas[11]['estado'] === 'INACTIVO');
    $borrar = $v->prepararEliminacion(11); $v->eliminar(41, 11, $borrar['version'], '1'); $assert(!isset($vehiculos->filas[11]));

    $persona = ['nombres' => 'Persona', 'apellidos' => 'Prueba', 'numero_licencia' => 'TEST0000000999'];
    foreach (['9513440979', '5512345678', '0012345678', '', null] as $i => $telefono) {
        $datos = $persona + ['telefono' => $telefono]; $datos['numero_licencia'] = 'TEST' . str_pad((string) ($i + 100), 10, '0', STR_PAD_LEFT);
        $c->registrar(41, $datos); $idC = max(array_keys($conductores->filas));
        $assert($conductores->filas[$idC]['telefono'] === ($telefono === '' ? null : $telefono));
        $c->modificar(41, $idC, $datos); $assert($conductores->filas[$idC]['telefono'] === ($telefono === '' ? null : $telefono));
    }
    $antes = $conductores->filas;
    foreach (['951344097', '95134409799', '951-344-0979', '+529513440979', '951ABC0979', '(951)3440979', '9513440979 ', ' ', '15.5', 9513440979, [], false, '٠١٢٣٤٥٦٧٨٩'] as $telefono) {
        $datos = $persona + ['telefono' => $telefono];
        foreach ([fn () => $c->registrar(41, $datos), fn () => $c->modificar(41, 11, $datos)] as $accion) {
            try { $accion(); throw new RuntimeException('Faltó rechazo de teléfono.'); }
            catch (ErrorGestionConductores $e) { $assert($e->estado === 422 && ($e->errores['telefono'] ?? '') === 'El teléfono debe contener exactamente 10 dígitos'); }
        }
    }
    $assert($conductores->filas === $antes);
    foreach ([false, true] as $edicion) {
        $r = $edicion ? $cc->editar(['id' => 11]) : $cc->nuevo(); $x = $documento($r->cuerpo);
        $assert($r->estado === 200 && $x->query('//input[@name="telefono" and @type="tel" and @maxlength="10" and @inputmode="numeric" and @pattern="[0-9]{10}" and not(@required)]')->length === 1);
    }
    $r = $cc->registrar(['csrf' => $csrf->token()] + $persona + ['telefono' => '+529513440979']);
    $assert($r->estado === 422 && $documento($r->cuerpo)->query('//input[@name="telefono" and @value="+529513440979"]')->length === 1);
    $r = $cc->registrar(['csrf' => $csrf->token()] + array_replace($persona, ['nombres' => '', 'telefono' => '0012345678']));
    $assert($r->estado === 422 && $documento($r->cuerpo)->query('//input[@name="telefono" and @value="0012345678"]')->length === 1);
    $conductores->filas[11]['telefono'] = '+52 555 000 0011';
    $assert($cc->listar()->estado === 200 && $cc->editar(['id' => 11])->estado === 200);
    $c->cambiarEstado(41, 11, 0, '1'); $assert($conductores->filas[11]['activo'] === 0);
    // No inventar validadores ni campos de correo donde no existen.
    $usuariosHtml = (new Vista())->renderizar('usuarios/formulario', ['usuario' => $actor, 'token' => $csrf->token(), 'edicion' => false,
        'valores' => [], 'roles' => $usuarios->roles(), 'errores' => [], 'error' => '', 'idUsuario' => null]);
    $assert($documento($usuariosHtml)->query('//input[@type="email" or @name="correo" or @name="telefono"]')->length === 0);
    $assert($documento($cc->nuevo()->cuerpo)->query('//input[@type="email" or @name="correo"]')->length === 0);
    echo 'Validaciones de captura: ' . $checks . " comprobaciones focalizadas correctas; memoria/controladores/renderizado, sin MariaDB.\n";
} catch (Throwable $e) { fwrite(STDERR, 'FALLO validaciones: ' . $e->getMessage() . "\n"); exit(1); }
