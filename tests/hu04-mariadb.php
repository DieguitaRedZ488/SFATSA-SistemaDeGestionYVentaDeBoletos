<?php

declare(strict_types=1);

// Opt-in: solo instancia desechable Windows identificada, sin TCP. No lee .env.
ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';

use SFATSA\Repositories\{PdoGestionUsuarioRepository, PdoParadaRepository, PdoRutaRepository, PdoRutaParadaRepository, PdoTipoServicioRepository, RutaParadaRepository, RutaRepository, TipoServicioRepository};
use SFATSA\Services\{ErrorGestionHU04, OperacionAdministrativaService, ParadaService, RutaService, TipoServicioService};

function configuracionEnsayo(array $config): array
{
    $id = $config['id'] ?? '';
    if (PHP_OS_FAMILY !== 'Windows' || !is_string($id) || preg_match('/\A[a-f0-9]{32}\z/', $id) !== 1
        || ($config['pipe'] ?? '') !== 'SFATSA_HU04_' . $id
        || ($config['database'] ?? '') !== 'sfatsa_hu04_ensayo_' . $id) {
        throw new RuntimeException('Destino de ensayo no permitido.');
    }
    $raiz = realpath(sys_get_temp_dir() . '/sfatsa-hu04-pdo-' . $id);
    $datos = realpath((string) ($config['datadir'] ?? ''));
    if ($raiz === false || $datos === false || realpath($raiz . '/data') !== $datos
        || trim((string) file_get_contents($raiz . '/identidad.txt')) !== $id) {
        throw new RuntimeException('Directorio de ensayo no permitido.');
    }
    return $config;
}

function identidadEnsayo(PDO $pdo, array $config, bool $conBase): array
{
    $i = $pdo->query('SELECT DATABASE() AS db, @@hostname AS host, @@datadir AS datadir,'
        . ' @@socket AS socket, @@skip_networking AS sin_tcp, VERSION() AS version')->fetch();
    $normalizar = static fn (string $ruta): string => strtolower(rtrim(str_replace('\\', '/', $ruta), '/'));
    if ($i['db'] !== ($conBase ? $config['database'] : null)
        || $i['socket'] !== $config['pipe'] || (int) $i['sin_tcp'] !== 1
        || !str_starts_with($i['version'], '12.0.2-MariaDB')
        || strcasecmp($i['host'], php_uname('n')) !== 0
        || $normalizar($i['datadir']) !== $normalizar($config['datadir'])) {
        throw new RuntimeException('Identidad de instancia no permitida.');
    }
    return $i;
}

function conectarEnsayo(array $config, bool $administracion = false): PDO
{
    $dsn = 'mysql:host=.;unix_socket=\\\\.\\pipe\\' . $config['pipe'] . ';charset=utf8mb4';
    if (!$administracion) { $dsn .= ';dbname=' . $config['database']; }
    $pdo = new PDO($dsn, $administracion ? 'root' : 'hu04_pruebas', $administracion ? '' : $config['clave'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5,
    ]);
    identidadEnsayo($pdo, $config, !$administracion);
    $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    return $pdo;
}

function serviciosHU04(PDO $pdo): array
{
    $ops = new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo));
    $p = new PdoParadaRepository($pdo);
    return [new RutaService(new PdoRutaRepository($pdo), $p, new PdoRutaParadaRepository($pdo), $ops),
        new ParadaService($p, $ops), new TipoServicioService(new PdoTipoServicioRepository($pdo), $ops)];
}

