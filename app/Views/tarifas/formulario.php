<?php
$titulo=$id===null?'Registrar tarifa':'Actualizar tarifa';$descripcion='Defina el precio en MXN y las fechas inclusivas del trayecto.';$moduloGestion='tarifas';$scriptOperacion=true;
require dirname(__DIR__).'/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=tarifas">Volver a tarifas</a>
<?php if($error!==''):?><div class="sfatsa-alert" role="alert" tabindex="-1"><?= $escapar($error) ?><?php if($id!==null):?> <a href="/?ruta=tarifas.editar&amp;id=<?= $id ?>">Consultar de nuevo la tarifa</a><?php endif;?></div><?php endif;?>
<?php if($opciones['rutas']===[]):?><p class="sfatsa-alert" role="status">No hay rutas y servicios habilitados para registrar tarifas.</p><?php endif;?>
<form class="form-card" method="post" action="/?ruta=<?= $id===null?'tarifas.registrar':'tarifas.editar&amp;id='.$id ?>" data-operacion-captura>
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <?php if($id!==null):?><input type="hidden" name="version" value="<?= $escapar($version) ?>"><?php endif;?>
    <div class="form-card__header"><h2>Datos de la tarifa</h2><p>Los campos con * son obligatorios. Origen y destino deben seguir el orden de una misma ruta.</p></div>
    <div class="form-card__body"><div class="form-grid">
        <?php require dirname(__DIR__).'/partials/operacion-campos.php';?>
        <?php foreach(['importe'=>['Importe (MXN)','text'],'fecha_inicial'=>['Fecha inicial','date'],'fecha_final'=>['Fecha final','date']]as$campo=>[$etiqueta,$tipo]):?>
        <div class="field"><label for="<?= $campo ?>"><?= $etiqueta ?> *</label><input class="form-control field__control" id="<?= $campo ?>" name="<?= $campo ?>" type="<?= $tipo ?>" required value="<?= $escapar($valores[$campo]) ?>"<?= $campo==='importe'?' inputmode="decimal" maxlength="11" pattern="[0-9]+([.][0-9]{1,2})?"':' min="1000-01-01" max="9999-12-31"' ?> aria-describedby="<?= $campo ?>-ayuda<?= isset($errores[$campo])?' '.$campo.'-error':'' ?>"<?= isset($errores[$campo])?' aria-invalid="true"':'' ?>>
            <p class="field-help" id="<?= $campo ?>-ayuda"><?= $campo==='importe'?'De 0.01 a 99999999.99 MXN. Use punto decimal.':($campo==='fecha_inicial'?'Vigente desde las 00:00:00.':'Vigente hasta las 23:59:59, inclusive.') ?></p>
            <?php if(isset($errores[$campo])):?><p class="field-error" role="alert" id="<?= $campo ?>-error"><?= $escapar($errores[$campo]) ?></p><?php endif;?>
        </div><?php endforeach;?>
    </div></div>
    <div class="form-card__footer"><a class="btn button button--secondary" href="/?ruta=tarifas">Cancelar</a><button class="btn button button--primary" type="submit">Guardar tarifa</button></div>
</form>
<p class="field-help">Las tarifas asociadas a boletos se conservan. Para un nuevo precio, registre otra tarifa con una vigencia que no se superponga.</p>
<?php require dirname(__DIR__).'/partials/gestion-fin.php';?>
