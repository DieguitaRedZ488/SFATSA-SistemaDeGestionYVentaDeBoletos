<?php
$titulo = 'Eliminar parada';
$descripcion = 'Confirme la eliminación física del registro. Las paradas en uso no se pueden eliminar.';
$moduloGestion = 'paradas';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=paradas"><?= $icono('arrow-right') ?> Volver a Gestión de paradas</a>
<section class="details-card" aria-labelledby="parada-title">
    <span class="eyebrow">PARADA SELECCIONADA · ID #<?= (int) $registro['id_parada'] ?></span>
    <h2 id="parada-title"><?= $escapar($registro['nombre']) ?></h2>
    <dl>
        <div><dt>Referencia</dt><dd><?= $escapar($registro['referencia'] ?? 'Sin referencia registrada') ?></dd></div>
        <div><dt>Estado</dt><dd><span class="status <?= (int) $registro['activo'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono((int) $registro['activo'] === 1 ? 'check-circle' : 'x-circle') ?></span><?= (int) $registro['activo'] === 1 ? 'Habilitada' : 'Deshabilitada' ?></span></dd></div>
    </dl>
</section>
<section data-simple-confirm class="confirm-card confirm-card--danger" aria-labelledby="confirm-title">
    <span class="confirm-icon confirm-icon--danger" aria-hidden="true"><?= $icono('alert-triangle') ?></span>
    <span class="eyebrow">ELIMINACIÓN FÍSICA</span>
    <h2 id="confirm-title">¿Eliminar esta parada?</h2>
    <p>La parada solo se eliminará si no pertenece a ninguna ruta ni tiene otras dependencias. Si está en uso, la operación se bloqueará; puede desactivarla en su lugar. Esta acción no se puede deshacer.</p>
    <form action="/?ruta=paradas.eliminar&amp;id=<?= (int) $registro['id_parada'] ?>" method="post" data-form-acceso>
        <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
        <input type="hidden" name="version" value="<?= $escapar($version) ?>">
        <label class="delete-confirm"><input type="checkbox" name="confirmacion" value="1" required> Confirmo que deseo eliminar físicamente esta parada.</label>
        <div class="confirm-actions">
            <a class="btn button button--secondary" href="/?ruta=paradas">Cancelar</a>
            <button class="btn button button--danger" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Eliminar parada</span></button>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
