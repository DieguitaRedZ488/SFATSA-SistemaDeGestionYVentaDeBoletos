<?php
$edicion = $edicion ?? false;
$titulo = $edicion ? 'Actualizar ruta' : 'Registrar ruta'; $descripcion = $edicion ? 'Actualice los datos y el recorrido. Solo se permite cuando la ruta no tiene viajes asociados.' : 'Capture los datos y el recorrido. Revise todo antes de confirmar el registro.'; $moduloGestion = 'rutas'; $scriptRutas = true;
require dirname(__DIR__) . '/partials/gestion-inicio.php';
$catalogoIds = array_map('strval', array_column($paradas, 'id_parada'));
$intermedias = is_array($valores['intermedias'] ?? null)
    ? array_values($valores['intermedias'])
    : [];
$opciones = static function (string $seleccionado) use ($paradas, $catalogoIds, $escapar): void {
    echo '<option value="">Seleccione una parada</option>';
    if ($seleccionado !== '' && !in_array($seleccionado, $catalogoIds, true)) {
        echo '<option selected disabled data-no-disponible value="' . $escapar($seleccionado) . '">Parada #' . $escapar($seleccionado) . ' no disponible; seleccione otra</option>';
    }
    foreach ($paradas as $p) {
        echo '<option value="' . (int) $p['id_parada'] . '"' . ($seleccionado === (string) $p['id_parada'] ? ' selected' : '') . '>'
            . $escapar('#' . $p['id_parada'] . ' · ' . $p['nombre'] . ($p['referencia'] !== null ? ' · ' . $p['referencia'] : '')) . '</option>';
    }
};
?>
<a class="back-link" href="/?ruta=rutas"><?= $icono('arrow-right') ?> Volver a Gestión de rutas</a>
<?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert"><?= $escapar($error) ?><?php if ($edicion && $idRuta !== null): ?> <a href="/?ruta=rutas.editar&amp;id=<?= (int) $idRuta ?>">Consultar de nuevo los datos vigentes</a><?php endif; ?></div><?php endif; ?>
<?php if (count($paradas) < 2): ?><div class="sfatsa-alert" role="status">Se necesitan al menos dos paradas habilitadas distintas. <a href="/?ruta=paradas">Administrar paradas</a></div><?php endif; ?>
<?php if (!$asociacionDisponible): ?><div class="sfatsa-alert" role="status">El registro de rutas no está disponible en este momento. Contacte al administrador; no se guardarán cambios.</div><?php endif; ?>
<?php if ($tipos === []): ?><div class="sfatsa-alert" role="status">No hay tipos de servicio activos disponibles.</div><?php endif; ?>
<?php if (count($intermedias) > 3): ?><div class="sfatsa-alert" role="alert">El recorrido existente tiene más de cinco puntos y se conserva sin cambios. Para actualizarlo, quite explícitamente las paradas sobrantes hasta dejar un máximo de tres intermedias.</div><?php endif; ?>
<form class="form-card" method="post" action="/?ruta=<?= $edicion ? 'rutas.editar&amp;id=' . (int) $idRuta : 'rutas.revisar' ?>">
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <?php if ($edicion): ?><input type="hidden" name="version" value="<?= $escapar($version) ?>"><?php endif; ?>
    <div class="form-card__header"><h2>Datos de la ruta</h2><p>Los campos con * son obligatorios.</p></div>
    <div class="form-card__body"><div class="form-grid">
        <?php foreach (['codigo' => ['Código', 30], 'nombre' => ['Nombre', 120]] as $campo => [$etiqueta, $limite]): ?>
        <div class="field"><label for="<?= $escapar($campo) ?>"><?= $escapar($etiqueta) ?> *</label><input class="form-control field__control" id="<?= $escapar($campo) ?>" name="<?= $escapar($campo) ?>" maxlength="<?= $limite ?>" required value="<?= $escapar($valores[$campo] ?? '') ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
            <?php if (isset($errores[$campo])): ?><p class="field-error" role="alert"><?= $escapar($errores[$campo]) ?></p><?php endif; ?>
        </div><?php endforeach; ?>
        <div class="field"><label for="id_tipo_servicio">Tipo de servicio *</label>
            <select class="form-select field__control" id="id_tipo_servicio" name="id_tipo_servicio" required<?= isset($errores['id_tipo_servicio']) ? ' aria-invalid="true"' : '' ?>>
                <option value="">Seleccione un tipo de servicio</option>
                <?php $tipoElegido = (string) ($valores['id_tipo_servicio'] ?? ''); $tiposIds = array_map('strval', array_column($tipos, 'id_tipo_servicio')); ?>
                <?php if ($tipoElegido !== '' && !in_array($tipoElegido, $tiposIds, true)): ?><option selected disabled value="<?= $escapar($tipoElegido) ?>">Tipo no disponible; seleccione otro</option><?php endif; ?>
                <?php foreach ($tipos as $tipo): ?><option value="<?= (int) $tipo['id_tipo_servicio'] ?>" data-servicio-directo="<?= strtoupper(trim((string) $tipo['nombre'])) === 'DIRECTO' ? '1' : '0' ?>"<?= $tipoElegido === (string) $tipo['id_tipo_servicio'] ? ' selected' : '' ?>><?= $escapar($tipo['nombre']) ?></option><?php endforeach; ?>
            </select>
            <?php if (isset($errores['id_tipo_servicio'])): ?><p class="field-error" role="alert"><?= $escapar($errores['id_tipo_servicio']) ?></p><?php endif; ?>
        </div>
        <?php foreach (['origen' => 'Origen', 'destino' => 'Destino'] as $campo => $etiqueta): ?>
        <div class="field"><label for="<?= $escapar($campo) ?>"><?= $escapar($etiqueta) ?> *</label><select class="form-select field__control" id="<?= $escapar($campo) ?>" name="<?= $escapar($campo) ?>" required aria-describedby="recorrido-ayuda" data-recorrido-control><?php $opciones((string) ($valores[$campo] ?? '')); ?></select></div>
        <?php endforeach; ?>
    </div>
    <p class="field-help" id="recorrido-ayuda">Origen y destino deben ser distintos. Seleccione únicamente paradas habilitadas, sin repetirlas. Las rutas nuevas se crean habilitadas. Las rutas con viajes asociados no pueden actualizarse.</p>
    <?php if (isset($errores['recorrido'])): ?><p class="field-error" role="alert"><?= $escapar($errores['recorrido']) ?></p><?php endif; ?>
    <p class="service-hint" data-directo-ayuda role="status" hidden>El servicio directo únicamente permite origen y destino.</p>
    <section class="intermedias" aria-labelledby="intermedias-titulo" data-intermedias data-max-intermedias="3">
        <div class="intermedias__header"><div><h3 id="intermedias-titulo">Paradas intermedias <span>(opcional)</span></h3>
            <p class="field-help">Agregue hasta tres paradas habilitadas (máximo cinco puntos en total). Use Subir o Bajar para cambiar el orden del recorrido; origen y destino cuentan como puntos 1 y final.</p></div>
            <button class="btn button button--secondary" type="button" data-agregar-intermedia>Agregar parada intermedia</button>
        </div>
        <div class="form-grid intermedias__lista" data-lista-intermedias>
            <?php foreach ($intermedias as $i => $seleccionado): ?>
            <div class="intermedia" data-fila-intermedia>
                <div class="field"><label for="intermedia-<?= $i ?>">Parada intermedia <?= $i + 1 ?></label>
                    <select class="form-select field__control" id="intermedia-<?= $i ?>" name="intermedias[]" data-intermedia-select><?php $opciones(is_string($seleccionado) || is_int($seleccionado) ? (string) $seleccionado : ''); ?></select></div>
                <div class="intermedia__acciones" aria-label="Orden y acciones de la parada intermedia">
                    <button class="btn button button--secondary" type="button" data-subir-intermedia aria-label="Subir parada intermedia <?= $i + 1 ?>">Subir</button>
                    <button class="btn button button--secondary" type="button" data-bajar-intermedia aria-label="Bajar parada intermedia <?= $i + 1 ?>">Bajar</button>
                    <button class="btn button button--secondary" type="button" data-quitar-intermedia aria-label="Quitar parada intermedia <?= $i + 1 ?>">Quitar</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="field-help" data-conteo-intermedias aria-live="polite"><?= count($intermedias) ?> de 3 paradas intermedias.</p>
        <p class="field-error" data-error-intermedias role="alert" hidden></p>
        <template data-plantilla-intermedia><div class="intermedia" data-fila-intermedia>
            <div class="field"><label for="intermedia-__INDEX__">Parada intermedia</label>
                <select class="form-select field__control" id="intermedia-__INDEX__" name="intermedias[]" data-intermedia-select><?php $opciones(''); ?></select></div>
            <div class="intermedia__acciones" aria-label="Orden y acciones de la parada intermedia">
                <button class="btn button button--secondary" type="button" data-subir-intermedia>Subir</button>
                <button class="btn button button--secondary" type="button" data-bajar-intermedia>Bajar</button>
                <button class="btn button button--secondary" type="button" data-quitar-intermedia>Quitar</button>
            </div>
        </div></template>
    </section>
    <p class="form-note"><?= $edicion ? 'Los cambios se aplican únicamente al guardar.' : 'Podrá revisar el recorrido antes de confirmar. Revisar datos no guarda la ruta.' ?> Cancelar conserva los datos guardados.</p></div>
    <div class="form-card__footer"><?php if ($edicion): ?><a class="btn button button--secondary" href="/?ruta=rutas">Cancelar</a><?php else: ?><button class="btn button button--secondary" type="submit" form="cancelar-ruta" formnovalidate>Cancelar</button><?php endif; ?><button class="btn button button--primary" type="submit"<?= count($paradas) < 2 || $tipos === [] || !$asociacionDisponible ? ' disabled' : '' ?>><?= $edicion ? 'Guardar cambios' : 'Revisar datos' ?></button></div>
</form>
<?php if (!$edicion): ?><form id="cancelar-ruta" method="post" action="/?ruta=rutas.cancelar"><input type="hidden" name="csrf" value="<?= $escapar($token) ?>"></form><?php endif; ?>
<?php require dirname(__DIR__) . '/partials/gestion-fin.php'; ?>
