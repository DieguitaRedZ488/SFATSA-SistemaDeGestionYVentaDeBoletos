<?php

declare(strict_types=1);

use SFATSA\Repositories\PdoGestionUsuarioRepository;
use SFATSA\Repositories\PdoVehiculoRepository;
use SFATSA\Services\ErrorGestionVehiculos;
use SFATSA\Services\OperacionAdministrativaService;
use SFATSA\Services\VehiculoService;

// Opt-in CLI. Solo admite una instancia Windows desechable sin TCP, creada para este ensayo.
ini_set('display_errors', '0'); ini_set('log_errors', '0'); ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap-web.php';

function configuracionEnsayo(array $config): array
{
    $id = $config['id'] ?? '';
    if (PHP_OS_FAMILY !== 'Windows' || !is_string($id) || preg_match('/\A[a-f0-9]{32}\z/', $id) !== 1
        || ($config['pipe'] ?? '') !== 'SFATSA_HU02_' . $id
        || ($config['database'] ?? '') !== 'sfatsa_hu02_ensayo_' . $id) {
        throw new RuntimeException('Destino de ensayo no permitido.');
    }
    $raiz = realpath(sys_get_temp_dir() . '/sfatsa-hu02-pdo-' . $id);
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
    $pdo = new PDO($dsn, $administracion ? 'root' : 'hu02_pruebas', $administracion ? '' : $config['clave'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5,
    ]);
    identidadEnsayo($pdo, $config, !$administracion);
    $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    return $pdo;
}

