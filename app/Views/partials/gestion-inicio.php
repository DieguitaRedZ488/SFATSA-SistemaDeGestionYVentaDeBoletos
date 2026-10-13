<?php
$esConductores = ($moduloGestion ?? 'usuarios') === 'conductores';
$claseBody = $esConductores ? 'pagina-conductores' : 'pagina-usuarios';
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
                <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
                <button class="btn button button--logout" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Cerrar sesión</span></button>
                <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
            </form>
        </div>
    </header>
    <aside class="sidebar">
        <nav class="main-nav" aria-label="Navegación principal">
            <a class="nav-item" href="/?ruta=inicio"><span class="nav-icon" aria-hidden="true">⌂</span> Inicio</a>
            <a class="nav-item<?= !$esConductores ? ' active' : '' ?>" href="/?ruta=usuarios"<?= !$esConductores ? ' aria-current="' . ($titulo === 'Gestión de usuarios' ? 'page' : 'true') . '"' : '' ?>><span class="nav-icon" aria-hidden="true">□</span> Usuarios</a>
            <a class="nav-item<?= $esConductores ? ' active' : '' ?>" href="/?ruta=conductores"<?= $esConductores ? ' aria-current="' . ($titulo === 'Gestión de conductores' ? 'page' : 'true') . '"' : '' ?>><span class="nav-icon" aria-hidden="true">○</span> Conductores</a>
        </nav>
        <span class="sprint-badge">Sprint 1</span>
    </aside>
    <main class="app-main" id="contenido" tabindex="-1">
        <div class="page-heading">
            <span class="eyebrow"><?= $esConductores ? 'HU-03 · GESTIÓN DE CONDUCTORES' : 'HU-01 · GESTIÓN DE USUARIOS' ?></span>
            <h1><?= $escapar($titulo) ?></h1>
            <p><?= $escapar($descripcion) ?></p>
        </div>
