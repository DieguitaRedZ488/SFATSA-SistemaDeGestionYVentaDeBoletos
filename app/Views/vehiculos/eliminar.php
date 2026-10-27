<?php
$titulo = 'Eliminar vehículo';
$descripcion = 'Confirme la eliminación física del registro. Los vehículos con viajes o mantenimiento no se pueden eliminar.';
$moduloGestion = 'vehiculos';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=vehiculos"><?= $icono('arrow-right') ?> Volver a Gestión de vehículos</a>
<section class="details-card" aria-labelledby="vehiculo-title">
    <span class="eyebrow">VEHÍCULO SELECCIONADO · FOLIO #<?= (int) $vehiculo['id_vehiculo'] ?></span>
    <h2 id="vehiculo-title"><?= $escapar($vehiculo['numero_economico']) ?></h2>
    <dl>
        <div><dt>Placa</dt><dd><?= $escapar($vehiculo['placa']) ?></dd></div>
        <div><dt>Descripción</dt><dd><?= $escapar($vehiculo['descripcion'] ?? 'Sin descripción registrada') ?></dd></div>
        <div><dt>Capacidad</dt><dd><?= $escapar($vehiculo['capacidad']) ?> pasajeros</dd></div>
        <div><dt>Estado</dt><dd><span class="status <?= $vehiculo['estado'] === 'ACTIVO' ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($vehiculo['estado'] === 'ACTIVO' ? 'check-circle' : 'x-circle') ?></span><?= $vehiculo['estado'] === 'ACTIVO' ? 'Habilitado' : 'Deshabilitado' ?></span></dd></div>
    </dl>
</section>
<section class="confirm-card confirm-card--danger" aria-labelledby="confirm-title">
    <span class="confirm-icon confirm-icon--danger" aria-hidden="true"><?= $icono('alert-triangle') ?></span>
    <span class="eyebrow">ELIMINACIÓN FÍSICA</span>
    <h2 id="confirm-title">¿Eliminar este vehículo?</h2>
    <p>La operación se bloqueará si el vehículo tiene viajes o registros de mantenimiento. Esta acción elimina el registro de forma permanente y no se puede deshacer.</p>
    <form action="/?ruta=vehiculos.eliminar&amp;id=<?= (int) $vehiculo['id_vehiculo'] ?>" method="post" data-form-acceso>
        <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
        <input type="hidden" name="version" value="<?= $escapar($version) ?>">
        <label class="delete-confirm"><input type="checkbox" name="confirmacion" value="1" required> Confirmo que deseo eliminar físicamente este vehículo.</label>
        <div class="confirm-actions">
            <a class="btn button button--secondary" href="/?ruta=vehiculos">Cancelar</a>
            <button class="btn button button--danger" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Eliminar vehículo</span></button>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
