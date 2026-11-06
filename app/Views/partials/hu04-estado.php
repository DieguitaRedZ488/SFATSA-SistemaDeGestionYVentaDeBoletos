<?php
$activar = (int) $nuevoEstado === 1;
$accion = $activar ? 'Habilitar' : ($moduloGestion === 'rutas' ? 'Suspender' : 'Deshabilitar');
$estadoHabilitado = $moduloGestion === 'tipos-servicio' ? 'Habilitado' : 'Habilitada';
$estadoDeshabilitado = $moduloGestion === 'rutas' ? 'Suspendida' : ($moduloGestion === 'paradas' ? 'Deshabilitada' : 'Deshabilitado');
$titulo = $accion . ' ' . $singularCatalogo;
$descripcion = 'Confirme el cambio de estado. Cancelar conserva el registro sin cambios.';
require __DIR__ . '/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=<?= $escapar($moduloGestion) ?>"><?= $icono('arrow-right') ?> Volver al listado</a>
<section class="details-card" aria-labelledby="registro-title">
    <span class="eyebrow">ID <?= (int) $registro[$campoId] ?></span><h2 id="registro-title"><?= $escapar($registro['nombre']) ?></h2>
    <?php if (isset($registro['codigo'])): ?><p>Código: <?= $escapar($registro['codigo']) ?></p><?php endif; ?>
    <p>Estado actual: <span class="status <?= (int) $registro[$campoEstado] === 1 ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono((int) $registro[$campoEstado] === 1 ? 'check-circle' : 'x-circle') ?></span><?= $escapar((int) $registro[$campoEstado] === 1 ? $estadoHabilitado : $estadoDeshabilitado) ?></span></p>
</section>
<section data-simple-confirm class="confirm-card" aria-labelledby="confirmar-title"><span class="eyebrow">CONFIRMACIÓN REQUERIDA</span>
    <h2 id="confirmar-title">¿Desea <?= $escapar(strtolower($accion)) ?> <?= $moduloGestion === 'rutas' ? 'esta ruta' : ($moduloGestion === 'paradas' ? 'esta parada' : 'este tipo de servicio') ?>?</h2><p><?= $escapar($notaEstado) ?></p>
    <?php if ($redundante): ?><p role="status">El registro ya tiene el estado solicitado.</p><a class="btn button button--secondary" href="/?ruta=<?= $escapar($moduloGestion) ?>">Volver al listado</a>
    <?php else: ?>
    <form method="post" action="/?ruta=<?= $escapar($moduloGestion) ?>.estado&amp;id=<?= (int) $registro[$campoId] ?>&amp;estado=<?= $activar ? 1 : 0 ?>">
        <input type="hidden" name="csrf" value="<?= $escapar($token) ?>"><input type="hidden" name="version" value="<?= $escapar($version) ?>"><input type="hidden" name="confirmacion" value="1">
        <div class="confirm-actions"><a class="btn button button--secondary" href="/?ruta=<?= $escapar($moduloGestion) ?>">Cancelar</a><button class="btn button button--primary" type="submit">Confirmar <?= $activar ? 'habilitación' : ($moduloGestion === 'rutas' ? 'suspensión' : 'deshabilitación') ?></button></div>
    </form><?php endif; ?>
</section>
<?php require __DIR__ . '/gestion-fin.php'; ?>
