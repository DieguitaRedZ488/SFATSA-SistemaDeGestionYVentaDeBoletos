<?php
$titulo = 'Gestión de usuarios';
$descripcion = 'Consulte las cuentas y administre su acceso al sistema.';
require __DIR__ . '/layout-inicio.php';
?>
<?php if ($exito !== ''): ?>
    <div class="sfatsa-alert sfatsa-alert--exito" role="status" aria-live="polite"><span class="alert__mark" aria-hidden="true">✓</span><span><?= $escapar($exito) ?></span></div>
<?php endif; ?>
<div class="toolbar">
    <form class="search-form" action="/" method="get" role="search">
        <input type="hidden" name="ruta" value="usuarios">
        <label class="visually-hidden" for="busqueda">Buscar por nombres, apellidos o usuario</label>
        <input class="form-control field__control search-control" id="busqueda" name="q" type="search" value="<?= $escapar($busqueda) ?>" placeholder="Nombres, apellidos o usuario" maxlength="482" data-max-caracteres="241">
        <button class="btn button button--secondary" type="submit"><?= $icono('search') ?> Buscar</button>
        <?php if ($busqueda !== ''): ?><a class="text-action" href="/?ruta=usuarios">Limpiar búsqueda</a><?php endif; ?>
    </form>
    <a class="btn button button--primary" href="/?ruta=usuarios.registrar"><?= $icono('plus') ?> Registrar usuario</a>
</div>
<section class="table-card" aria-labelledby="usuarios-title">
    <h2 class="visually-hidden" id="usuarios-title">Cuentas de usuario</h2>
    <?php if ($usuarios === []): ?>
        <div class="empty-state"><span class="empty-state__icon"><?= $icono('search') ?></span><h3><?= $busqueda === '' ? 'No hay usuarios registrados' : 'No se encontraron usuarios' ?></h3><p><?= $busqueda === '' ? 'Use Registrar usuario para crear una cuenta.' : 'Pruebe con otro nombre, apellido o nombre de usuario.' ?></p></div>
    <?php else: ?>
        <p class="mobile-table-note" id="tabla-ayuda">En pantallas pequeñas puede desplazar la tabla horizontalmente.</p>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla de usuarios" aria-describedby="tabla-ayuda">
            <table class="table usuarios-table mb-0">
                <caption class="visually-hidden">Usuarios<?= $busqueda !== '' ? ' que coinciden con la búsqueda' : ' registrados' ?>. No se muestran contraseñas.</caption>
                <thead><tr><th scope="col">Nombre completo</th><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $cuenta): $idCuenta = (int) $cuenta['id_usuario']; ?>
                    <tr>
                        <th scope="row" class="nombre-completo"><?= $escapar($cuenta['nombres'] . ' ' . $cuenta['apellidos']) ?><?php if ($idCuenta === $usuario->idUsuario): ?><small>Su cuenta</small><?php endif; ?></th>
                        <td class="nombre-usuario"><?= $escapar($cuenta['usuario']) ?></td>
                        <td><span class="table-role"><?= $escapar($cuenta['rol']) ?></span></td>
                        <td><span class="status <?= (int) $cuenta['activo'] === 1 ? 'status--activo' : 'status--inactivo' ?>"><span class="status__icon" aria-hidden="true"><?= $icono((int) $cuenta['activo'] === 1 ? 'check-circle' : 'x-circle') ?></span><?= (int) $cuenta['activo'] === 1 ? 'Habilitado' : 'Deshabilitado' ?></span></td>
                        <td><div class="table-actions">
                            <a class="text-action" href="/?ruta=usuarios.editar&amp;id=<?= $idCuenta ?>" aria-label="Modificar a <?= $escapar($cuenta['usuario']) ?>"><?= $icono('pencil') ?> Modificar</a>
                            <?php if ((int) $cuenta['activo'] === 1 && $idCuenta !== $usuario->idUsuario): ?>
                                <a class="text-action" href="/?ruta=usuarios.desactivar&amp;id=<?= $idCuenta ?>" aria-label="Deshabilitar a <?= $escapar($cuenta['usuario']) ?>">Deshabilitar</a>
                            <?php elseif ($idCuenta === $usuario->idUsuario): ?><span class="action-note">Cuenta protegida</span>
                            <?php else: ?><a class="text-action" href="/?ruta=usuarios.activar&amp;id=<?= $idCuenta ?>" aria-label="Habilitar a <?= $escapar($cuenta['usuario']) ?>">Habilitar</a><?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="table-footer"><?= count($usuarios) ?> <?= count($usuarios) === 1 ? 'usuario' : 'usuarios' ?><?= $busqueda !== '' ? ' en los resultados' : ' en el listado' ?></div>
</section>
<?php require __DIR__ . '/layout-fin.php'; ?>
