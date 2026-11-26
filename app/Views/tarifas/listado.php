<?php
$titulo='Gestión de tarifas';$descripcion='Consulte precios, trayectos y periodos de vigencia en MXN.';$moduloGestion='tarifas';
require dirname(__DIR__).'/partials/gestion-inicio.php';
?>
<?php if($exito!==''):?><div class="sfatsa-alert sfatsa-alert--exito" role="status"><?= $escapar($exito) ?></div><?php endif;?>
<div class="toolbar"><a class="btn button button--primary" href="/?ruta=tarifas.registrar"><?= $icono('plus') ?> Registrar tarifa</a></div>
<form class="form-card" action="/" method="get" role="search"><input type="hidden" name="ruta" value="tarifas"><div class="form-card__body"><div class="form-grid">
    <div class="field"><label for="q">Origen, destino o ruta</label><input class="form-control field__control" id="q" type="search" name="q" maxlength="120" value="<?= $escapar($filtros['q']??'') ?>"></div>
    <?php foreach(['id_ruta'=>['Ruta',$opciones['rutas'],'id_ruta'],'id_tipo_servicio'=>['Servicio',$opciones['tipos'],'id_tipo_servicio']]as$campo=>[$label,$filas,$clave]):?><div class="field"><label for="<?= $campo ?>"><?= $label ?></label><select class="form-select field__control" name="<?= $campo ?>" id="<?= $campo ?>"><option value="">Todos</option><?php foreach($filas as$f):?><option value="<?= (int)$f[$clave] ?>"<?= (string)($filtros[$campo]??'')===(string)$f[$clave]?' selected':'' ?>><?= $escapar($f['nombre']) ?></option><?php endforeach;?></select></div><?php endforeach;?>
</div></div><div class="form-card__footer"><button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button><a class="text-action" href="/?ruta=tarifas">Limpiar filtros</a></div></form>
<section class="table-card" aria-label="Tarifas registradas">
<?php if($tarifas===[]):?><div class="empty-state"><h2>No hay resultados</h2><p>Registre una tarifa o cambie los filtros.</p></div><?php else:?>
<div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de tarifas"><table class="table usuarios-table mb-0"><caption class="visually-hidden">Tarifas y vigencias en hora de Ciudad de México</caption><thead><tr><?php foreach(['ID','Servicio / ruta','Trayecto','Importe','Vigencia inclusiva','Situación','Acción']as$label):?><th scope="col"><?= $label ?></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($tarifas as$f):$situacion=(int)$f['activa']!==1?'Deshabilitada':($f['vigente_desde']>$ahora?'Próxima':($f['vigente_hasta']!==null&&$f['vigente_hasta']<$ahora?'Vencida':'Vigente'));?>
<tr><th scope="row"><?= (int)$f['id_tarifa'] ?></th><td><?= $escapar($f['servicio']) ?><br><?= $escapar($f['ruta']) ?></td><td><?= $escapar($f['origen']) ?> → <?= $escapar($f['destino']) ?></td><td><?= $escapar($f['importe']) ?> MXN</td><td><?= $escapar($f['vigente_desde']) ?><br><?= $escapar($f['vigente_hasta']??'Sin fecha final') ?></td><td><span class="status <?= $situacion==='Vigente'?'status--activo':'status--inactivo' ?>"><?= $escapar($situacion) ?></span></td><td><a class="text-action" href="/?ruta=tarifas.editar&amp;id=<?= (int)$f['id_tarifa'] ?>" aria-label="Actualizar tarifa <?= (int)$f['id_tarifa'] ?>"><?= $icono('pencil') ?> Actualizar</a></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?><div class="table-footer"><?= count($tarifas) ?> tarifas · Horario de Ciudad de México</div></section>
<?php require dirname(__DIR__).'/partials/gestion-fin.php';?>
