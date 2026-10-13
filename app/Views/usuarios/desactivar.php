<?php
$titulo = 'Desactivar usuario';
$descripcion = 'Revise la cuenta seleccionada antes de confirmar la operación.';
require __DIR__ . '/layout-inicio.php';
?>
<a class="back-link" href="/?ruta=usuarios">← Volver a Gestión de usuarios</a>
<section class="details-card" aria-labelledby="cuenta-title">
    <span class="eyebrow">USUARIO SELECCIONADO</span>
    <h2 id="cuenta-title"><?= $escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?></h2>
    <dl><div><dt>Usuario</dt><dd><?= $escapar($cuenta['usuario']) ?></dd></div><div><dt>Rol</dt><dd><?= $escapar($cuenta['rol']) ?></dd></div><div><dt>Estado</dt><dd><span class="status <?= (int) $cuenta['activo'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><?= (int) $cuenta['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></dd></div></dl>
</section>
<section class="confirm-card" aria-labelledby="confirm-title">
    <span class="confirm-icon" aria-hidden="true">!</span>
    <span class="eyebrow">CONFIRMACIÓN REQUERIDA</span>
    <h2 id="confirm-title">¿Desea desactivar este usuario?</h2>
    <p>La cuenta <strong><?= $escapar($cuenta['usuario']) ?></strong> perderá el acceso al sistema. Su registro e historial se conservarán.</p>
    <?php if ($error !== ''): ?><div class="sfatsa-alert" role="alert" tabindex="-1" data-error-focus><?= $escapar($error) ?></div><?php endif; ?>
    <?php if ($protegido): ?>
        <div class="sfatsa-alert" role="status"><?= (int) $cuenta['id_usuario'] === $usuario->idUsuario ? 'No puede desactivar su propia cuenta mientras está autenticado.' : 'Esta cuenta ya está inactiva. No se realizaron cambios.' ?></div>
        <a class="btn button button--secondary" href="/?ruta=usuarios">Volver al listado</a>
    <?php else: ?>
        <form action="/?ruta=usuarios.desactivar&amp;id=<?= (int) $cuenta['id_usuario'] ?>" method="post" data-form-acceso>
            <input type="hidden" name="csrf" value="<?= $escapar($token) ?>">
            <label class="confirm-check" for="confirmacion"><input class="form-check-input" id="confirmacion" name="confirmacion" type="checkbox" value="1" required> Confirmo que deseo desactivar esta cuenta.</label>
            <div class="confirm-actions"><a class="btn button button--secondary" href="/?ruta=usuarios">Cancelar</a><button class="btn button button--primary" type="submit" data-submit><span class="loading-indicator" aria-hidden="true" hidden></span><span data-button-label>Confirmar desactivación</span></button></div>
            <span class="visually-hidden" role="status" aria-live="polite" data-submit-status></span>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/layout-fin.php'; ?>
