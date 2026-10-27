<?php
$titulo = 'Confirmar registro de ruta'; $descripcion = 'Revise los datos y el orden de los puntos. Todavía no se ha guardado la ruta.'; $moduloGestion = 'rutas';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<section class="details-card"><h2><?= $escapar($datos['nombre']) ?></h2><dl><div><dt>Código</dt><dd><?= $escapar($datos['codigo']) ?></dd></div><div><dt>Tipo de servicio</dt><dd><?= $escapar($tipoServicio) ?></dd></div><div><dt>Estado inicial</dt><dd><span class="status status--activo"><span class="status__icon" aria-hidden="true"><?= $icono('check-circle') ?></span>Habilitada</span></dd></div><div><dt>Recorrido</dt><dd><?= count($recorrido) ?> puntos</dd></div></dl></section>
<?php $puntos = $recorrido; require dirname(__DIR__) . '/partials/hu04-recorrido.php'; ?>
<section class="confirm-card"><h2>¿Desea registrar esta ruta y su recorrido?</h2><p>El resumen caduca a los 15 minutos y solo puede confirmarse una vez. Las paradas se comprobarán nuevamente antes de guardar.</p>
    <div class="confirm-actions">
        <form method="post" action="/?ruta=rutas.cancelar"><input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf) ?>"><input type="hidden" name="token" value="<?= $escapar($token) ?>"><button class="btn button button--secondary" type="submit">Cancelar registro</button></form>
        <form method="post" action="/?ruta=rutas.confirmar"><input type="hidden" name="csrf" value="<?= $escapar($tokenCsrf) ?>"><input type="hidden" name="token" value="<?= $escapar($token) ?>"><button class="btn button button--primary" type="submit">Confirmar registro</button></form>
    </div>
</section>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
