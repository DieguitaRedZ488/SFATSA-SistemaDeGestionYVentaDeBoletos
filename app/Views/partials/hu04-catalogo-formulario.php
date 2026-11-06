<?php
$titulo = ($edicion ? 'Modificar ' : 'Registrar ') . $singularCatalogo;
$descripcion = $edicion ? 'Actualice los datos descriptivos. El estado se administra por separado.' : 'Los nuevos registros se crean habilitados.';
require __DIR__ . '/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=<?= $escapar($moduloGestion) ?>"><?= $icono('arrow-right') ?> Volver a <?= $escapar($pluralCatalogo) ?></a>
<?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert"><p><?= $escapar($error) ?></p>
    <?php if ($edicion): ?><a href="/?ruta=<?= $escapar($moduloGestion) ?>.editar&amp;id=<?= (int) $id ?>">Volver a consultar los datos vigentes</a><?php endif; ?>
</div><?php endif; ?>
<form class="form-card" method="post" action="/?ruta=<?= $escapar($moduloGestion) ?>.<?= $edicion ? 'editar' : 'registrar' ?><?= $edicion ? '&amp;id=' . (int) $id : '' ?>">
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <?php if ($edicion): ?><input type="hidden" name="version" value="<?= $escapar($version) ?>"><?php endif; ?>
    <div class="form-card__header"><h2>Datos del catálogo</h2><p>Los campos con * son obligatorios.</p></div>
    <div class="form-card__body"><div class="form-grid">
        <?php $campos = ['nombre' => ['Nombre', $moduloGestion === 'paradas' ? 120 : 40]]; if ($moduloGestion === 'paradas') { $campos['referencia'] = ['Referencia (opcional)', 255]; } ?>
        <?php foreach ($campos as $campo => [$etiqueta, $limite]): ?>
        <div class="field"><label for="<?= $escapar($campo) ?>"><?= $escapar($etiqueta) ?><?= $campo === 'nombre' ? ' *' : '' ?></label>
            <input class="form-control field__control" id="<?= $escapar($campo) ?>" name="<?= $escapar($campo) ?>" value="<?= $escapar($valores[$campo] ?? '') ?>" maxlength="<?= $limite ?>"<?= $campo === 'nombre' ? ' required' : '' ?> aria-describedby="<?= $escapar($campo) ?>-ayuda<?= isset($errores[$campo]) ? ' ' . $escapar($campo) . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
            <p class="field-help" id="<?= $escapar($campo) ?>-ayuda">Máximo <?= $limite ?> caracteres.</p>
            <?php if (isset($errores[$campo])): ?><p class="field-error" id="<?= $escapar($campo) ?>-error" role="alert"><?= $escapar($errores[$campo]) ?></p><?php endif; ?>
        </div><?php endforeach; ?>
    </div><p class="form-note"><?= $moduloGestion === 'paradas' ? 'El nombre debe ser único, también entre paradas deshabilitadas. Sus asociaciones se conservarán.' : 'El nombre identifica el servicio en los formularios de rutas.' ?></p></div>
    <div class="form-card__footer"><a class="btn button button--secondary" href="/?ruta=<?= $escapar($moduloGestion) ?>">Cancelar</a><button class="btn button button--primary" type="submit"><?= $edicion ? 'Guardar cambios' : 'Registrar' ?></button></div>
</form>
<?php require __DIR__ . '/gestion-fin.php'; ?>
