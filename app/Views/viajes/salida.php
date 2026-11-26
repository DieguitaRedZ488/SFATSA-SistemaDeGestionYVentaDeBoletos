<?php
$titulo='Registrar salida';$descripcion='Seleccione los recursos disponibles y confirme el inicio del recorrido.';$moduloGestion='viajes';$scriptOperacion=true;
require dirname(__DIR__).'/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=viajes">Volver a viajes</a>
<?php if($error!==''):?><div class="sfatsa-alert" role="alert" tabindex="-1"><?= $escapar($error) ?></div><?php endif;?>
<?php foreach(['rutas'=>'rutas habilitadas','vehiculos'=>'vehículos disponibles','conductores'=>'conductores disponibles']as$grupo=>$nombre):if($opciones[$grupo]===[]):?><p class="sfatsa-alert" role="status">No hay <?= $nombre ?>. Solicite al administrador revisar los recursos.</p><?php endif;endforeach;?>
<form class="form-card" method="post" action="/?ruta=viajes.salida" data-operacion-captura>
<input type="hidden" name="csrf" value="<?= $escapar($token) ?>"><input type="hidden" name="operacion" value="<?= $escapar($operacion) ?>">
<div class="form-card__header"><h2>Datos de salida</h2><p>Todos los campos son obligatorios. La disponibilidad se comprobará nuevamente al guardar.</p></div>
<div class="form-card__body"><div class="form-grid"><?php require dirname(__DIR__).'/partials/operacion-campos.php';?></div>
<p class="field-help">La fecha y hora de salida se registrarán automáticamente al confirmar, en horario de Ciudad de México.</p>
<label><input type="checkbox" name="confirmacion" value="1" required<?= isset($errores['confirmacion'])?' aria-invalid="true" aria-describedby="confirmacion-error"':'' ?>> Confirmo que el vehículo y conductor están listos para iniciar el viaje.</label>
<?php if(isset($errores['confirmacion'])):?><p class="field-error" id="confirmacion-error" role="alert"><?= $escapar($errores['confirmacion']) ?></p><?php endif;?>
</div><div class="form-card__footer"><a class="btn button button--secondary" href="/?ruta=viajes">Cancelar</a><button class="btn button button--primary" type="submit">Confirmar salida</button></div></form>
<?php require dirname(__DIR__).'/partials/gestion-fin.php';?>
