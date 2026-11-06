<?php
$titulo = $edicion ? 'Editar vehículo' : 'Registrar vehículo';
$descripcion = $edicion ? 'Modifique los datos del vehículo. El cambio de estado se confirma por separado.' : 'Capture los datos de la unidad. Podrá revisarlos antes de confirmar su registro.';
$moduloGestion = 'vehiculos';
$capturaPlacaVehiculo = true;
$accion = $edicion ? '/?ruta=vehiculos.editar&id=' . (int) $idVehiculo : '/?ruta=vehiculos.revisar';
require dirname(__DIR__) . '/partials/gestion-inicio.php';
?>
<a class="back-link" href="/?ruta=vehiculos"><?= $icono('arrow-right') ?> Volver a Gestión de vehículos</a>
<form class="form-card mw-100" action="<?= $escapar($accion) ?>" method="post" data-form-acceso>
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <div class="form-card__header"><h2>Datos del vehículo<?= $edicion ? ' · Folio #' . (int) $idVehiculo : '' ?></h2><p>Todos los campos de captura son obligatorios.</p></div>
    <div class="form-card__body">
        <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><span class="alert__mark" aria-hidden="true">!</span><div><strong><?= $escapar($error) ?></strong><?php if ($errores !== []): ?><ul class="error-summary"><?php foreach ($errores as $campo => $mensaje): ?><li><a href="#<?= $escapar($campo) ?>"><?= $escapar($mensaje) ?></a></li><?php endforeach; ?></ul><?php endif; ?></div></div><?php endif; ?>
        <div class="row g-4">
            <?php foreach (['numero_economico' => ['Número económico', 30], 'placa' => ['Placa', 9], 'descripcion' => ['Descripción', 100], 'capacidad' => ['Capacidad (pasajeros)', null]] as $campo => [$etiqueta, $limite]): ?>
            <div class="<?= $campo === 'descripcion' ? 'col-12' : 'col-12 col-md-6' ?>">
                <?php if ($campo === 'placa'):
                    $placaCapturada = (string) ($valores['placa'] ?? '');
                    $partesPlaca = explode('-', $placaCapturada);
                    $gruposPlaca = count($partesPlaca) === 3 ? $partesPlaca : [$placaCapturada, '', ''];
                    $placaConFormato = strlen($placaCapturada) === 9 && preg_match('/\A[A-Z0-9]+-[A-Z0-9]+-[A-Z0-9]+\z/i', $placaCapturada) === 1;
                ?>
                <fieldset class="field placa-captura" id="placa" tabindex="-1" data-placa-captura aria-describedby="placa-ayuda placa-conteo<?= isset($errores['placa']) ? ' placa-error' : '' ?>">
                    <legend class="field__label">Placa <span aria-hidden="true">*</span></legend>
                    <div data-placa-fallback>
                        <label class="visually-hidden" for="placa-valor">Placa completa</label>
                        <input class="form-control field__control" id="placa-valor" name="placa" type="text" value="<?= $escapar($placaCapturada) ?>" required minlength="9" maxlength="9" pattern="[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+" autocapitalize="characters" spellcheck="false" aria-describedby="placa-ayuda<?= isset($errores['placa']) ? ' placa-error' : '' ?>"<?= isset($errores['placa']) ? ' aria-invalid="true"' : '' ?>>
                        <noscript><p class="field-help">Sin JavaScript, escriba la placa completa con sus dos guiones, por ejemplo ABC-123-A.</p></noscript>
                    </div>
                    <div class="placa-grupos" data-placa-grupos hidden>
                        <?php foreach ($gruposPlaca as $indiceGrupo => $valorGrupo): ?>
                        <?php if ($indiceGrupo > 0): ?><span class="placa-separador" aria-hidden="true">-</span><?php endif; ?>
                        <div class="placa-grupo">
                            <label class="visually-hidden" for="placa-grupo-<?= $indiceGrupo + 1 ?>">Grupo <?= $indiceGrupo + 1 ?> de la placa</label>
                            <input class="form-control field__control" id="placa-grupo-<?= $indiceGrupo + 1 ?>" type="text" value="<?= $escapar($valorGrupo) ?>" maxlength="7" pattern="[A-Z0-9]+" data-placa-grupo disabled autocapitalize="characters" autocomplete="off" spellcheck="false" aria-describedby="placa-ayuda placa-conteo placa-mensaje<?= isset($errores['placa']) ? ' placa-error' : '' ?>"<?= isset($errores['placa']) ? ' aria-invalid="true"' : '' ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <span class="field-help" id="placa-ayuda">Distribuya 7 letras A-Z o números en tres grupos. Los dos guiones se agregan automáticamente. Regla interna de SFATSA.</span>
                    <span class="field-help" id="placa-conteo" data-placa-conteo aria-live="polite">0/7 caracteres alfanuméricos</span>
                    <span class="field-error" id="placa-mensaje" data-placa-mensaje aria-live="polite" hidden></span>
                    <?php if ($placaCapturada !== '' && !$placaConFormato): ?><p class="form-note">Valor actual o capturado: <strong><?= $escapar($placaCapturada) ?></strong>. Para guardar debe proporcionar una placa con el nuevo formato; no se modifica automáticamente.</p><?php endif; ?>
                    <?php if (isset($errores['placa'])): ?><span class="field-error" id="placa-error" role="alert"><?= $escapar($errores['placa']) ?></span><?php endif; ?>
                </fieldset>
                <?php else: ?>
                <div class="field">
                    <label class="field__label" for="<?= $campo ?>"><?= $etiqueta ?> <span aria-hidden="true">*</span></label>
                    <input class="form-control field__control" id="<?= $campo ?>" name="<?= $campo ?>" type="<?= $campo === 'capacidad' ? 'number' : 'text' ?>" value="<?= $escapar($valores[$campo] ?? '') ?>" required<?= $campo === 'capacidad' ? ' min="1" max="30" step="1" inputmode="numeric" data-capacidad-vehiculo' : ' maxlength="' . ($limite * 2) . '" data-max-caracteres="' . $limite . '"' ?><?= in_array($campo, ['placa', 'numero_economico'], true) ? ' autocapitalize="characters" spellcheck="false"' : '' ?> aria-describedby="<?= $campo ?>-ayuda<?= isset($errores[$campo]) ? ' ' . $campo . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="<?= $campo ?>-ayuda"><?php if ($campo === 'numero_economico'): ?>Máximo 30 caracteres. Se quitarán espacios de los extremos y las letras se convertirán a mayúsculas.<?php elseif ($campo === 'capacidad'): ?>La capacidad debe estar entre 1 y 30 pasajeros<?php else: ?>Máximo 100 caracteres.<?php endif; ?></span>
                    <?php if (isset($errores[$campo])): ?><span class="field-error" id="<?= $campo ?>-error"><?= $escapar($errores[$campo]) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$edicion): ?>
            <div class="col-12 col-md-6"><div class="field">
                <label class="field__label" for="estado">Estado inicial <span aria-hidden="true">*</span></label>
                <select class="form-select field__control" id="estado" name="estado" required aria-describedby="estado-ayuda<?= isset($errores['estado']) ? ' estado-error' : '' ?>"<?= isset($errores['estado']) ? ' aria-invalid="true"' : '' ?>>
                    <?php foreach (['ACTIVO' => 'Habilitado', 'INACTIVO' => 'Deshabilitado'] as $valorEstado => $etiquetaEstado): ?><option value="<?= $valorEstado ?>"<?= ($valores['estado'] ?? 'ACTIVO') === $valorEstado ? ' selected' : '' ?>><?= $etiquetaEstado ?></option><?php endforeach; ?>
                </select>
                <span class="field-help" id="estado-ayuda">Seleccione el estado administrativo del vehículo. No indica disponibilidad para un viaje.</span>
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
