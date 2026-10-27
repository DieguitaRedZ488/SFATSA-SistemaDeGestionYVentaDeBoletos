<?php
$titulo = $edicion ? 'Modificar conductor' : 'Registrar conductor';
$descripcion = $edicion ? 'Edite la información del conductor seleccionado.' : 'Complete los datos para dar de alta un conductor habilitado.';
$moduloGestion = 'conductores';
$accion = $edicion ? '/?ruta=conductores.editar&id=' . (int) $idConductor : '/?ruta=conductores.registrar';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=conductores"><?= $icono('arrow-right') ?> Volver a Gestión de conductores</a>
<form class="form-card" action="<?= $escapar($accion) ?>" method="post" data-form-acceso>
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <div class="form-card__header"><h2>Información general<?= $edicion ? ' · Folio #' . (int) $idConductor : '' ?></h2><p>Nombre(s), apellidos y número de licencia son obligatorios. El teléfono es opcional.</p></div>
    <div class="form-card__body">
        <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><span class="alert__mark" aria-hidden="true">!</span><div><strong><?= $escapar($error) ?></strong><?php if ($errores !== []): ?><ul class="error-summary"><?php foreach ($errores as $campo => $mensaje): ?><li><a href="#<?= $escapar($campo) ?>"><?= $escapar($mensaje) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div></div><?php endif; ?>
        <div class="form-grid">
            <?php foreach (['nombres' => ['Nombre(s)', 120, true], 'apellidos' => ['Apellidos', 120, true], 'numero_licencia' => ['Número de licencia', 40, true], 'telefono' => ['Teléfono', 10, false]] as $campo => [$etiqueta, $limite, $obligatorio]): ?>
                <div class="field">
                    <?php $esLicencia = $campo === 'numero_licencia'; $esTelefono = $campo === 'telefono'; ?>
                    <label class="field__label" for="<?= $campo ?>"><?= $etiqueta ?><?= $obligatorio ? ' <span aria-hidden="true">*</span>' : ' <span class="field-help">(opcional)</span>' ?></label>
                    <input class="form-control field__control" id="<?= $campo ?>" name="<?= $campo ?>" type="<?= $campo === 'telefono' ? 'tel' : 'text' ?>" value="<?= $escapar($valores[$campo] ?? '') ?>"<?= $obligatorio ? ' required' : '' ?><?= $esLicencia ? ' minlength="14"' : '' ?> maxlength="<?= $esLicencia ? 14 : ($esTelefono ? 10 : $limite * 2) ?>"<?= $esLicencia ? ' pattern="[A-Za-z0-9]{14}" data-licencia-conductor autocapitalize="characters" spellcheck="false"' : ($esTelefono ? ' inputmode="numeric" minlength="10" pattern="[0-9]{10}" data-telefono' : ' data-max-caracteres="' . $limite . '"') ?> autocomplete="off" aria-describedby="<?= $campo ?>-ayuda<?= isset($errores[$campo]) ? ' ' . $campo . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="<?= $campo ?>-ayuda"><?= $esLicencia ? 'Exactamente 14 caracteres alfanuméricos (A-Z y 0-9), sin espacios ni símbolos. Las letras se convierten a mayúsculas.' : ($esTelefono ? 'El teléfono debe contener exactamente 10 dígitos. Puede dejar este campo vacío.' : 'Máximo ' . $limite . ' caracteres.' . (!$obligatorio ? ' Puede dejar este campo vacío.' : '')) ?></span>
                    <?php if (isset($errores[$campo])): ?><span class="field-error" id="<?= $campo ?>-error"><?= $escapar($errores[$campo]) ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="form-note"><?= $edicion ? 'Esta edición conserva el estado actual. Para habilitarlo o deshabilitarlo, use la acción del listado.' : 'El conductor se registrará habilitado.' ?></p>
    </div>
    <div class="form-card__footer"><a class="btn button button--secondary" href="/?ruta=conductores">Cancelar</a><button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label><?= $edicion ? 'Guardar cambios' : 'Guardar conductor' ?></span></button><span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span></div>
</form>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
