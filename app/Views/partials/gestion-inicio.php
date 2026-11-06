<?php
$esInicio = ($moduloGestion ?? '') === 'inicio';
$esAdministrador = $usuario->rol === 'ADMINISTRADOR';
$esConductores = ($moduloGestion ?? 'usuarios') === 'conductores';
$esVehiculos = ($moduloGestion ?? 'usuarios') === 'vehiculos';
$esHU04 = isset(\SFATSA\Http\IntegracionHU04::PLANTILLAS[$moduloGestion ?? 'usuarios']);
$rutasHU04Disponibles = \SFATSA\Http\IntegracionHU04::rutasDisponibles(require dirname(__DIR__, 3) . '/routes/web.php');
$vehiculosDisponibles = true;
foreach (['listado', 'formulario', 'confirmacion', 'estado', 'eliminar'] as $plantillaVehiculo) {
    $vehiculosDisponibles = $vehiculosDisponibles && is_file(dirname(__DIR__) . '/vehiculos/' . $plantillaVehiculo . '.php');
}
$claseBody = $esInicio ? 'pagina-inicio' : ($esVehiculos ? 'pagina-vehiculos' : ($esConductores ? 'pagina-conductores' : 'pagina-usuarios'));
$estilosUsuarios = true;
require __DIR__ . '/cabecera.php';
?>
<a class="skip-link" href="#contenido">Ir al contenido</a>
<div class="app-shell">
    <header class="topbar">
        <div class="topbar__start">
            <button class="icon-button" type="button" data-sidebar-toggle aria-controls="admin-navigation-panel" aria-expanded="true" aria-label="Contraer menú de navegación" hidden><?= $icono('panel-left') ?></button>
            <a class="brand" href="/?ruta=inicio" aria-label="SFATSA, inicio">SFATSA</a>
        </div>
        <div class="user-menu">
            <span class="user-menu__avatar" aria-hidden="true"><?= $icono('contact-round') ?></span>
            <span class="user-menu__identity"><strong><?= $escapar($usuario->usuario) ?></strong><small><?= $escapar(['ADMINISTRADOR' => 'Administrador', 'CAJERO' => 'Cajero', 'CHECADORA' => 'Checadora'][$usuario->rol] ?? $usuario->rol) ?> · Sesión activa</small></span>
            <form action="/?ruta=logout" method="post" data-form-acceso>
                <input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf ?? $token) ?>">
                <button class="btn button button--logout" type="submit" data-submit><?= $icono('log-out') ?><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Cerrar sesión</span></button>
                <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
            </form>
        </div>
    </header>
    <aside class="sidebar" id="admin-navigation-panel" tabindex="-1" aria-label="Navegación administrativa">
        <div class="sidebar__mobile-header" data-sidebar-mobile-header hidden><span class="brand">SFATSA</span><button class="icon-button" type="button" data-sidebar-close aria-label="Cerrar menú de navegación"><?= $icono('x') ?></button></div>
        <span class="nav-label">Espacio de trabajo</span>
        <nav class="main-nav" aria-label="Navegación principal">
            <a class="nav-item<?= $esInicio ? ' active' : '' ?>" href="/?ruta=inicio"<?= $esInicio ? ' aria-current="page"' : '' ?>><?= $icono('dashboard') ?><span class="nav-text">Inicio</span><span class="nav-tooltip" aria-hidden="true">Inicio</span></a>
            <?php if ($esAdministrador): ?>
            <a class="nav-item<?= !$esInicio && !$esConductores && !$esVehiculos && !$esHU04 ? ' active' : '' ?>" href="/?ruta=usuarios"<?= !$esInicio && !$esConductores && !$esVehiculos && !$esHU04 ? ' aria-current="page"' : '' ?>><?= $icono('users') ?><span class="nav-text">Usuarios</span><span class="nav-tooltip" aria-hidden="true">Usuarios</span></a>
            <a class="nav-item<?= $esConductores ? ' active' : '' ?>" href="/?ruta=conductores"<?= $esConductores ? ' aria-current="page"' : '' ?>><?= $icono('contact-round') ?><span class="nav-text">Conductores</span><span class="nav-tooltip" aria-hidden="true">Conductores</span></a>
            <?php endif; ?>
            <?php if ($vehiculosDisponibles && $usuario->rol === 'ADMINISTRADOR'): ?>
            <a class="nav-item<?= $esVehiculos ? ' active' : '' ?>" href="/?ruta=vehiculos"<?= $esVehiculos ? ' aria-current="page"' : '' ?>><?= $icono('bus-front') ?><span class="nav-text">Vehículos</span><span class="nav-tooltip" aria-hidden="true">Vehículos</span></a>
            <?php endif; ?>
            <?php if ($usuario->rol === 'ADMINISTRADOR'): ?>
            <?php foreach (['rutas' => 'Rutas', 'paradas' => 'Paradas'] as $enlaceModulo => $etiquetaModulo): if (!isset($rutasHU04Disponibles[$enlaceModulo])) { continue; } $seleccionado = ($moduloGestion ?? '') === $enlaceModulo; ?>
            <a class="nav-item<?= $seleccionado ? ' active' : '' ?>" href="/?ruta=<?= $escapar($enlaceModulo) ?>"<?= $seleccionado ? ' aria-current="page"' : '' ?>><?= $icono($enlaceModulo === 'rutas' ? 'route' : 'map-pin') ?><span class="nav-text"><?= $escapar($etiquetaModulo) ?></span><span class="nav-tooltip" aria-hidden="true"><?= $escapar($etiquetaModulo) ?></span></a>
            <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer"><?= $icono('shield-check') ?><div><strong>SFATSA</strong><small>Gestión de transporte</small></div></div>
    </aside>
    <div class="nav-backdrop" data-nav-backdrop hidden aria-hidden="true"></div>
    <main class="app-main" id="contenido" tabindex="-1">
        <div class="page-heading">
            <span class="module-icon"><?= $icono(['inicio' => 'dashboard', 'usuarios' => 'users', 'conductores' => 'contact-round', 'vehiculos' => 'bus-front', 'rutas' => 'route', 'paradas' => 'map-pin'][$moduloGestion ?? 'usuarios'] ?? 'dashboard') ?></span>
            <div class="page-heading__copy"><span class="eyebrow"><?= $esAdministrador ? 'PANEL ADMINISTRATIVO' : 'MI ESPACIO' ?></span>
            <h1><?= $escapar($titulo) ?></h1>
            <p><?= $escapar($descripcion) ?></p></div>
        </div>
