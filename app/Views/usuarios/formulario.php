<?php
$titulo = $edicion ? 'Modificar usuario' : 'Registrar usuario';
$descripcion = $edicion ? 'Edite los datos y el rol de la cuenta seleccionada.' : 'Cree una cuenta activa y asigne uno de los roles disponibles.';
$accion = $edicion ? '/?ruta=usuarios.editar&id=' . (int) $idUsuario : '/?ruta=usuarios.registrar';
require __DIR__ . '/layout-inicio.php';
?>
<a class="back-link" href="/?ruta=usuarios">← Volver a Gestión de usuarios</a>
<form class="form-card" action="<?= $escapar($accion) ?>" method="post" data-form-acceso data-form-usuario>
    <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
    <div class="form-card__header"><h2>Información general</h2><p>Los campos marcados con * son obligatorios.</p></div>
    <div class="form-card__body">
        <?php if ($error !== ''): ?>
            <div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><span class="alert__mark" aria-hidden="true">!</span><div><strong><?= $escapar($error) ?></strong>
                <?php if ($errores !== []): ?><ul class="error-summary"><?php foreach ($errores as $campo => $mensaje): ?><li><a href="#<?= $escapar($campo) ?>"><?= $escapar($mensaje) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
            </div></div>
        <?php endif; ?>
        <div class="form-grid">
            <?php foreach (['nombres' => ['Nombre(s)', 120], 'apellidos' => ['Apellidos', 120], 'usuario' => ['Nombre de usuario', 50]] as $campo => [$etiqueta, $limite]): ?>
                <div class="field">
                    <label class="field__label" for="<?= $campo ?>"><?= $etiqueta ?> <span aria-hidden="true">*</span></label>
                    <input class="form-control field__control" id="<?= $campo ?>" name="<?= $campo ?>" type="text" value="<?= $escapar($valores[$campo] ?? '') ?>" required maxlength="<?= $limite * 2 ?>" data-max-caracteres="<?= $limite ?>" autocomplete="<?= $campo === 'usuario' ? 'off' : ($campo === 'nombres' ? 'given-name' : 'family-name') ?>" aria-describedby="<?= $campo ?>-ayuda<?= isset($errores[$campo]) ? ' ' . $campo . '-error' : '' ?>"<?= isset($errores[$campo]) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="<?= $campo ?>-ayuda">Máximo <?= $limite ?> caracteres.</span>
                    <?php if (isset($errores[$campo])): ?><span class="field-error" id="<?= $campo ?>-error"><?= $escapar($errores[$campo]) ?></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field">
                <label class="field__label" for="id_rol">Rol <span aria-hidden="true">*</span></label>
                <select class="form-select field__control" id="id_rol" name="id_rol" required aria-describedby="rol-ayuda<?= isset($errores['id_rol']) ? ' id_rol-error' : '' ?>"<?= isset($errores['id_rol']) ? ' aria-invalid="true"' : '' ?>>
                    <option value="">Seleccione un rol</option>
                    <?php foreach ($roles as $rol): ?><option value="<?= (int) $rol['id_rol'] ?>"<?= (string) ($valores['id_rol'] ?? '') === (string) $rol['id_rol'] ? ' selected' : '' ?>><?= $escapar($rol['nombre']) ?></option><?php endforeach; ?>
                </select>
                <span class="field-help" id="rol-ayuda">El rol determina las funciones permitidas.</span>
                <?php if (isset($errores['id_rol'])): ?><span class="field-error" id="id_rol-error"><?= $escapar($errores['id_rol']) ?></span><?php endif; ?>
            </div>
            <?php if (!$edicion): ?>
                <div class="field">
                    <label class="field__label" for="contrasena">Contraseña <span aria-hidden="true">*</span></label>
                    <input class="form-control field__control" id="contrasena" name="contrasena" type="password" required minlength="12" maxlength="72" autocomplete="new-password" aria-describedby="contrasena-ayuda<?= isset($errores['contrasena']) ? ' contrasena-error' : '' ?>"<?= isset($errores['contrasena']) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="contrasena-ayuda">Al menos 12 caracteres. Máximo 72 bytes; los caracteres multibyte ocupan más de un byte.</span>
                    <?php if (isset($errores['contrasena'])): ?><span class="field-error" id="contrasena-error"><?= $escapar($errores['contrasena']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="confirmacion">Confirmar contraseña <span aria-hidden="true">*</span></label>
                    <input class="form-control field__control" id="confirmacion" name="confirmacion" type="password" required maxlength="72" autocomplete="new-password" aria-describedby="confirmacion-ayuda<?= isset($errores['confirmacion']) ? ' confirmacion-error' : '' ?>"<?= isset($errores['confirmacion']) ? ' aria-invalid="true"' : '' ?>>
                    <span class="field-help" id="confirmacion-ayuda">Escriba la misma contraseña.</span>
                    <?php if (isset($errores['confirmacion'])): ?><span class="field-error" id="confirmacion-error"><?= $escapar($errores['confirmacion']) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($edicion): ?><p class="form-note">Esta edición conserva el estado de la cuenta y su contraseña.</p><?php endif; ?>
        <?php if ($edicion && $idUsuario === $usuario->idUsuario): ?><p class="sfatsa-alert form-note">Está editando su propia cuenta. Cambiar su rol puede retirar su acceso a Gestión de usuarios; asegúrese de mantener otra cuenta ADMINISTRADOR activa.</p><?php endif; ?>
    </div>
    <div class="form-card__footer">
        <a class="btn button button--secondary" href="/?ruta=usuarios">Cancelar</a>
        <button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label><?= $edicion ? 'Guardar cambios' : 'Guardar usuario' ?></span></button>
        <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
    </div>
</form>
<?php require __DIR__ . '/layout-fin.php'; ?>
