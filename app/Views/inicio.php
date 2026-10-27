<?php
$titulo = 'Panel principal';
$descripcion = 'Bienvenido, ' . $usuario->nombreCompleto() . '. Acceda a las herramientas de su espacio de trabajo.';
$moduloGestion = 'inicio';
require __DIR__ . '/partials/gestion-inicio.php';
?>
<div class="dashboard-grid">
    <section class="card bienvenida-card" aria-labelledby="cuenta-title">
        <div class="card-heading"><span class="module-icon module-icon--mint"><?= $icono('shield-check') ?></span><div><span class="eyebrow">SU ESPACIO DE TRABAJO</span><h2 id="cuenta-title">Su cuenta</h2></div></div>
        <dl class="datos-cuenta">
            <div><dt>Usuario</dt><dd><?= $escapar($usuario->usuario) ?></dd></div>
            <div><dt>Perfil de acceso</dt><dd><span class="role-badge"><?= $escapar($usuario->rol) ?></span></dd></div>
        </dl>
    </section>
    <section class="card desarrollo-card" aria-labelledby="desarrollo-title">
        <span class="module-icon"><?= $icono('users') ?></span>
        <h2 id="desarrollo-title">Gestión de usuarios</h2>
        <?php if ($puedeGestionar): ?>
            <p>Consulte las cuentas, registre usuarios y administre sus datos, roles y acceso.</p>
            <a class="btn button button--primary" href="/?ruta=usuarios">Gestionar usuarios <?= $icono('arrow-right') ?></a>
        <?php else: ?>
            <p>La gestión de usuarios y conductores está reservada al rol ADMINISTRADOR.</p>
        <?php endif; ?>
    </section>
    <?php if ($puedeGestionar): ?>
    <section class="card desarrollo-card" aria-labelledby="conductores-title">
        <span class="module-icon"><?= $icono('contact-round') ?></span>
        <h2 id="conductores-title">Gestión de conductores</h2>
        <p>Registre conductores, consulte sus datos y administre su habilitación en el sistema.</p>
        <a class="btn button button--primary" href="/?ruta=conductores">Gestionar conductores <?= $icono('arrow-right') ?></a>
    </section>
    <?php endif; ?>
    <?php if ($puedeGestionar && $vehiculosDisponibles): ?>
    <section class="card desarrollo-card" aria-labelledby="vehiculos-title">
        <span class="module-icon"><?= $icono('bus-front') ?></span>
        <h2 id="vehiculos-title">Gestión de vehículos</h2>
        <p>Consulte las unidades, revise nuevos registros y administre sus datos y estado.</p>
        <a class="btn button button--primary" href="/?ruta=vehiculos">Gestionar vehículos <?= $icono('arrow-right') ?></a>
    </section>
    <?php endif; ?>
    <?php if ($puedeGestionar): ?>
    <?php foreach (['rutas' => ['Gestión de rutas', 'Registre recorridos ordenados, consulte su detalle y administre su estado.'], 'paradas' => ['Catálogo de paradas', 'Registre y administre paradas reutilizables, sus referencias y su estado.']] as $moduloHU04 => [$nombreHU04, $descripcionHU04]): if (!isset($rutasHU04Disponibles[$moduloHU04])) { continue; } ?>
    <section class="card desarrollo-card" aria-labelledby="<?= $escapar($moduloHU04) ?>-title"><span class="module-icon"><?= $icono($moduloHU04 === 'rutas' ? 'route' : 'map-pin') ?></span>
        <h2 id="<?= $escapar($moduloHU04) ?>-title"><?= $escapar($nombreHU04) ?></h2><p><?= $escapar($descripcionHU04) ?></p>
        <a class="btn button button--primary" href="/?ruta=<?= $escapar($moduloHU04) ?>">Abrir módulo <?= $icono('arrow-right') ?></a>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/gestion-fin.php'; ?>
