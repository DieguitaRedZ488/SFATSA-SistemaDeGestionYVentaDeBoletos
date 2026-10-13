<?php
$activar = $nuevoEstado === 1;
$accion = $activar ? 'Activar' : 'Desactivar';
$titulo = $accion . ' conductor';
$descripcion = 'Revise la identidad y el cambio de estado antes de confirmar.';
$moduloGestion = 'conductores';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=conductores">← Volver a Gestión de conductores</a>
<section class="details-card" aria-labelledby="conductor-title">
    <span class="eyebrow">CONDUCTOR SELECCIONADO · FOLIO #<?= (int) $conductor['id_conductor'] ?></span>
    <h2 id="conductor-title"><?= $escapar($conductor['nombres'] . ' ' . $conductor['apellidos']) ?></h2>
    <dl><div><dt>Licencia</dt><dd><?= $escapar($conductor['numero_licencia'] ?? 'Sin registrar') ?></dd></div><div><dt>Teléfono</dt><dd><?= $escapar($conductor['telefono'] ?? 'Sin registrar') ?></dd></div><div><dt>Estado actual</dt><dd><span class="status <?= (int) $conductor['activo'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><?= (int) $conductor['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></dd></div></dl>
</section>
<section class="confirm-card" aria-labelledby="confirm-title">
    <span class="confirm-icon" aria-hidden="true">!</span><span class="eyebrow">CONFIRMACIÓN REQUERIDA</span>
    <h2 id="confirm-title">¿Desea <?= $activar ? 'activar' : 'desactivar' ?> este conductor?</h2>
    <p>El conductor <strong><?= $escapar($conductor['nombres'] . ' ' . $conductor['apellidos']) ?></strong> cambiará a estado <strong><?= $activar ? 'Activo' : 'Inactivo' ?></strong>. Su información e historial se conservarán.</p>
    <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><?= $escapar($error) ?></div><?php endif; ?>
    <?php if ($redundante): ?><div class="sfatsa-alert" role="status">El conductor ya está <?= $activar ? 'activo' : 'inactivo' ?>. No se realizaron cambios.</div><a class="btn button button--secondary" href="/?ruta=conductores">Volver al listado</a>
    <?php else: ?>
        <form action="/?ruta=conductores.estado&amp;id=<?= (int) $conductor['id_conductor'] ?>&amp;estado=<?= $nuevoEstado ?>" method="post" data-form-acceso>
            <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
            <label class="confirm-check" for="confirmacion"><input class="form-check-input" id="confirmacion" name="confirmacion" type="checkbox" value="1" required> Confirmo que deseo <?= $activar ? 'activar' : 'desactivar' ?> este conductor.</label>
            <div class="confirm-actions"><a class="btn button button--secondary" href="/?ruta=conductores">Cancelar</a><button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Confirmar <?= $activar ? 'activación' : 'desactivación' ?></span></button></div>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
