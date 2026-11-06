<?php
$titulo = 'Eliminar ruta';
$descripcion = 'Confirme la eliminación física de la ruta y sus asociaciones de recorrido.';
$moduloGestion = 'rutas';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=rutas"><?= $icono('arrow-right') ?> Volver a Gestión de rutas</a>
<section class="details-card" aria-labelledby="ruta-title">
    <span class="eyebrow">RUTA SELECCIONADA · FOLIO #<?= (int) $ruta['id_ruta'] ?></span>
    <h2 id="ruta-title"><?= $escapar($ruta['codigo']) ?> · <?= $escapar($ruta['nombre']) ?></h2>
    <dl><div><dt>Estado</dt><dd><?= (int) $ruta['activa'] === 1 ? 'Habilitada' : 'Deshabilitada' ?></dd></div></dl>
    <h3>Recorrido ordenado</h3>
    <ol class="route-preview">
        <?php foreach ($ruta['paradas'] as $parada): ?><li><?= $escapar($parada['nombre']) ?><?= (int) $parada['activo'] === 1 ? '' : ' (Deshabilitada)' ?></li><?php endforeach; ?>
    </ol>
</section>
<section data-simple-confirm class="confirm-card confirm-card--danger" aria-labelledby="confirm-title">
    <span class="confirm-icon confirm-icon--danger" aria-hidden="true"><?= $icono('alert-triangle') ?></span>
    <span class="eyebrow">ELIMINACIÓN FÍSICA</span>
    <h2 id="confirm-title">¿Eliminar esta ruta?</h2>
    <p>La eliminación se bloqueará si existen viajes o tarifas asociadas. Si se permite, solo se borrarán las asociaciones de esta ruta; las paradas del catálogo y los registros históricos se conservarán. Esta acción no se puede deshacer.</p>
    <form action="/?ruta=rutas.eliminar&amp;id=<?= (int) $ruta['id_ruta'] ?>" method="post" data-form-acceso>
        <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
        <input type="hidden" name="version" value="<?= $escapar($version) ?>">
        <label class="delete-confirm"><input type="checkbox" name="confirmacion" value="1" required> Confirmo que deseo eliminar físicamente esta ruta.</label>
        <div class="confirm-actions">
            <a class="btn button button--secondary" href="/?ruta=rutas">Cancelar</a>
            <button class="btn button button--danger" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Eliminar ruta</span></button>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
