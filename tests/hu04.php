<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Pruebas de dominio con dobles deterministas, sin MariaDB ni configuración local. */
require_once dirname(__DIR__) . '/app/bootstrap-web.php';
require_once __DIR__ . '/Support/GestionUsuariosFicticia.php';
require_once __DIR__ . '/Support/HU04Ficticios.php';

use SFATSA\Services\{ErrorGestionHU04, OperacionAdministrativaService, ParadaService, RutaService, TipoServicioService};

$comprobaciones = 0;
function verificarHU04(bool $condicion, string $mensaje): void
{
    global $comprobaciones; $comprobaciones++;
    if (!$condicion) { throw new RuntimeException($mensaje); }
}
function errorHU04(Closure $accion, int $estado): ErrorGestionHU04
{
    try { $accion(); } catch (ErrorGestionHU04 $e) {
        verificarHU04($e->estado === $estado, 'Estado esperado ' . $estado . ', recibido ' . $e->estado);
        verificarHU04(!str_contains($e->getMessage(), 'SENSIBLE') && $e->getPrevious() === null, 'Error controlado sin datos internos');
        return $e;
    }
    throw new RuntimeException('Se esperaba error ' . $estado);
}
function escenarioHU04(): array
{
    $datos = new DatosHU04Ficticios(); $usuarios = new GestionUsuariosFicticia();
    $admin = new AdministracionHU04Ficticia($usuarios, $datos); $ops = new OperacionAdministrativaService($admin);
    $p = new ParadasHU04Ficticias($datos);
    return [$datos, new RutaService(new RutasHU04Ficticias($datos), $p, new AsociacionesHU04Ficticias($datos), $ops),
        new ParadaService($p, $ops), new TipoServicioService(new TiposHU04Ficticios($datos), $ops), $admin, $usuarios];
}
$alta = ['codigo' => '  ru-01  ', 'nombre' => '  Ruta de Prueba  ', 'origen' => '1', 'destino' => 2];

