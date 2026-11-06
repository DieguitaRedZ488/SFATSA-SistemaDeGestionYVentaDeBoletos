<?php
$activar = $nuevoEstado === 'ACTIVO';
$accion = $activar ? 'Habilitar' : 'Deshabilitar';
$titulo = $accion . ' vehículo';
$descripcion = 'Revise el vehículo y confirme el cambio de estado por separado.';
$moduloGestion = 'vehiculos';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=vehiculos"><?= $icono('arrow-right') ?> Volver a Gestión de vehículos</a>
<section class="details-card" aria-labelledby="vehiculo-title">
    <span class="eyebrow">VEHÍCULO SELECCIONADO · FOLIO #<?= (int) $vehiculo['id_vehiculo'] ?></span>
    <h2 id="vehiculo-title"><?= $escapar($vehiculo['numero_economico']) ?></h2>
    <dl><div><dt>Placa</dt><dd><?= $escapar($vehiculo['placa']) ?></dd></div><div><dt>Descripción</dt><dd><?= $escapar($vehiculo['descripcion'] ?? 'Sin descripción registrada') ?></dd></div><div><dt>Estado actual</dt><dd><span class="status <?= $vehiculo['estado'] === 'ACTIVO' ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono($vehiculo['estado'] === 'ACTIVO' ? 'check-circle' : 'x-circle') ?></span><?= $vehiculo['estado'] === 'ACTIVO' ? 'Habilitado' : 'Deshabilitado' ?></span></dd></div></dl>
</section>
<section data-simple-confirm class="confirm-card" aria-labelledby="confirm-title">
    <span class="confirm-icon" aria-hidden="true"><?= $icono('shield-check') ?></span><span class="eyebrow">CONFIRMACIÓN REQUERIDA</span>
    <h2 id="confirm-title">¿Desea <?= $activar ? 'habilitar' : 'deshabilitar' ?> este vehículo?</h2>
    <p>El vehículo <strong><?= $escapar($vehiculo['numero_economico']) ?></strong>, con placa <strong><?= $escapar($vehiculo['placa']) ?></strong>, cambiará a estado <strong><?= $activar ? 'Habilitado' : 'Deshabilitado' ?></strong>. Sus datos se conservarán.</p>
    <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><?= $escapar($error) ?></div><?php endif; ?>
    <?php if ($redundante): ?>
        <div class="sfatsa-alert" role="status">El vehículo ya tiene el estado solicitado. No se realizaron cambios.</div><a class="btn button button--secondary" href="/?ruta=vehiculos">Volver al listado</a>
    <?php else: ?>
        <form action="/?ruta=vehiculos.estado&amp;id=<?= (int) $vehiculo['id_vehiculo'] ?>&amp;estado=<?= $escapar($nuevoEstado) ?>" method="post" data-form-acceso>
            <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
            <input type="hidden" name="confirmacion" value="1">
            <div class="confirm-actions"><a class="btn button button--secondary" href="/?ruta=vehiculos">Cancelar</a><button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Confirmar <?= $activar ? 'habilitación' : 'deshabilitación' ?></span></button><span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span></div>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
