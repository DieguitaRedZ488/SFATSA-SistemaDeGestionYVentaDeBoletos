<?php
$titulo = 'Gestión de ' . $pluralCatalogo;
$descripcion = 'Consulte el catálogo, registre nuevos elementos y administre sus datos y estado.';
require __DIR__ . '/gestion-inicio.php';
?>
<?php if ($exito !== ''): ?><div class="sfatsa-alert sfatsa-alert--exito" role="status"><?= $escapar($exito) ?></div><?php endif; ?>
<div class="toolbar">
    <form class="search-form" action="/" method="get" role="search">
        <input type="hidden" name="ruta" value="<?= $escapar($moduloGestion) ?>">
        <label class="visually-hidden" for="busqueda">Buscar <?= $escapar($pluralCatalogo) ?></label>
        <input class="form-control field__control search-control" id="busqueda" name="q" type="search" value="<?= $escapar($busqueda) ?>" maxlength="<?= $moduloGestion === 'paradas' ? 120 : 40 ?>" placeholder="Buscar por nombre<?= $moduloGestion === 'paradas' ? ' o referencia' : '' ?>">
        <button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button>
        <?php if ($busqueda !== ''): ?><a class="text-action" href="/?ruta=<?= $escapar($moduloGestion) ?>">Limpiar búsqueda</a><?php endif; ?>
    </form>
    <a class="btn button button--primary" href="/?ruta=<?= $escapar($moduloGestion) ?>.registrar">Registrar <?= $escapar($singularCatalogo) ?></a>
</div>
<section class="table-card" aria-label="Listado de <?= $escapar($pluralCatalogo) ?>">
<?php if ($filasCatalogo === []): ?>
    <div class="empty-state"><span class="empty-state__icon"><?= $icono('search') ?></span><h2>No hay resultados</h2><p><?= $busqueda === '' ? 'Registre un elemento para comenzar.' : 'Cambie los términos de búsqueda.' ?></p></div>
<?php else: ?>
    <p class="mobile-table-note">Puede desplazar la tabla horizontalmente.</p>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de <?= $escapar($pluralCatalogo) ?>">
    <table class="table usuarios-table mb-0"><caption class="visually-hidden">Catálogo y estado administrativo</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Nombre</th><?php if ($moduloGestion === 'paradas'): ?><th scope="col">Referencia</th><?php endif; ?><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
        <tbody><?php foreach ($filasCatalogo as $fila): $idFila = (int) $fila[$campoId]; $activo = (int) $fila['activo'] === 1; ?>
        <tr><th scope="row"><?= $idFila ?></th><td class="nombre-completo"><?= $escapar($fila['nombre']) ?></td>
            <?php if ($moduloGestion === 'paradas'): ?><td class="nombre-completo"><?= $escapar($fila['referencia'] ?? 'Sin referencia') ?></td><?php endif; ?>
            <td><span class="status <?= $activo ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($activo ? 'check-circle' : 'x-circle') ?></span><?= $activo ? ($moduloGestion === 'paradas' || $moduloGestion === 'rutas' ? 'Habilitada' : 'Habilitado') : ($moduloGestion === 'rutas' ? 'Suspendida' : ($moduloGestion === 'paradas' ? 'Deshabilitada' : 'Deshabilitado')) ?></span></td>
            <td><div class="table-actions"><a class="text-action" href="/?ruta=<?= $escapar($moduloGestion) ?>.editar&amp;id=<?= $idFila ?>" aria-label="Modificar <?= $escapar($fila['nombre']) ?>, ID <?= $idFila ?>"><?= $icono('pencil') ?> Modificar</a>
                <a class="text-action" href="/?ruta=<?= $escapar($moduloGestion) ?>.estado&amp;id=<?= $idFila ?>&amp;estado=<?= $activo ? 0 : 1 ?>" aria-label="<?= $activo ? 'Deshabilitar' : 'Habilitar' ?> <?= $escapar($fila['nombre']) ?>, ID <?= $idFila ?>"><?= $activo ? 'Deshabilitar' : 'Habilitar' ?></a>
                <?php if ($moduloGestion === 'paradas'): ?><a class="text-action text-action--danger" href="/?ruta=paradas.eliminar&amp;id=<?= $idFila ?>" aria-label="Eliminar parada <?= $escapar($fila['nombre']) ?>, ID <?= $idFila ?>"><?= $icono('trash') ?> Eliminar</a><?php endif; ?></div></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
<?php endif; ?>
    <div class="table-footer"><?= count($filasCatalogo) ?> elementos en el listado</div>
</section>
<?php require __DIR__ . '/gestion-fin.php'; ?>
