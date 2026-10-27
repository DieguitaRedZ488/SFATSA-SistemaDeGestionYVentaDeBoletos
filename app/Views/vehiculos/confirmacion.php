<?php
$titulo = 'Confirmar registro de vehículo';
$descripcion = 'Revise los datos normalizados antes de registrar la unidad.';
$moduloGestion = 'vehiculos';
$tokenResumen = $token;
// tokenCsrf es el CSRF tanto de estos POST como del cierre de sesión del encabezado.
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<section class="details-card" aria-labelledby="vehiculo-title">
    <span class="eyebrow">REVISIÓN PREVIA · SIN REGISTRAR</span>
    <h2 id="vehiculo-title">Vehículo <?= $escapar($datos['numero_economico']) ?></h2>
    <dl>
        <?php foreach (['numero_economico' => 'Número económico', 'placa' => 'Placa', 'descripcion' => 'Descripción', 'capacidad' => 'Capacidad (pasajeros)'] as $campo => $etiqueta): ?>
        <div><dt><?= $etiqueta ?></dt><dd><?= $escapar($datos[$campo]) ?></dd></div>
        <?php endforeach; ?>
        <div><dt>Estado inicial</dt><dd><?= $datos['estado'] === 'ACTIVO' ? 'Activo' : 'Inactivo' ?></dd></div>
    </dl>
</section>
<section class="confirm-card" aria-labelledby="confirm-title">
    <span class="confirm-icon" aria-hidden="true">!</span><span class="eyebrow">CONFIRMACIÓN REQUERIDA</span>
    <h2 id="confirm-title">¿Desea registrar este vehículo?</h2>
    <p>El registro aún no se ha guardado. Confirme estos datos o cancele para descartarlos. Para corregirlos, cancele e inicie una nueva captura.</p>
    <div class="confirm-actions">
        <form action="/?ruta=vehiculos.cancelar" method="post">
            <input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf) ?>">
            <input type="hidden" name="token" value="<?= $escapar($tokenResumen) ?>">
            <button class="btn button button--secondary" type="submit">Cancelar</button>
        </form>
        <form action="/?ruta=vehiculos.confirmar" method="post" data-form-acceso>
            <input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf) ?>">
            <input type="hidden" name="token" value="<?= $escapar($tokenResumen) ?>">
            <button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Confirmar registro</span></button>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </form>
    </div>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
