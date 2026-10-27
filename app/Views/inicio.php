<?php
$titulo = 'Inicio';
$claseBody = 'pagina-inicio';
$vehiculosDisponibles = true;
foreach (['listado', 'formulario', 'confirmacion', 'estado'] as $plantillaVehiculo) {
    $vehiculosDisponibles = $vehiculosDisponibles && is_file(__DIR__ . '/vehiculos/' . $plantillaVehiculo . '.php');
}
require __DIR__ . '/partials/cabecera.php';
?>
<header class="topbar">
    <a class="brand" href="/?ruta=inicio" aria-label="SFATSA, inicio">SFATSA</a>
    <form action="/?ruta=logout" method="post" data-form-acceso>
        <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
        <button class="btn button button--logout" type="submit" data-submit>
            <span class="loading-indicator" aria-hidden="true" hidden></span>
            <span data-button-label>Cerrar sesión</span>
        </button>
        <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
    </form>
</header>
<main class="container inicio-main">
    <span class="eyebrow">ACCESO INSTITUCIONAL</span>
    <h1>Bienvenido, <?= $escapar($usuario->nombreCompleto()) ?></h1>
    <p class="inicio-intro">Ha iniciado sesión en SFATSA.</p>
    <section class="card bienvenida-card" aria-labelledby="cuenta-title">
        <h2 id="cuenta-title">Su cuenta</h2>
        <dl class="datos-cuenta">
            <div><dt>Usuario</dt><dd><?= $escapar($usuario->usuario) ?></dd></div>
            <div><dt>Rol autenticado</dt><dd><span class="role-badge"><?= $escapar($usuario->rol) ?></span></dd></div>
        </dl>
    </section>
    <section class="card desarrollo-card" aria-labelledby="desarrollo-title">
        <span class="eyebrow">SPRINT 1</span>
        <h2 id="desarrollo-title">Gestión de usuarios</h2>
        <?php if ($puedeGestionar): ?>
            <p>Consulte las cuentas, registre usuarios y administre sus datos, roles y acceso.</p>
            <a class="btn button button--primary" href="/?ruta=usuarios">Abrir Gestión de usuarios</a>
        <?php else: ?>
            <p>La gestión de usuarios y conductores está reservada al rol ADMINISTRADOR.</p>
        <?php endif; ?>
    </section>
    <?php if ($puedeGestionar): ?>
    <section class="card desarrollo-card" aria-labelledby="conductores-title">
        <span class="eyebrow">SPRINT 1 · HU-03</span>
        <h2 id="conductores-title">Gestión de conductores</h2>
        <p>Registre conductores, consulte sus datos y administre su estado activo o inactivo.</p>
        <a class="btn button button--primary" href="/?ruta=conductores">Abrir Gestión de conductores</a>
    </section>
    <?php endif; ?>
    <?php if ($puedeGestionar && $vehiculosDisponibles): ?>
    <section class="card desarrollo-card" aria-labelledby="vehiculos-title">
        <span class="eyebrow">SPRINT 2 · HU-02</span>
        <h2 id="vehiculos-title">Gestión de vehículos</h2>
        <p>Consulte las unidades, revise nuevos registros y administre sus datos y estado.</p>
        <a class="btn button button--primary" href="/?ruta=vehiculos">Abrir Gestión de vehículos</a>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/partials/pie.php'; ?>
