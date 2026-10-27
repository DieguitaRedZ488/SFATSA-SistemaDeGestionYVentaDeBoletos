<?php
$titulo = 'Gestión de rutas'; $descripcion = 'Consulte rutas y recorridos. Actualice las rutas sin viajes y administre su estado de forma independiente.'; $moduloGestion = 'rutas';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<?php if ($exito !== ''): ?><div class="sfatsa-alert sfatsa-alert--exito" role="status"><?= $escapar($exito) ?></div><?php endif; ?>
<div class="toolbar"><form class="search-form" action="/" method="get" role="search">
    <input type="hidden" name="ruta" value="rutas"><label class="visually-hidden" for="busqueda">Buscar por código o nombre</label>
    <input class="form-control field__control search-control" id="busqueda" name="q" type="search" maxlength="120" value="<?= $escapar($busqueda) ?>" placeholder="Código o nombre">
    <button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button><?php if ($busqueda !== ''): ?><a class="text-action" href="/?ruta=rutas">Limpiar búsqueda</a><?php endif; ?>
</form><a class="btn button button--primary" href="/?ruta=rutas.registrar">Registrar ruta</a></div>
<section class="table-card" aria-label="Listado de rutas">
<?php if ($rutas === []): ?><div class="empty-state"><span class="empty-state__icon"><?= $icono('search') ?></span><h2>No hay resultados</h2><p><?= $busqueda === '' ? 'Registre una ruta para comenzar.' : 'Revise el código o nombre e intente nuevamente.' ?></p></div>
<?php else: ?><p class="mobile-table-note">Puede desplazar la tabla horizontalmente.</p><div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de rutas">
<table class="table usuarios-table mb-0"><caption class="visually-hidden">Rutas registradas y estado actual</caption>
    <thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Tipo de servicio</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
    <tbody><?php foreach ($rutas as $fila): $idFila = (int) $fila['id_ruta']; $activo = (int) $fila['activa'] === 1; ?>
    <tr><th scope="row"><?= $escapar($fila['codigo']) ?></th><td class="nombre-completo"><?= $escapar($fila['nombre']) ?></td><td><?= $escapar($fila['tipo_servicio_nombre'] ?? 'Sin asignar') ?></td><td><span class="status <?= $activo ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($activo ? 'check-circle' : 'x-circle') ?></span><?= $activo ? 'Habilitada' : 'Suspendida' ?></span></td>
    <td><div class="table-actions"><a class="text-action" href="/?ruta=rutas.editar&amp;id=<?= $idFila ?>" aria-label="Actualizar ruta <?= $escapar($fila['codigo']) ?>"><?= $icono('pencil') ?> Actualizar</a><a class="text-action" href="/?ruta=rutas.detalle&amp;id=<?= $idFila ?>" aria-label="Consultar recorrido <?= $escapar($fila['codigo']) ?>">Ver recorrido</a><a class="text-action" href="/?ruta=rutas.estado&amp;id=<?= $idFila ?>&amp;estado=<?= $activo ? 0 : 1 ?>" aria-label="<?= $activo ? 'Suspender' : 'Habilitar' ?> ruta <?= $escapar($fila['codigo']) ?>"><?= $activo ? 'Suspender' : 'Habilitar' ?></a><a class="text-action text-action--danger" href="/?ruta=rutas.eliminar&amp;id=<?= $idFila ?>" aria-label="Eliminar ruta <?= $escapar($fila['codigo']) ?>"><?= $icono('trash') ?> Eliminar</a></div></td></tr>
    <?php endforeach; ?></tbody>
</table></div><?php endif; ?><div class="table-footer"><?= count($rutas) ?> rutas en el listado</div>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
