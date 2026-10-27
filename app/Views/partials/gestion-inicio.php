<?php
$esConductores = ($moduloGestion ?? 'usuarios') === 'conductores';
$esVehiculos = ($moduloGestion ?? 'usuarios') === 'vehiculos';
$vehiculosDisponibles = true;
foreach (['listado', 'formulario', 'confirmacion', 'estado'] as $plantillaVehiculo) {
    $vehiculosDisponibles = $vehiculosDisponibles && is_file(dirname(__DIR__) . '/vehiculos/' . $plantillaVehiculo . '.php');
}
$claseBody = $esVehiculos ? 'pagina-vehiculos' : ($esConductores ? 'pagina-conductores' : 'pagina-usuarios');
$estilosUsuarios = true;
require __DIR__ . '/cabecera.php';
?>
<a class="skip-link" href="#contenido">Ir al contenido</a>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="/?ruta=inicio" aria-label="SFATSA, inicio">SFATSA</a>
        <div class="user-menu">
            <span class="user-menu__avatar" aria-hidden="true">AD</span>
            <span class="user-menu__identity"><strong><?= $escapar($usuario->usuario) ?></strong><small>Administrador · Sesión activa</small></span>
            <form action="/?ruta=logout" method="post" data-form-acceso>
                <input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf ?? $token) ?>">
                <button class="btn button button--logout" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Cerrar sesión</span></button>
                <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
            </form>
        </div>
    </header>
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Navegación principal">
            <a class="nav-item" href="/?ruta=inicio"><span class="nav-icon" aria-hidden="true">⌂</span> Inicio</a>
            <a class="nav-item<?= !$esConductores && !$esVehiculos ? ' active' : '' ?>" href="/?ruta=usuarios"<?= !$esConductores && !$esVehiculos ? ' aria-current="' . ($titulo === 'Gestión de usuarios' ? 'page' : 'true') . '"' : '' ?>><span class="nav-icon" aria-hidden="true">□</span> Usuarios</a>
            <a class="nav-item<?= $esConductores ? ' active' : '' ?>" href="/?ruta=conductores"<?= $esConductores ? ' aria-current="' . ($titulo === 'Gestión de conductores' ? 'page' : 'true') . '"' : '' ?>><span class="nav-icon" aria-hidden="true">○</span> Conductores</a>
            <?php if ($vehiculosDisponibles && $usuario->rol === 'ADMINISTRADOR'): ?>
            <a class="nav-item<?= $esVehiculos ? ' active' : '' ?>" href="/?ruta=vehiculos"<?= $esVehiculos ? ' aria-current="' . ($titulo === 'Gestión de vehículos' ? 'page' : 'true') . '"' : '' ?>><span class="nav-icon" aria-hidden="true">▱</span> Vehículos</a>
            <?php endif; ?>
        </nav>
        <span class="sprint-badge"><?= $esVehiculos ? 'Sprint 2 · HU-02' : 'Sprint 1' ?></span>
    </aside>
    <main class="app-main" id="contenido" tabindex="-1">
        <div class="page-heading">
            <span class="eyebrow"><?= $esVehiculos ? 'HU-02 · GESTIÓN DE VEHÍCULOS' : ($esConductores ? 'HU-03 · GESTIÓN DE CONDUCTORES' : 'HU-01 · GESTIÓN DE USUARIOS') ?></span>
            <h1><?= $escapar($titulo) ?></h1>
            <p><?= $escapar($descripcion) ?></p>
        </div>