function servicioEnsayo(PDO $pdo): VehiculoService
{
    return new VehiculoService(new PdoVehiculoRepository($pdo),
        new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
}

function datosEnsayo(string $sufijo): array
{
    return ['numero_economico' => 'ECO-' . $sufijo, 'placa' => 'A-' . $sufijo . '-A',
        'descripcion' => 'Autobús sintético', 'capacidad' => 40, 'estado' => 'ACTIVO'];
}

$checks = 0; $caso = 'configuración'; $admin = null; $pdo = null; $otra = null; $baseCreada = false;
$hijos = []; $grupos = []; $codigoSalida = 0;
$assert = static function (bool $condicion) use (&$checks): void {
    if (!$condicion) {
        throw new RuntimeException('Comprobación fallida en línea ' . (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'] ?? 0));
    }
    $checks++;
};
$rechazo = static function (Closure $operacion, int $estado, ?string $campo = null) use ($assert): void {
    try { $operacion(); throw new LogicException('Faltó rechazo.'); }
    catch (ErrorGestionVehiculos $e) {
        $assert($e->estado === $estado && ($campo === null || isset($e->errores[$campo])));
        $assert($e->getPrevious() === null && !str_contains($e->getMessage(), 'SQLSTATE'));
    }
};
$rechazoSql = static function (Closure $operacion, array $codigos) use ($assert): void {
    try { $operacion(); throw new LogicException('Faltó restricción.'); }
    catch (PDOException $e) { $assert(in_array((int) ($e->errorInfo[1] ?? 0), $codigos, true)); }
};

try {
    $entrada = ($argv[1] ?? '') === '--trabajador' ? (string) fgets(STDIN) : stream_get_contents(STDIN);
    $config = configuracionEnsayo(json_decode($entrada, true, 32, JSON_THROW_ON_ERROR));
    if (($argv[1] ?? '') === '--trabajador') {
        $pdo = conectarEnsayo($config);
        $repo = new PdoVehiculoRepository($pdo);
        // Ambas conexiones precomprueban antes del INSERT del padre.
        if ($repo->existePlaca($config['datos']['placa']) || $repo->existeNumeroEconomico($config['datos']['numero_economico'])) {
            throw new RuntimeException('Precondición de carrera no satisfecha.');
        }
        echo "LISTO\n"; fflush(STDOUT);
        if (trim((string) fgets(STDIN)) !== 'INSERTAR') { throw new RuntimeException('Sin coordinación.'); }
        echo "ESCRIBIENDO\n"; fflush(STDOUT);
        try { $repo->insertar($config['datos']); echo "INSERTADO\n"; }
        catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
            echo "DUPLICADO\n";
        }
        exit(0);
    }

    $admin = conectarEnsayo($config, true);
    $identidad = identidadEnsayo($admin, $config, false);
    $assert((int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='sfatza'")->fetchColumn() === 0);
    $assert((int) $admin->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $admin->quote($config['database']))->fetchColumn() === 0);
    // No ejecutar install.sql: extraer exclusivamente CREATE TABLE de la fuente versionada.
    $instalador = file_get_contents(dirname(__DIR__) . '/database/install.sql');
    preg_match_all('/CREATE TABLE\s+`[^`]+`\s*\([\s\S]*?\) ENGINE=InnoDB[^;]*;/i', $instalador, $tablas);
    $assert(count($tablas[0]) === 15);
    $nombreBase = '`' . $config['database'] . '`';
    identidadEnsayo($admin, $config, false); // Guarda anterior al primer DDL.
    $admin->exec('CREATE DATABASE ' . $nombreBase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci');
    $baseCreada = true;
    $admin->exec('USE ' . $nombreBase);
    identidadEnsayo($admin, $config, true);
    foreach ($tablas[0] as $ddl) { $admin->exec($ddl); }
    $definiciones = static function () use ($admin): array {
        $ddl = [];
        foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
            if (preg_match('/\A[a-z_]+\z/', $tabla) !== 1) { throw new RuntimeException('Tabla no permitida.'); }
            $ddl[$tabla] = preg_replace('/ AUTO_INCREMENT=[0-9]+/', '', $admin->query('SHOW CREATE TABLE `' . $tabla . '`')->fetch()['Create Table']);
        }
        return $ddl;
    };
    $ddlAntes = $definiciones();
    $config['clave'] = bin2hex(random_bytes(32)); // Solo memoria; no argumentos, archivos ni logs.
    $admin->exec("CREATE USER 'hu02_pruebas'@'localhost' IDENTIFIED BY " . $admin->quote($config['clave']));
    $admin->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON $nombreBase.* TO 'hu02_pruebas'@'localhost'");
    $pdo = conectarEnsayo($config); $otra = conectarEnsayo($config);
    $repo = new PdoVehiculoRepository($pdo); $repoOtra = new PdoVehiculoRepository($otra); $servicio = servicioEnsayo($pdo);
    $pdo->exec("INSERT INTO rol(id_rol,nombre) VALUES(1,'ADMINISTRADOR'),(2,'CAJERO')");
    $pdo->exec("INSERT INTO usuario(id_usuario,id_rol,nombres,apellidos,usuario,hash_contrasena,activo) VALUES"
        . " (1,1,'Persona','Sintética','admin_sintetico','NO_ES_CREDENCIAL',1),"
        . " (2,2,'Persona','Sintética','cajero_sintetico','NO_ES_CREDENCIAL',1)");

    $caso = 'identidad, permisos y esquema';
    $assert($pdo->query('SELECT CURRENT_USER()')->fetchColumn() === 'hu02_pruebas@localhost');
    $privilegios = implode(' ', $pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN));
    $assert(str_contains($privilegios, 'SELECT, INSERT, UPDATE, DELETE') && !str_contains($privilegios, 'ALL PRIVILEGES'));
    $assert((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) === false);
    $col = $pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS"
        . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehiculo' AND COLUMN_NAME='estado'")->fetch();
    $assert($col['COLUMN_TYPE'] === "enum('ACTIVO','INACTIVO')" && $col['IS_NULLABLE'] === 'NO' && $col['COLUMN_DEFAULT'] === "'ACTIVO'");
    $assert($col['COLLATION_NAME'] === 'utf8mb4_uca1400_ai_ci');
    $grupos[] = $caso;

    $caso = 'alta, normalización y collation real';
    $datos = datosEnsayo('100'); $datos['placa'] = ' a-00100-a '; $datos['numero_economico'] = ' écó-100 '; unset($datos['estado']);
    $normalizados = $servicio->prepararRegistro($datos);
    $assert($repo->listar('') === [] && !$pdo->inTransaction());
    $servicio->registrar(1, $datos, '1'); $fila = $repo->listar('')[0]; $id = (int) $fila['id_vehiculo'];
    $assert($fila['placa'] === 'A-00100-A' && $fila['numero_economico'] === 'ÉCÓ-100' && $fila['estado'] === 'ACTIVO');
    $assert($normalizados['numero_economico'] === $fila['numero_economico']);
    $assert($repo->existePlaca('a-00100-a') && $repo->existeNumeroEconomico('eco-100'));
    $assert(count($servicio->listar('eco-100')['vehiculos']) === 1 && count($servicio->listar('écó')['vehiculos']) === 1);
    foreach (['00100', 'A-00100-A', 'a-00100-a', 'co-100'] as $q) { $assert(count($repo->listar($q)) === 1); }
    $duplicado = datosEnsayo('101'); $duplicado['placa'] = 'a-00100-a';
    $rechazo(fn () => $servicio->registrar(1, $duplicado, '1'), 409, 'placa');
    $rechazoSql(fn () => $repo->insertar($duplicado), [1062]);
    $duplicado = datosEnsayo('102'); $duplicado['numero_economico'] = 'eco-100';
    $rechazo(fn () => $servicio->registrar(1, $duplicado, '1'), 409, 'numero_economico');
    $rechazoSql(fn () => $repo->insertar($duplicado), [1062]);
    $especial = datosEnsayo('103'); $especial['numero_economico'] = "ECO-%_!'103"; $repo->insertar($especial);
    $assert(count($repo->listar("%_!'")) === 1 && count($repo->listar('%')) === 1 && count($repo->listar('_')) === 1);
    $assert($repo->listar("' OR 1=1 --") === [] && $repo->listar('ausente') === []);
    $grupos[] = $caso;

    $caso = 'edición, estados y rollback reales';
    $editar = $fila; unset($editar['estado']); $editar['descripcion'] = 'Modificado con acento á';
    $servicio->modificar(1, $id, $editar); $servicio->modificar(1, $id, $editar);
    $assert($repo->buscar($id)['descripcion'] === $editar['descripcion'] && $repo->buscar($id)['estado'] === 'ACTIVO');
    $assert(!$repo->existePlaca($fila['placa'], $id) && !$repo->existeNumeroEconomico($fila['numero_economico'], $id));
    $editar['placa'] = $especial['placa']; $antes = $repo->buscar($id);
    $rechazo(fn () => $servicio->modificar(1, $id, $editar), 409, 'placa');
    $assert($repo->buscar($id) === $antes && !$pdo->inTransaction());
    $servicio->cambiarEstado(1, $id, 'INACTIVO', '1'); $assert($repo->buscar($id)['estado'] === 'INACTIVO');
    $rechazo(fn () => $servicio->cambiarEstado(1, $id, 'INACTIVO', '1'), 409);
    $servicio->cambiarEstado(1, $id, 'ACTIVO', '1'); $assert($repo->buscar($id)['estado'] === 'ACTIVO');
    $pdo->beginTransaction(); $repo->insertar(datosEnsayo('ROLLBACK')); $pdo->rollBack();
    $assert(!$repo->existePlaca('A-ROLLBACK-A'));
    $rechazo(fn () => $servicio->registrar(2, datosEnsayo('DENEGADO'), '1'), 403);
    $assert(!$repo->existePlaca('A-DENEGADO-A') && !$pdo->inTransaction());
    $grupos[] = $caso;

    $caso = 'restricciones CHECK, ENUM, límites y FK';
    foreach ([0, -1, 65536] as $capacidad) {
        $mal = datosEnsayo('CAP' . (string) abs($capacidad)); $mal['capacidad'] = $capacidad;
        $rechazoSql(fn () => $repo->insertar($mal), [4025, 1264]);
    }
    $mal = datosEnsayo('MAL'); $mal['estado'] = 'MANTENIMIENTO'; $rechazoSql(fn () => $repo->insertar($mal), [1265]);
    $mal = datosEnsayo('MAL'); $mal['placa'] = str_repeat('A', 21); $rechazoSql(fn () => $repo->insertar($mal), [1406]);
    $mal = datosEnsayo('MAL'); $mal['numero_economico'] = str_repeat('A', 31); $rechazoSql(fn () => $repo->insertar($mal), [1406]);
    $mal = datosEnsayo('MAL'); $mal['descripcion'] = str_repeat('á', 101); $rechazoSql(fn () => $repo->insertar($mal), [1406]);
    $limite = datosEnsayo('LIMITE'); $limite['placa'] = str_repeat('A', 20); $limite['numero_economico'] = str_repeat('É', 30);
    $limite['descripcion'] = str_repeat('á', 100); $limite['capacidad'] = 65535; $servicio->registrar(1, $limite, '1');
    $assert(count($repo->listar(str_repeat('É', 30))) === 1);
    $limiteLeido = $repo->listar(str_repeat('É', 30))[0];
    $assert($limiteLeido['placa'] === $limite['placa'] && $limiteLeido['numero_economico'] === $limite['numero_economico']
        && $limiteLeido['descripcion'] === $limite['descripcion'] && (int) $limiteLeido['capacidad'] === 65535);
    $assert((int) $pdo->query("SELECT COUNT(*) FROM vehiculo WHERE estado NOT IN ('ACTIVO','INACTIVO') OR capacidad<=0")->fetchColumn() === 0);
    $mal = datosEnsayo('NULL'); $mal['descripcion'] = null; $repo->insertar($mal); // SQL nullable, aplicación obligatoria.
    $rechazo(fn () => $servicio->registrar(1, $mal, '1'), 422, 'descripcion');
    $rechazoSql(fn () => $pdo->exec("INSERT INTO mantenimiento(id_vehiculo,fecha_inicio,descripcion) VALUES(999999,NOW(),'Sintético')"), [1452]);
    $pdo->exec("INSERT INTO mantenimiento(id_vehiculo,fecha_inicio,descripcion) VALUES($id,NOW(),'Sintético')");
    $rechazoSql(fn () => $pdo->exec("DELETE FROM vehiculo WHERE id_vehiculo=$id"), [1451]);
    $servicio->cambiarEstado(1, $id, 'INACTIVO', '1');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM mantenimiento')->fetchColumn() === 1);
    $assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='vehiculo'")->fetchColumn() === 2);
    $grupos[] = $caso;

    $caso = 'bloqueos entre conexiones y recuperación tras timeout';
    $pdo->beginTransaction(); $repo->buscar($id, true);
    $otra->exec('SET SESSION innodb_lock_wait_timeout=1');
    $rechazoSql(fn () => $repoOtra->cambiarEstado($id, 'ACTIVO', 'INACTIVO'), [1205]);
    $pdo->rollBack(); $repoOtra->cambiarEstado($id, 'ACTIVO', 'INACTIVO');
    $assert($repo->buscar($id)['estado'] === 'ACTIVO');
    // El bloqueo de rol de S1 también impide escrituras administrativas simultáneas.
    $pdo->beginTransaction(); (new PdoGestionUsuarioRepository($pdo))->bloquearAdministracion();
    $rechazo(fn () => servicioEnsayo($otra)->registrar(1, datosEnsayo('BLOQUEADO'), '1'), 503);
    $assert(!$otra->inTransaction() && !$repo->existePlaca('A-BLOQUEADO-A'));
    $pdo->rollBack(); servicioEnsayo($otra)->registrar(1, datosEnsayo('BLOQUEADO'), '1');
    $assert($repo->existePlaca('A-BLOQUEADO-A'));
    $grupos[] = $caso;

    $caso = 'carreras UNIQUE con procesos y conexiones independientes';
    foreach (['placa', 'numero_economico'] as $campo) {
        $a = datosEnsayo('CARRERA' . ($campo === 'placa' ? 'P' : 'E'));
        $b = $a; $b[$campo === 'placa' ? 'numero_economico' : 'placa'] = 'OTRO-' . ($campo === 'placa' ? 'P' : 'E');
        $tuberias = [];
        $hijo = proc_open([PHP_BINARY, __FILE__, '--trabajador'], [['pipe','r'],['pipe','w'],['pipe','w']], $tuberias);
        if (!is_resource($hijo)) { throw new RuntimeException('No se pudo iniciar trabajador.'); }
        $hijos[] = [$hijo, $tuberias];
        stream_set_timeout($tuberias[1], 10); stream_set_timeout($tuberias[2], 10);
        // El protocolo transmite configuración únicamente por stdin, nunca argumentos/archivos.
        $trabajador = $config + ['datos' => $b];
        fwrite($tuberias[0], json_encode($trabajador, JSON_THROW_ON_ERROR) . "\n"); fflush($tuberias[0]);
        $assert(trim((string) fgets($tuberias[1])) === 'LISTO');
        $assert(!$repo->existePlaca($a['placa']) && !$repo->existeNumeroEconomico($a['numero_economico']));
        $pdo->beginTransaction(); $repo->insertar($a);
        fwrite($tuberias[0], "INSERTAR\n"); fflush($tuberias[0]); fclose($tuberias[0]);
        $assert(trim((string) fgets($tuberias[1])) === 'ESCRIBIENDO');
        // Verificar que el trabajador realmente espera un lock InnoDB, sin inferirlo de un sleep.
        $esperando = false; $limiteEspera = microtime(true) + 2;
        do {
            $esperando = (int) $admin->query('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS')->fetchColumn() > 0;
            if (!$esperando) { usleep(20000); }
        } while (!$esperando && microtime(true) < $limiteEspera);
        if (!$esperando) {
            $estados = $admin->query("SELECT COMMAND,STATE FROM information_schema.PROCESSLIST WHERE USER='hu02_pruebas'")->fetchAll();
            fwrite(STDERR, 'Diagnóstico de espera (sin consultas): ' . json_encode($estados) . "\n");
        }
        $assert($esperando); $pdo->commit();
        $assert(trim((string) fgets($tuberias[1])) === 'DUPLICADO');
        $assert(stream_get_contents($tuberias[2]) === ''); fclose($tuberias[1]); fclose($tuberias[2]);
        $assert(proc_close($hijo) === 0); array_pop($hijos);
        $assert(count($repo->listar($a[$campo])) === 1);
    }
    $grupos[] = $caso;
    $caso = 'error 1062 real tras precomprobación del servicio';
    foreach (['placa', 'numero_economico'] as $campo) {
        $datos = datosEnsayo($campo === 'placa' ? 'RACESP' : 'RACESE');
        $externos = $datos; $externos[$campo === 'placa' ? 'numero_economico' : 'placa'] = 'EXTERNO-' . ($campo === 'placa' ? 'P' : 'E');
        // Simular escritor externo ENTRE precomprobación e INSERT con una segunda PDO real.
        $competencia = new class($repo, $repoOtra, $externos) implements \SFATSA\Repositories\VehiculoRepository {
            public function __construct(private PdoVehiculoRepository $base, private PdoVehiculoRepository $externo, private array $datos) {}
            public function listar(string $q): array { return $this->base->listar($q); }
            public function buscar(int $id, bool $bloquear = false): ?array { return $this->base->buscar($id, $bloquear); }
            public function existePlaca(string $valor, ?int $excepto = null): bool { return $this->base->existePlaca($valor, $excepto); }
            public function existeNumeroEconomico(string $valor, ?int $excepto = null): bool { return $this->base->existeNumeroEconomico($valor, $excepto); }
            public function insertar(array $datos): void { $this->externo->insertar($this->datos); $this->base->insertar($datos); }
            public function actualizar(int $id, array $datos): void { $this->base->actualizar($id, $datos); }
            public function cambiarEstado(int $id, string $nuevo, string $anterior): void { $this->base->cambiarEstado($id, $nuevo, $anterior); }
        };
        $conCarrera = new VehiculoService($competencia, new OperacionAdministrativaService(new PdoGestionUsuarioRepository($pdo)));
        $rechazo(fn () => $conCarrera->registrar(1, $datos, '1'), 409, $campo);
        $assert(!$pdo->inTransaction() && count($repo->listar($datos[$campo])) === 1);
    }
    $assert(count($ddlAntes) === 15 && $definiciones() === $ddlAntes);
    $grupos[] = $caso;
    echo json_encode(['resultado'=>'PASO','comprobaciones'=>$checks,'version'=>$identidad['version'],
        'database'=>$config['database'],'pipe'=>$config['pipe'],'collation'=>$col['COLLATION_NAME'],
        'grupos'=>$grupos], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    if (($argv[1] ?? '') === '--trabajador') { fwrite(STDERR, "Trabajador de ensayo rechazado.\n"); }
    else {
        $detalle = preg_match('/\AComprobación fallida en línea [0-9]+\z/', $e->getMessage()) === 1 ? $e->getMessage() : '';
        fwrite(STDERR, "Ensayo detenido: $caso; " . get_class($e) . ' línea ' . $e->getLine() . ". $detalle Sin detalles privados.\n");
    }
    $codigoSalida = 1;
} finally {
    foreach ([$pdo, $otra] as $conexion) { if ($conexion instanceof PDO && $conexion->inTransaction()) { $conexion->rollBack(); } }
    foreach ($hijos as [$proceso, $pipes]) {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        proc_terminate($proceso); proc_close($proceso); // Solo trabajadores propios de este archivo.
    }
    if ($admin instanceof PDO && $baseCreada) {
        identidadEnsayo($admin, $config, true);
        $pdo = null; $otra = null;
        $admin->exec('DROP DATABASE `' . $config['database'] . '`');
        $admin->exec("DROP USER IF EXISTS 'hu02_pruebas'@'localhost'");
        echo "Recursos SQL del ensayo eliminados.\n";
    }
}
exit($codigoSalida);
