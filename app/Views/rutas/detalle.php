<?php
$titulo = 'Detalle de ruta'; $descripcion = 'Consulte el recorrido registrado. Las rutas con viajes asociados no pueden actualizarse.'; $moduloGestion = 'rutas';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=rutas"><?= $icono('arrow-right') ?> Volver a Gestión de rutas</a>
<section class="details-card">
    <span class="eyebrow">RUTA #<?= (int) $ruta['id_ruta'] ?></span>
    <h2><?= $escapar($ruta['nombre']) ?></h2>
    <dl>
        <div><dt>Código</dt><dd><?= $escapar($ruta['codigo']) ?></dd></div>
        <div><dt>Tipo de servicio</dt><dd><?= $escapar($ruta['tipo_servicio_nombre'] ?? 'Sin asignar') ?></dd></div>
        <div><dt>Estado</dt><dd><span class="status <?= (int) $ruta['activa'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono((int) $ruta['activa'] === 1 ? 'check-circle' : 'x-circle') ?></span><?= (int) $ruta['activa'] === 1 ? 'Habilitada' : 'Suspendida' ?></span></dd></div>
    </dl>
    <a class="text-action" href="/?ruta=rutas.editar&amp;id=<?= (int) $ruta['id_ruta'] ?>">Actualizar ruta</a>
    <a class="text-action" href="/?ruta=rutas.estado&amp;id=<?= (int) $ruta['id_ruta'] ?>&amp;estado=<?= (int) $ruta['activa'] === 1 ? 0 : 1 ?>"><?= (int) $ruta['activa'] === 1 ? 'Suspender' : 'Habilitar' ?> ruta</a>
</section>
<p class="field-help">Se muestran los nombres y estados actuales de las paradas. Una parada deshabilitada conserva su asociación histórica.</p>
<?php $puntos = $ruta['paradas']; require dirname(__DIR__) . '/partials/hu04-recorrido.php'; ?>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
