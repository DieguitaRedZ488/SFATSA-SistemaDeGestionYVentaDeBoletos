<?php
$titulo = $edicion ? 'Editar vehículo' : 'Registrar vehículo';
$descripcion = $edicion ? 'Modifique los datos del vehículo. El cambio de estado se confirma por separado.' : 'Capture los datos de la unidad. Podrá revisarlos antes de confirmar su registro.';
$moduloGestion = 'vehiculos';
$accion = $edicion ? '/?ruta=vehiculos.editar&id=' . (int) $idVehiculo : '/?ruta=vehiculos.revisar';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=vehiculos">← Volver a Gestión de vehículos</a>
<form class="form-card mw-100" action="<?= $escapar($accion) ?>" method="post" data-form-acceso>
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <div class="form-card__header"><h2>Datos del vehículo<?= $edicion ? ' · Folio #' . (int) $idVehiculo : '' ?></h2><p>Todos los campos de captura son obligatorios.</p></div>
    <div class="form-card__body">
        <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><span class="alert__mark" aria-hidden="true">!</span><div><strong><?= $escapar($error) ?></strong><?php if ($errores !== []): ?><ul class="error-summary"><?php foreach ($errores as $campo => $mensaje): ?><li><a href="#<?= $escapar($campo) ?>"><?= $escapar($mensaje) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div></div><?php endif; ?>
        <div class="row g-4">
            <?php foreach (['numero_economico' => ['Número económico', 30], 'placa' => ['Placa', 20], 'descripcion' => ['Descripción', 100], 'capacidad' => ['Capacidad (pasajeros)', null]] as $campo => [$etiqueta, $limite]): ?>
            <div class="<?= $campo === 'descripcion' ? 'col-12' : 'col-12 col-md-6' ?>">
                <div class="field">
                    <label class="field__label" for="<?= $campo ?>"><?= $etiqueta ?> <span aria-hidden="true">*</span></label>
                    <input class="form-control field__control" id="<?= $campo ?>" name="<?= $campo ?>" type="<?= $campo === 'capacidad' ? 'number' : 'text' ?>" value="<?= $escapar($valores[$campo] ?? '') ?>" required<?= $campo === 'capacidad' ? ' min="1" max="65535" step="1" inputmode="numeric"' : ' maxlength="' . ($limite * 2) . '" data-max-caracteres="' . $limite . '"' ?><?= in_array($campo, ['placa', 'numero_economico'], true) ? ' autocapitalize="characters" spellcheck="false"' : '' ?> aria-describedby="<?= $campo ?>-ayuda<?= isset($errores[$campo]) ? ' ' . $campo . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="<?= $campo ?>-ayuda"><?php if ($campo === 'placa'): ?>Máximo 20 caracteres: letras A-Z, números y guiones internos, sin espacios. No se exige una serie específica.<?php elseif ($campo === 'numero_economico'): ?>Máximo 30 caracteres. Se quitarán espacios de los extremos y las letras se convertirán a mayúsculas.<?php elseif ($campo === 'capacidad'): ?>Número entero de pasajeros entre 1 y 65535.<?php else: ?>Máximo 100 caracteres.<?php endif; ?></span>
                    <?php if (isset($errores[$campo])): ?><span class="field-error" id="<?= $campo ?>-error"><?= $escapar($errores[$campo]) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$edicion): ?>
            <div class="col-12 col-md-6"><div class="field">
                <label class="field__label" for="estado">Estado inicial <span aria-hidden="true">*</span></label>
                <select class="form-select field__control" id="estado" name="estado" required aria-describedby="estado-ayuda<?= isset($errores['estado']) ? ' estado-error' : '' ?>"<?= isset($errores['estado']) ? ' aria-invalid="true"' : '' ?>>
                    <?php foreach (['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo'] as $valorEstado => $etiquetaEstado): ?><option value="<?= $valorEstado ?>"<?= ($valores['estado'] ?? 'ACTIVO') === $valorEstado ? ' selected' : '' ?>><?= $etiquetaEstado ?></option><?php endforeach; ?>
                </select>
                <span class="field-help" id="estado-ayuda">Elija Activo o Inactivo para el registro.</span>
                <?php if (isset($errores['estado'])): ?><span class="field-error" id="estado-error"><?= $escapar($errores['estado']) ?></span><?php endif; ?>
            </div></div>
            <?php endif; ?>
        </div>
        <?php if ($edicion): ?><p class="form-note" id="estado">Esta edición conserva el estado actual. Para activar o desactivar el vehículo, use la acción independiente del listado.</p><?php if (isset($errores['estado'])): ?><p class="field-error"><?= $escapar($errores['estado']) ?></p><?php endif; ?>
        <?php else: ?><p class="form-note">Revisar datos no registra el vehículo. El alta se realizará únicamente después de confirmar el resumen.</p><?php endif; ?>
    </div>
    <div class="form-card__footer"><a class="btn button button--secondary" href="/?ruta=vehiculos">Cancelar</a><button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label><?= $edicion ? 'Guardar cambios' : 'Revisar datos' ?></span></button><span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span></div>
</form>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