$checks = 0; $caso = 'configuración'; $admin = null; $pdo = null; $otra = null;
$baseCreada = false; $codigoSalida = 0; $hijos = []; $grupos = [];
$assert = static function (bool $valor) use (&$checks): void {
    if (!$valor) { throw new RuntimeException('Comprobación fallida en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0)); }
    $checks++;
};
$rechazo = static function (Closure $operacion, int $estado, ?string $campo = null) use ($assert): void {
    try { $operacion(); throw new LogicException('Faltó rechazo.'); }
    catch (ErrorGestionHU04 $e) {
        $assert($e->estado === $estado && ($campo === null || isset($e->errores[$campo])));
        $assert($e->getPrevious() === null && !str_contains($e->getMessage(), 'SQLSTATE'));
    }
};
$sqlRechazado = static function (Closure $operacion, array $codigos) use ($assert): void {
    try { $operacion(); throw new LogicException('Faltó restricción SQL.'); }
    catch (PDOException $e) { $assert(in_array((int) ($e->errorInfo[1] ?? 0), $codigos, true)); }
};

try {
    $entrada = ($argv[1] ?? '') === '--trabajador' ? (string) fgets(STDIN) : stream_get_contents(STDIN);
    $config = configuracionEnsayo(json_decode($entrada, true, 32, JSON_THROW_ON_ERROR));
    if (($argv[1] ?? '') === '--trabajador') {
        $pdo = conectarEnsayo($config); $repo = new PdoRutaRepository($pdo);
        $assert(!$repo->existeCodigo('CARRERA'));
        echo "LISTO\n"; fflush(STDOUT);
        if (trim((string) fgets(STDIN)) !== 'INSERTAR') { throw new RuntimeException('Sin coordinación.'); }
        $pdo->beginTransaction(); echo "ESCRIBIENDO\n"; fflush(STDOUT);
        try { $repo->insertar(['codigo' => 'CARRERA', 'nombre' => 'Competidor']); $pdo->commit(); echo "INSERTADO\n"; }
        catch (PDOException $e) {
            $pdo->rollBack(); if ((int) ($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
            echo "DUPLICADO\n";
        }
    } else {
        $admin = conectarEnsayo($config, true); $identidad = identidadEnsayo($admin, $config, false);
        $assert((int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='sfatza'")->fetchColumn() === 0);
        $assert((int) $admin->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=' . $admin->quote($config['database']))->fetchColumn() === 0);
        preg_match_all('/CREATE TABLE\s+`[^`]+`\s*\([\s\S]*?\) ENGINE=InnoDB[^;]*;/i', file_get_contents(dirname(__DIR__) . '/database/install.sql'), $tablas);
        $assert(count($tablas[0]) === 15);
        $base = '`' . $config['database'] . '`';
        identidadEnsayo($admin, $config, false); // Guarda inmediatamente anterior al primer DDL.
        $admin->exec('CREATE DATABASE ' . $base . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci'); $baseCreada = true;
        $admin->exec('USE ' . $base); identidadEnsayo($admin, $config, true);
        foreach ($tablas[0] as $ddl) { $admin->exec($ddl); }
        $definiciones = static function () use ($admin): array {
            $ddl = [];
            foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
                if (preg_match('/\A[a-z_]+\z/', $tabla) !== 1) { throw new RuntimeException('Tabla no permitida.'); }
                $ddl[$tabla] = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $admin->query('SHOW CREATE TABLE `' . $tabla . '`')->fetch()['Create Table']);
            }
            return $ddl;
        };
        $ddlAntes = $definiciones(); $config['clave'] = bin2hex(random_bytes(32));
        $admin->exec("CREATE USER 'hu04_pruebas'@'localhost' IDENTIFIED BY " . $admin->quote($config['clave']));
        $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $base.* TO 'hu04_pruebas'@'localhost'");
        $pdo = conectarEnsayo($config); $otra = conectarEnsayo($config);
        [$r, $p, $t] = serviciosHU04($pdo); [$rOtra, $pOtra, $tOtra] = serviciosHU04($otra);
        $rp = new PdoRutaParadaRepository($pdo); $repo = new PdoRutaRepository($pdo);
        $pdo->exec("INSERT INTO rol(id_rol,nombre) VALUES(1,'ADMINISTRADOR'),(2,'CAJERO')");
        $pdo->exec("INSERT INTO usuario(id_usuario,id_rol,nombres,apellidos,usuario,hash_contrasena,activo) VALUES"
            . "(1,1,'Persona','Sintética','admin_sintetico','NO_ES_CREDENCIAL',1),(2,2,'Persona','Sintética','cajero_sintetico','NO_ES_CREDENCIAL',1)");
        $pdo->exec("INSERT INTO tipo_servicio(id_tipo_servicio,nombre,controla_capacidad,activo) VALUES(1,'DIRECTO',1,1),(2,'PARADAS_CONTINUAS',0,1)");
        $caso = 'identidad, permisos y collation';
        $assert($pdo->query('SELECT CURRENT_USER()')->fetchColumn() === 'hu04_pruebas@localhost');
        $sqlRechazado(fn () => $pdo->exec('CREATE TABLE no_autorizada(id INT)'), [1142]);
        $collation = $pdo->query("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ruta' AND COLUMN_NAME='codigo'")->fetchColumn();
        $assert($collation === 'utf8mb4_uca1400_ai_ci'); $grupos[] = $caso;
        $caso = 'paradas y tipos: normalización, edición y estado';
        for ($i = 1; $i <= 52; $i++) { $assert($p->registrar(1, ['nombre' => 'Parada ' . $i]) === $i); }
        $idP = $p->registrar(1, ['nombre' => ' Terminal ', 'referencia' => ' Centro ']);
        $idP2 = $p->registrar(1, ['nombre' => 'Terminal']);
        $assert($idP !== $idP2 && count($p->listar('terminal')['paradas']) === 2);
        $assert($p->obtener($idP)['referencia'] === 'Centro' && $p->obtener($idP2)['referencia'] === null);
        $s = $t->registrar(1, ['nombre' => ' Turístico ']); $assert((int) $t->obtener($s)['controla_capacidad'] === 1);
        $rechazo(fn () => $t->registrar(1, ['nombre' => 'TURISTICO']), 409, 'nombre');
        foreach ([1, 2] as $id) {
            $antes = $t->obtener($id); $t->modificar(1, $id, ['nombre' => $antes['nombre'] . ' LOCAL'], $antes['version']);
            $t->cambiarEstado(1, $id, 0, $t->obtener($id)['version'], '1');
            $assert($t->obtener($id)['controla_capacidad'] === $antes['controla_capacidad']);
            $t->cambiarEstado(1, $id, 1, $t->obtener($id)['version'], '1');
        }
        $grupos[] = $caso;
        $caso = 'recorridos mínimo, intermedio y 50 puntos';
        $alta = ['codigo' => ' ruta-á ', 'nombre' => ' Ruta sintética ', 'origen' => 1, 'destino' => 2];
        $antes = (int) $pdo->query('SELECT COUNT(*) FROM ruta')->fetchColumn(); $r->prepararRegistro($alta);
        $assert((int) $pdo->query('SELECT COUNT(*) FROM ruta')->fetchColumn() === $antes);
        $id = $r->registrar(1, $alta, '1'); $assert($r->obtener($id)['codigo'] === 'RUTA-Á' && (int) $r->obtener($id)['activa'] === 1);
        $assert(array_map('intval', array_column($r->obtener($id)['paradas'], 'id_parada')) === [1, 2]);
        $rechazo(fn () => $r->registrar(1, array_replace($alta, ['codigo' => 'ruta-a']), '1'), 409, 'codigo');
        $assert(count($r->listar('RUTA-a')['rutas']) === 1 && count($r->listar('%')['rutas']) === 0);
        $idMedio = $r->registrar(1, array_replace($alta, ['codigo' => 'INTERMEDIA', 'intermedias' => [4, 3]]), '1');
        $assert(array_map('intval', array_column($r->obtener($idMedio)['paradas'], 'id_parada')) === [1, 4, 3, 2]);
        $id50 = $r->registrar(1, array_replace($alta, ['codigo' => 'CINCUENTA', 'destino' => 50, 'intermedias' => range(2, 49)]), '1');
        $filas = $r->obtener($id50)['paradas'];
        $assert(array_map('intval', array_column($filas, 'orden')) === range(1, 50));
        $assert(array_column($filas, 'distancia_acumulada_km') === array_fill(0, 50, null));
        $rechazo(fn () => $r->registrar(1, array_replace($alta, ['codigo' => '51', 'destino' => 51, 'intermedias' => range(2, 50)]), '1'), 422);
        foreach ([['destino' => 1], ['destino' => 999], ['intermedias' => [2]], ['intermedias' => [3, 3]]] as $cambio) {
            $rechazo(fn () => $r->registrar(1, array_replace($alta, ['codigo' => 'INVALIDA'], $cambio), '1'), 422);
        }
        $grupos[] = $caso;
        $caso = 'asociaciones históricas y concurrencia optimista';
        $historico = $rp->listarPorRutaOrdenado($id); $version = $p->obtener(1)['version'];
        $pOtra->modificar(1, 1, ['nombre' => 'Nombre actualizado', 'referencia' => 'Referencia nueva'], $version);
        $rechazo(fn () => $p->modificar(1, 1, ['nombre' => 'Obsoleto'], $version), 409);
        $p->cambiarEstado(1, 1, 0, $p->obtener(1)['version'], '1');
        $rechazo(fn () => $r->registrar(1, array_replace($alta, ['codigo' => 'INACTIVA']), '1'), 422);
        $actual = $rp->listarPorRutaOrdenado($id);
        $assert(array_column($historico, 'id_ruta_parada') === array_column($actual, 'id_ruta_parada'));
        $assert((int) $actual[0]['activo'] === 0 && $actual[0]['nombre'] === 'Nombre actualizado');
        $p->cambiarEstado(1, 1, 1, $p->obtener(1)['version'], '1');
        $antes = $rp->listarPorRutaOrdenado($id); $version = $r->obtener($id)['version'];
        $rOtra->cambiarEstado(1, $id, 0, $version, '1');
        $rechazo(fn () => $r->cambiarEstado(1, $id, 0, $version, '1'), 409);
        $r->cambiarEstado(1, $id, 1, $r->obtener($id)['version'], '1');
        $assert($antes === $rp->listarPorRutaOrdenado($id));
        $version = $t->obtener(2)['version']; $tOtra->modificar(1, 2, ['nombre' => 'Conserva cero'], $version);
        $rechazo(fn () => $t->modificar(1, 2, ['nombre' => 'Obsoleto'], $version), 409);
        $assert((int) $t->obtener(2)['controla_capacidad'] === 0); $grupos[] = $caso;
        $caso = 'rollback real tras inserciones parciales y fallo FK';
        $foto = static fn (): array => [$pdo->query('SELECT * FROM ruta ORDER BY id_ruta')->fetchAll(), $pdo->query('SELECT * FROM ruta_parada ORDER BY id_ruta_parada')->fetchAll()];
        $antes = $foto();
        $fallo = new class($rp) implements RutaParadaRepository {
            public function __construct(private PdoRutaParadaRepository $base) {}
            public function insertarPuntos(int $idRuta, array $idsParadas): void { $this->base->insertarPuntos($idRuta, array_slice($idsParadas, 0, 2)); throw new RuntimeException('FALLO_SINTETICO'); }
            public function listarPorRutaOrdenado(int $idRuta): array { return $this->base->listarPorRutaOrdenado($idRuta); }
        };
        $conFallo = new RutaService($repo, new PdoParadaRepository($pdo), $fallo, new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
        $rechazo(fn () => $conFallo->registrar(1, array_replace($alta, ['codigo' => 'ROLLBACK', 'intermedias' => [3]]), '1'), 503);
        $assert($foto() === $antes && !$pdo->inTransaction());
        $pdo->beginTransaction();
        $sqlRechazado(fn () => $pdo->exec("INSERT INTO ruta_parada(id_ruta,id_parada,orden) VALUES($id,999999,3)"), [1452]); $pdo->rollBack();
        $grupos[] = $caso;
        $caso = 'UNIQUE, CHECK, FK y límites SQL';
        foreach (["($id,3,1,NULL)", "($id,1,3,NULL)", "($id,3,0,NULL)", "($id,3,3,-1)"] as $valores) {
            $sqlRechazado(fn () => $pdo->exec('INSERT INTO ruta_parada(id_ruta,id_parada,orden,distancia_acumulada_km) VALUES' . $valores), [1062, 4025]);
        }
        $sqlRechazado(fn () => $pdo->exec('DELETE FROM parada WHERE id_parada=1'), [1451]);
        $sqlRechazado(fn () => $pdo->exec("INSERT INTO ruta(codigo,nombre) VALUES('" . str_repeat('X', 31) . "','Larga')"), [1406]);
        $sqlRechazado(fn () => $pdo->exec("INSERT INTO tipo_servicio(nombre,controla_capacidad) VALUES('TURÍSTICO',1)"), [1062]);
        foreach ([fn () => $repo->insertar(['codigo' => 'SIN_TX', 'nombre' => 'Sin TX']), fn () => $rp->insertarPuntos($id, [1, 2]), fn () => (new PdoParadaRepository($pdo))->buscar(1, true)] as $accion) {
            try { $accion(); throw new LogicException('Faltó guarda TX'); } catch (RuntimeException $e) { $assert(!$e instanceof LogicException && !$e instanceof PDOException); }
        }
        $grupos[] = $caso;
        $caso = 'bloqueos reales y cuenta no administradora';
        $rechazo(fn () => $r->registrar(2, array_replace($alta, ['codigo' => 'CAJERO']), '1'), 403);
        $pdo->beginTransaction(); (new PdoParadaRepository($pdo))->buscar(1, true);
        $rechazo(fn () => $rOtra->registrar(1, array_replace($alta, ['codigo' => 'BLOQUEADA']), '1'), 409);
        $assert(!$otra->inTransaction() && !$repo->existeCodigo('BLOQUEADA')); $pdo->rollBack();
        $rOtra->registrar(1, array_replace($alta, ['codigo' => 'BLOQUEADA']), '1');
        $assert($repo->existeCodigo('BLOQUEADA'));
        $pdo->beginTransaction(); (new PdoGestionUsuarioRepository($pdo))->bloquearAdministracion();
        $rechazo(fn () => $pOtra->registrar(1, ['nombre' => 'No persistir']), 409); $pdo->rollBack();
        $grupos[] = $caso;
        $caso = 'carrera UNIQUE observada entre procesos';
        $pipes = []; $hijo = proc_open([PHP_BINARY, __FILE__, '--trabajador'], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
        if (!is_resource($hijo)) { throw new RuntimeException('Trabajador no disponible.'); } $hijos[] = [$hijo, $pipes];
        stream_set_timeout($pipes[1], 10); stream_set_timeout($pipes[2], 10);
        fwrite($pipes[0], json_encode($config, JSON_THROW_ON_ERROR) . "\n"); fflush($pipes[0]);
        $assert(trim((string) fgets($pipes[1])) === 'LISTO'); $assert(!$repo->existeCodigo('CARRERA'));
        $pdo->beginTransaction(); $repo->insertar(['codigo' => 'CARRERA', 'nombre' => 'Ganador']);
        fwrite($pipes[0], "INSERTAR\n"); fflush($pipes[0]); fclose($pipes[0]);
        $assert(trim((string) fgets($pipes[1])) === 'ESCRIBIENDO');
        $esperando = false; $limite = microtime(true) + 2;
        do { $esperando = (int) $admin->query('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS')->fetchColumn() > 0; if (!$esperando) { usleep(20000); } } while (!$esperando && microtime(true) < $limite);
        $assert($esperando); $pdo->commit();
        $assert(trim((string) fgets($pipes[1])) === 'DUPLICADO'); $assert(stream_get_contents($pipes[2]) === '');
        fclose($pipes[1]); fclose($pipes[2]); $assert(proc_close($hijo) === 0); array_pop($hijos);
        $assert(count($repo->listar('CARRERA')) === 1); $grupos[] = $caso;
        $caso = '1062 real después de precomprobación y guarda de conexión compartida';
        $competencia = new class($repo, new PdoRutaRepository($otra), $otra) implements RutaRepository {
            public function __construct(private PdoRutaRepository $base, private PdoRutaRepository $externo, private PDO $conexion) {}
            public function listar(string $q): array { return $this->base->listar($q); }
            public function buscar(int $idRuta, bool $bloquear = false): ?array { return $this->base->buscar($idRuta, $bloquear); }
            public function existeCodigo(string $codigo, ?int $excepto = null): bool { return $this->base->existeCodigo($codigo, $excepto); }
            public function servicioDisponible(): bool { return $this->base->servicioDisponible(); }
            public function actualizar(int $idRuta, array $datos): void { $this->base->actualizar($idRuta, $datos); }
            public function insertar(array $datos): int {
                $this->conexion->beginTransaction();
                try { $this->externo->insertar($datos); $this->conexion->commit(); }
                catch (Throwable $e) { $this->conexion->rollBack(); throw $e; }
                return $this->base->insertar($datos);
            }
            public function cambiarEstado(int $idRuta, int $nuevo, int $anterior): void { $this->base->cambiarEstado($idRuta, $nuevo, $anterior); }
        };
        $conCarrera = new RutaService($competencia, new PdoParadaRepository($pdo), $rp, new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
        $cuentaAsociaciones = (int) $pdo->query('SELECT COUNT(*) FROM ruta_parada')->fetchColumn();
        $rechazo(fn () => $conCarrera->registrar(1, array_replace($alta, ['codigo' => 'RACE-SERVICIO']), '1'), 409, 'codigo');
        $assert(count($repo->listar('RACE-SERVICIO')) === 1 && (int) $pdo->query('SELECT COUNT(*) FROM ruta_parada')->fetchColumn() === $cuentaAsociaciones);
        $tipoConCarrera = new class(new PdoTipoServicioRepository($pdo), new PdoTipoServicioRepository($otra), $otra) implements TipoServicioRepository {
            public function __construct(private PdoTipoServicioRepository $base, private PdoTipoServicioRepository $externo, private PDO $conexion) {}
            public function listar(string $q): array { return $this->base->listar($q); }
            public function buscar(int $idTipoServicio, bool $bloquear = false): ?array { return $this->base->buscar($idTipoServicio, $bloquear); }
            public function existeNombre(string $nombre, ?int $excepto = null): bool { return $this->base->existeNombre($nombre, $excepto); }
            public function insertar(array $datos): int {
                $this->conexion->beginTransaction();
                try { $this->externo->insertar($datos); $this->conexion->commit(); }
                catch (Throwable $e) { $this->conexion->rollBack(); throw $e; }
                return $this->base->insertar($datos);
            }
            public function actualizarNombre(int $idTipoServicio, string $nombre): void { $this->base->actualizarNombre($idTipoServicio, $nombre); }
            public function cambiarEstado(int $idTipoServicio, int $nuevo, int $anterior): void { $this->base->cambiarEstado($idTipoServicio, $nuevo, $anterior); }
        };
        $tipoCarrera = new TipoServicioService($tipoConCarrera, new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
        $rechazo(fn () => $tipoCarrera->registrar(1, ['nombre' => 'Servicio en carrera']), 409, 'nombre');
        $assert(count($t->listar('Servicio en carrera')['tipos_servicio']) === 1);
        // PDO ajena sin transacción: rechaza antes de escribir; no confirma escrituras parciales.
        $malConectada = new RutaService($repo, new PdoParadaRepository($otra), $rp, new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
        $rechazo(fn () => $malConectada->registrar(1, array_replace($alta, ['codigo' => 'PDO-AJENA']), '1'), 503);
        $assert(!$repo->existeCodigo('PDO-AJENA') && !$pdo->inTransaction() && !$otra->inTransaction());
        $grupos[] = $caso;
        $assert(count($ddlAntes) === 15 && $ddlAntes === $definiciones());
        echo json_encode(['resultado' => 'PASO', 'comprobaciones' => $checks, 'version' => $identidad['version'], 'database' => $config['database'], 'pipe' => $config['pipe'], 'collation' => $collation, 'grupos' => $grupos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }
} catch (Throwable $e) {
    $detalle = preg_match('/\AComprobación fallida en línea [0-9]+\z/', $e->getMessage()) ? $e->getMessage() : '';
    fwrite(STDERR, "Ensayo HU-04 detenido: $caso; " . get_class($e) . ' línea ' . $e->getLine() . ". $detalle Sin detalles privados.\n"); $codigoSalida = 1;
} finally {
    foreach ([$pdo, $otra] as $conexion) { if ($conexion instanceof PDO && $conexion->inTransaction()) { $conexion->rollBack(); } }
    foreach ($hijos as [$proceso, $pipes]) { foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } proc_terminate($proceso); proc_close($proceso); }
    if ($admin instanceof PDO && $baseCreada) {
        identidadEnsayo($admin, $config, true); $pdo = null; $otra = null;
        $admin->exec('DROP DATABASE `' . $config['database'] . '`'); $admin->exec("DROP USER IF EXISTS 'hu04_pruebas'@'localhost'");
        echo "Recursos SQL del ensayo eliminados.\n";
    }
}
exit($codigoSalida);
