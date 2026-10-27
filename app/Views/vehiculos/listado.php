<?php
$titulo = 'Gestión de vehículos';
$descripcion = 'Consulte los vehículos registrados, edite sus datos o administre su estado.';
$moduloGestion = 'vehiculos';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<?php if ($exito !== ''): ?>
<div class="sfatsa-alert sfatsa-alert--exito" role="status" aria-live="polite"><span class="alert__mark" aria-hidden="true">✓</span><span><?= $escapar($exito) ?></span></div>
<?php endif; ?>
<div class="toolbar">
    <form class="search-form" action="/" method="get" role="search">
        <input type="hidden" name="ruta" value="vehiculos">
        <label class="visually-hidden" for="busqueda">Buscar por placa o número económico</label>
        <input class="form-control field__control search-control" id="busqueda" name="q" type="search" value="<?= $escapar($busqueda) ?>" placeholder="Placa o número económico" maxlength="60" data-max-caracteres="30" aria-describedby="busqueda-ayuda">
        <button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button>
        <?php if ($busqueda !== ''): ?><a class="text-action" href="/?ruta=vehiculos">Limpiar búsqueda</a><?php endif; ?>
    </form>
    <a class="btn button button--primary" href="/?ruta=vehiculos.registrar">Registrar vehículo</a>
</div>
<p class="field-help" id="busqueda-ayuda">Puede escribir la placa o el número económico completos, o solo una parte.</p>
<section class="table-card" aria-labelledby="vehiculos-title">
    <h2 class="visually-hidden" id="vehiculos-title">Vehículos registrados</h2>
    <?php if ($vehiculos === []): ?>
    <div class="empty-state"><span class="empty-state__icon"><?= $icono('search') ?></span><h3><?= $busqueda === '' ? 'No hay vehículos registrados' : 'No se encontraron vehículos' ?></h3><p><?= $busqueda === '' ? 'Use Registrar vehículo para dar de alta una unidad.' : 'Revise la placa o el número económico y vuelva a buscar.' ?></p></div>
    <?php else: ?>
    <p class="mobile-table-note" id="tabla-ayuda">En pantallas pequeñas puede desplazar la tabla horizontalmente.</p>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de vehículos" aria-describedby="tabla-ayuda">
        <table class="table usuarios-table mb-0">
            <caption class="visually-hidden">Vehículos<?= $busqueda !== '' ? ' que coinciden con la búsqueda' : ' registrados' ?> y su estado actual.</caption>
            <thead><tr><th scope="col">Número económico</th><th scope="col">Placa</th><th scope="col">Descripción</th><th scope="col">Capacidad</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($vehiculos as $vehiculo): $id = (int) $vehiculo['id_vehiculo']; $activo = $vehiculo['estado'] === 'ACTIVO'; ?>
                <tr>
                    <th scope="row" class="nombre-usuario"><?= $escapar($vehiculo['numero_economico']) ?></th>
                    <td class="nombre-usuario"><?= $escapar($vehiculo['placa']) ?></td>
                    <td class="nombre-completo"><?= $escapar($vehiculo['descripcion'] ?? 'Sin descripción registrada') ?></td>
                    <td><?= $escapar($vehiculo['capacidad']) ?> pasajeros</td>
                    <td><span class="status <?= $activo ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($activo ? 'check-circle' : 'x-circle') ?></span><?= $activo ? 'Habilitado' : 'Deshabilitado' ?></span></td>
                    <td><div class="table-actions"><a class="text-action" href="/?ruta=vehiculos.editar&amp;id=<?= $id ?>" aria-label="Editar vehículo <?= $escapar($vehiculo['numero_economico']) ?>"><?= $icono('pencil') ?> Editar</a><a class="text-action" href="/?ruta=vehiculos.estado&amp;id=<?= $id ?>&amp;estado=<?= $activo ? 'INACTIVO' : 'ACTIVO' ?>" aria-label="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?> vehículo <?= $escapar($vehiculo['numero_economico']) ?>"><?= $activo ? 'Deshabilitar' : 'Habilitar' ?></a><a class="text-action text-action--danger" href="/?ruta=vehiculos.eliminar&amp;id=<?= $id ?>" aria-label="Eliminar vehículo <?= $escapar($vehiculo['numero_economico']) ?>"><?= $icono('trash') ?> Eliminar</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="table-footer"><?= count($vehiculos) ?> <?= count($vehiculos) === 1 ? 'vehículo' : 'vehículos' ?><?= $busqueda !== '' ? ' en los resultados' : ' en el listado' ?></div>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