try {
    [$d, $r, $p, $t, $a, $u] = escenarioHU04();
    $previo = $d->foto(); $normal = $r->prepararRegistro($alta);
    verificarHU04($normal['codigo'] === 'RU-01' && $normal['nombre'] === 'Ruta de Prueba', 'Normalización');
    verificarHU04($previo === $d->foto() && $u->eventos === [], 'Revisar/cancelar no escribe ni inicia transacción');
    foreach ([null, '', 1, true, '0'] as $confirmacion) { errorHU04(fn () => $r->registrar(41, $alta, $confirmacion), 422); }
    $id = $r->registrar(41, $normal, '1'); $ruta = $r->obtener($id);
    verificarHU04($ruta['activa'] === 1 && $ruta['codigo'] === 'RU-01', 'Ruta activa');
    verificarHU04(array_column($ruta['paradas'], 'id_parada') === [1, 2], 'Recorrido mínimo');
    verificarHU04(array_column($ruta['paradas'], 'orden') === [1, 2], 'Orden consecutivo');
    verificarHU04(array_column($ruta['paradas'], 'distancia_acumulada_km') === [null, null], 'Sin distancias');
    verificarHU04(count($r->listar('ru-')['rutas']) === 1 && count($r->listar('inexistente')['rutas']) === 0, 'Buscar rutas');
    errorHU04(fn () => $r->registrar(41, $alta, '1'), 409);
    $asociaciones = $d->asociaciones;
    $r->cambiarEstado(41, $id, 0, $ruta['version'], '1');
    verificarHU04($r->obtener($id)['activa'] === 0 && $d->asociaciones === $asociaciones, 'Desactivar conserva recorrido');
    errorHU04(fn () => $r->cambiarEstado(41, $id, 1, $ruta['version'], '1'), 409);
    $r->cambiarEstado(41, $id, 1, $r->obtener($id)['version'], '1');
    errorHU04(fn () => $r->cambiarEstado(41, $id, 1, $r->obtener($id)['version'], '1'), 409);
    foreach (['codigo' => ['', str_repeat('X', 31), null, [], "A\nB"], 'nombre' => ['', str_repeat('ñ', 121), false, "\xff"]] as $campo => $valores) {
        foreach ($valores as $valor) { errorHU04(fn () => $r->prepararRegistro(array_replace($alta, [$campo => $valor])), 422); }
    }
    $limites = $r->prepararRegistro(array_replace($alta, ['codigo' => str_repeat('a', 30), 'nombre' => str_repeat('ñ', 120)]));
    verificarHU04(mb_strlen($limites['codigo']) === 30 && mb_strlen($limites['nombre']) === 120, 'Límites en caracteres');
    errorHU04(fn () => $r->prepararRegistro(array_replace($alta, ['codigo' => str_repeat('ß', 16)])), 422);
    foreach (['activa', 'id_ruta', 'distancia_acumulada_km', 'tipo_servicio'] as $campo) { errorHU04(fn () => $r->registrar(41, $alta + [$campo => 1], '1'), 422); }
    foreach ([0, -1, '01', '1.0', 1.5, true, [], null, '9223372036854775808'] as $valor) {
        errorHU04(fn () => $r->obtener($valor), 400);
        errorHU04(fn () => $r->prepararRegistro(array_replace($alta, ['origen' => $valor])), 422);
    }
    foreach ([['destino' => '1'], ['destino' => 999], ['destino' => 60], ['intermedias' => [2]], ['intermedias' => ['1']], ['intermedias' => null], ['intermedias' => [1 => 3]]] as $cambio) {
        errorHU04(fn () => $r->registrar(41, array_replace($alta, ['codigo' => 'OTRA'], $cambio), '1'), 422);
    }
    [$d, $r, $p, $t, $a, $u] = escenarioHU04();
    $cincuenta = array_replace($alta, ['destino' => 50, 'intermedias' => range(2, 49)]);
    $id = $r->registrar(41, $cincuenta, '1'); $filas = $r->obtener($id)['paradas'];
    verificarHU04(array_column($filas, 'id_parada') === range(1, 50) && array_column($filas, 'orden') === range(1, 50), '50 puntos ordenados');
    errorHU04(fn () => $r->registrar(41, array_replace($cincuenta, ['codigo' => 'OTRA', 'destino' => 51, 'intermedias' => range(2, 50)]), '1'), 422);
    // Cada posición de inserción puede fallar; ninguna deja ruta ni asociaciones parciales.
    for ($fallo = 1; $fallo <= 50; $fallo++) {
        [$datos, $servicio, , , $admin, $usuarios] = escenarioHU04(); $foto = $datos->foto();
        $datos->fallar = 'asociacion:' . $fallo;
        errorHU04(fn () => $servicio->registrar(41, $cincuenta, '1'), 503);
        verificarHU04($datos->foto() === $foto && !$admin->enTransaccion(), 'Rollback completo en punto ' . $fallo);
    }
    [$d, $r, $p, $t, $a, $u] = escenarioHU04();
    $r->prepararRegistro($alta); $d->paradas[2]['activo'] = 0;
    errorHU04(fn () => $r->registrar(41, $alta, '1'), 422);
    verificarHU04($d->rutas === [] && $d->asociaciones === [], 'Revalidar paradas al confirmar');
    $d->paradas[2]['activo'] = 1; $u->fallar = 'confirmar'; $foto = $d->foto();
    errorHU04(fn () => $r->registrar(41, $alta, '1'), 503);
    verificarHU04($d->foto() === $foto && !$a->enTransaccion(), 'Fallo al confirmar revierte'); $u->fallar = null;
    foreach ([42, 43, 44, 999] as $actor) {
        $foto = $d->foto();
        errorHU04(fn () => $r->registrar($actor, $alta, '1'), 403);
        errorHU04(fn () => $p->registrar($actor, ['nombre' => 'No autorizada']), 403);
        errorHU04(fn () => $t->registrar($actor, ['nombre' => 'No autorizado']), 403);
        verificarHU04($foto === $d->foto(), 'Sin escritura por actor no autorizado');
    }
    $a->trasBloqueo = function () use ($u): void { $u->filas[41]['activo'] = 0; };
    errorHU04(fn () => $r->registrar(41, $alta, '1'), 403); $a->trasBloqueo = null;
    $a->iniciarTransaccion(); errorHU04(fn () => $p->registrar(41, ['nombre' => 'Anidada']), 503);
    verificarHU04($a->enTransaccion(), 'No revierte transacción ajena'); $a->revertirTransaccion();

    // Catálogo de paradas: nombres repetidos, edición descriptiva e historia intacta.
    [$d, $r, $p, $t, $a, $u] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1');
    $id = $p->registrar(41, ['nombre' => '  Terminal  ', 'referencia' => '  Centro  ']);
    $otro = $p->registrar(41, ['nombre' => 'Terminal']);
    verificarHU04($id !== $otro && $p->obtener($id)['referencia'] === 'Centro' && $p->obtener($otro)['referencia'] === null, 'Nombres duplicados y referencia opcional');
    $antes = $p->obtener(1); $asociaciones = $d->asociaciones;
    $p->modificar(41, 1, ['nombre' => 'Nuevo nombre', 'referencia' => ''], $antes['version']);
    verificarHU04($p->obtener(1)['nombre'] === 'Nuevo nombre' && $d->asociaciones === $asociaciones, 'Edición conserva identidad y asociaciones');
    errorHU04(fn () => $p->modificar(41, 1, ['nombre' => 'Obsoleto'], $antes['version']), 409);
    $p->cambiarEstado(41, 1, 0, $p->obtener(1)['version'], '1');
    verificarHU04($d->asociaciones === $asociaciones && $r->obtener($idRuta)['paradas'][0]['activo'] === 0, 'Parada inactiva conserva historia');
    verificarHU04(count($p->listar()['paradas']) === 62 && count($p->listar('terminal')['paradas']) === 2, 'Consulta incluye inactivas');
    $p->cambiarEstado(41, 1, 1, $p->obtener(1)['version'], '1');
    $p->registrar(41, ['nombre' => str_repeat('ñ', 120), 'referencia' => str_repeat('ñ', 255)]);
    foreach ([['nombre' => ''], ['nombre' => str_repeat('ñ', 121)], ['nombre' => 'A', 'referencia' => str_repeat('x', 256)], ['nombre' => "A\0B"], ['nombre' => 'A', 'activo' => 0]] as $datos) { errorHU04(fn () => $p->registrar(41, $datos), 422); }
    // Tipos de servicio: no alterar los controles de filas existentes.
    $id = $t->registrar(41, ['nombre' => '  Especial  ']);
    verificarHU04($t->obtener($id)['controla_capacidad'] === 1 && $t->obtener($id)['activo'] === 1, 'Nuevo servicio con control fijo');
    foreach ([1, 2] as $tipo) {
        $control = $t->obtener($tipo)['controla_capacidad']; $version = $t->obtener($tipo)['version'];
        $t->modificar(41, $tipo, ['nombre' => 'Servicio ' . $tipo], $version);
        errorHU04(fn () => $t->modificar(41, $tipo, ['nombre' => 'Obsoleto'], $version), 409);
        $t->cambiarEstado(41, $tipo, 0, $t->obtener($tipo)['version'], '1');
        verificarHU04($t->obtener($tipo)['controla_capacidad'] === $control, 'Conservación controla_capacidad');
        $t->cambiarEstado(41, $tipo, 1, $t->obtener($tipo)['version'], '1');
    }
    errorHU04(fn () => $t->registrar(41, ['nombre' => 'especial']), 409);
    errorHU04(fn () => $t->modificar(41, 1, ['nombre' => 'Especial'], $t->obtener(1)['version']), 409);
    $t->registrar(41, ['nombre' => str_repeat('ñ', 40)]);
    foreach ([['nombre' => ''], ['nombre' => str_repeat('ñ', 41)], ['nombre' => 'X', 'controla_capacidad' => 0], ['nombre' => 'X', 'activo' => 0]] as $datos) { errorHU04(fn () => $t->registrar(41, $datos), 422); }
    errorHU04(fn () => $t->obtener(256), 400);
    foreach ([$r, $p, $t] as $servicio) {
        errorHU04(fn () => $servicio->obtener(999), $servicio === $t ? 400 : 404);
        foreach ([null, [], str_repeat('x', 121)] as $busqueda) { errorHU04(fn () => $servicio->listar($busqueda), 400); }
        foreach ([2, true, 'ACTIVO', null] as $estado) { errorHU04(fn () => $servicio->cambiarEstado(41, 1, $estado, '', '1'), 400); }
        errorHU04(fn () => $servicio->cambiarEstado(41, 1, 0, '', '1'), 400);
        errorHU04(fn () => $servicio->cambiarEstado(41, 1, 0, $servicio->obtener(1)['version'], '0'), 422);
        errorHU04(fn () => $servicio->cambiarEstado(42, 1, 0, $servicio->obtener(1)['version'], '1'), 403);
    }
    foreach ([1062, 1205, 1213, 1451, 1452, 9999] as $codigo) {
        [$datos, $rutas, $paradas, $tipos] = escenarioHU04(); $foto = $datos->foto(); $datos->fallar = 'sql:' . $codigo . ':codigo';
        $e = errorHU04(fn () => $rutas->registrar(41, $alta, '1'), $codigo === 9999 ? 503 : 409);
        verificarHU04($datos->foto() === $foto, 'Rollback conflicto SQL');
        if ($codigo === 1062) { verificarHU04(isset($e->errores['codigo']), 'Duplicado identifica campo SQL'); }
    }
    [$d, $r, $p, $t] = escenarioHU04(); $d->fallar = 'sql:1062:nombre';
    verificarHU04(isset(errorHU04(fn () => $t->registrar(41, ['nombre' => 'Otro']), 409)->errores['nombre']), 'Duplicado servicio concurrente');
    $caso = 'eliminación física de ruta y parada con dependencias y concurrencia';
    [$d, $r, $p] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1'); $versionRuta = $r->prepararEliminacion($idRuta)['version'];
    $r->eliminar(41, $idRuta, $versionRuta, '1');
    verificarHU04(!isset($d->rutas[$idRuta]) && $d->asociaciones === [] && isset($d->paradas[1], $d->paradas[2]), 'Ruta elimina asociaciones, no paradas');
    errorHU04(fn () => $r->eliminar(41, $idRuta, $versionRuta, '1'), 404);
    [$d, $r, $p] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1'); $d->viajes[$idRuta] = true;
    $versionRuta = $r->prepararEliminacion($idRuta)['version']; errorHU04(fn () => $r->eliminar(41, $idRuta, $versionRuta, '1'), 409);
    verificarHU04(isset($d->rutas[$idRuta]) && count($d->asociaciones) === 2, 'Ruta con viajes y asociaciones se conserva');
    [$d, $r] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1'); $d->tarifas[$idRuta] = true;
    $versionRuta = $r->prepararEliminacion($idRuta)['version']; errorHU04(fn () => $r->eliminar(41, $idRuta, $versionRuta, '1'), 409);
    verificarHU04(isset($d->rutas[$idRuta]) && count($d->asociaciones) === 2, 'Ruta con tarifa histórica se conserva');
    [$d, $r] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1'); $versionRuta = $r->prepararEliminacion($idRuta)['version'];
    $d->asociaciones[0]['orden'] = 2;
    errorHU04(fn () => $r->eliminar(41, $idRuta, $versionRuta, '1'), 409);
    verificarHU04(isset($d->rutas[$idRuta]) && count($d->asociaciones) === 2, 'Cambio concurrente del recorrido cancela eliminación');
    [$d, $r] = escenarioHU04(); $idRuta = $r->registrar(41, $alta, '1'); $versionRuta = $r->prepararEliminacion($idRuta)['version'];
    $d->fallar = 'ruta_eliminar'; errorHU04(fn () => $r->eliminar(41, $idRuta, $versionRuta, '1'), 503);
    verificarHU04(isset($d->rutas[$idRuta]) && count($d->asociaciones) === 2, 'Rollback restaura ruta y asociaciones');
    [$d, , $p] = escenarioHU04(); $versionParada = $p->prepararEliminacion(10)['version']; $p->eliminar(41, 10, $versionParada, '1');
    verificarHU04(!isset($d->paradas[10]), 'Eliminación permitida de parada sin uso');
    errorHU04(fn () => $p->eliminar(41, 10, $versionParada, '1'), 404);
    [$d, $r, $p] = escenarioHU04(); $r->registrar(41, $alta, '1'); $versionParada = $p->prepararEliminacion(1)['version'];
    errorHU04(fn () => $p->eliminar(41, 1, $versionParada, '1'), 409);
    verificarHU04(isset($d->paradas[1]), 'Parada usada se conserva');
    [$d, , $p] = escenarioHU04(); $versionParada = $p->prepararEliminacion(10)['version']; $p->modificar(41, 10, ['nombre' => 'Cambió', 'referencia' => null], $versionParada);
    errorHU04(fn () => $p->eliminar(41, 10, $versionParada, '1'), 409);
    [$d, , $p] = escenarioHU04(); $d->dependenciasDisponibles = false; errorHU04(fn () => $p->prepararEliminacion(10), 503);
    [$d, $r, $p, $t] = escenarioHU04(); $d->fallar = 'listar';
    foreach ([$r, $p, $t] as $s) { errorHU04(fn () => $s->listar(), 503); }
    echo 'HU-04 dominio: ' . $comprobaciones . " comprobaciones satisfactorias (simuladas).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FALLO HU-04: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n"); exit(1);
}
